<?php

use App\Enums\IssuedCertificateStatus;
use App\Models\IssuedCertificate;
use App\Models\Training;
use App\Services\Certificates\CertificateIssuanceService;
use App\Support\Auth\Permissions;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Ramsey\Uuid\Uuid;

new class extends Component
{
    use WithPagination;

    private const MAX_BULK_ISSUE = 100;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $status_filter = '';

    public bool $show_issue_modal = false;

    public bool $show_revoke_modal = false;

    public bool $show_preview_modal = false;

    public bool $show_reissue_modal = false;

    public ?int $preview_certificate_id = null;

    public string $preview_certificate_number = '';

    public ?int $reissue_certificate_id = null;

    public string $reissue_certificate_number = '';

    public ?int $selected_training_id = null;

    /** @var array<int, int|string> */
    public array $selected_employee_ids = [];

    public string $bulk_request_key = '';

    /** @var array<int, string> */
    public array $bulk_issue_errors = [];

    /** @var array<int, int> */
    public array $tracked_certificate_ids = [];

    public ?int $revoke_certificate_id = null;

    public string $revocation_reason = '';

    public function mount(): void
    {
        Gate::authorize(Permissions::VIEW_CERTIFICATE);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        Gate::authorize(Permissions::VIEW_CERTIFICATE);

        $this->search = '';
        $this->status_filter = '';
        $this->resetPage();
    }

    public function checkTrackedCertificateStatuses(): void
    {
        Gate::authorize(Permissions::VIEW_CERTIFICATE);

        $trackedIds = collect($this->tracked_certificate_ids)
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($trackedIds->isEmpty()) {
            $this->tracked_certificate_ids = [];

            return;
        }

        $certificates = IssuedCertificate::query()
            ->whereKey($trackedIds->all())
            ->where('issued_by', (int) auth()->id())
            ->get(['id', 'status']);

        $issuedIds = $certificates
            ->filter(
                static fn (IssuedCertificate $certificate): bool =>
                    $certificate->status
                        === IssuedCertificateStatus::ISSUED
            )
            ->pluck('id');

        $failedIds = $certificates
            ->filter(
                static fn (IssuedCertificate $certificate): bool =>
                    $certificate->status
                        === IssuedCertificateStatus::FAILED
            )
            ->pluck('id');

        $terminalIds = $issuedIds->merge($failedIds);
        $existingIds = $certificates->pluck('id');

        $this->tracked_certificate_ids = $trackedIds
            ->intersect($existingIds)
            ->diff($terminalIds)
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($issuedIds->isNotEmpty()) {
            Flux::toast(
                heading: 'Certificate berhasil dibuat',
                text: $issuedIds->count()
                    .' certificate selesai dibuat dan siap diunduh.',
                variant: 'success',
                duration: 5000,
            );
        }

        if ($failedIds->isNotEmpty()) {
            Flux::toast(
                heading: 'Pembuatan certificate gagal',
                text: $failedIds->count()
                    .' certificate gagal diproses. Lihat detail error pada tabel.',
                variant: 'danger',
                duration: 6000,
            );
        }
    }

    public function updatedSelectedTrainingId(): void
    {
        $this->selected_employee_ids = [];
        $this->bulk_issue_errors = [];
        $this->bulk_request_key = (string) Str::uuid();
        $this->resetValidation([
            'selected_training_id',
            'selected_employee_ids',
            'selected_employee_ids.*',
            'training',
            'employee',
        ]);
    }

    public function openIssue(?int $trainingId = null): void
    {
        Gate::authorize(Permissions::ISSUE_CERTIFICATE);

        $this->resetValidation();
        $this->selected_training_id = $trainingId;
        $this->selected_employee_ids = [];
        $this->bulk_request_key = (string) Str::uuid();
        $this->bulk_issue_errors = [];
        $this->show_issue_modal = true;
    }

    public function closeIssue(): void
    {
        $this->show_issue_modal = false;
        $this->resetIssueFields();
        $this->resetValidation();
    }

    public function clearParticipantSelection(): void
    {
        Gate::authorize(Permissions::ISSUE_CERTIFICATE);

        $this->selected_employee_ids = [];
        $this->bulk_issue_errors = [];
        $this->resetValidation([
            'selected_employee_ids',
            'selected_employee_ids.*',
            'employee',
        ]);
    }

    public function selectAllEligibleParticipants(): void
    {
        Gate::authorize(Permissions::ISSUE_CERTIFICATE);

        $this->bulk_issue_errors = [];
        $this->resetValidation([
            'selected_training_id',
            'selected_employee_ids',
            'selected_employee_ids.*',
            'training',
            'employee',
        ]);

        if ($this->selected_training_id === null) {
            $this->addError(
                'selected_training_id',
                'Pilih training terlebih dahulu.'
            );

            return;
        }

        $training = $this->eligibleTrainingQuery()
            ->find($this->selected_training_id);

        if ($training === null) {
            $this->addError(
                'selected_training_id',
                'Training tidak valid atau certificate template tidak aktif.'
            );

            return;
        }

        $blockingStatuses = $this->blockingStatusValues();

        $eligibleIds = $training->participants()
            ->whereDoesntHave(
                'issuedCertificates',
                function ($query) use (
                    $training,
                    $blockingStatuses
                ): void {
                    $query
                        ->where('training_id', $training->id)
                        ->whereIn('status', $blockingStatuses);
                }
            )
            ->orderBy('employees.name')
            ->limit(self::MAX_BULK_ISSUE + 1)
            ->pluck('employees.id')
            ->map(static fn ($id): int => (int) $id)
            ->values();

        if ($eligibleIds->isEmpty()) {
            $this->selected_employee_ids = [];

            Flux::toast(
                heading: 'Tidak ada participant eligible',
                text: 'Semua participant sudah memiliki certificate atau perlu di-retry.',
                variant: 'danger',
                duration: 4000,
            );

            return;
        }

        $hasMoreThanLimit = $eligibleIds->count()
            > self::MAX_BULK_ISSUE;

        $this->selected_employee_ids = $eligibleIds
            ->take(self::MAX_BULK_ISSUE)
            ->all();

        Flux::toast(
            heading: 'Participant dipilih',
            text: $hasMoreThanLimit
                ? self::MAX_BULK_ISSUE
                    .' participant pertama dipilih. Batas satu proses adalah '
                    .self::MAX_BULK_ISSUE.'.'
                : $eligibleIds->count()
                    .' participant eligible dipilih.',
            variant: 'success',
            duration: 4000,
        );
    }

    public function issue(
        CertificateIssuanceService $service
    ): void {
        Gate::authorize(Permissions::ISSUE_CERTIFICATE);

        $this->bulk_issue_errors = [];
        $queued = 0;
        $skipped = 0;
        $errors = [];

        try {
            $validated = $this->validate([
                'selected_training_id' => [
                    'required',
                    'integer',
                    'exists:trainings,id',
                ],
                'selected_employee_ids' => [
                    'required',
                    'array',
                    'min:1',
                    'max:'.self::MAX_BULK_ISSUE,
                ],
                'selected_employee_ids.*' => [
                    'required',
                    'integer',
                    'distinct',
                    'exists:employees,id',
                ],
                'bulk_request_key' => ['required', 'uuid'],
            ], [
                'selected_employee_ids.required' =>
                    'Pilih minimal satu participant.',
                'selected_employee_ids.min' =>
                    'Pilih minimal satu participant.',
                'selected_employee_ids.max' =>
                    'Maksimal '.self::MAX_BULK_ISSUE
                    .' participant dalam satu proses.',
            ]);

            $training = $this->eligibleTrainingQuery()
                ->find((int) $validated['selected_training_id']);

            if ($training === null) {
                throw ValidationException::withMessages([
                    'selected_training_id' =>
                        'Training tidak valid atau certificate template tidak aktif.',
                ]);
            }

            $requestedIds = collect(
                $validated['selected_employee_ids']
            )
                ->map(static fn ($id): int => (int) $id)
                ->unique()
                ->values();

            $participants = $training->participants()
                ->whereKey($requestedIds->all())
                ->get([
                    'employees.id',
                    'employees.name',
                    'employees.nik',
                ])
                ->keyBy('id');

            if ($participants->count() !== $requestedIds->count()) {
                throw ValidationException::withMessages([
                    'selected_employee_ids' =>
                        'Terdapat employee yang bukan participant training ini.',
                ]);
            }

            $issuedOn = CarbonImmutable::now(
                config('app.timezone')
            )->startOfDay();
            $expiresAt = $issuedOn->addYearNoOverflow();

            foreach ($requestedIds as $employeeId) {
                $participant = $participants->get($employeeId);
                $participantName = trim(
                    (string) ($participant?->name ?? 'Participant')
                );

                try {
                    $certificate = $service->issue(
                        trainingId: $training->id,
                        employeeId: $employeeId,
                        requestKey: $this->bulkRequestKeyFor(
                            $training->id,
                            $employeeId
                        ),
                        issuedBy: (int) auth()->id(),
                        issuedOn: $issuedOn,
                        expiresAt: $expiresAt,
                    );

                    $this->trackCertificate($certificate->id);

                    $queued++;
                } catch (ValidationException $exception) {
                    $skipped++;
                    $errors[] = $participantName.': '
                        .$this->firstValidationMessage($exception);
                } catch (ModelNotFoundException) {
                    $skipped++;
                    $errors[] = $participantName
                        .': data participant tidak lagi tersedia.';
                }
            }

            $this->bulk_issue_errors = $errors;
            $total = $requestedIds->count();

            if ($queued > 0) {
                $this->show_issue_modal = false;
                $this->resetIssueFields();
                $this->resetPage();

                Flux::toast(
                    heading: 'Hasil Bulk Issue',
                    text: $queued.' certificate dijadwalkan dari '
                        .$total.' participant. '
                        .$skipped.' dilewati.',
                    variant: $skipped > 0
                        ? 'warning'
                        : 'success',
                    duration: 6000,
                );

                return;
            }

            Flux::toast(
                heading: 'Bulk Issue Gagal',
                text: 'Tidak ada certificate yang dijadwalkan. '
                    .$skipped.' participant dilewati.',
                variant: 'danger',
                duration: 6000,
            );
        } catch (ValidationException $exception) {
            $this->showValidationFailure(
                $exception,
                'Certificate gagal dijadwalkan'
            );
        } catch (\Throwable $exception) {
            report($exception);

            $this->bulk_issue_errors = $errors;

            Flux::toast(
                heading: $queued > 0
                    ? 'Sebagian certificate dijadwalkan'
                    : 'Certificate gagal dijadwalkan',
                text: $queued > 0
                    ? $queued.' certificate berhasil dijadwalkan sebelum proses terhenti. Silakan cek tabel dan log aplikasi.'
                    : 'Terjadi kesalahan sistem. Silakan coba lagi atau hubungi administrator.',
                variant: 'danger',
                duration: 6000,
            );
        }
    }

    public function retry(
        int $certificateId,
        CertificateIssuanceService $service
    ): void {
        Gate::authorize(Permissions::ISSUE_CERTIFICATE);

        try {
            $certificate = IssuedCertificate::query()
                ->findOrFail($certificateId);

            $certificate = $service->retry($certificate->id);
            $this->trackCertificate($certificate->id);

            Flux::toast(
                heading: 'Retry dijadwalkan',
                text: $certificate->certificate_number
                    .' akan diproses ulang.',
                variant: 'success',
                duration: 3500,
            );
        } catch (ValidationException $exception) {
            $this->showValidationFailure(
                $exception,
                'Retry certificate gagal'
            );
        } catch (\Throwable $exception) {
            report($exception);

            Flux::toast(
                heading: 'Retry certificate gagal',
                text: 'Terjadi kesalahan sistem saat menjadwalkan retry.',
                variant: 'danger',
                duration: 5000,
            );
        }
    }

    public function prepareReissue(int $certificateId): void
    {
        Gate::authorize(Permissions::REISSUE_CERTIFICATE);

        try {
            $certificate = IssuedCertificate::query()
                ->with('supersededBy:id,supersedes_id')
                ->findOrFail($certificateId);

            if (! in_array(
                $certificate->status,
                [
                    IssuedCertificateStatus::ISSUED,
                    IssuedCertificateStatus::REVOKED,
                ],
                true
            )) {
                throw ValidationException::withMessages([
                    'certificate' =>
                        'Hanya certificate issued atau revoked yang dapat di-reissue.',
                ]);
            }

            if ($certificate->supersededBy !== null) {
                throw ValidationException::withMessages([
                    'certificate' =>
                        'Certificate ini sudah memiliki pengganti.',
                ]);
            }

            $this->reissue_certificate_id = $certificate->id;
            $this->reissue_certificate_number = (string) $certificate
                ->certificate_number;
            $this->resetValidation();
            $this->show_reissue_modal = true;
        } catch (ValidationException $exception) {
            $this->showValidationFailure(
                $exception,
                'Reissue certificate tidak tersedia'
            );
        } catch (ModelNotFoundException) {
            Flux::toast(
                heading: 'Certificate tidak ditemukan',
                text: 'Data certificate mungkin sudah berubah atau dihapus.',
                variant: 'danger',
                duration: 5000,
            );
        } catch (\Throwable $exception) {
            report($exception);

            Flux::toast(
                heading: 'Reissue certificate gagal',
                text: 'Terjadi kesalahan saat menyiapkan proses reissue.',
                variant: 'danger',
                duration: 5000,
            );
        }
    }

    public function closeReissue(): void
    {
        $this->show_reissue_modal = false;
        $this->reissue_certificate_id = null;
        $this->reissue_certificate_number = '';
        $this->resetValidation();
    }

    public function confirmReissue(
        CertificateIssuanceService $service
    ): void {
        Gate::authorize(Permissions::REISSUE_CERTIFICATE);

        try {
            $validated = $this->validate([
                'reissue_certificate_id' => [
                    'required',
                    'integer',
                    'exists:issued_certificates,id',
                ],
            ]);

            $source = IssuedCertificate::query()
                ->findOrFail(
                    (int) $validated['reissue_certificate_id']
                );

            $certificate = $service->reissue(
                certificateId: $source->id,
                requestKey: (string) Str::uuid(),
                issuedBy: (int) auth()->id(),
            );

            $this->trackCertificate($certificate->id);
            $this->closeReissue();
            $this->resetPage();

            Flux::toast(
                heading: 'Reissue dijadwalkan',
                text: $certificate->certificate_number
                    .' sedang dibuat menggunakan template training terbaru.',
                variant: 'success',
                duration: 4500,
            );
        } catch (ValidationException $exception) {
            $this->showValidationFailure(
                $exception,
                'Reissue certificate gagal'
            );
        } catch (ModelNotFoundException) {
            Flux::toast(
                heading: 'Reissue certificate gagal',
                text: 'Certificate sumber tidak ditemukan.',
                variant: 'danger',
                duration: 5000,
            );
        } catch (\Throwable $exception) {
            report($exception);

            Flux::toast(
                heading: 'Reissue certificate gagal',
                text: 'Terjadi kesalahan sistem saat menjadwalkan reissue.',
                variant: 'danger',
                duration: 5000,
            );
        }
    }

    public function openPreview(int $certificateId): void
    {
        Gate::authorize(Permissions::DOWNLOAD_CERTIFICATE);

        $certificate = IssuedCertificate::query()
            ->where(
                'status',
                IssuedCertificateStatus::ISSUED
            )
            ->findOrFail($certificateId);

        $this->preview_certificate_id = $certificate->id;
        $this->preview_certificate_number = (string) $certificate
            ->certificate_number;
        $this->show_preview_modal = true;
    }

    public function closePreview(): void
    {
        $this->show_preview_modal = false;
        $this->preview_certificate_id = null;
        $this->preview_certificate_number = '';
    }

    public function openRevoke(int $certificateId): void
    {
        Gate::authorize(Permissions::REVOKE_CERTIFICATE);

        $certificate = IssuedCertificate::query()
            ->findOrFail($certificateId);

        abort_unless(
            $certificate->status
                === IssuedCertificateStatus::ISSUED,
            422
        );

        $this->revoke_certificate_id = $certificate->id;
        $this->revocation_reason = '';
        $this->resetValidation();
        $this->show_revoke_modal = true;
    }

    public function revoke(
        CertificateIssuanceService $service
    ): void {
        Gate::authorize(Permissions::REVOKE_CERTIFICATE);

        try {
            $validated = $this->validate([
                'revoke_certificate_id' => [
                    'required',
                    'integer',
                    'exists:issued_certificates,id',
                ],
                'revocation_reason' => [
                    'required',
                    'string',
                    'min:5',
                    'max:1000',
                ],
            ]);

            $service->revoke(
                certificateId: (int) $validated[
                    'revoke_certificate_id'
                ],
                revokedBy: (int) auth()->id(),
                reason: $validated['revocation_reason'],
            );

            $this->show_revoke_modal = false;
            $this->revoke_certificate_id = null;
            $this->revocation_reason = '';

            Flux::toast(
                heading: 'Certificate berhasil dicabut',
                text: 'Certificate tidak dapat diunduh lagi.',
                variant: 'success',
                duration: 3500,
            );
        } catch (ValidationException $exception) {
            $this->showValidationFailure(
                $exception,
                'Revoke certificate gagal'
            );
        } catch (\Throwable $exception) {
            report($exception);

            Flux::toast(
                heading: 'Revoke certificate gagal',
                text: 'Terjadi kesalahan sistem saat mencabut certificate.',
                variant: 'danger',
                duration: 5000,
            );
        }
    }

    public function with(): array
    {
        Gate::authorize(Permissions::VIEW_CERTIFICATE);

        $certificates = IssuedCertificate::query()
            ->with([
                'training:id,title,batch_number,batch_name',
                'employee:id,name,nik',
                'supersededBy:id,supersedes_id',
            ])
            ->when(
                $this->search !== '',
                function ($query): void {
                    $search = '%'.$this->search.'%';

                    $query->where(function ($query) use (
                        $search
                    ): void {
                        $query
                            ->where(
                                'certificate_number',
                                'like',
                                $search
                            )
                            ->orWhereHas(
                                'employee',
                                fn ($employee) => $employee
                                    ->where('name', 'like', $search)
                                    ->orWhere('nik', 'like', $search)
                            )
                            ->orWhereHas(
                                'training',
                                fn ($training) => $training
                                    ->where('title', 'like', $search)
                            );
                    });
                }
            )
            ->when(
                in_array(
                    $this->status_filter,
                    array_map(
                        static fn (
                            IssuedCertificateStatus $status
                        ): string => $status->value,
                        IssuedCertificateStatus::cases()
                    ),
                    true
                ),
                fn ($query) => $query->where(
                    'status',
                    $this->status_filter
                )
            )
            ->latest('id')
            ->paginate(10);

        $canIssue = auth()->user()?->can(
            Permissions::ISSUE_CERTIFICATE
        ) ?? false;

        $trainings = $canIssue && $this->show_issue_modal
            ? $this->eligibleTrainingQuery()
                ->select([
                    'id',
                    'title',
                    'batch_number',
                    'batch_name',
                    'training_date',
                ])
                ->orderByDesc('training_date')
                ->orderByDesc('id')
                ->get()
            : collect();

        [$participants, $participantSummary] =
            $this->issueParticipantRows($canIssue);

        $automaticIssuedOn = CarbonImmutable::now(
            config('app.timezone')
        )->startOfDay();

        return [
            'certificates' => $certificates,
            'trainings' => $trainings,
            'participants' => $participants,
            'participant_summary' => $participantSummary,
            'status_options' => IssuedCertificateStatus::cases(),
            'max_bulk_issue' => self::MAX_BULK_ISSUE,
            'automatic_issued_on' => $automaticIssuedOn,
            'automatic_expires_at' => $automaticIssuedOn
                ->addYearNoOverflow(),
        ];
    }

    private function eligibleTrainingQuery()
    {
        return Training::query()
            ->where('is_certified', 'Yes')
            ->whereNotNull('certificate_template_id')
            ->whereHas(
                'certificateTemplate',
                fn ($query) => $query->active()
            );
    }

    /**
     * @return array{0: Collection<int, array<string, mixed>>, 1: array<string, int>}
     */
    private function issueParticipantRows(bool $canIssue): array
    {
        $emptySummary = [
            'total' => 0,
            'eligible' => 0,
            'blocked' => 0,
        ];

        if (
            ! $canIssue
            || ! $this->show_issue_modal
            || $this->selected_training_id === null
        ) {
            return [collect(), $emptySummary];
        }

        $training = $this->eligibleTrainingQuery()
            ->find($this->selected_training_id);

        if ($training === null) {
            return [collect(), $emptySummary];
        }

        $allParticipants = $training->participants()
            ->select([
                'employees.id',
                'employees.name',
                'employees.nik',
            ])
            ->orderBy('employees.name')
            ->get();

        if ($allParticipants->isEmpty()) {
            return [collect(), $emptySummary];
        }

        $blockingCertificates = IssuedCertificate::query()
            ->where('training_id', $training->id)
            ->whereIn('employee_id', $allParticipants->pluck('id'))
            ->whereIn('status', $this->blockingStatusValues())
            ->orderByDesc('id')
            ->get([
                'employee_id',
                'certificate_number',
                'status',
            ])
            ->unique('employee_id')
            ->keyBy('employee_id');

        $rows = $allParticipants->map(
            function ($participant) use (
                $blockingCertificates
            ): array {
                $blockingCertificate = $blockingCertificates->get(
                    $participant->id
                );

                return [
                    'id' => (int) $participant->id,
                    'name' => (string) $participant->name,
                    'nik' => (string) $participant->nik,
                    'eligible' => $blockingCertificate === null,
                    'certificate_number' =>
                        $blockingCertificate?->certificate_number,
                    'certificate_status' =>
                        $blockingCertificate?->status,
                ];
            }
        );

        $summary = [
            'total' => $rows->count(),
            'eligible' => $rows
                ->where('eligible', true)
                ->count(),
            'blocked' => $rows
                ->where('eligible', false)
                ->count(),
        ];

        return [$rows, $summary];
    }

    /** @return array<int, string> */
    private function blockingStatusValues(): array
    {
        return [
            IssuedCertificateStatus::PENDING->value,
            IssuedCertificateStatus::PROCESSING->value,
            IssuedCertificateStatus::ISSUED->value,
            IssuedCertificateStatus::FAILED->value,
        ];
    }

    private function bulkRequestKeyFor(
        int $trainingId,
        int $employeeId
    ): string {
        return Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            implode(':', [
                'certificate-bulk',
                $this->bulk_request_key,
                $trainingId,
                $employeeId,
            ])
        )->toString();
    }

    private function firstValidationMessage(
        ValidationException $exception
    ): string {
        return (string) (
            collect($exception->errors())
                ->flatten()
                ->first()
            ?? 'Certificate tidak dapat dijadwalkan.'
        );
    }

    private function showValidationFailure(
        ValidationException $exception,
        string $heading
    ): void {
        $this->resetValidation();

        foreach ($exception->errors() as $field => $messages) {
            foreach ((array) $messages as $message) {
                $this->addError($field, (string) $message);
            }
        }

        Flux::toast(
            heading: $heading,
            text: $this->firstValidationMessage($exception),
            variant: 'danger',
            duration: 5000,
        );
    }

    private function trackCertificate(int $certificateId): void
    {
        $this->tracked_certificate_ids = collect([
            ...$this->tracked_certificate_ids,
            $certificateId,
        ])
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function resetIssueFields(): void
    {
        $this->selected_training_id = null;
        $this->selected_employee_ids = [];
        $this->bulk_request_key = '';
        $this->bulk_issue_errors = [];
    }
};