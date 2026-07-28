<?php

use App\Exports\TrainingPenetrationSummaryExport;
use App\Support\Auth\Permissions;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new class extends Component
{
    public string $departmentId = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $trainingSearch = '';

    public ?int $selectedTrainingId = null;

    public string $selectedTrainingTitle = '';

    public bool $showDetailModal = false;

    public ?int $selectedDepartmentId = null;

    public string $selectedDepartmentName = '';

    public string $selectedType = '';

    public array $employeeList = [];

    protected $queryString = [
        'departmentId' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
    ];

    public function updatedDepartmentId(): void
    {
        $this->closeDetail();
    }

    public function updatedDateFrom(): void
    {
        $this->closeDetail();
    }

    public function updatedDateTo(): void
    {
        $this->closeDetail();
    }

    public function selectTraining(int $trainingId): void
    {
        $training = DB::table('trainings')
            ->where('id', $trainingId)
            ->whereNull('deleted_at')
            ->select([
                'id',
                'title',
            ])
            ->first();

        abort_if($training === null, 404);

        $this->selectedTrainingId = (int) $training->id;
        $this->selectedTrainingTitle = trim(
            (string) $training->title
        );
        $this->trainingSearch = '';

        $this->closeDetail();
    }

    public function clearTraining(): void
    {
        $this->selectedTrainingId = null;
        $this->selectedTrainingTitle = '';
        $this->trainingSearch = '';

        $this->closeDetail();
    }

    public function resetFilters(): void
    {
        $this->reset([
            'departmentId',
            'dateFrom',
            'dateTo',
            'trainingSearch',
            'selectedTrainingId',
            'selectedTrainingTitle',
        ]);

        $this->closeDetail();
    }

    public function showDetail(
        string $type,
        int $departmentId
    ): void {
        abort_unless(
            in_array($type, ['trained', 'untrained'], true),
            404
        );

        $this->validateFilters();

        $department = DB::table('organizations')
            ->where('id', $departmentId)
            ->select([
                'id',
                'org_name',
            ])
            ->first();

        abort_if($department === null, 404);

        $query = $this->activeEmployeesQuery()
            ->where('e.org_id', $departmentId);

        $this->applyTrainingStatusFilter(
            $query,
            $type === 'trained'
        );

        $this->selectedDepartmentId = (int) $department->id;
        $this->selectedDepartmentName =
            (string) $department->org_name;
        $this->selectedType = $type;

        $this->employeeList = $query
            ->select([
                'e.id',
                'e.name',
                'e.nik',
            ])
            ->orderBy('e.name')
            ->get()
            ->map(
                static fn(object $employee): array => [
                    'id' => (int) $employee->id,
                    'name' => (string) $employee->name,
                    'nik' => (string) $employee->nik,
                ]
            )
            ->all();

        $this->showDetailModal = true;
    }

    public function closeDetail(): void
    {
        $this->showDetailModal = false;
        $this->selectedDepartmentId = null;
        $this->selectedDepartmentName = '';
        $this->selectedType = '';
        $this->employeeList = [];
    }

    public function exportExcel(): BinaryFileResponse
    {
        abort_unless(
            auth()->user()?->can(
                Permissions::EXPORT_TRAINING_REPORT
            ),
            403
        );

        $this->validateFilters();

        $report = $this->reportData();

        return Excel::download(
            new TrainingPenetrationSummaryExport(
                rows: $report['results'],
                sumTotal: $report['sumTotal'],
                sumTrained: $report['sumTrained'],
                totalPercentage: $report['totalPct'],
                departmentLabel: $this->selectedDepartmentLabel(),
                periodLabel: $this->selectedPeriodLabel(),
                trainingLabel: $this->selectedTrainingTitle
                    ?: 'Semua Training',
            ),
            'Training_Penetration_'
                . now()->format('Ymd_His')
                . '.xlsx'
        );
    }

    public function with(): array
    {
        return [
            ...$this->reportData(),
            'trainingOptions' => $this->trainingOptions(),
            'allOrganizations' => DB::table('organizations')
                ->select([
                    'id',
                    'org_name',
                ])
                ->orderBy('org_name')
                ->get(),
        ];
    }

    private function reportData(): array
    {
        $organizations = $this->organizationsQuery()->get();
        $employeeCounts = $this->activeEmployeeCounts();
        $trainedCounts = $this->trainedEmployeeCounts();

        $sumTotal = 0;
        $sumTrained = 0;

        $results = $organizations->map(
            function (object $organization) use (
                $employeeCounts,
                $trainedCounts,
                &$sumTotal,
                &$sumTrained
            ): object {
                $organizationId = (int) $organization->id;

                $totalEmployees = (int) (
                    $employeeCounts[$organizationId] ?? 0
                );

                $trainedEmployees = min(
                    (int) (
                        $trainedCounts[$organizationId] ?? 0
                    ),
                    $totalEmployees
                );

                $sumTotal += $totalEmployees;
                $sumTrained += $trainedEmployees;

                return (object) [
                    'org_id' => $organizationId,
                    'org_name' =>
                    (string) $organization->org_name,
                    'total_emp' => $totalEmployees,
                    'trained' => $trainedEmployees,
                    'untrained' => max(
                        0,
                        $totalEmployees - $trainedEmployees
                    ),
                    'percentage' => $this->percentage(
                        $trainedEmployees,
                        $totalEmployees
                    ),
                ];
            }
        );

        return [
            'results' => $results,
            'sumTotal' => $sumTotal,
            'sumTrained' => $sumTrained,
            'totalPct' => $this->percentage(
                $sumTrained,
                $sumTotal
            ),
        ];
    }

    private function organizationsQuery(): Builder
    {
        return DB::table('organizations')
            ->select([
                'id',
                'org_name',
            ])
            ->when(
                $this->departmentId !== '',
                fn(Builder $query) => $query->where(
                    'id',
                    (int) $this->departmentId
                )
            )
            ->orderBy('org_name');
    }

    private function activeEmployeesQuery(): Builder
    {
        return DB::table('employees as e')
            ->whereNull('e.deleted_at')
            ->whereRaw(
                'LOWER(TRIM(e.status)) = ?',
                ['active']
            )
            ->where(
                function (Builder $query): void {
                    $query
                        ->whereNull('e.status_employee')
                        ->orWhereRaw(
                            'LOWER(TRIM(e.status_employee)) <> ?',
                            ['harian lepas']
                        );
                }
            );
    }

    private function activeEmployeeCounts(): array
    {
        return $this->activeEmployeesQuery()
            ->whereNotNull('e.org_id')
            ->when(
                $this->departmentId !== '',
                fn(Builder $query) => $query->where(
                    'e.org_id',
                    (int) $this->departmentId
                )
            )
            ->selectRaw(
                'e.org_id as org_id, '
                    . 'COUNT(*) as employee_count'
            )
            ->groupBy('e.org_id')
            ->pluck('employee_count', 'org_id')
            ->map(
                static fn(mixed $count): int =>
                (int) $count
            )
            ->all();
    }

    private function trainedEmployeeCounts(): array
    {
        $query = DB::table(
            'training_participants as tp'
        )
            ->join(
                'trainings as t',
                't.id',
                '=',
                'tp.training_id'
            )
            ->join(
                'employees as e',
                'e.id',
                '=',
                'tp.employee_id'
            )
            ->whereNull('t.deleted_at')
            ->whereNull('e.deleted_at')
            ->whereRaw(
                'LOWER(TRIM(e.status)) = ?',
                ['active']
            )
            ->where(
                function (Builder $query): void {
                    $query
                        ->whereNull('e.status_employee')
                        ->orWhereRaw(
                            'LOWER(TRIM(e.status_employee)) <> ?',
                            ['harian lepas']
                        );
                }
            )
            ->whereNotNull('e.org_id')
            ->when(
                $this->departmentId !== '',
                fn(Builder $query) => $query->where(
                    'e.org_id',
                    (int) $this->departmentId
                )
            );

        $this->applyTrainingFilters($query);

        return $query
            ->selectRaw(
                'e.org_id as org_id, '
                    . 'COUNT(DISTINCT tp.employee_id) '
                    . 'as trained_count'
            )
            ->groupBy('e.org_id')
            ->pluck('trained_count', 'org_id')
            ->map(
                static fn(mixed $count): int =>
                (int) $count
            )
            ->all();
    }

    private function applyTrainingStatusFilter(
        Builder $employeeQuery,
        bool $trained
    ): void {
        $callback = function (Builder $query): void {
            $query
                ->selectRaw('1')
                ->from('training_participants as tp')
                ->join(
                    'trainings as t',
                    't.id',
                    '=',
                    'tp.training_id'
                )
                ->whereColumn(
                    'tp.employee_id',
                    'e.id'
                )
                ->whereNull('t.deleted_at');

            $this->applyTrainingFilters($query);
        };

        if ($trained) {
            $employeeQuery->whereExists($callback);

            return;
        }

        $employeeQuery->whereNotExists($callback);
    }

    private function applyTrainingFilters(
        Builder $query
    ): Builder {
        return $query
            ->when(
                $this->dateFrom !== '',
                fn(Builder $dateQuery) =>
                $dateQuery->whereDate(
                    't.training_date',
                    '>=',
                    $this->dateFrom
                )
            )
            ->when(
                $this->dateTo !== '',
                fn(Builder $dateQuery) =>
                $dateQuery->whereDate(
                    't.training_date',
                    '<=',
                    $this->dateTo
                )
            )
            ->when(
                $this->selectedTrainingId !== null,
                fn(Builder $titleQuery) =>
                $titleQuery->whereRaw(
                    'LOWER(TRIM(t.title)) = ('
                        . 'SELECT LOWER(TRIM(selected.title)) '
                        . 'FROM trainings as selected '
                        . 'WHERE selected.id = ? '
                        . 'AND selected.deleted_at IS NULL '
                        . 'LIMIT 1'
                        . ')',
                    [$this->selectedTrainingId]
                )
            );
    }

    private function trainingOptions(): Collection
    {
        $search = mb_strtolower(
            trim($this->trainingSearch)
        );

        return DB::table('trainings')
            ->whereNull('deleted_at')
            ->whereNotNull('title')
            ->whereRaw("TRIM(title) <> ''")
            ->when(
                $search !== '',
                fn(Builder $query) => $query->whereRaw(
                    'LOWER(TRIM(title)) LIKE ?',
                    ["%{$search}%"]
                )
            )
            ->selectRaw('MIN(id) as id')
            ->selectRaw('MIN(TRIM(title)) as title')
            ->selectRaw('COUNT(*) as schedule_count')
            ->groupByRaw('LOWER(TRIM(title))')
            ->orderBy('title')
            ->limit(20)
            ->get()
            ->map(
                static fn(object $training): object =>
                (object) [
                    'id' => (int) $training->id,
                    'title' => (string) $training->title,
                    'description' =>
                    (int) $training->schedule_count
                        . ' sesi',
                ]
            );
    }

    private function selectedDepartmentLabel(): string
    {
        if ($this->departmentId === '') {
            return 'Semua Department';
        }

        return (string) DB::table('organizations')
            ->where('id', (int) $this->departmentId)
            ->value('org_name');
    }

    private function selectedPeriodLabel(): string
    {
        if (
            $this->dateFrom === ''
            && $this->dateTo === ''
        ) {
            return 'Semua Periode';
        }

        return ($this->dateFrom ?: '-')
            . ' s/d '
            . ($this->dateTo ?: '-');
    }

    private function validateFilters(): void
    {
        $this->departmentId = trim(
            $this->departmentId
        );

        $this->trainingSearch = trim(
            $this->trainingSearch
        );

        $this->validate([
            'departmentId' => [
                'nullable',
                'integer',
                Rule::exists(
                    'organizations',
                    'id'
                ),
            ],
            'dateFrom' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'dateTo' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:dateFrom',
            ],
            'selectedTrainingId' => [
                'nullable',
                'integer',
                Rule::exists(
                    'trainings',
                    'id'
                )->where(
                    fn(Builder $query) =>
                    $query->whereNull('deleted_at')
                ),
            ],
            'trainingSearch' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);
    }

    private function percentage(
        int $part,
        int $total
    ): float {
        return $total > 0
            ? round(
                ($part / $total) * 100,
                2
            )
            : 0.0;
    }
};
