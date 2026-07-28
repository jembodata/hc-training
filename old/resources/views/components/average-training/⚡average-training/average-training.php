<?php

use App\Support\Auth\Permissions;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component
{
    private const MINIMUM_YEAR = 2026;

    private const MONTHS = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    public int $year;
    public bool $show_export_modal = false;

    public function mount(): void
    {
        Gate::authorize(Permissions::VIEW_AVERAGE_TRAINING);

        $this->year = (int) date('Y');
    }

    public function updatedYear(mixed $value): void
    {
        $year = (int) $value;
        $currentYear = (int) date('Y');

        $this->year = min(
            max($year, self::MINIMUM_YEAR),
            $currentYear
        );
    }

    public function openExportModal(): void
    {
        Gate::authorize(Permissions::EXPORT_TRAINING_REPORT);

        $this->show_export_modal = true;
    }

    public function closeExportModal(): void
    {
        $this->show_export_modal = false;
    }

    public function exportExcel(): StreamedResponse
    {
        Gate::authorize(Permissions::EXPORT_TRAINING_REPORT);

        try {
            $data = $this->buildData();
            $year = $this->year;
            $fileName = "Average_Training_Hours_{$year}.csv";

            $this->show_export_modal = false;

            Flux::toast(
                heading: 'Export Started',
                text: 'File Average Training Hours sedang diunduh.',
                variant: 'success',
                duration: 3000,
            );

            return response()->streamDownload(
                function () use ($data, $year): void {
                    $stream = fopen('php://output', 'wb');

                    if ($stream === false) {
                        return;
                    }

                    fwrite($stream, "\xEF\xBB\xBF");
                    fwrite($stream, "sep=;\n");

                    fputcsv(
                        $stream,
                        ["REPORT AVERAGE TRAINING HOURS YTD {$year}"],
                        ';'
                    );

                    fputcsv(
                        $stream,
                        [
                            'Tanggal Cetak',
                            now()->format('d/m/Y H:i'),
                        ],
                        ';'
                    );

                    fputcsv($stream, [], ';');

                    fputcsv(
                        $stream,
                        [
                            'Department',
                            'Total Employees',
                            ...array_values(self::MONTHS),
                        ],
                        ';'
                    );

                    foreach ($data['rows'] as $row) {
                        $line = [
                            $this->spreadsheetSafe(
                                $row['organization_name']
                            ),
                            $row['employee_count'],
                        ];

                        foreach ($row['averages'] as $average) {
                            $line[] = $average === null
                                ? ''
                                : number_format($average, 2, ',', '');
                        }

                        fputcsv($stream, $line, ';');
                    }

                    $overallLine = [
                        'Overall Average',
                        '',
                    ];

                    foreach ($data['overallAverages'] as $average) {
                        $overallLine[] = $average === null
                            ? ''
                            : number_format($average, 2, ',', '');
                    }

                    fputcsv($stream, $overallLine, ';');

                    fclose($stream);
                },
                $fileName,
                [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                    'Cache-Control' => 'no-store, no-cache',
                ]
            );
        } catch (\Throwable $exception) {
            report($exception);

            $this->show_export_modal = false;

            Flux::toast(
                heading: 'Export Failed',
                text: 'Laporan gagal diekspor. Silakan coba kembali.',
                variant: 'danger',
                duration: 4000,
            );

            throw $exception;
        }
    }

    public function with(): array
    {
        return [
            ...$this->buildData(),
            'months' => self::MONTHS,
            'years' => range(
                (int) date('Y'),
                self::MINIMUM_YEAR
            ),
        ];
    }

    private function buildData(): array
    {
        $organizations = DB::table('organizations')
            ->select(['id', 'org_name'])
            ->orderBy('org_name')
            ->get();

        $employeeCounts = $this->employeeCounts();
        $monthlyMinutes = $this->monthlyTrainingMinutes();

        $currentMonth = $this->year === (int) date('Y')
            ? (int) date('n')
            : 12;

        $rows = [];
        $columnTotals = array_fill(1, 12, 0.0);
        $organizationCount = max($organizations->count(), 1);
        $totalTrainingMinutesYtd = 0;

        foreach ($organizations as $organization) {
            $employeeCount = (int) (
                $employeeCounts[$organization->id] ?? 0
            );

            $runningMinutes = 0;
            $averages = [];

            for ($month = 1; $month <= 12; $month++) {
                if ($month > $currentMonth) {
                    $averages[$month] = null;

                    continue;
                }

                $monthlyValue = (int) (
                    $monthlyMinutes[$organization->id][$month] ?? 0
                );

                $runningMinutes += $monthlyValue;
                $totalTrainingMinutesYtd += $monthlyValue;

                $average = $employeeCount > 0
                    ? ($runningMinutes / 60) / $employeeCount
                    : 0.0;

                $averages[$month] = $average;
                $columnTotals[$month] += $average;
            }

            $rows[] = [
                'organization_id' => (int) $organization->id,
                'organization_name' => (string) $organization->org_name,
                'employee_count' => $employeeCount,
                'averages' => $averages,
            ];
        }

        $overallAverages = [];

        for ($month = 1; $month <= 12; $month++) {
            $overallAverages[$month] = $month > $currentMonth
                ? null
                : $columnTotals[$month] / $organizationCount;
        }

        $activeEmployeeCount = array_sum($employeeCounts);

        return [
            'rows' => $rows,
            'overallAverages' => $overallAverages,
            'summary' => [
                'department_count' => $organizations->count(),
                'active_employee_count' => $activeEmployeeCount,
                'current_month' => $currentMonth,
                'current_month_name' => self::MONTHS[$currentMonth],
                'period_label' => sprintf(
                    'Jan - %s %d',
                    self::MONTHS[$currentMonth],
                    $this->year
                ),
                'overall_average' => (float) (
                    $overallAverages[$currentMonth] ?? 0
                ),
                'training_hours_ytd' => $totalTrainingMinutesYtd / 60,
            ],
        ];
    }

    private function employeeCounts(): array
    {
        return DB::table('employees')
            ->select('org_id', DB::raw('COUNT(*) AS total'))
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->where('status_employee', '!=', 'Harian Lepas')
            ->whereNotNull('org_id')
            ->groupBy('org_id')
            ->pluck('total', 'org_id')
            ->map(fn(mixed $total): int => (int) $total)
            ->all();
    }

    private function monthlyTrainingMinutes(): array
    {
        $records = DB::table('training_participants as tp')
            ->join('trainings as t', 'tp.training_id', '=', 't.id')
            ->join('employees as e', 'tp.employee_id', '=', 'e.id')
            ->select(
                'e.org_id',
                DB::raw('MONTH(t.training_date) AS training_month'),
                DB::raw(
                    'COALESCE(SUM(
                        CASE
                            WHEN t.start_time IS NOT NULL
                                AND t.finish_time IS NOT NULL
                            THEN GREATEST(
                                TIMESTAMPDIFF(
                                    MINUTE,
                                    t.start_time,
                                    t.finish_time
                                ),
                                0
                            )
                            ELSE 0
                        END
                    ), 0) AS total_minutes'
                )
            )
            ->where('e.status', 'Active')
            ->whereNull('e.deleted_at')
            ->where('e.status_employee', '!=', 'Harian Lepas')
            ->whereNull('t.deleted_at')
            ->whereNotNull('e.org_id')
            ->whereYear('t.training_date', $this->year)
            ->groupBy(
                'e.org_id',
                DB::raw('MONTH(t.training_date)')
            )
            ->get();

        $matrix = [];

        foreach ($records as $record) {
            $matrix[(int) $record->org_id][(int) $record->training_month]
                = (int) $record->total_minutes;
        }

        return $matrix;
    }

    private function spreadsheetSafe(string $value): string
    {
        $trimmed = ltrim($value);

        if (
            $trimmed !== '' &&
            in_array($trimmed[0], ['=', '+', '-', '@'], true)
        ) {
            return "'" . $value;
        }

        return $value;
    }
};
