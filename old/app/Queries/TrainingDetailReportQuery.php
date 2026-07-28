<?php

namespace App\Queries;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TrainingDetailReportQuery
{
    public function rows(array $filters): Builder
    {
        return $this->base($filters)
            ->select(
                'tp.id as participant_id',
                'tp.score',
                'e.id as employee_id',
                'e.nik',
                'e.name as employee_name',
                'e.status',
                'e.status_employee',
                'o.org_name as department',
                'p.position_name',
                't.id as training_id',
                't.title',
                't.held_by',
                't.training_date',
                't.start_time',
                't.finish_time',
                't.fee',
                't.activity_name',
                't.skill_name',
                't.is_certified',
                't.trainer_external_name',
                'tr.name as trainer_internal_name',
                'tr.nik as trainer_internal_nik'
            )
            ->orderByDesc('t.training_date')
            ->orderByDesc('tp.id');
    }

    public function stats(array $filters): object
    {
        return $this->base($filters)
            ->selectRaw("
                COUNT(tp.id) as total_attendances,
                COUNT(DISTINCT t.id) as total_unique_trainings,
                COALESCE(SUM(
                    CASE
                        WHEN t.start_time IS NOT NULL
                            AND t.finish_time IS NOT NULL
                        THEN GREATEST(
                            TIME_TO_SEC(TIMEDIFF(t.finish_time, t.start_time)) / 60,
                            0
                        )
                        ELSE 0
                    END
                ), 0) as total_minutes
            ")
            ->first();
    }

    public function titles()
    {
        return DB::table('trainings')
            ->whereNull('deleted_at')
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->select('title')
            ->distinct()
            ->orderBy('title')
            ->get();
    }

    public function trainers()
    {
        return DB::table('trainings as t')
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
            ->whereRaw(
                "TRIM(COALESCE(tr.name, t.trainer_external_name)) != ''"
            )
            ->distinct()
            ->orderBy('name')
            ->get();
    }

    public function rekapRows(array $filters): Builder
    {
        return DB::table('employees as e')
            ->leftJoin('organizations as o', 'e.org_id', '=', 'o.id')
            ->leftJoin('positions as p', 'e.position_id', '=', 'p.id')
            ->leftJoin('training_participants as tp', 'tp.employee_id', '=', 'e.id')
            ->leftJoin('trainings as t', function ($join) use ($filters) {
                $join->on('tp.training_id', '=', 't.id')
                    ->whereNull('t.deleted_at');

                if (! empty($filters['date_from'])) {
                    $join->whereDate('t.training_date', '>=', $filters['date_from']);
                }

                if (! empty($filters['date_to'])) {
                    $join->whereDate('t.training_date', '<=', $filters['date_to']);
                }
            })
            ->where('e.status', 'Active')
            ->whereNull('e.deleted_at')
            ->select(
                'e.id as employee_id',
                'e.nik',
                'e.name as employee_name',
                'e.status_employee',
                'o.org_name as department',
                'p.position_name'
            )
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN t.id IS NOT NULL
                            AND t.start_time IS NOT NULL
                            AND t.finish_time IS NOT NULL
                        THEN GREATEST(
                            TIME_TO_SEC(TIMEDIFF(t.finish_time, t.start_time)) / 60,
                            0
                        )
                        ELSE 0
                    END
                ), 0) as total_minutes
            ")
            ->groupBy(
                'e.id',
                'e.nik',
                'e.name',
                'e.status_employee',
                'o.org_name',
                'p.position_name'
            )
            ->orderBy('o.org_name')
            ->orderBy('e.name');
    }

    private function base(array $filters): Builder
    {
        return DB::table('training_participants as tp')
            ->join('trainings as t', 'tp.training_id', '=', 't.id')
            ->whereNull('t.deleted_at')
            ->leftJoin('employees as e', 'tp.employee_id', '=', 'e.id')
            ->leftJoin('organizations as o', 'e.org_id', '=', 'o.id')
            ->leftJoin('positions as p', 'e.position_id', '=', 'p.id')
            ->leftJoin('employees as tr', 't.trainer_employee_id', '=', 'tr.id')
            ->when($this->clean($filters['search'] ?? null), function ($query, string $search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('e.name', 'like', "%{$search}%")
                        ->orWhere('e.nik', 'like', "%{$search}%")
                        ->orWhere('o.org_name', 'like', "%{$search}%");
                });
            })
            ->when($this->clean($filters['title_filter'] ?? null), function ($query, string $title) {
                $query->where('t.title', $title);
            })
            ->when($this->clean($filters['trainer_filter'] ?? null), function ($query, string $trainer) {
                $query->where(function ($sub) use ($trainer) {
                    $sub->where('tr.name', 'like', "%{$trainer}%")
                        ->orWhere('tr.nik', 'like', "%{$trainer}%")
                        ->orWhere('t.trainer_external_name', 'like', "%{$trainer}%");
                });
            })
            ->when($this->clean($filters['date_from'] ?? null), function ($query, string $date) {
                $query->whereDate('t.training_date', '>=', $date);
            })
            ->when($this->clean($filters['date_to'] ?? null), function ($query, string $date) {
                $query->whereDate('t.training_date', '<=', $date);
            });
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}