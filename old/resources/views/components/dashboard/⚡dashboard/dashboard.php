<?php

use App\Support\Auth\Permissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
    // =========================================
    // 1. STATE FILTER
    // =========================================
    public $filter_month = 'all';
    public $filter_year;
    public $filter_org = 'all';

    public function mount(): void
    {
        Gate::authorize(
            Permissions::VIEW_DASHBOARD
        );

        $this->filter_year = date('Y');
    }

    public function updated($property)
    {
        // Setiap filter berubah, kirim data chart terbaru ke front-end
        $this->dispatch('update-chart', chartData: $this->getChartData());
    }

    // =========================================
    // 2. HELPER
    // =========================================

    private function selectedMonth()
    {
        if ($this->filter_month === 'all' || $this->filter_month === null || $this->filter_month === '') {
            return null;
        }

        return (int) $this->filter_month;
    }

    private function currentReportMonth()
    {
        // Sama seperti average-training.php:
        // Jika tahun berjalan, berhenti di bulan saat ini.
        // Jika tahun sebelumnya, tampilkan sampai Desember.
        if ((int) $this->filter_year === (int) date('Y')) {
            return (int) date('n');
        }

        return 12;
    }

    private function reportEndMonth()
    {
        // Untuk KPI:
        // Jika user pilih bulan tertentu, hitung YTD sampai bulan itu.
        // Jika pilih "all", hitung sampai bulan berjalan / Desember.
        $selectedMonth = $this->selectedMonth();

        if ($selectedMonth !== null) {
            return $selectedMonth;
        }

        return $this->currentReportMonth();
    }

    private function baseParticipantTrainingQuery()
    {
        return DB::table('training_participants as tp')
            ->join('trainings as t', 'tp.training_id', '=', 't.id')
            ->join('employees as e', 'tp.employee_id', '=', 'e.id')
            ->where('e.status', 'Active')
            ->whereNull('e.deleted_at')
            ->whereNull('t.deleted_at')
            ->where('e.status_employee', '!=', 'Harian Lepas')
            ->whereYear('t.training_date', $this->filter_year);
    }

    private function baseEmployeeQuery()
    {
        return DB::table('employees')
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->where('status_employee', '!=', 'Harian Lepas');
    }

    // =========================================
    // 3. CHART DATA
    // =========================================

    private function getChartData()
    {
        /*
        Chart Trend Jam Pelatihan:
        - Tidak YTD
        - Hitung total jam training per bulan
        - Tetap pakai logic peserta training seperti average-training.php
        - Tetap exclude Harian Lepas
    */

        $actualData = array_fill(0, 12, null);

        $query = $this->baseParticipantTrainingQuery()
            ->select(
                DB::raw('MONTH(t.training_date) as bln'),
                DB::raw('SUM(TIMESTAMPDIFF(MINUTE, t.start_time, t.finish_time)) as total_mins')
            );

        if ($this->filter_org !== 'all') {
            $query->where('e.org_id', $this->filter_org);
        }

        $results = $query
            ->groupBy('bln')
            ->get();

        $currentMonth = $this->currentReportMonth();

        // Isi bulan yang sudah berjalan dengan 0 dulu
        // supaya bulan tanpa training tetap tampil 0, bukan kosong
        for ($bln = 1; $bln <= $currentMonth; $bln++) {
            $actualData[$bln - 1] = 0;
        }

        foreach ($results as $row) {
            $idx = (int) $row->bln - 1;

            // Total jam training bulan tersebut, bukan akumulasi YTD
            $actualData[$idx] = round(($row->total_mins ?? 0) / 60, 1);
        }

        $trendData = $this->calculateLinearRegression($actualData);

        return [
            'actualData' => $actualData,
            'trendData'  => $trendData,
        ];
    }

    private function calculateLinearRegression(array $data)
    {
        /*
        Support null supaya bulan yang belum berjalan
        tidak dianggap 0 dalam perhitungan trend.
    */

        $points = [];

        foreach ($data as $x => $y) {
            if ($y !== null) {
                $points[] = [
                    'x' => $x,
                    'y' => $y,
                ];
            }
        }

        $n = count($points);

        if ($n === 0) {
            return array_fill(0, count($data), null);
        }

        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumXX = 0;

        foreach ($points as $point) {
            $x = $point['x'];
            $y = $point['y'];

            $sumX += $x;
            $sumY += $y;
            $sumXY += ($x * $y);
            $sumXX += ($x * $x);
        }

        $denominator = ($n * $sumXX) - ($sumX * $sumX);

        if ($denominator == 0) {
            $avg = round($sumY / $n, 1);

            return array_map(function ($value) use ($avg) {
                return $value === null ? null : $avg;
            }, $data);
        }

        $m = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $c = ($sumY - ($m * $sumX)) / $n;

        $trend = [];

        foreach ($data as $x => $value) {
            if ($value === null) {
                $trend[] = null;
                continue;
            }

            $trend[] = round(($m * $x) + $c, 1);
        }

        return $trend;
    }

    // =========================================
    // 4. DATA UNTUK VIEW
    // =========================================

    public function with(): array
    {
        $orgs_master = DB::table('organizations')
            ->orderBy('org_name', 'ASC')
            ->get();

        $chartResult = $this->getChartData();

        $actualHoursData = $chartResult['actualData'];
        $trendHoursData = $chartResult['trendData'];

        // =========================================
        // KPI CARDS LOGIC
        // =========================================

        /*
            Sama dengan average-training.php:

            Total jam training dihitung dari peserta training:
            SUM(durasi training per peserta)

            Jika filter bulan Januari:
            hitung YTD sampai Januari.

            Jika filter bulan Februari:
            hitung YTD Januari + Februari.

            Jika filter bulan all:
            hitung sampai bulan berjalan untuk tahun ini,
            atau sampai Desember untuk tahun lampau.
        */

        $endMonth = $this->reportEndMonth();

        $queryKpi = $this->baseParticipantTrainingQuery()
            ->whereMonth('t.training_date', '<=', $endMonth);

        if ($this->filter_org !== 'all') {
            $queryKpi->where('e.org_id', $this->filter_org);
        }

        $kpi = $queryKpi
            ->select(
                DB::raw('COUNT(DISTINCT t.id) as total_realized'),
                DB::raw('SUM(TIMESTAMPDIFF(MINUTE, t.start_time, t.finish_time)) as total_mins')
            )
            ->first();

        $totalMinutes = (float) ($kpi->total_mins ?? 0);
        $total_hours = round($totalMinutes / 60, 1);

        // =========================================
        // TOTAL EMPLOYEES
        // =========================================

        /*
            Sama dengan average-training.php:

            - status = Active
            - deleted_at IS NULL
            - status_employee != Harian Lepas
        */

        $empQuery = $this->baseEmployeeQuery();

        if ($this->filter_org !== 'all') {
            $empQuery->where('org_id', $this->filter_org);
        }

        $total_employees = $empQuery->count();

        $avg_training_hours = ($total_employees > 0)
            ? round(($totalMinutes / 60) / $total_employees, 2)
            : 0;

        // =========================================
        // DATA TRAINING PENETRATION
        // =========================================

        $penetration_list = DB::table('organizations as o')
            ->when($this->filter_org !== 'all', function ($q) {
                $q->where('o.id', $this->filter_org);
            })
            ->select([
                'o.id',
                'o.org_name',

                'total_emp' => DB::table('employees')
                    ->selectRaw('count(*)')
                    ->whereColumn('org_id', 'o.id')
                    ->where('status', 'Active')
                    ->whereNull('deleted_at')
                    ->where('status_employee', '!=', 'Harian Lepas'),

                'trained_emp' => DB::table('training_participants as tp')
                    ->join('employees as e', 'tp.employee_id', '=', 'e.id')
                    ->join('trainings as t', 'tp.training_id', '=', 't.id')
                    ->selectRaw('count(distinct tp.employee_id)')
                    ->whereColumn('e.org_id', 'o.id')
                    ->where('e.status', 'Active')
                    ->whereNull('e.deleted_at')
                    ->whereNull('t.deleted_at')
                    ->where('e.status_employee', '!=', 'Harian Lepas')
                    ->whereYear('t.training_date', $this->filter_year)
                    ->whereMonth('t.training_date', '<=', $endMonth),
            ])
            ->orderBy('o.org_name', 'ASC')
            ->get();

        return [
            'actualHoursData'    => $actualHoursData,
            'trendHoursData'     => $trendHoursData,
            'orgs_master'        => $orgs_master,
            'total_hours'        => $total_hours,
            'avg_training_hours' => $avg_training_hours,
            'total_employees'    => $total_employees,
            'penetration_list'   => $penetration_list,

            'months' => [
                '01' => 'Januari',
                '02' => 'Februari',
                '03' => 'Maret',
                '04' => 'April',
                '05' => 'Mei',
                '06' => 'Juni',
                '07' => 'Juli',
                '08' => 'Agustus',
                '09' => 'September',
                '10' => 'Oktober',
                '11' => 'November',
                '12' => 'Desember',
            ],
        ];
    }
};