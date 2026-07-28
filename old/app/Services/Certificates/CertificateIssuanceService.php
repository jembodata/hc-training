<?php

namespace App\Services\Certificates;

use App\Enums\IssuedCertificateStatus;
use App\Jobs\Certificates\GenerateIssuedCertificatePdf;
use App\Models\CertificateTemplate;
use App\Models\Employee;
use App\Models\IssuedCertificate;
use App\Models\Training;
use App\Support\Certificates\CertificateNumberGenerator;
use App\Support\Certificates\CertificateParticipantDataBuilder;
use App\Support\Certificates\CertificateTemplateSnapshotBuilder;
use App\Support\Certificates\CertificateVariablesBuilder;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CertificateIssuanceService
{
    public function __construct(
        private readonly CertificateNumberGenerator $numberGenerator,
        private readonly CertificateTemplateSnapshotBuilder $templateSnapshotBuilder,
        private readonly CertificateParticipantDataBuilder $participantDataBuilder,
        private readonly CertificateVariablesBuilder $variablesBuilder,
    ) {
    }

    public function issue(
        int $trainingId,
        int $employeeId,
        string $requestKey,
        int $issuedBy,
        ?CarbonInterface $issuedOn = null,
        ?CarbonInterface $expiresAt = null
    ): IssuedCertificate {
        $this->assertRequestKey($requestKey);
        [$issuedOn, $expiresAt] = $this->normalizeDates(
            $issuedOn,
            $expiresAt
        );

        [$certificate, $shouldDispatch] = DB::transaction(
            function () use (
                $trainingId,
                $employeeId,
                $requestKey,
                $issuedBy,
                $issuedOn,
                $expiresAt
            ): array {
                $existing = IssuedCertificate::query()
                    ->where('request_key', $requestKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $this->assertSameRequest(
                        $existing,
                        $trainingId,
                        $employeeId
                    );

                    return [
                        $existing,
                        in_array(
                            $existing->status,
                            [
                                IssuedCertificateStatus::PENDING,
                                IssuedCertificateStatus::PROCESSING,
                            ],
                            true
                        ),
                    ];
                }

                $training = Training::query()
                    ->lockForUpdate()
                    ->findOrFail($trainingId);

                if ($training->is_certified !== 'Yes') {
                    throw ValidationException::withMessages([
                        'training' =>
                            'Training ini tidak mengaktifkan certificate.',
                    ]);
                }

                if ($training->certificate_template_id === null) {
                    throw ValidationException::withMessages([
                        'training' =>
                            'Training belum memiliki certificate template.',
                    ]);
                }

                $employee = Employee::query()
                    ->findOrFail($employeeId);

                $isParticipant = $training->participants()
                    ->whereKey($employee->id)
                    ->exists();

                if (! $isParticipant) {
                    throw ValidationException::withMessages([
                        'employee' =>
                            'Employee bukan participant training ini.',
                    ]);
                }

                $template = CertificateTemplate::query()
                    ->active()
                    ->find($training->certificate_template_id);

                if ($template === null) {
                    throw ValidationException::withMessages([
                        'training' =>
                            'Certificate template training tidak aktif.',
                    ]);
                }

                $this->assertNoActiveCertificate(
                    $training->id,
                    $employee->id
                );

                $certificateNumber =
                    $this->numberGenerator->next(
                        (int) $issuedOn->format('Y')
                    );

                $templateSnapshot =
                    $this->templateSnapshotBuilder->build($template);

                $participantSnapshot =
                    $this->participantDataBuilder->build(
                        $training,
                        $employee
                    );

                $variablesSnapshot =
                    $this->variablesBuilder->build(
                        $participantSnapshot,
                        $certificateNumber,
                        $issuedOn,
                        $expiresAt
                    );

                $certificate = IssuedCertificate::query()->create([
                    'certificate_number' => $certificateNumber,
                    'request_key' => $requestKey,
                    'training_id' => $training->id,
                    'employee_id' => $employee->id,
                    'certificate_template_id' => $template->id,
                    'status' => IssuedCertificateStatus::PENDING,
                    'template_snapshot' => $templateSnapshot,
                    'participant_snapshot' => $participantSnapshot,
                    'variables_snapshot' => $variablesSnapshot,
                    'issued_on' => $issuedOn->toDateString(),
                    'expires_at' => $expiresAt?->toDateString(),
                    'issued_by' => $issuedBy,
                ]);

                return [$certificate, true];
            },
            3
        );

        if ($shouldDispatch) {
            $this->dispatchGeneration($certificate);
        }

        return $certificate->refresh();
    }

    public function reissue(
        int $certificateId,
        string $requestKey,
        int $issuedBy,
        ?CarbonInterface $issuedOn = null,
        ?CarbonInterface $expiresAt = null
    ): IssuedCertificate {
        $this->assertRequestKey($requestKey);

        [$certificate, $shouldDispatch] = DB::transaction(
            function () use (
                $certificateId,
                $requestKey,
                $issuedBy,
                $issuedOn,
                $expiresAt
            ): array {
                $source = IssuedCertificate::query()
                    ->lockForUpdate()
                    ->findOrFail($certificateId);

                if (
                    ! in_array(
                        $source->status,
                        [
                            IssuedCertificateStatus::ISSUED,
                            IssuedCertificateStatus::REVOKED,
                        ],
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'certificate' =>
                            'Certificate hanya dapat di-reissue setelah issued atau revoked.',
                    ]);
                }

                $existing = IssuedCertificate::query()
                    ->where('request_key', $requestKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if ($existing->supersedes_id !== $source->id) {
                        throw ValidationException::withMessages([
                            'request_key' =>
                                'Request key sudah digunakan untuk operasi lain.',
                        ]);
                    }

                    return [
                        $existing,
                        in_array(
                            $existing->status,
                            [
                                IssuedCertificateStatus::PENDING,
                                IssuedCertificateStatus::PROCESSING,
                            ],
                            true
                        ),
                    ];
                }

                if ($source->supersededBy()->exists()) {
                    throw ValidationException::withMessages([
                        'certificate' =>
                            'Certificate ini sudah pernah di-reissue.',
                    ]);
                }

                [$reissuedOn, $reissuedExpiresAt] =
                    $this->normalizeDates(
                        $issuedOn ?? $source->issued_on,
                        $expiresAt ?? $source->expires_at
                    );

                $training = Training::query()
                    ->whereKey($source->training_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($training->is_certified !== 'Yes') {
                    throw ValidationException::withMessages([
                        'training' =>
                            'Training ini tidak mengaktifkan certificate.',
                    ]);
                }

                if ($training->certificate_template_id === null) {
                    throw ValidationException::withMessages([
                        'training' =>
                            'Training belum memiliki certificate template.',
                    ]);
                }

                $template = CertificateTemplate::query()
                    ->active()
                    ->find($training->certificate_template_id);

                if ($template === null) {
                    throw ValidationException::withMessages([
                        'training' =>
                            'Certificate template training tidak aktif.',
                    ]);
                }

                $templateSnapshot =
                    $this->templateSnapshotBuilder->build($template);

                $this->assertNoActiveCertificate(
                    $source->training_id,
                    $source->employee_id,
                    $source->id
                );

                $certificateNumber =
                    $this->numberGenerator->next(
                        (int) $reissuedOn->format('Y')
                    );

                $participantSnapshot =
                    $source->participant_snapshot;

                $variablesSnapshot =
                    $this->variablesBuilder->build(
                        $participantSnapshot,
                        $certificateNumber,
                        $reissuedOn,
                        $reissuedExpiresAt
                    );

                $certificate = IssuedCertificate::query()->create([
                    'certificate_number' => $certificateNumber,
                    'request_key' => $requestKey,
                    'training_id' => $source->training_id,
                    'employee_id' => $source->employee_id,
                    'certificate_template_id' => $template->id,
                    'supersedes_id' => $source->id,
                    'status' => IssuedCertificateStatus::PENDING,
                    'template_snapshot' => $templateSnapshot,
                    'participant_snapshot' => $participantSnapshot,
                    'variables_snapshot' => $variablesSnapshot,
                    'issued_on' => $reissuedOn->toDateString(),
                    'expires_at' =>
                        $reissuedExpiresAt?->toDateString(),
                    'issued_by' => $issuedBy,
                ]);

                if ($source->status === IssuedCertificateStatus::ISSUED) {
                    $source->update([
                        'status' => IssuedCertificateStatus::REVOKED,
                        'revoked_at' => now(),
                        'revoked_by' => $issuedBy,
                        'revocation_reason' =>
                            'Superseded by certificate reissue.',
                    ]);
                }

                return [$certificate, true];
            },
            3
        );

        if ($shouldDispatch) {
            $this->dispatchGeneration($certificate);
        }

        return $certificate->refresh();
    }

    public function retry(
        int $certificateId
    ): IssuedCertificate {
        $certificate = DB::transaction(function () use (
            $certificateId
        ): IssuedCertificate {
            $certificate = IssuedCertificate::query()
                ->lockForUpdate()
                ->findOrFail($certificateId);

            if (
                $certificate->status
                !== IssuedCertificateStatus::FAILED
            ) {
                throw ValidationException::withMessages([
                    'certificate' =>
                        'Hanya certificate berstatus failed yang dapat di-retry.',
                ]);
            }

            $certificate->update([
                'status' => IssuedCertificateStatus::PENDING,
                'processing_started_at' => null,
                'failure_message' => null,
            ]);

            return $certificate;
        }, 3);

        $this->dispatchGeneration($certificate);

        return $certificate->refresh();
    }

    public function revoke(
        int $certificateId,
        int $revokedBy,
        string $reason
    ): IssuedCertificate {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'revocation_reason' =>
                    'Alasan revoke wajib diisi.',
            ]);
        }

        return DB::transaction(function () use (
            $certificateId,
            $revokedBy,
            $reason
        ): IssuedCertificate {
            $certificate = IssuedCertificate::query()
                ->lockForUpdate()
                ->findOrFail($certificateId);

            if (
                $certificate->status
                !== IssuedCertificateStatus::ISSUED
            ) {
                throw ValidationException::withMessages([
                    'certificate' =>
                        'Hanya certificate issued yang dapat di-revoke.',
                ]);
            }

            $certificate->update([
                'status' => IssuedCertificateStatus::REVOKED,
                'revoked_at' => now(),
                'revoked_by' => $revokedBy,
                'revocation_reason' => $reason,
            ]);

            return $certificate;
        }, 3);
    }

    private function assertNoActiveCertificate(
        int $trainingId,
        int $employeeId,
        ?int $ignoreCertificateId = null
    ): void {
        $exists = IssuedCertificate::query()
            ->where('training_id', $trainingId)
            ->where('employee_id', $employeeId)
            ->whereIn('status', [
                IssuedCertificateStatus::PENDING->value,
                IssuedCertificateStatus::PROCESSING->value,
                IssuedCertificateStatus::ISSUED->value,
                IssuedCertificateStatus::FAILED->value,
            ])
            ->when(
                $ignoreCertificateId !== null,
                fn ($query) => $query->where(
                    'id',
                    '!=',
                    $ignoreCertificateId
                )
            )
            ->lockForUpdate()
            ->first(['id']) !== null;

        if ($exists) {
            throw ValidationException::withMessages([
                'employee' =>
                    'Participant sudah memiliki certificate aktif atau certificate yang harus di-retry.',
            ]);
        }
    }

    private function assertRequestKey(string $requestKey): void
    {
        if (! Str::isUuid($requestKey)) {
            throw ValidationException::withMessages([
                'request_key' => 'Request key tidak valid.',
            ]);
        }
    }

    private function assertSameRequest(
        IssuedCertificate $certificate,
        int $trainingId,
        int $employeeId
    ): void {
        if (
            $certificate->training_id !== $trainingId
            || $certificate->employee_id !== $employeeId
        ) {
            throw ValidationException::withMessages([
                'request_key' =>
                    'Request key sudah digunakan untuk certificate lain.',
            ]);
        }
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable|null}
     */
    private function normalizeDates(
        ?CarbonInterface $issuedOn,
        ?CarbonInterface $expiresAt
    ): array {
        $issued = $issuedOn === null
            ? CarbonImmutable::today()
            : CarbonImmutable::instance($issuedOn)->startOfDay();

        $expires = $expiresAt === null
            ? null
            : CarbonImmutable::instance($expiresAt)->startOfDay();

        if ($expires !== null && $expires->isBefore($issued)) {
            throw ValidationException::withMessages([
                'expires_at' =>
                    'Tanggal berlaku tidak boleh sebelum tanggal issue.',
            ]);
        }

        return [$issued, $expires];
    }

    private function dispatchGeneration(
        IssuedCertificate $certificate
    ): void {
        GenerateIssuedCertificatePdf::dispatch(
            $certificate->id
        )->onQueue(
            (string) config(
                'certificates.queue',
                'certificates'
            )
        );
    }
}