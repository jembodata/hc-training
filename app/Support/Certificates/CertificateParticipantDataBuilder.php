<?php

namespace App\Support\Certificates;

use App\Models\Employee;
use App\Models\Training;

final class CertificateParticipantDataBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(
        Training $training,
        Employee $employee
    ): array {
        $employee->loadMissing(['organization', 'position']);
        $training->loadMissing('trainingGroup');

        return [
            'employee' => [
                'id' => (int) $employee->id,
                'name' => (string) $employee->name,
                'nik' => (string) $employee->nik,
                'department_name' =>
                    $employee->organization?->org_name,
                'position_name' =>
                    $employee->position?->position_name,
            ],
            'training' => [
                'id' => (int) $training->id,
                'title' => (string) $training->title,
                'held_by' => (string) $training->held_by,
                'training_date' =>
                    $training->training_date?->toDateString(),
                'start_time' => $training->start_time,
                'finish_time' => $training->finish_time,
                'training_group_id' => $training->training_group_id,
                'training_group_code' =>
                    $training->trainingGroup?->code,
                'training_group_title' =>
                    $training->trainingGroup?->title,
                'batch_number' => $training->batch_number,
                'batch_name' => $training->batch_name,
            ],
            'snapshotted_at' => now()->toIso8601String(),
        ];
    }
}
