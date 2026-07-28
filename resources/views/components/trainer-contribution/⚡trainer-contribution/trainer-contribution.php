<?php

use App\Support\Auth\Permissions;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component
{
    use WithPagination;

    private const PER_PAGE = 10;

    public string $search = '';

    /** @var list<string> */
    public array $position_filter = [];

    public ?string $date_from = null;

    public ?string $date_to = null;

    public bool $showDetailModal = false;

    public string $selectedTrainerName = '';

    public string $selectedTrainerToken = '';

    /** @var list<array<string, mixed>> */
    public array $trainerDetails = [];

    public function mount(): void
    {
        Gate::authorize(
            Permissions::VIEW_TRAINING_CONTRIBUTION
        );
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPositionFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function selectAllPositions(): void
    {
        Gate::authorize(
            Permissions::VIEW_TRAINING_CONTRIBUTION
        );

        $this->position_filter = DB::table('positions')
            ->orderBy('position_name')
            ->pluck('position_name')
            ->all();

        $this->resetPage();
    }

    public function clearPositions(): void
    {
        Gate::authorize(
            Permissions::VIEW_TRAINING_CONTRIBUTION
        );

        $this->position_filter = [];

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        Gate::authorize(
            Permissions::VIEW_TRAINING_CONTRIBUTION
        );

        $this->reset([
            'search',
            'position_filter',
            'date_from',
            'date_to',
        ]);

        $this->closeDetail();
        $this->resetPage();
    }

    public function showDetail(string $trainerToken): void
    {
        Gate::authorize(
            Permissions::VIEW_TRAINING_CONTRIBUTION
        );

        $this->validateFilters();

        $identity = $this->decodeTrainerToken(
            $trainerToken
        );

        $details = $this->detailQuery($identity)
            ->orderByDesc('t.training_date')
            ->orderByDesc('t.id')
            ->get();

        $this->selectedTrainerToken =
            $trainerToken;

        $this->selectedTrainerName =
            $this->trainerName($identity);

        $this->trainerDetails = $details
            ->map(
                static fn(object $row): array => [
                    'title' => (string) $row->title,
                    'training_date' =>
                    (string) $row->training_date,
                    'start_time' =>
                    (string) $row->start_time,
                    'finish_time' =>
                    (string) $row->finish_time,
                    'minutes' =>
                    (int) ($row->minutes ?? 0),
                ]
            )
            ->all();

        $this->showDetailModal = true;
    }

    public function closeDetail(): void
    {
        $this->showDetailModal = false;
        $this->selectedTrainerName = '';
        $this->selectedTrainerToken = '';
        $this->trainerDetails = [];
    }

    public function exportCsv(): StreamedResponse
    {
        Gate::authorize(
            Permissions::EXPORT_TRAINING_CONTRIBUTION
        );

        $this->validateFilters();

        $rows = $this->baseQuery()->get();
        $dateFrom = $this->date_from ?: '-';
        $dateTo = $this->date_to ?: '-';

        return response()->streamDownload(
            function () use (
                $rows,
                $dateFrom,
                $dateTo
            ): void {
                $stream = fopen('php://output', 'wb');

                if ($stream === false) {
                    return;
                }

                fwrite($stream, "\xEF\xBB\xBF");
                fwrite($stream, "sep=;\n");

                fputcsv(
                    $stream,
                    ['TRAINING CONTRIBUTION REPORT'],
                    ';'
                );

                fputcsv(
                    $stream,
                    ['Periode', "{$dateFrom} s/d {$dateTo}"],
                    ';'
                );

                fputcsv(
                    $stream,
                    [
                        'Tanggal Cetak',
                        Carbon::now('Asia/Jakarta')
                            ->format('d/m/Y H:i')
                            . ' WIB',
                    ],
                    ';'
                );

                fputcsv($stream, [], ';');

                fputcsv(
                    $stream,
                    [
                        'Nama Trainer',
                        'NIK',
                        'Position',
                        'Organization',
                        'Activity',
                        'Skill',
                        'Total Jam Mengajar',
                    ],
                    ';'
                );

                foreach ($rows as $row) {
                    fputcsv(
                        $stream,
                        [
                            $this->spreadsheetSafe(
                                $row->trainer_name
                                    ?? 'Tanpa Nama'
                            ),
                            $this->spreadsheetSafe(
                                $row->nik ?? '-'
                            ),
                            $this->spreadsheetSafe(
                                $row->position ?? '-'
                            ),
                            $this->spreadsheetSafe(
                                $row->organization ?? '-'
                            ),
                            $this->spreadsheetSafe(
                                $row->activity_name ?? '-'
                            ),
                            $this->spreadsheetSafe(
                                $row->skill_name ?? '-'
                            ),
                            round(
                                ((int) ($row->total_minutes ?? 0))
                                    / 60,
                                2
                            ),
                        ],
                        ';'
                    );
                }

                fclose($stream);
            },
            'Training_Contribution_'
                . now()->format('Ymd_His')
                . '.csv',
            [
                'Content-Type' =>
                'text/csv; charset=UTF-8',
            ]
        );
    }

    public function exportDetailCsv(): StreamedResponse
    {
        Gate::authorize(
            Permissions::EXPORT_TRAINING_CONTRIBUTION
        );

        $this->validateFilters();

        if ($this->selectedTrainerToken === '') {
            abort(404);
        }

        $identity = $this->decodeTrainerToken(
            $this->selectedTrainerToken
        );

        $trainerName = $this->trainerName($identity);

        $rows = $this->detailQuery($identity)
            ->orderByDesc('t.training_date')
            ->orderByDesc('t.id')
            ->get();

        $dateFrom = $this->date_from ?: '-';
        $dateTo = $this->date_to ?: '-';

        $fileName = 'Trainer_Detail_'
            . Str::slug($trainerName, '_')
            . '_'
            . now()->format('Ymd_His')
            . '.csv';

        return response()->streamDownload(
            function () use (
                $rows,
                $trainerName,
                $dateFrom,
                $dateTo
            ): void {
                $stream = fopen('php://output', 'wb');

                if ($stream === false) {
                    return;
                }

                fwrite($stream, "\xEF\xBB\xBF");
                fwrite($stream, "sep=;\n");

                fputcsv(
                    $stream,
                    ['DETAIL RIWAYAT MENGAJAR TRAINER'],
                    ';'
                );

                fputcsv(
                    $stream,
                    [
                        'Nama Trainer',
                        $this->spreadsheetSafe($trainerName),
                    ],
                    ';'
                );

                fputcsv(
                    $stream,
                    ['Periode', "{$dateFrom} s/d {$dateTo}"],
                    ';'
                );

                fputcsv($stream, [], ';');

                fputcsv(
                    $stream,
                    [
                        'Topik Pelatihan',
                        'Tanggal',
                        'Jam Mulai',
                        'Jam Selesai',
                        'Durasi Jam',
                    ],
                    ';'
                );

                foreach ($rows as $row) {
                    fputcsv(
                        $stream,
                        [
                            $this->spreadsheetSafe(
                                $row->title ?? '-'
                            ),
                            $row->training_date ?? '-',
                            $row->start_time ?? '-',
                            $row->finish_time ?? '-',
                            round(
                                ((int) ($row->minutes ?? 0))
                                    / 60,
                                2
                            ),
                        ],
                        ';'
                    );
                }

                fclose($stream);
            },
            $fileName,
            [
                'Content-Type' =>
                'text/csv; charset=UTF-8',
            ]
        );
    }

    public function with(): array
    {
        Gate::authorize(
            Permissions::VIEW_TRAINING_CONTRIBUTION
        );

        $contributions = $this->baseQuery()
            ->paginate(self::PER_PAGE);

        $contributions->through(
            function (object $row): object {
                $row->trainer_token =
                    $this->encodeTrainerToken(
                        isset($row->trainer_employee_id)
                            ? (int) $row->trainer_employee_id
                            : null,
                        isset($row->trainer_external_name)
                            ? (string) $row->trainer_external_name
                            : null
                    );

                return $row;
            }
        );

        return [
            'contributions' => $contributions,

            'trainerList' => DB::table('trainings as t')
                ->leftJoin(
                    'employees as tr',
                    't.trainer_employee_id',
                    '=',
                    'tr.id'
                )
                ->whereNull('t.deleted_at')
                ->selectRaw(
                    'COALESCE(tr.name, t.trainer_external_name) as name'
                )
                ->whereNotNull(
                    DB::raw(
                        'COALESCE(tr.name, t.trainer_external_name)'
                    )
                )
                ->distinct()
                ->orderBy('name')
                ->get(),

            'positionList' => DB::table('positions')
                ->orderBy('position_name')
                ->get(),
        ];
    }

    private function baseQuery(): Builder
    {
        if ($this->position_filter !== []) {
            return $this->internalTrainerMonitoringQuery();
        }

        return DB::table('trainings as t')
            ->leftJoin(
                'employees as tr',
                't.trainer_employee_id',
                '=',
                'tr.id'
            )
            ->leftJoin(
                'organizations as o',
                'tr.org_id',
                '=',
                'o.id'
            )
            ->leftJoin(
                'positions as p',
                'tr.position_id',
                '=',
                'p.id'
            )
            ->whereNull('t.deleted_at')
            ->select([
                'tr.id as trainer_employee_id',
                DB::raw(
                    'CASE WHEN tr.id IS NULL '
                        . 'THEN t.trainer_external_name '
                        . 'ELSE NULL END as trainer_external_name'
                ),
                DB::raw(
                    'COALESCE(tr.name, t.trainer_external_name) '
                        . 'as trainer_name'
                ),
                'tr.nik',
                DB::raw(
                    'COALESCE(p.position_name, "EXTERNAL") '
                        . 'as position'
                ),
                DB::raw(
                    'COALESCE(o.org_name, "-") '
                        . 'as organization'
                ),
                DB::raw(
                    'COALESCE('
                        . 'GROUP_CONCAT(DISTINCT t.activity_name '
                        . 'ORDER BY t.activity_name SEPARATOR ", "), '
                        . '"-") as activity_name'
                ),
                DB::raw(
                    'COALESCE('
                        . 'GROUP_CONCAT(DISTINCT t.skill_name '
                        . 'ORDER BY t.skill_name SEPARATOR ", "), '
                        . '"-") as skill_name'
                ),
                DB::raw(
                    'SUM(COALESCE(GREATEST('
                        . 'TIMESTAMPDIFF('
                        . 'MINUTE, t.start_time, t.finish_time'
                        . '), 0), 0)) as total_minutes'
                ),
            ])
            ->whereNotNull(
                DB::raw(
                    'COALESCE(tr.name, t.trainer_external_name)'
                )
            )
            ->when(
                $this->search !== '',
                function (Builder $query): void {
                    $search = '%' . $this->search . '%';

                    $query->where(
                        function (Builder $subQuery) use (
                            $search
                        ): void {
                            $subQuery
                                ->where('tr.name', 'like', $search)
                                ->orWhere(
                                    't.trainer_external_name',
                                    'like',
                                    $search
                                );
                        }
                    );
                }
            )
            ->when(
                $this->date_from,
                fn(Builder $query) => $query->whereDate(
                    't.training_date',
                    '>=',
                    $this->date_from
                )
            )
            ->when(
                $this->date_to,
                fn(Builder $query) => $query->whereDate(
                    't.training_date',
                    '<=',
                    $this->date_to
                )
            )
            ->groupBy([
                'tr.id',
                'tr.name',
                'tr.nik',
                'p.position_name',
                'o.org_name',
                't.trainer_external_name',
            ])
            ->orderByDesc('total_minutes')
            ->orderBy('trainer_name');
    }

    private function internalTrainerMonitoringQuery(): Builder
    {
        return DB::table('employees as e')
            ->leftJoin(
                'positions as p',
                'e.position_id',
                '=',
                'p.id'
            )
            ->leftJoin(
                'organizations as o',
                'e.org_id',
                '=',
                'o.id'
            )
            ->leftJoin(
                'trainings as t',
                function ($join): void {
                    $join->on(
                        'e.id',
                        '=',
                        't.trainer_employee_id'
                    )
                        ->whereNull('t.deleted_at');

                    if ($this->date_from) {
                        $join->whereDate(
                            't.training_date',
                            '>=',
                            $this->date_from
                        );
                    }

                    if ($this->date_to) {
                        $join->whereDate(
                            't.training_date',
                            '<=',
                            $this->date_to
                        );
                    }
                }
            )
            ->whereNull('e.deleted_at')
            ->whereIn(
                'p.position_name',
                $this->position_filter
            )
            ->select([
                'e.id as trainer_employee_id',
                DB::raw(
                    'NULL as trainer_external_name'
                ),
                'e.name as trainer_name',
                'e.nik',
                DB::raw(
                    'COALESCE(p.position_name, "-") '
                        . 'as position'
                ),
                DB::raw(
                    'COALESCE(o.org_name, "-") '
                        . 'as organization'
                ),
                DB::raw(
                    'COALESCE('
                        . 'GROUP_CONCAT(DISTINCT t.activity_name '
                        . 'ORDER BY t.activity_name SEPARATOR ", "), '
                        . '"-") as activity_name'
                ),
                DB::raw(
                    'COALESCE('
                        . 'GROUP_CONCAT(DISTINCT t.skill_name '
                        . 'ORDER BY t.skill_name SEPARATOR ", "), '
                        . '"-") as skill_name'
                ),
                DB::raw(
                    'SUM(COALESCE(GREATEST('
                        . 'TIMESTAMPDIFF('
                        . 'MINUTE, t.start_time, t.finish_time'
                        . '), 0), 0)) as total_minutes'
                ),
            ])
            ->when(
                $this->search !== '',
                fn(Builder $query) => $query->where(
                    'e.name',
                    'like',
                    '%' . $this->search . '%'
                )
            )
            ->groupBy([
                'e.id',
                'e.name',
                'e.nik',
                'p.position_name',
                'o.org_name',
            ])
            ->orderByDesc('total_minutes')
            ->orderBy('e.name');
    }

    /**
     * @param array{id: ?int, external: ?string} $identity
     */
    private function detailQuery(array $identity): Builder
    {
        return DB::table('trainings as t')
            ->whereNull('t.deleted_at')
            ->when(
                $identity['id'] !== null,
                fn(Builder $query) => $query->where(
                    't.trainer_employee_id',
                    $identity['id']
                ),
                fn(Builder $query) => $query
                    ->whereNull('t.trainer_employee_id')
                    ->where(
                        't.trainer_external_name',
                        $identity['external']
                    )
            )
            ->when(
                $this->date_from,
                fn(Builder $query) => $query->whereDate(
                    't.training_date',
                    '>=',
                    $this->date_from
                )
            )
            ->when(
                $this->date_to,
                fn(Builder $query) => $query->whereDate(
                    't.training_date',
                    '<=',
                    $this->date_to
                )
            )
            ->select([
                't.id',
                't.title',
                't.training_date',
                't.start_time',
                't.finish_time',
                DB::raw(
                    'COALESCE(GREATEST('
                        . 'TIMESTAMPDIFF('
                        . 'MINUTE, t.start_time, t.finish_time'
                        . '), 0), 0) as minutes'
                ),
            ]);
    }

    /**
     * @return array{id: ?int, external: ?string}
     */
    private function decodeTrainerToken(
        string $token
    ): array {
        $base64 = strtr($token, '-_', '+/');
        $padding = strlen($base64) % 4;

        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($base64, true);

        abort_unless($decoded !== false, 404);

        $identity = json_decode(
            $decoded,
            true
        );

        abort_unless(is_array($identity), 404);

        $id = isset($identity['id'])
            && is_numeric($identity['id'])
            ? (int) $identity['id']
            : null;

        $external = isset($identity['external'])
            && is_string($identity['external'])
            ? trim($identity['external'])
            : null;

        if ($external === '') {
            $external = null;
        }

        abort_unless(
            ($id !== null && $id > 0)
                || $external !== null,
            404
        );

        if ($id !== null) {
            $exists = DB::table('employees')
                ->where('id', $id)
                ->whereNull('deleted_at')
                ->exists();

            abort_unless($exists, 404);

            return [
                'id' => $id,
                'external' => null,
            ];
        }

        abort_if(mb_strlen($external) > 255, 404);

        return [
            'id' => null,
            'external' => $external,
        ];
    }

    private function encodeTrainerToken(
        ?int $employeeId,
        ?string $externalName
    ): string {
        $json = json_encode(
            [
                'id' => $employeeId,
                'external' => $employeeId === null
                    ? $externalName
                    : null,
            ],
            JSON_THROW_ON_ERROR
        );

        return rtrim(
            strtr(
                base64_encode($json),
                '+/',
                '-_'
            ),
            '='
        );
    }

    /**
     * @param array{id: ?int, external: ?string} $identity
     */
    private function trainerName(array $identity): string
    {
        if ($identity['id'] !== null) {
            return (string) DB::table('employees')
                ->where('id', $identity['id'])
                ->value('name');
        }

        return (string) $identity['external'];
    }

    private function validateFilters(): void
    {
        $this->search = trim($this->search);

        $this->position_filter = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn(mixed $value): string =>
                        trim((string) $value),
                        $this->position_filter
                    )
                )
            )
        );

        $this->validate([
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],

            'position_filter' => [
                'array',
            ],

            'position_filter.*' => [
                'string',
                'distinct',
                Rule::exists(
                    'positions',
                    'position_name'
                ),
            ],

            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],
        ]);
    }

    private function spreadsheetSafe(
        mixed $value
    ): string {
        $value = trim((string) $value);

        if (
            $value !== ''
            && in_array(
                $value[0],
                ['=', '+', '-', '@'],
                true
            )
        ) {
            return "'{$value}";
        }

        return $value;
    }
};
