<?php

use App\Models\CertificateTemplate;
use App\Models\Employee;
use App\Models\Training;
use App\Models\TrainingGroup;
use App\Support\Auth\Permissions;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    private const MODE_CREATE = 'create';
    private const MODE_ADD = 'add';
    private const MODE_APPEND_STANDALONE = 'append-standalone';
    private const MODE_EDIT = 'edit';
    private const MODE_EDIT_SINGLE_GROUP = 'edit-single-group';
    private const MODE_EDIT_STANDALONE = 'edit-standalone';

    private const TRAINER_INTERNAL = 'internal';
    private const TRAINER_EXTERNAL = 'external';
    private const TRAINER_NONE = 'none';

    private const CERTIFIED_YES = 'Yes';
    private const CERTIFIED_NO = 'No';

    private const ACTIVITY_INTERNAL = 'Internal';
    private const ACTIVITY_EXTERNAL = 'External';

    private const SKILL_HARD = 'Hard Skill';
    private const SKILL_SOFT = 'Soft Skill';

    private const DEFAULT_HELD_BY = 'PT JEMBO CABLE COMPANY TBK.';

    public bool $show_modal = false;

    public string $mode = self::MODE_CREATE;

    public ?int $training_id = null;

    public ?int $training_group_id = null;

    public int $batch_number_start = 1;

    public string $title = '';

    public string $held_by = self::DEFAULT_HELD_BY;

    public string $activity_name = '';

    public string $skill_name = '';

    public string $is_certified = self::CERTIFIED_NO;

    public ?int $certificate_template_id = null;

    /**
     * @var array<int, array{
     *     batch_name: string,
     *     training_date: ?string,
     *     start_time: ?string,
     *     finish_time: ?string
     * }>
     */
    public array $batches = [];

    public string $trainer_type = self::TRAINER_INTERNAL;

    public ?int $trainer_employee_id = null;

    /** @var array<int, int|string> */
    public array $trainer_employee_ids = [];

    public string $trainer_external_name = '';

    public mixed $fee = 0;

    public function mount(): void
    {
        Gate::authorize(Permissions::VIEW_TRAINING);
    }

    #[On('open-training-batch-form')]
    public function open(
        ?int $trainingGroupId = null,
        ?int $trainingId = null,
        ?int $standaloneTrainingId = null,
        bool $createNew = false
    ): void {
        $this->resetForm();

        if ($standaloneTrainingId !== null) {
            Gate::authorize(Permissions::CREATE_TRAINING);
            Gate::authorize(Permissions::UPDATE_TRAINING);

            $this->mode = self::MODE_APPEND_STANDALONE;

            $this->loadStandaloneTrainingForAppend(
                $standaloneTrainingId
            );

            $this->show_modal = true;

            return;
        }

        if ($trainingId !== null) {
            Gate::authorize(Permissions::UPDATE_TRAINING);

            $this->loadTrainingForEdit($trainingId);

            $this->show_modal = true;

            return;
        }

        Gate::authorize(Permissions::CREATE_TRAINING);

        if ($trainingGroupId !== null) {
            $this->mode = self::MODE_ADD;

            $this->loadTrainingGroupForAdd(
                $trainingGroupId
            );

            $this->show_modal = true;

            return;
        }

        $this->mode = self::MODE_CREATE;

        $this->batches = [
            $this->emptyBatch(),
        ];

        $this->show_modal = true;
    }

    public function addBatch(): void
    {
        Gate::authorize(Permissions::CREATE_TRAINING);

        if ($this->isEditing()) {
            return;
        }

        if (count($this->batches) >= 30) {
            $this->warningToast(
                'Maksimal 30 sesi dalam satu proses.'
            );

            return;
        }

        $nextBatchNumber =
            $this->nextVisibleBatchNumber();

        $this->batches[] = $this->emptyBatch(
            'Sesi ' . $nextBatchNumber
        );

        $this->resetValidation('batches');
    }

    public function removeBatch(int $index): void
    {
        Gate::authorize(Permissions::CREATE_TRAINING);

        if ($this->isEditing()) {
            return;
        }

        if (count($this->batches) <= 1) {
            $this->warningToast(
                'Minimal harus tersedia satu sesi.'
            );

            return;
        }

        if (! array_key_exists($index, $this->batches)) {
            return;
        }

        unset($this->batches[$index]);

        $this->batches = array_values(
            $this->batches
        );

        $this->resetValidation();
    }

    public function updatedTrainerType(
        string $value
    ): void {
        if ($value === self::TRAINER_INTERNAL) {
            $this->trainer_external_name = '';

            return;
        }

        if ($value === self::TRAINER_EXTERNAL) {
            $this->trainer_employee_id = null;
            $this->trainer_employee_ids = [];

            return;
        }

        $this->trainer_employee_id = null;
        $this->trainer_employee_ids = [];
        $this->trainer_external_name = '';

        $this->resetValidation([
            'trainer_employee_id',
            'trainer_external_name',
        ]);
    }

    public function updatedTrainerEmployeeIds(): void
    {
        $selectedId = collect(
            $this->trainer_employee_ids
        )
            ->filter(
                fn ($id): bool =>
                    is_numeric($id)
                    && (int) $id > 0
            )
            ->map(
                fn ($id): int => (int) $id
            )
            ->unique()
            ->first();

        $this->trainer_employee_id =
            $selectedId !== null
                ? (int) $selectedId
                : null;

        $this->resetValidation(
            'trainer_employee_id'
        );
    }

    public function updatedIsCertified(
        string $value
    ): void {
        $this->resetValidation(
            'certificate_template_id'
        );

        if ($value === self::CERTIFIED_NO) {
            $this->certificate_template_id = null;

            return;
        }

        if ($value !== self::CERTIFIED_YES) {
            return;
        }

        if (
            $this->certificateTemplateIsUsable(
                $this->certificate_template_id
            )
        ) {
            return;
        }

        $this->certificate_template_id =
            $this->defaultCertificateTemplateId();
    }

    public function save(): void
    {
        $isEditing = $this->isEditing();

        if ($this->mode === self::MODE_APPEND_STANDALONE) {
            Gate::authorize(Permissions::CREATE_TRAINING);
            Gate::authorize(Permissions::UPDATE_TRAINING);
        } else {
            $isEditing
                ? Gate::authorize(
                    Permissions::UPDATE_TRAINING
                )
                : Gate::authorize(
                    Permissions::CREATE_TRAINING
                );
        }

        $this->normalizeForm();

        $validated = $this->validate(
            $this->rules(),
            $this->validationMessages()
        );

        try {
            $savedTrainingIds = DB::transaction(
                function () use ($validated): array {
                    return match ($this->mode) {
                        self::MODE_CREATE =>
                            $this->createTrainingWithBatches(
                                $validated
                            ),

                        self::MODE_ADD =>
                            $this->addBatchesToTrainingGroup(
                                $validated
                            ),

                        self::MODE_APPEND_STANDALONE =>
                            $this->convertStandaloneAndAddBatches(
                                $validated
                            ),

                        self::MODE_EDIT => [
                            $this->updateGroupedBatch(
                                $validated
                            )->id,
                        ],

                        self::MODE_EDIT_SINGLE_GROUP => [
                            $this->updateSingleGroupedTraining(
                                $validated
                            )->id,
                        ],

                        self::MODE_EDIT_STANDALONE => [
                            $this->updateStandaloneTraining(
                                $validated
                            )->id,
                        ],

                        default =>
                            throw ValidationException::withMessages([
                                'batches' =>
                                    'Mode form tidak valid.',
                            ]),
                    };
                }
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            Flux::toast(
                heading: 'Failed',
                text: 'Data training gagal disimpan.',
                variant: 'danger',
                duration: 4000,
            );

            return;
        }

        $this->dispatch(
            'training-batch-saved',
            trainingIds: array_map(
                fn ($id): int => (int) $id,
                $savedTrainingIds
            ),
            trainingGroupId:
                $this->training_group_id,
        );

        $message = match ($this->mode) {
            self::MODE_CREATE =>
                count($savedTrainingIds)
                . ' sesi untuk training baru '
                . 'berhasil dibuat.',

            self::MODE_ADD,
            self::MODE_APPEND_STANDALONE =>
                count($savedTrainingIds)
                . ' sesi baru berhasil ditambahkan.',

            self::MODE_EDIT =>
                'Data sesi dan konfigurasi sertifikat '
                . 'berhasil diperbarui.',

            self::MODE_EDIT_SINGLE_GROUP,
            self::MODE_EDIT_STANDALONE =>
                'Data training berhasil diperbarui.',

            default =>
                'Data berhasil disimpan.',
        };

        Flux::toast(
            heading: 'Success',
            text: $message,
            variant: 'success',
            duration: 3500,
        );

        $this->closeBatchModal();
    }

    public function closeBatchModal(): void
    {
        $this->show_modal = false;

        $this->resetForm();
    }

    public function with(): array
    {
        $trainerEmployees = collect();

        $certificateTemplates = collect();

        $selectedCertificateTemplate = null;

        if ($this->show_modal) {
            $trainerEmployees = Employee::query()
                ->where(function ($query): void {
                    $query->where(
                        'status',
                        'Active'
                    );

                    if (
                        $this->trainer_employee_id
                        !== null
                    ) {
                        $query->orWhere(
                            'employees.id',
                            $this->trainer_employee_id
                        );
                    }
                })
                ->orderBy('name')
                ->get([
                    'id',
                    'nik',
                    'name',
                ]);

            $certificateTemplates =
                CertificateTemplate::query()
                    ->active()
                    ->orderByDesc('is_default')
                    ->orderByRaw(
                        'CASE WHEN kind = ? THEN 0 ELSE 1 END',
                        [
                            CertificateTemplate::KIND_COMPLETION,
                        ]
                    )
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'kind',
                        'is_default',
                        'archived_at',
                    ]);

            if ($this->certificate_template_id) {
                $selectedCertificateTemplate =
                    CertificateTemplate::query()
                        ->withTrashed()
                        ->find(
                            $this->certificate_template_id
                        );
            }
        }

        return [
            'trainer_employees' =>
                $trainerEmployees,

            'certificate_templates' =>
                $certificateTemplates,

            'selected_certificate_template' =>
                $selectedCertificateTemplate,
        ];
    }

    private function loadTrainingGroupForAdd(
        int $groupId
    ): void {
        $group = TrainingGroup::query()
            ->with([
                'trainings' => function ($query): void {
                    $query
                        ->orderByDesc('batch_number')
                        ->orderByDesc('id');
                },
            ])
            ->findOrFail($groupId);

        $sourceTraining =
            $group->trainings->first();

        if (! $sourceTraining) {
            throw ValidationException::withMessages([
                'training_group_id' =>
                    'Training Group belum mempunyai '
                    . 'sesi sumber.',
            ]);
        }

        $this->training_group_id =
            (int) $group->id;

        $this->title =
            (string) $group->title;

        $this->held_by =
            (string) (
                $sourceTraining->held_by ?? ''
            );

        $this->activity_name =
            (string) (
                $sourceTraining->activity_name ?? ''
            );

        $this->skill_name =
            (string) (
                $sourceTraining->skill_name ?? ''
            );

        $this->is_certified =
            (string) (
                $sourceTraining->is_certified
                ?? self::CERTIFIED_NO
            );

        $this->certificate_template_id =
            $sourceTraining->certificate_template_id
                ? (int) $sourceTraining
                    ->certificate_template_id
                : null;

        $this->loadTrainerAndFeeFromTraining(
            $sourceTraining
        );

        $nextBatchNumber =
            ((int) $group->trainings->max(
                'batch_number'
            )) + 1;

        $this->batch_number_start =
            $nextBatchNumber;

        $this->batches = [
            $this->emptyBatch(
                'Sesi ' . $nextBatchNumber
            ),
        ];
    }

    private function loadStandaloneTrainingForAppend(
        int $trainingId
    ): void {
        $training = Training::query()
            ->whereNull('training_group_id')
            ->with('certificateTemplate')
            ->findOrFail($trainingId);

        $this->training_id = (int) $training->id;
        $this->training_group_id = null;
        $this->batch_number_start = 2;
        $this->title = (string) $training->title;
        $this->held_by = (string) ($training->held_by ?? '');
        $this->activity_name =
            (string) ($training->activity_name ?? '');
        $this->skill_name =
            (string) ($training->skill_name ?? '');
        $this->is_certified =
            (string) (
                $training->is_certified
                ?? self::CERTIFIED_NO
            );
        $this->certificate_template_id =
            $training->certificate_template_id !== null
                ? (int) $training->certificate_template_id
                : null;

        $this->loadTrainerAndFeeFromTraining($training);

        $this->batches = [
            $this->emptyBatch('Sesi 2'),
        ];
    }

    private function loadTrainingForEdit(
        int $trainingId
    ): void {
        $training = Training::query()
            ->with([
                'trainingGroup' => function ($query): void {
                    $query->withCount('trainings');
                },
                'certificateTemplate',
            ])
            ->findOrFail($trainingId);

        $isGrouped =
            $training->training_group_id !== null
            && $training->trainingGroup !== null;

        $this->mode = ! $isGrouped
            ? self::MODE_EDIT_STANDALONE
            : (
                (int) ($training->trainingGroup->trainings_count ?? 0) <= 1
                    ? self::MODE_EDIT_SINGLE_GROUP
                    : self::MODE_EDIT
            );

        $this->training_id = (int) $training->id;
        $this->training_group_id = $isGrouped
            ? (int) $training->training_group_id
            : null;
        $this->batch_number_start =
            (int) ($training->batch_number ?: 1);

        $this->title = $isGrouped
            ? (string) $training->trainingGroup->title
            : (string) $training->title;
        $this->held_by =
            (string) ($training->held_by ?? '');
        $this->activity_name =
            (string) ($training->activity_name ?? '');
        $this->skill_name =
            (string) ($training->skill_name ?? '');
        $this->is_certified =
            (string) (
                $training->is_certified
                ?? self::CERTIFIED_NO
            );
        $this->certificate_template_id =
            $training->certificate_template_id !== null
                ? (int) $training->certificate_template_id
                : null;

        if (
            $this->is_certified === self::CERTIFIED_YES
            && $this->certificate_template_id === null
        ) {
            $this->certificate_template_id =
                $this->defaultCertificateTemplateId();
        }

        $this->batches = [[
            'batch_name' => $isGrouped
                ? (string) ($training->batch_name ?? '')
                : '',
            'training_date' => $training->training_date
                ? $training->training_date->format('Y-m-d')
                : null,
            'start_time' => $this->formatTimeForInput(
                $training->start_time
            ),
            'finish_time' => $this->formatTimeForInput(
                $training->finish_time
            ),
        ]];

        $this->loadTrainerAndFeeFromTraining($training);
    }

    private function loadTrainerAndFeeFromTraining(
        Training $training
    ): void {
        $this->fee =
            $training->fee ?? 0;

        if ($training->trainer_employee_id) {
            $this->trainer_type =
                self::TRAINER_INTERNAL;

            $this->trainer_employee_id =
                (int) $training
                    ->trainer_employee_id;

            $this->trainer_employee_ids = [
                $this->trainer_employee_id,
            ];

            $this->trainer_external_name = '';

            return;
        }

        if (
            trim(
                (string) $training->trainer_external_name
            ) !== ''
        ) {
            $this->trainer_type =
                self::TRAINER_EXTERNAL;
            $this->trainer_employee_id = null;
            $this->trainer_employee_ids = [];
            $this->trainer_external_name =
                (string) $training->trainer_external_name;

            return;
        }

        $this->trainer_type = self::TRAINER_NONE;
        $this->trainer_employee_id = null;
        $this->trainer_employee_ids = [];
        $this->trainer_external_name = '';
    }

    private function createTrainingWithBatches(
        array $validated
    ): array {
        $group = TrainingGroup::query()->create([
            'title' => $validated['title'],
            'created_by' => auth()->id(),
        ]);

        $this->training_group_id =
            (int) $group->id;

        $trainingIds = [];

        foreach (
            $validated['batches']
            as $index => $batch
        ) {
            $batchNumber = $index + 1;

            $training = Training::query()->create(
                $this->trainingPayload(
                    group: $group,
                    validated: $validated,
                    batch: $batch,
                    batchNumber: $batchNumber,
                    sharedMetadata: [
                        'held_by' =>
                            $validated['held_by']
                            ?? null,

                        'activity_name' =>
                            $validated['activity_name']
                            ?? null,

                        'skill_name' =>
                            $validated['skill_name']
                            ?? null,

                        'is_certified' =>
                            $validated['is_certified'],

                        'certificate_template_id' =>
                            $validated[
                                'certificate_template_id'
                            ] ?? null,
                    ],
                )
            );

            $trainingIds[] =
                (int) $training->id;
        }

        return $trainingIds;
    }

    private function convertStandaloneAndAddBatches(
        array $validated
    ): array {
        $sourceTraining = Training::query()
            ->whereNull('training_group_id')
            ->lockForUpdate()
            ->findOrFail((int) $this->training_id);

        $this->assertCertificateConfigurationIsUsable(
            (string) (
                $sourceTraining->is_certified
                ?? self::CERTIFIED_NO
            ),
            $sourceTraining->certificate_template_id !== null
                ? (int) $sourceTraining->certificate_template_id
                : null
        );

        $group = TrainingGroup::query()->create([
            'title' => $sourceTraining->title,
            'created_by' => auth()->id(),
        ]);

        $sourceBatchName = trim(
            (string) ($sourceTraining->batch_name ?? '')
        );

        $sourceTraining->update([
            'training_group_id' => $group->id,
            'batch_number' => 1,
            'batch_name' => $sourceBatchName !== ''
                ? $sourceBatchName
                : 'Sesi 1',
            'title' => $group->title,
        ]);

        $this->training_group_id = (int) $group->id;

        $trainingIds = [];

        foreach ($validated['batches'] as $index => $batch) {
            $batchNumber = $index + 2;

            $training = Training::query()->create(
                $this->trainingPayload(
                    group: $group,
                    validated: $validated,
                    batch: $batch,
                    batchNumber: $batchNumber,
                    sharedMetadata: [
                        'held_by' => $sourceTraining->held_by,
                        'activity_name' =>
                            $sourceTraining->activity_name,
                        'skill_name' =>
                            $sourceTraining->skill_name,
                        'is_certified' =>
                            $sourceTraining->is_certified,
                        'certificate_template_id' =>
                            $sourceTraining->certificate_template_id,
                    ],
                )
            );

            $trainingIds[] = (int) $training->id;
        }

        return $trainingIds;
    }

    private function addBatchesToTrainingGroup(
        array $validated
    ): array {
        $group = TrainingGroup::query()
            ->lockForUpdate()
            ->findOrFail(
                (int) $this->training_group_id
            );

        $sourceTraining = Training::query()
            ->where(
                'training_group_id',
                $group->id
            )
            ->orderByDesc('batch_number')
            ->orderByDesc('id')
            ->first();

        if (! $sourceTraining) {
            throw ValidationException::withMessages([
                'training_group_id' =>
                    'Training Group belum mempunyai '
                    . 'sesi sumber.',
            ]);
        }

        $this->assertCertificateConfigurationIsUsable(
            (string) (
                $sourceTraining->is_certified
                ?? self::CERTIFIED_NO
            ),
            $sourceTraining->certificate_template_id
                ? (int) $sourceTraining
                    ->certificate_template_id
                : null
        );

        $lastBatchNumber =
            (int) Training::query()
                ->where(
                    'training_group_id',
                    $group->id
                )
                ->max('batch_number');

        $trainingIds = [];

        foreach (
            $validated['batches']
            as $index => $batch
        ) {
            $batchNumber =
                $lastBatchNumber + $index + 1;

            $training = Training::query()->create(
                $this->trainingPayload(
                    group: $group,
                    validated: $validated,
                    batch: $batch,
                    batchNumber: $batchNumber,
                    sharedMetadata: [
                        'held_by' =>
                            $sourceTraining->held_by,

                        'activity_name' =>
                            $sourceTraining->activity_name,

                        'skill_name' =>
                            $sourceTraining->skill_name,

                        'is_certified' =>
                            $sourceTraining->is_certified,

                        'certificate_template_id' =>
                            $sourceTraining
                                ->certificate_template_id,
                    ],
                )
            );

            $trainingIds[] =
                (int) $training->id;
        }

        return $trainingIds;
    }

    private function updateGroupedBatch(
        array $validated
    ): Training {
        $training = Training::query()
            ->lockForUpdate()
            ->findOrFail(
                (int) $this->training_id
            );

        if (
            ! $training->training_group_id
            || (int) $training->training_group_id
                !== (int) $this->training_group_id
        ) {
            throw ValidationException::withMessages([
                'training_group_id' =>
                    'Training Group pada sesi '
                    . 'tidak valid.',
            ]);
        }

        $certificateTemplateId =
            $validated['is_certified']
                === self::CERTIFIED_YES
                    ? (
                        $validated[
                            'certificate_template_id'
                        ] ?? null
                    )
                    : null;

        Training::query()
            ->where(
                'training_group_id',
                $training->training_group_id
            )
            ->update([
                'is_certified' =>
                    $validated['is_certified'],

                'certificate_template_id' =>
                    $certificateTemplateId,
            ]);

        $batch = $validated['batches'][0];

        $batchName = trim(
            (string) (
                $batch['batch_name']
                ?? ''
            )
        );

        if ($batchName === '') {
            $batchName =
                'Sesi '
                . ($training->batch_number ?: 1);
        }

        $training->update([
            'batch_name' =>
                $batchName,

            'training_date' =>
                $batch['training_date'],

            'start_time' =>
                $batch['start_time'] ?? null,

            'finish_time' =>
                $batch['finish_time'] ?? null,

            'fee' =>
                $validated['fee'] ?? 0,

            'trainer_employee_id' =>
                $validated['trainer_type']
                    === self::TRAINER_INTERNAL
                        ? (
                            $validated[
                                'trainer_employee_id'
                            ] ?? null
                        )
                        : null,

            'trainer_external_name' =>
                $validated['trainer_type']
                    === self::TRAINER_EXTERNAL
                        ? (
                            $validated[
                                'trainer_external_name'
                            ] ?? null
                        )
                        : null,
        ]);

        return $training->refresh();
    }


    private function updateSingleGroupedTraining(
        array $validated
    ): Training {
        $group = TrainingGroup::query()
            ->lockForUpdate()
            ->findOrFail((int) $this->training_group_id);

        $training = Training::query()
            ->where('training_group_id', $group->id)
            ->lockForUpdate()
            ->findOrFail((int) $this->training_id);

        $sessionCount = Training::query()
            ->where('training_group_id', $group->id)
            ->count();

        if ($sessionCount !== 1) {
            throw ValidationException::withMessages([
                'training_group_id' =>
                    'Training sudah mempunyai lebih dari satu sesi. '
                    . 'Gunakan edit pada sesi terkait.',
            ]);
        }

        $batch = $validated['batches'][0];

        $certificateTemplateId =
            $validated['is_certified']
                === self::CERTIFIED_YES
                    ? (
                        $validated['certificate_template_id']
                        ?? null
                    )
                    : null;

        $group->update([
            'title' => $validated['title'],
        ]);

        $training->update([
            'title' => $validated['title'],
            'held_by' => $validated['held_by'],
            'activity_name' =>
                $validated['activity_name'] ?? null,
            'skill_name' =>
                $validated['skill_name'] ?? null,
            'is_certified' =>
                $validated['is_certified'],
            'certificate_template_id' =>
                $certificateTemplateId,
            'training_date' =>
                $batch['training_date'],
            'start_time' =>
                $batch['start_time'] ?? null,
            'finish_time' =>
                $batch['finish_time'] ?? null,
            'fee' =>
                $validated['fee'] ?? 0,
            'trainer_employee_id' =>
                $validated['trainer_type']
                    === self::TRAINER_INTERNAL
                        ? (
                            $validated['trainer_employee_id']
                            ?? null
                        )
                        : null,
            'trainer_external_name' =>
                $validated['trainer_type']
                    === self::TRAINER_EXTERNAL
                        ? (
                            $validated['trainer_external_name']
                            ?? null
                        )
                        : null,
        ]);

        return $training->refresh();
    }

    private function updateStandaloneTraining(
        array $validated
    ): Training {
        $training = Training::query()
            ->whereNull('training_group_id')
            ->lockForUpdate()
            ->findOrFail(
                (int) $this->training_id
            );

        $batch = $validated['batches'][0];

        $certificateTemplateId =
            $validated['is_certified']
                === self::CERTIFIED_YES
                    ? (
                        $validated[
                            'certificate_template_id'
                        ] ?? null
                    )
                    : null;

        $training->update([
            'title' => $validated['title'],
            'held_by' => $validated['held_by'],
            'activity_name' =>
                $validated['activity_name'] ?? null,
            'skill_name' =>
                $validated['skill_name'] ?? null,
            'is_certified' =>
                $validated['is_certified'],
            'certificate_template_id' =>
                $certificateTemplateId,
            'training_date' =>
                $batch['training_date'],
            'start_time' =>
                $batch['start_time'] ?? null,
            'finish_time' =>
                $batch['finish_time'] ?? null,
            'fee' =>
                $validated['fee'] ?? 0,
            'trainer_employee_id' =>
                $validated['trainer_type']
                    === self::TRAINER_INTERNAL
                        ? (
                            $validated[
                                'trainer_employee_id'
                            ] ?? null
                        )
                        : null,
            'trainer_external_name' =>
                $validated['trainer_type']
                    === self::TRAINER_EXTERNAL
                        ? (
                            $validated[
                                'trainer_external_name'
                            ] ?? null
                        )
                        : null,
        ]);

        return $training->refresh();
    }

    private function trainingPayload(
        TrainingGroup $group,
        array $validated,
        array $batch,
        int $batchNumber,
        array $sharedMetadata
    ): array {
        $batchName = trim(
            (string) (
                $batch['batch_name'] ?? ''
            )
        );

        if ($batchName === '') {
            $batchName =
                'Sesi ' . $batchNumber;
        }

        $isCertified =
            $sharedMetadata['is_certified']
            ?? self::CERTIFIED_NO;

        return [
            'training_group_id' =>
                $group->id,

            'batch_number' =>
                $batchNumber,

            'batch_name' =>
                $batchName,

            'title' =>
                $group->title,

            'held_by' =>
                $sharedMetadata['held_by']
                ?? null,

            'activity_name' =>
                $sharedMetadata['activity_name']
                ?? null,

            'skill_name' =>
                $sharedMetadata['skill_name']
                ?? null,

            'is_certified' =>
                $isCertified,

            'certificate_template_id' =>
                $isCertified === self::CERTIFIED_YES
                    ? (
                        $sharedMetadata[
                            'certificate_template_id'
                        ] ?? null
                    )
                    : null,

            'training_date' =>
                $batch['training_date'],

            'start_time' =>
                $batch['start_time'] ?? null,

            'finish_time' =>
                $batch['finish_time'] ?? null,

            'fee' =>
                $validated['fee'] ?? 0,

            'trainer_employee_id' =>
                $validated['trainer_type']
                    === self::TRAINER_INTERNAL
                        ? (
                            $validated[
                                'trainer_employee_id'
                            ] ?? null
                        )
                        : null,

            'trainer_external_name' =>
                $validated['trainer_type']
                    === self::TRAINER_EXTERNAL
                        ? (
                            $validated[
                                'trainer_external_name'
                            ] ?? null
                        )
                        : null,
        ];
    }

    private function rules(): array
    {
        $rules = [
            'batches' => [
                'required',
                'array',
                'min:1',
                'max:30',
            ],

            'batches.*.batch_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'batches.*.training_date' => [
                'required',
                'date',
            ],

            'batches.*.start_time' => [
                'nullable',
                'date_format:H:i',
            ],

            'batches.*.finish_time' => [
                'nullable',
                'date_format:H:i',

                function (
                    string $attribute,
                    mixed $value,
                    \Closure $fail
                ): void {
                    preg_match(
                        '/batches\.(\d+)\.finish_time/',
                        $attribute,
                        $matches
                    );

                    $index = isset($matches[1])
                        ? (int) $matches[1]
                        : null;

                    $startTime =
                        $index !== null
                            ? (
                                $this->batches[
                                    $index
                                ]['start_time']
                                ?? null
                            )
                            : null;

                    if (
                        $startTime
                        && $value
                        && strcmp(
                            (string) $value,
                            (string) $startTime
                        ) <= 0
                    ) {
                        $fail(
                            'Jam selesai harus lebih besar '
                            . 'dari jam mulai.'
                        );
                    }
                },
            ],

            'fee' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999',
            ],

            'trainer_type' => [
                'required',
                Rule::in([
                    self::TRAINER_INTERNAL,
                    self::TRAINER_EXTERNAL,
                    self::TRAINER_NONE,
                ]),
            ],

            'trainer_employee_id' => [
                Rule::requiredIf(
                    $this->trainer_type
                        === self::TRAINER_INTERNAL
                ),
                'nullable',
                'integer',
                Rule::exists('employees', 'id'),
            ],

            'trainer_external_name' => [
                Rule::requiredIf(
                    $this->trainer_type
                        === self::TRAINER_EXTERNAL
                ),
                'nullable',
                'string',
                'max:255',
            ],
        ];

        if (
            in_array(
                $this->mode,
                [
                    self::MODE_CREATE,
                    self::MODE_EDIT,
                    self::MODE_EDIT_SINGLE_GROUP,
                    self::MODE_EDIT_STANDALONE,
                ],
                true
            )
        ) {
            $rules['is_certified'] = [
                'required',
                Rule::in([
                    self::CERTIFIED_YES,
                    self::CERTIFIED_NO,
                ]),
            ];

            $rules['certificate_template_id'] = [
                Rule::requiredIf(
                    $this->is_certified
                        === self::CERTIFIED_YES
                ),
                'nullable',
                'integer',

                Rule::exists(
                    'certificate_templates',
                    'id'
                )->where(
                    fn ($query) => $query
                        ->whereNull('archived_at')
                        ->whereNull('deleted_at')
                ),
            ];
        }

        if (
            in_array(
                $this->mode,
                [
                    self::MODE_CREATE,
                    self::MODE_EDIT_SINGLE_GROUP,
                    self::MODE_EDIT_STANDALONE,
                ],
                true
            )
        ) {
            $rules['title'] = [
                'required',
                'string',
                'min:3',
                'max:255',
            ];

            if ($this->mode === self::MODE_CREATE) {
                $rules['title'][] = Rule::unique(
                    'training_groups',
                    'title'
                )->whereNull('deleted_at');
            }

            if ($this->mode === self::MODE_EDIT_SINGLE_GROUP) {
                $rules['title'][] = Rule::unique(
                    'training_groups',
                    'title'
                )
                    ->ignore($this->training_group_id)
                    ->whereNull('deleted_at');
            }

            $rules['held_by'] = [
                'required',
                'string',
                'max:255',
            ];

            $rules['activity_name'] = [
                'nullable',
                Rule::in([
                    self::ACTIVITY_INTERNAL,
                    self::ACTIVITY_EXTERNAL,
                ]),
            ];

            $rules['skill_name'] = [
                'nullable',
                Rule::in([
                    self::SKILL_HARD,
                    self::SKILL_SOFT,
                ]),
            ];
        }

        return $rules;
    }

    private function validationMessages(): array
    {
        return [
            'title.required' =>
                'Judul training wajib diisi.',

            'title.min' =>
                'Judul training minimal 3 karakter.',

            'title.unique' =>
                'Judul training sudah tersedia. '
                . 'Tambahkan sesi melalui tombol + '
                . 'pada tabel.',

            'batches.required' =>
                'Minimal satu sesi wajib tersedia.',

            'batches.min' =>
                'Minimal satu sesi wajib tersedia.',

            'batches.max' =>
                'Maksimal 30 sesi dalam satu proses.',

            'batches.*.training_date.required' =>
                'Tanggal setiap sesi wajib diisi.',

            'trainer_employee_id.required' =>
                'Trainer internal wajib dipilih.',

            'trainer_external_name.required' =>
                'Nama trainer external wajib diisi.',

            'certificate_template_id.required' =>
                'Certificate template wajib dipilih.',

            'certificate_template_id.exists' =>
                'Certificate template tidak tersedia '
                . 'atau sudah diarsipkan.',
        ];
    }

    private function normalizeForm(): void
    {
        $this->title =
            trim($this->title);

        $this->held_by =
            trim($this->held_by);

        $this->activity_name =
            trim($this->activity_name);

        $this->skill_name =
            trim($this->skill_name);

        $selectedTrainerId = collect(
            $this->trainer_employee_ids
        )
            ->filter(
                fn ($id): bool =>
                    is_numeric($id)
                    && (int) $id > 0
            )
            ->map(
                fn ($id): int => (int) $id
            )
            ->unique()
            ->first();

        $this->trainer_employee_id =
            $selectedTrainerId !== null
                ? (int) $selectedTrainerId
                : null;

        $this->trainer_employee_ids =
            $this->trainer_employee_id !== null
                ? [$this->trainer_employee_id]
                : [];

        $this->trainer_external_name =
            trim($this->trainer_external_name);

        $this->batches = collect($this->batches)
            ->map(function (array $batch): array {
                return [
                    'batch_name' => trim(
                        (string) (
                            $batch['batch_name']
                            ?? ''
                        )
                    ),

                    'training_date' =>
                        (
                            $batch['training_date']
                            ?? ''
                        ) ?: null,

                    'start_time' =>
                        (
                            $batch['start_time']
                            ?? ''
                        ) ?: null,

                    'finish_time' =>
                        (
                            $batch['finish_time']
                            ?? ''
                        ) ?: null,
                ];
            })
            ->values()
            ->all();

        $this->fee =
            $this->fee === ''
            || $this->fee === null
                ? 0
                : $this->fee;

        if (
            in_array(
                $this->mode,
                [
                    self::MODE_CREATE,
                    self::MODE_EDIT,
                    self::MODE_EDIT_SINGLE_GROUP,
                    self::MODE_EDIT_STANDALONE,
                ],
                true
            )
        ) {
            if (
                $this->is_certified
                    === self::CERTIFIED_NO
            ) {
                $this->certificate_template_id = null;
            }

            if (
                $this->is_certified
                    === self::CERTIFIED_YES
                && $this->certificate_template_id
                    === null
            ) {
                $this->certificate_template_id =
                    $this
                        ->defaultCertificateTemplateId();
            }
        }

        if (
            $this->trainer_type
                === self::TRAINER_INTERNAL
        ) {
            $this->trainer_external_name = '';
        }

        if (
            $this->trainer_type
                === self::TRAINER_EXTERNAL
        ) {
            $this->trainer_employee_id = null;
            $this->trainer_employee_ids = [];
        }

        if (
            $this->trainer_type
                === self::TRAINER_NONE
        ) {
            $this->trainer_employee_id = null;
            $this->trainer_employee_ids = [];
            $this->trainer_external_name = '';
        }
    }

    private function defaultCertificateTemplateId(): ?int
    {
        $template = CertificateTemplate::query()
            ->active()
            ->where('is_default', true)
            ->orderByRaw(
                'CASE WHEN kind = ? THEN 0 ELSE 1 END',
                [
                    CertificateTemplate::KIND_COMPLETION,
                ]
            )
            ->orderBy('name')
            ->first(['id']);

        return $template
            ? (int) $template->id
            : null;
    }

    private function certificateTemplateIsUsable(
        ?int $templateId
    ): bool {
        if ($templateId === null) {
            return false;
        }

        return CertificateTemplate::query()
            ->active()
            ->whereKey($templateId)
            ->exists();
    }

    private function assertCertificateConfigurationIsUsable(
        string $isCertified,
        ?int $templateId
    ): void {
        if ($isCertified !== self::CERTIFIED_YES) {
            return;
        }

        if (
            $this->certificateTemplateIsUsable(
                $templateId
            )
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'certificate_template_id' =>
                'Certificate template pada training '
                . 'tidak tersedia. Edit salah satu sesi '
                . 'dan pilih template aktif terlebih dahulu.',
        ]);
    }

    private function isEditing(): bool
    {
        return in_array(
            $this->mode,
            [
                self::MODE_EDIT,
                self::MODE_EDIT_SINGLE_GROUP,
                self::MODE_EDIT_STANDALONE,
            ],
            true
        );
    }

    private function nextVisibleBatchNumber(): int
    {
        return $this->batch_number_start
            + count($this->batches);
    }

    private function emptyBatch(
        string $name = 'Sesi 1'
    ): array {
        return [
            'batch_name' => $name,
            'training_date' => null,
            'start_time' => null,
            'finish_time' => null,
        ];
    }

    private function resetForm(): void
    {
        $this->reset([
            'mode',
            'training_id',
            'training_group_id',
            'batch_number_start',
            'title',
            'held_by',
            'activity_name',
            'skill_name',
            'is_certified',
            'certificate_template_id',
            'batches',
            'trainer_type',
            'trainer_employee_id',
            'trainer_employee_ids',
            'trainer_external_name',
            'fee',
        ]);

        $this->mode = self::MODE_CREATE;
        $this->held_by = self::DEFAULT_HELD_BY;

        $this->batch_number_start = 1;

        $this->is_certified =
            self::CERTIFIED_NO;

        $this->certificate_template_id = null;

        $this->trainer_type =
            self::TRAINER_INTERNAL;

        $this->trainer_employee_id = null;
        $this->trainer_employee_ids = [];

        $this->fee = 0;

        $this->batches = [];

        $this->resetValidation();
    }

    private function formatTimeForInput(
        mixed $time
    ): ?string {
        if ($time === null || $time === '') {
            return null;
        }

        return substr(
            (string) $time,
            0,
            5
        );
    }

    private function warningToast(
        string $text
    ): void {
        Flux::toast(
            heading: 'Warning',
            text: $text,
            variant: 'warning',
            duration: 3000,
        );
    }
};
