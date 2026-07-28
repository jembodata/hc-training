<?php

namespace App\Services;

use App\Queries\TrainingDetailReportQuery;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingReportExportService
{
    public function __construct(
        private readonly TrainingDetailReportQuery $query
    ) {}

    public function detail(array $filters): StreamedResponse
    {
        $fileName = 'Training_Report_' . now()->format('Ymd_His') . '.csv';
        $printedBy = Auth::user()->name ?? 'Admin';

        return response()->streamDownload(function () use ($filters, $printedBy) {
            $this->writeBomAndSeparator();

            echo "REKAPITULASI DURASI PELATIHAN KARYAWAN;\n";
            echo "Periode: ;" . $this->periodeLabel($filters) . "\n";
            echo "Tanggal Cetak: ;" . now()->timezone('Asia/Jakarta')->format('d/m/Y H:i') . " WIB\n";
            echo "Dicetak Oleh: ;" . $this->csvCell($printedBy) . "\n\n";

            echo "NIK;Nama Karyawan;Departemen;Judul Training;Trainer;Held By;Activities;Skill;Tanggal;Jam Mulai;Jam Selesai;Durasi;Biaya;Score;Sertifikat\n";

            foreach ($this->query->rows($filters)->cursor() as $row) {
                $trainer = '-';

                if ($row->trainer_internal_name) {
                    $trainer = ($row->trainer_internal_nik ? $row->trainer_internal_nik . ' - ' : '') . $row->trainer_internal_name;
                } elseif ($row->trainer_external_name) {
                    $trainer = $row->trainer_external_name;
                }

                $durationHours = $this->durationHours($row->start_time, $row->finish_time);

                echo implode(';', [
                    $this->excelTextCell($row->nik),
                    $this->csvCell($row->employee_name),
                    $this->csvCell($row->department ?? 'N/A'),
                    $this->csvCell($row->title),
                    $this->csvCell($trainer),
                    $this->csvCell($row->held_by),
                    $this->csvCell($row->activity_name),
                    $this->csvCell($row->skill_name),
                    $this->csvCell($row->training_date),
                    $this->csvCell($row->start_time),
                    $this->csvCell($row->finish_time),
                    $this->csvCell(str_replace('.', ',', (string) $durationHours)),
                    $this->csvCell($row->fee),
                    $this->csvCell($row->score ?? 0),
                    $this->csvCell($row->is_certified),
                ]) . "\n";
            }
        }, $fileName);
    }

    public function rekap(array $filters): StreamedResponse
    {
        $fileName = 'Rekap_Jam_Training_Seluruh_Karyawan_' . now()->format('Ymd_His') . '.csv';
        $printedBy = Auth::user()->name ?? 'Admin';

        return response()->streamDownload(function () use ($filters, $printedBy) {
            $this->writeBomAndSeparator();

            echo "REKAPITULASI TOTAL JAM PELATIHAN KARYAWAN AKTIF;\n";
            echo "Periode: ;" . $this->periodeLabel($filters) . "\n";
            echo "Tanggal Cetak: ;" . now()->timezone('Asia/Jakarta')->format('d/m/Y H:i') . " WIB\n";
            echo "Dicetak Oleh: ;" . $this->csvCell($printedBy) . "\n\n";

            echo "NIK;Nama;Status;Departemen;Posisi;Jam Training\n";

            foreach ($this->query->rekapRows($filters)->cursor() as $row) {
                $totalMinutes = (int) $row->total_minutes;
                $hours = intdiv($totalMinutes, 60);
                $minutes = $totalMinutes % 60;
                $decimalHours = round($totalMinutes / 60, 2);

                $totalString = $totalMinutes > 0
                    ? "{$hours} Jam {$minutes} Menit (" . str_replace('.', ',', (string) $decimalHours) . " Jam)"
                    : '0 Jam';

                echo implode(';', [
                    $this->excelTextCell($row->nik),
                    $this->csvCell($row->employee_name),
                    $this->csvCell($row->status_employee ?? '-'),
                    $this->csvCell($row->department ?? 'N/A'),
                    $this->csvCell($row->position_name ?? 'N/A'),
                    $this->csvCell($totalString),
                ]) . "\n";
            }
        }, $fileName);
    }

    private function durationHours(?string $startTime, ?string $finishTime): float
    {
        if (! $startTime || ! $finishTime) {
            return 0;
        }

        $start = Carbon::parse($startTime);
        $end = Carbon::parse($finishTime);

        if ($end->lessThan($start)) {
            return 0;
        }

        return round($start->diffInMinutes($end) / 60, 1);
    }

    private function periodeLabel(array $filters): string
    {
        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            return Carbon::parse($filters['date_from'])->format('d/m/Y')
                . ' s/d '
                . Carbon::parse($filters['date_to'])->format('d/m/Y');
        }

        if (! empty($filters['date_from'])) {
            return 'Mulai ' . Carbon::parse($filters['date_from'])->format('d/m/Y');
        }

        if (! empty($filters['date_to'])) {
            return 'Sampai ' . Carbon::parse($filters['date_to'])->format('d/m/Y');
        }

        return 'Semua Periode';
    }

    private function writeBomAndSeparator(): void
    {
        echo "\xEF\xBB\xBF";
        echo "sep=;\n";
    }

    private function csvCell(mixed $value): string
    {
        return '"' . str_replace('"', '""', (string) ($value ?? '')) . '"';
    }

    private function excelTextCell(mixed $value): string
    {
        $value = str_replace('"', '""', (string) ($value ?? ''));

        return '="' . $value . '"';
    }
}