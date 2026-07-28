<?php

namespace App\Support\Certificates;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

final class CertificateVariablesBuilder
{
    /**
     * @param array<string, mixed> $participantSnapshot
     *
     * @return array<string, string>
     */
    public function build(
        array $participantSnapshot,
        string $certificateNumber,
        CarbonInterface $issuedOn,
        ?CarbonInterface $expiresAt
    ): array {
        $employee = $participantSnapshot['employee'] ?? [];
        $training = $participantSnapshot['training'] ?? [];
        $format = (string) config(
            'certificates.date_format',
            'd M Y'
        );

        $trainingDate = ! empty($training['training_date'])
            ? \Carbon\CarbonImmutable::parse(
                $training['training_date']
            )->format($format)
            : '';

        return [
            'employee_name' => Str::title(
                Str::lower(
                    trim((string) ($employee['name'] ?? ''))
                )
            ),
            'employee_nik' => (string) ($employee['nik'] ?? ''),
            'department_name' =>
            (string) ($employee['department_name'] ?? ''),
            'position_name' =>
            (string) ($employee['position_name'] ?? ''),
            'course_title' => (string) ($training['title'] ?? ''),
            'tanggal_training' => $trainingDate,
            'training_group_title' =>
            (string) ($training['training_group_title'] ?? ''),
            'training_group_code' =>
            (string) ($training['training_group_code'] ?? ''),
            'batch_number' =>
            (string) ($training['batch_number'] ?? ''),
            'batch_name' =>
            (string) ($training['batch_name'] ?? ''),
            'held_by' => (string) ($training['held_by'] ?? ''),
            'training_start_time' =>
            (string) ($training['start_time'] ?? ''),
            'training_finish_time' =>
            (string) ($training['finish_time'] ?? ''),
            'certificate_id' => $certificateNumber,
            'issued_on' => $issuedOn->format($format),
            'expires_at' => $expiresAt?->format($format) ?? '',
        ];
    }
}
