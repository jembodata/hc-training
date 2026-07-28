<?php

namespace App\Imports;

use App\Models\CertificateTemplate;
use App\Models\Employee;
use App\Models\Training;
use App\Models\TrainingGroup;
use App\Models\TrainingParticipant;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class UsersImport implements
    ToCollection,
    WithHeadingRow,
    WithCalculatedFormulas,
    SkipsEmptyRows
{
    /** @var array<string, int> */
    private array $trainingCache = [];

    private ?int $defaultCertificateTemplateId = null;

    private bool $defaultCertificateTemplateResolved = false;

    public function headingRow(): int
    {
        return 1;
    }

    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows): void {
            foreach ($rows as $index => $row) {
                $this->importRow(
                    $row->toArray(),
                    $index + 2,
                );
            }
        });
    }

    /**
     * Header yang digunakan:
     * - judul_training
     * - nik_peserta
     * - tanggal_training
     * - trainer
     * - held_by
     * - activities
     * - skill
     * - jam_mulai
     * - jam_selesai
     * - biaya
     * - sertifikat
     */
    private function importRow(array $row, int $excelRow): void
    {
        $title = $this->stringValue(
            $row['judul_training'] ?? null
        );

        // Alias lama tetap didukung agar file sebelumnya tidak langsung rusak.
        $participantNik = $this->stringValue(
            $row['nik_peserta']
                ?? $row['nik']
                ?? null
        );

        $trainingDate = $this->transformDate(
            $row['tanggal_training']
                ?? $row['tanggal']
                ?? null
        );

        if ($title === '') {
            $this->fail(
                $excelRow,
                'judul_training',
                'Judul training wajib diisi.'
            );
        }

        if ($participantNik === '') {
            $this->fail(
                $excelRow,
                'nik_peserta',
                'NIK peserta wajib diisi.'
            );
        }

        if ($trainingDate === null) {
            $this->fail(
                $excelRow,
                'tanggal_training',
                'Tanggal training tidak valid.'
            );
        }

        $participant = Employee::query()
            ->where('nik', $participantNik)
            ->first();

        if ($participant === null) {
            $this->fail(
                $excelRow,
                'nik_peserta',
                "Peserta dengan NIK {$participantNik} tidak ditemukan."
            );
        }

        $startTime = $this->transformTime(
            $row['jam_mulai'] ?? null
        );

        $finishTime = $this->transformTime(
            $row['jam_selesai'] ?? null
        );

        if (
            $startTime !== null
            && $finishTime !== null
            && strcmp($finishTime, $startTime) <= 0
        ) {
            $this->fail(
                $excelRow,
                'jam_selesai',
                'Jam selesai harus lebih besar dari jam mulai.'
            );
        }

        [$trainerEmployeeId, $trainerExternalName] =
            $this->resolveTrainer(
                $row['trainer'] ?? null,
                $excelRow,
            );

        $isCertified = $this->isTruthyCertificate(
            $row['sertifikat'] ?? null
        );

        $certificateTemplateId = $isCertified
            ? $this->requireDefaultCertificateTemplateId(
                $excelRow
            )
            : null;

        $trainingGroup = $this->findOrCreateTrainingGroup(
            $title
        );

        $training = $this->findOrCreateSession(
            group: $trainingGroup,
            title: $title,
            trainingDate: $trainingDate,
            startTime: $startTime,
            finishTime: $finishTime,
            data: [
                'held_by' => $this->stringValue(
                    $row['held_by'] ?? null
                ) ?: 'PT JEMBO CABLE COMPANY TBK.',

                'activity_name' => $this->stringValue(
                    $row['activities'] ?? null
                ) ?: 'Internal',

                'skill_name' => $this->stringValue(
                    $row['skill'] ?? null
                ) ?: 'Hard Skill',

                'fee' => $this->normalizeMoney(
                    $row['biaya'] ?? null
                ),

                'is_certified' => $isCertified
                    ? 'Yes'
                    : 'No',

                'certificate_template_id' =>
                    $certificateTemplateId,

                'trainer_employee_id' =>
                    $trainerEmployeeId,

                'trainer_external_name' =>
                    $trainerExternalName,
            ],
        );

        // Score sengaja tidak diisi dari import. Score dikelola dari fitur khusus.
        TrainingParticipant::query()->firstOrCreate([
            'training_id' => (int) $training->id,
            'employee_id' => (int) $participant->id,
        ]);
    }

    private function findOrCreateTrainingGroup(
        string $title
    ): TrainingGroup {
        $group = TrainingGroup::query()
            ->withTrashed()
            ->where('title', $title)
            ->first();

        if ($group !== null) {
            if ($group->trashed()) {
                $group->restore();
            }

            return $group;
        }

        return TrainingGroup::query()->create([
            'title' => $title,
            'created_by' => auth()->id(),
        ]);
    }

    private function findOrCreateSession(
        TrainingGroup $group,
        string $title,
        string $trainingDate,
        ?string $startTime,
        ?string $finishTime,
        array $data,
    ): Training {
        $cacheKey = implode('|', [
            $group->id,
            $trainingDate,
            $startTime ?? '-',
            $finishTime ?? '-',
        ]);

        if (isset($this->trainingCache[$cacheKey])) {
            return Training::query()->findOrFail(
                $this->trainingCache[$cacheKey]
            );
        }

        $training = Training::query()
            ->where('training_group_id', $group->id)
            ->whereDate('training_date', $trainingDate)
            ->where(function ($query) use ($startTime): void {
                $startTime === null
                    ? $query->whereNull('start_time')
                    : $query->where('start_time', $startTime);
            })
            ->where(function ($query) use ($finishTime): void {
                $finishTime === null
                    ? $query->whereNull('finish_time')
                    : $query->where('finish_time', $finishTime);
            })
            ->first();

        /*
         * Membantu memperbaiki hasil import lama: training standalone dengan
         * judul + tanggal yang sama akan dipindahkan ke Training Group dan
         * dijadikan Sesi, sehingga tombol Tambah Sesi dapat digunakan.
         */
        if ($training === null) {
            $legacyCandidates = Training::query()
                ->whereNull('training_group_id')
                ->where('title', $title)
                ->whereDate('training_date', $trainingDate)
                ->orderBy('id')
                ->get();

            if ($legacyCandidates->count() === 1) {
                $training = $legacyCandidates->first();
            }
        }

        if ($training === null) {
            $group = TrainingGroup::query()
                ->lockForUpdate()
                ->findOrFail($group->id);

            $batchNumber = ((int) Training::query()
                ->where('training_group_id', $group->id)
                ->max('batch_number')) + 1;

            $training = new Training();
            $training->training_group_id = $group->id;
            $training->batch_number = $batchNumber;
            $training->batch_name = 'Sesi ' . $batchNumber;
        } elseif ($training->training_group_id === null) {
            $batchNumber = ((int) Training::query()
                ->where('training_group_id', $group->id)
                ->max('batch_number')) + 1;

            $training->training_group_id = $group->id;
            $training->batch_number = $batchNumber;
            $training->batch_name = 'Sesi ' . $batchNumber;
        }

        $training->title = $title;
        $training->training_date = $trainingDate;
        $training->start_time = $startTime;
        $training->finish_time = $finishTime;
        $training->held_by = $data['held_by'];
        $training->activity_name = $data['activity_name'];
        $training->skill_name = $data['skill_name'];
        $training->fee = $data['fee'];
        $training->is_certified = $data['is_certified'];
        $training->certificate_template_id =
            $data['certificate_template_id'];
        $training->trainer_employee_id =
            $data['trainer_employee_id'];
        $training->trainer_external_name =
            $data['trainer_external_name'];
        $training->save();

        $this->trainingCache[$cacheKey] =
            (int) $training->id;

        return $training;
    }

    /**
     * @return array{0: ?int, 1: ?string}
     */
    private function resolveTrainer(
        mixed $value,
        int $excelRow,
    ): array {
        $raw = $this->stringValue($value);

        if ($raw === '') {
            return [null, null];
        }

        // 1. Seluruh isi cell dianggap sebagai NIK.
        $employee = Employee::query()
            ->where('nik', $raw)
            ->first();

        if ($employee !== null) {
            return [(int) $employee->id, null];
        }

        // 2. Mendukung format "1003 - ANDI PRATAMA".
        $nameCandidate = $raw;

        if (preg_match('/\b\d{2,}\b/u', $raw, $matches)) {
            $employee = Employee::query()
                ->where('nik', $matches[0])
                ->first();

            if ($employee !== null) {
                return [(int) $employee->id, null];
            }

            $nameCandidate = trim((string) preg_replace(
                '/^\s*\d+\s*[-–—|:]?\s*/u',
                '',
                $raw
            ));
        }

        // 3. Jika NIK tidak cocok, cari nama employee secara exact.
        $employeesByName = Employee::query()
            ->whereRaw(
                'LOWER(TRIM(name)) = ?',
                [mb_strtolower(trim($nameCandidate))]
            )
            ->get(['id']);

        if ($employeesByName->count() === 1) {
            return [
                (int) $employeesByName->first()->id,
                null,
            ];
        }

        if ($employeesByName->count() > 1) {
            $this->fail(
                $excelRow,
                'trainer',
                'Nama trainer ditemukan lebih dari satu. Gunakan NIK trainer agar tidak ambigu.'
            );
        }

        // 4. Tidak ditemukan di Employee, maka dianggap trainer external.
        return [null, $raw];
    }

    private function requireDefaultCertificateTemplateId(
        int $excelRow
    ): int {
        if (! $this->defaultCertificateTemplateResolved) {
            $this->defaultCertificateTemplateId =
                CertificateTemplate::query()
                    ->active()
                    ->where('is_default', true)
                    ->orderByDesc('updated_at')
                    ->value('id');

            $this->defaultCertificateTemplateResolved = true;
        }

        if ($this->defaultCertificateTemplateId === null) {
            $this->fail(
                $excelRow,
                'sertifikat',
                'Training menggunakan sertifikat, tetapi tidak ada certificate template default yang aktif.'
            );
        }

        return $this->defaultCertificateTemplateId;
    }

    private function isTruthyCertificate(mixed $value): bool
    {
        return in_array(
            mb_strtolower($this->stringValue($value)),
            ['ada', 'ya', 'yes', 'y', '1', 'true'],
            true,
        );
    }

    private function transformDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof DateTimeInterface) {
                return Carbon::instance($value)
                    ->format('Y-m-d');
            }

            if (is_numeric($value)) {
                return Carbon::instance(
                    ExcelDate::excelToDateTimeObject(
                        (float) $value
                    )
                )->format('Y-m-d');
            }

            return Carbon::parse(
                trim((string) $value)
            )->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function transformTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof DateTimeInterface) {
                return Carbon::instance($value)
                    ->format('H:i:s');
            }

            if (is_numeric($value)) {
                return Carbon::instance(
                    ExcelDate::excelToDateTimeObject(
                        (float) $value
                    )
                )->format('H:i:s');
            }

            $time = trim((string) $value);
            $time = preg_replace(
                '/^(\d{1,2})\.(\d{2})$/',
                '$1:$2',
                $time
            );

            foreach (
                ['H:i:s', 'H:i', 'G:i', 'g:i A', 'g:i a']
                as $format
            ) {
                try {
                    return Carbon::createFromFormat(
                        $format,
                        $time
                    )->format('H:i:s');
                } catch (\Throwable) {
                    // Coba format berikutnya.
                }
            }

            return Carbon::parse($time)
                ->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeMoney(mixed $value): int|float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $money = trim((string) $value);
        $money = preg_replace('/[^0-9,.-]/', '', $money);

        if ($money === null || $money === '') {
            return 0;
        }

        if (
            substr_count($money, '.') > 1
            && ! str_contains($money, ',')
        ) {
            $money = str_replace('.', '', $money);
        } elseif (
            str_contains($money, '.')
            && str_contains($money, ',')
        ) {
            $money = str_replace('.', '', $money);
            $money = str_replace(',', '.', $money);
        } elseif (str_contains($money, ',')) {
            $money = str_replace(',', '.', $money);
        }

        return is_numeric($money)
            ? (float) $money
            : 0;
    }

    private function stringValue(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function fail(
        int $excelRow,
        string $field,
        string $message,
    ): never {
        throw ValidationException::withMessages([
            $field => "Baris {$excelRow}: {$message}",
        ]);
    }
}