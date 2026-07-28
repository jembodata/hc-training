<?php

use App\Imports\UsersImport;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Training;
use App\Models\TrainingGroup;
use App\Support\Auth\Permissions;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

new class extends Component
{
    use WithPagination;
    use WithFileUploads;

    private const DEFAULT_PER_PAGE = 10;
    private const PARTICIPANT_PAGE_SIZE = 20;
    private const PARTICIPANT_BULK_LIMIT = 500;

    private const SORTABLE_COLUMNS = [
        'title',
        'training_date',
        'held_by',
        'activity_name',
        'skill_name',
    ];

    public bool $show_import_modal = false;
    public bool $show_participant_modal = false;
    public array $expanded_training_groups = [];

    public bool $legacy_grouping_mode = false;
    public bool $show_convert_group_modal = false;

    /** @var array<int, int|string> */
    public array $selected_standalone_training_ids = [];

    public string $convert_group_title = '';

    /** @var array<int, array<string, mixed>> */
    public array $conversion_review_rows = [];

    /** @var array<int, string> */
    public array $conversion_warnings = [];

    public bool $show_remove_from_group_modal = false;
    public ?int $remove_from_group_training_id = null;
    public string $remove_from_group_training_title = '';
    public string $remove_from_group_session_label = '';

    public string $search = '';
    public string $activity_filter = '';
    public string $skill_filter = '';

    public string $sortBy = 'training_date';
    public string $sortDirection = 'desc';
    public int $perPage = self::DEFAULT_PER_PAGE;

    public ?int $participant_training_id = null;
    public string $participant_training_title = '';

    public string $participant_search = '';
    public string $participant_department_id = '';
    public string $participant_position_id = '';

    public string $selected_participant_search = '';
    public string $selected_participant_department_id = '';
    public string $selected_participant_position_id = '';

    public int $available_participant_page = 1;

    public int $selected_participant_page = 1;

    /** @var array<int, int|string> */
    public array $available_employee_ids = [];

    /** @var array<int, int|string> */
    public array $selected_employee_ids_for_removal = [];

    /** @var array<int, array<string, mixed>> */
    public array $selected_participants = [];

    /** @var array<int, int> */
    public array $original_participant_ids = [];

    public bool $show_participant_discard_modal = false;
    public bool $show_participant_bulk_add_modal = false;
    public bool $show_participant_clear_modal = false;

    public int $pending_bulk_add_count = 0;
    public int $pending_bulk_add_limit = 0;

    public mixed $excel_file = null;

    public function mount(): void
    {
        Gate::authorize(Permissions::VIEW_TRAINING);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedActivityFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSkillFilter(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if (! in_array($column, self::SORTABLE_COLUMNS, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset([
            'search',
            'activity_filter',
            'skill_filter',
        ]);

        $this->resetPage();
    }

    #[On('training-batch-saved')]
    public function refreshTrainingTable(): void
    {
        Gate::authorize(
            Permissions::VIEW_TRAINING
        );

    }

    public function toggleTrainingGroup(int $groupId): void
    {
        Gate::authorize(Permissions::VIEW_TRAINING);

        if (in_array($groupId, $this->expanded_training_groups, true)) {
            $this->expanded_training_groups = array_values(
                array_filter(
                    $this->expanded_training_groups,
                    fn (int $id): bool => $id !== $groupId
                )
            );

            return;
        }

        $this->expanded_training_groups[] = $groupId;
    }

    public function startLegacyGrouping(): void
    {
        Gate::authorize(Permissions::CREATE_TRAINING);
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $this->legacy_grouping_mode = true;
        $this->selected_standalone_training_ids = [];
        $this->resetConversionModal();
    }

    public function cancelLegacyGrouping(): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $this->legacy_grouping_mode = false;
        $this->selected_standalone_training_ids = [];
        $this->resetConversionModal();
    }

    public function openConvertTrainingGroupModal(): void
    {
        Gate::authorize(Permissions::CREATE_TRAINING);
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $ids = $this->normalizedStandaloneTrainingIds();

        $trainings = $this->standaloneTrainingsForConversion(
            $ids
        )->get();

        $validIds = $trainings
            ->pluck('id')
            ->map(
                static fn ($id): int => (int) $id
            )
            ->values()
            ->all();

        $this->selected_standalone_training_ids =
            $validIds;

        if ($trainings->count() < 2) {
            $this->warningToast(
                'Pilih minimal dua training standalone.',
                'Data Belum Cukup'
            );

            return;
        }

        $this->convert_group_title =
            $this->suggestedTrainingGroupTitle(
                $trainings
            );

        $this->conversion_review_rows =
            $trainings
                ->map(
                    static function (Training $training): array {
                        return [
                            'id' => (int) $training->id,
                            'title' => (string) $training->title,
                            'held_by' =>
                                (string) ($training->held_by ?? ''),
                            'activity_name' =>
                                (string) (
                                    $training->activity_name ?? ''
                                ),
                            'skill_name' =>
                                (string) (
                                    $training->skill_name ?? ''
                                ),
                            'training_date' =>
                                $training->training_date
                                    ? $training->training_date
                                        ->format('Y-m-d')
                                    : null,
                            'start_time' =>
                                $training->start_time
                                    ? substr(
                                        (string) $training->start_time,
                                        0,
                                        5
                                    )
                                    : null,
                            'finish_time' =>
                                $training->finish_time
                                    ? substr(
                                        (string) $training->finish_time,
                                        0,
                                        5
                                    )
                                    : null,
                            'participants_count' =>
                                (int) $training
                                    ->participants_count,
                            'is_certified' =>
                                (string) (
                                    $training->is_certified ?? 'No'
                                ),
                        ];
                    }
                )
                ->values()
                ->all();

        $this->conversion_warnings =
            $this->buildConversionWarnings(
                $trainings
            );

        $this->resetValidation([
            'convert_group_title',
            'selected_standalone_training_ids',
        ]);

        $this->show_convert_group_modal = true;
    }

    public function closeConvertTrainingGroupModal(): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $this->resetConversionModal();
    }

    public function convertSelectedTrainingsToGroup(): void
    {
        Gate::authorize(Permissions::CREATE_TRAINING);
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $this->convert_group_title =
            trim($this->convert_group_title);

        $this->selected_standalone_training_ids =
            $this->normalizedStandaloneTrainingIds();

        $validated = $this->validate([
            'convert_group_title' => [
                'required',
                'string',
                'max:255',
            ],
            'selected_standalone_training_ids' => [
                'required',
                'array',
                'min:2',
            ],
            'selected_standalone_training_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('trainings', 'id')
                    ->whereNull('training_group_id')
                    ->whereNull('deleted_at'),
            ],
        ], [
            'convert_group_title.required' =>
                'Nama Training Group wajib diisi.',
            'convert_group_title.max' =>
                'Nama Training Group maksimal 255 karakter.',
            'selected_standalone_training_ids.min' =>
                'Pilih minimal dua training standalone.',
            'selected_standalone_training_ids.*.exists' =>
                'Salah satu training sudah berubah atau tidak tersedia.',
        ]);

        $ids = collect(
            $validated['selected_standalone_training_ids']
        )
            ->map(
                static fn ($id): int => (int) $id
            )
            ->unique()
            ->values()
            ->all();

        $groupId = DB::transaction(
            function () use ($ids, $validated): int {
                $trainings =
                    $this->standaloneTrainingsForConversion(
                        $ids,
                        lock: true
                    )->get();

                if (
                    $trainings->count() < 2
                    || $trainings->count() !== count($ids)
                ) {
                    throw ValidationException::withMessages([
                        'selected_standalone_training_ids' =>
                            'Data training berubah. Buka ulang '
                            . 'review sebelum melakukan konversi.',
                    ]);
                }

                $group = TrainingGroup::query()->create([
                    'title' => trim(
                        (string) $validated[
                            'convert_group_title'
                        ]
                    ),
                    'created_by' => auth()->id(),
                ]);

                foreach (
                    $trainings
                    as $index => $training
                ) {
                    $sessionNumber = $index + 1;
                    $batchName = trim(
                        (string) (
                            $training->batch_name ?? ''
                        )
                    );

                    if (
                        $batchName === ''
                        || preg_match(
                            '/^(?:Sesi|Batch)\s+\d+$/i',
                            $batchName
                        ) === 1
                    ) {
                        $batchName =
                            'Sesi ' . $sessionNumber;
                    }

                    $training->update([
                        'training_group_id' =>
                            (int) $group->id,
                        'batch_number' =>
                            $sessionNumber,
                        'batch_name' =>
                            $batchName,
                        'title' =>
                            (string) $group->title,
                    ]);
                }

                return (int) $group->id;
            }
        );

        $this->expanded_training_groups =
            collect($this->expanded_training_groups)
                ->push($groupId)
                ->map(
                    static fn ($id): int => (int) $id
                )
                ->unique()
                ->values()
                ->all();

        $convertedCount = count($ids);

        $this->legacy_grouping_mode = false;
        $this->selected_standalone_training_ids = [];
        $this->resetConversionModal();
        $this->resetPage();

        $this->successToast(
            "{$convertedCount} training berhasil "
            . 'dikonversi menjadi Training Group.',
            'Konversi Berhasil'
        );
    }

    public function prepareRemoveTrainingFromGroup(
        int $trainingId
    ): void {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $training = Training::query()
            ->with('trainingGroup')
            ->findOrFail($trainingId);

        if (
            $training->training_group_id === null
            || $training->trainingGroup === null
        ) {
            $this->warningToast(
                'Training ini sudah menjadi standalone.',
                'Tidak Perlu Diubah'
            );

            return;
        }

        $sessionCount = Training::query()
            ->where(
                'training_group_id',
                $training->training_group_id
            )
            ->count();

        if ($sessionCount < 2) {
            $this->warningToast(
                'Training Group hanya memiliki satu sesi.',
                'Tidak Dapat Dikeluarkan'
            );

            return;
        }

        $this->remove_from_group_training_id =
            (int) $training->id;

        $this->remove_from_group_training_title =
            (string) (
                $training->trainingGroup->title
                ?: $training->title
            );

        $this->remove_from_group_session_label =
            trim((string) ($training->batch_name ?? ''))
            ?: 'Sesi ' . (
                $training->batch_number ?: $sessionCount
            );

        $this->show_remove_from_group_modal = true;
    }

    public function closeRemoveTrainingFromGroupModal(): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $this->resetRemoveFromGroupModal();
    }

    public function confirmRemoveTrainingFromGroup(): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        if ($this->remove_from_group_training_id === null) {
            $this->resetRemoveFromGroupModal();

            return;
        }

        $result = DB::transaction(
            function (): array {
                $training = Training::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        (int) $this
                            ->remove_from_group_training_id
                    );

                if (
                    $training->training_group_id === null
                ) {
                    throw ValidationException::withMessages([
                        'remove_from_group_training_id' =>
                            'Training ini sudah menjadi standalone.',
                    ]);
                }

                $groupId =
                    (int) $training->training_group_id;

                TrainingGroup::query()
                    ->lockForUpdate()
                    ->findOrFail($groupId);

                $sessions = Training::query()
                    ->where(
                        'training_group_id',
                        $groupId
                    )
                    ->orderByRaw(
                        'COALESCE(batch_number, 999999)'
                    )
                    ->orderBy('training_date')
                    ->orderBy('start_time')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($sessions->count() < 2) {
                    throw ValidationException::withMessages([
                        'remove_from_group_training_id' =>
                            'Training Group hanya memiliki '
                            . 'satu sesi.',
                    ]);
                }

                $training->update([
                    'training_group_id' => null,
                    'batch_number' => null,
                    'batch_name' => null,
                ]);

                $remainingSessions = $sessions
                    ->reject(
                        static fn (Training $session): bool =>
                            (int) $session->id
                            === (int) $training->id
                    )
                    ->values();

                // Kosongkan nomor lama terlebih dahulu agar perubahan
                // urutan tidak berbenturan dengan unique index.
                foreach ($remainingSessions as $session) {
                    $session->update([
                        'batch_number' => null,
                    ]);
                }

                foreach (
                    $remainingSessions
                    as $index => $session
                ) {
                    $sessionNumber = $index + 1;
                    $batchName = trim(
                        (string) (
                            $session->batch_name ?? ''
                        )
                    );

                    if (
                        $batchName === ''
                        || preg_match(
                            '/^(?:Sesi|Batch)\s+\d+$/i',
                            $batchName
                        ) === 1
                    ) {
                        $batchName =
                            'Sesi ' . $sessionNumber;
                    }

                    $session->update([
                        'batch_number' =>
                            $sessionNumber,
                        'batch_name' =>
                            $batchName,
                    ]);
                }

                return [
                    'group_id' => $groupId,
                    'remaining_count' =>
                        $remainingSessions->count(),
                ];
            }
        );

        if ($result['remaining_count'] < 2) {
            $this->expanded_training_groups =
                array_values(
                    array_filter(
                        $this->expanded_training_groups,
                        static fn (int $id): bool =>
                            $id !== $result['group_id']
                    )
                );
        }

        $this->resetRemoveFromGroupModal();
        $this->resetPage();

        $this->successToast(
            'Sesi berhasil dikeluarkan dan kembali '
            . 'menjadi training standalone.',
            'Training Dikeluarkan'
        );
    }

    public function openParticipantsModal(
        int $trainingId
    ): void {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $training = Training::query()
            ->with([
                'participants.organization',
                'participants.position',
            ])
            ->findOrFail($trainingId);

        $this->resetParticipantModal();

        $this->participant_training_id =
            (int) $training->id;

        $batchLabel = $training->batch_name
            ?: (
                $training->batch_number
                    ? 'Batch ' . $training->batch_number
                    : null
            );

        $this->participant_training_title = $batchLabel
            ? $training->title . ' — ' . $batchLabel
            : (string) $training->title;

        $this->selected_participants = $training
            ->participants
            ->map(
                fn (Employee $employee): array =>
                    $this->participantPayloadFromEmployeeModel(
                        $employee
                    )
            )
            ->values()
            ->all();

        $this->original_participant_ids =
            $this->selectedParticipantIds()
                ->sort()
                ->values()
                ->all();

        $this->show_participant_modal = true;
    }

    public function closeParticipantsModal(
        bool $force = false
    ): void {
        if (
            ! $force
            && $this->participantHasChanges
        ) {
            $this->show_participant_modal = true;
            $this->show_participant_discard_modal = true;

            return;
        }

        $this->show_participant_modal = false;
        $this->show_participant_discard_modal = false;
        $this->resetParticipantModal();
    }

    public function discardParticipantChanges(): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $this->show_participant_discard_modal = false;
        $this->closeParticipantsModal(true);
    }

    public function cancelDiscardParticipantChanges(): void
    {
        $this->show_participant_discard_modal = false;
        $this->show_participant_modal = true;
    }

    public function updatedParticipantSearch(): void
    {
        $this->resetAvailableParticipantPage();
    }

    public function updatedParticipantDepartmentId(): void
    {
        $this->participant_position_id = '';
        $this->resetAvailableParticipantPage();
    }

    public function updatedParticipantPositionId(): void
    {
        $this->resetAvailableParticipantPage();
    }

    public function updatedSelectedParticipantSearch(): void
    {
        $this->resetSelectedParticipantPage();
    }

    public function updatedSelectedParticipantDepartmentId(): void
    {
        $this->selected_participant_position_id = '';
        $this->resetSelectedParticipantPage();
    }

    public function updatedSelectedParticipantPositionId(): void
    {
        $this->resetSelectedParticipantPage();
    }

    public function clearParticipantFilters(): void
    {
        $this->participant_search = '';
        $this->participant_department_id = '';
        $this->participant_position_id = '';

        $this->resetAvailableParticipantPage();
    }

    public function clearSelectedParticipantFilters(): void
    {
        $this->selected_participant_search = '';
        $this->selected_participant_department_id = '';
        $this->selected_participant_position_id = '';

        $this->resetSelectedParticipantPage();
    }

    public function previousAvailableParticipantPage(): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $this->available_participant_page = max(
            1,
            $this->available_participant_page - 1
        );

        $this->available_employee_ids = [];
    }

    public function nextAvailableParticipantPage(): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $this->available_participant_page = min(
            $this->availableEmployeeTotalPages,
            $this->available_participant_page + 1
        );

        $this->available_employee_ids = [];
    }

    public function previousSelectedParticipantPage(): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $this->selected_participant_page = max(
            1,
            $this->selected_participant_page - 1
        );

        $this->selected_employee_ids_for_removal = [];
    }

    public function nextSelectedParticipantPage(): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $this->selected_participant_page = min(
            $this->selectedParticipantTotalPages,
            $this->selected_participant_page + 1
        );

        $this->selected_employee_ids_for_removal = [];
    }

    public function selectVisibleAvailableEmployees(): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $this->available_employee_ids = collect([
            ...$this->available_employee_ids,
            ...$this->visibleAvailableEmployeeIds,
        ])
            ->map(
                static fn ($id): int => (int) $id
            )
            ->filter(
                static fn (int $id): bool => $id > 0
            )
            ->unique()
            ->values()
            ->all();
    }

    public function clearAvailableEmployeeSelection(): void
    {
        $this->available_employee_ids = [];
    }

    public function selectVisibleSelectedParticipants(): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $this->selected_employee_ids_for_removal =
            collect([
                ...$this->selected_employee_ids_for_removal,
                ...$this->visibleSelectedParticipantIds,
            ])
                ->map(
                    static fn ($id): int => (int) $id
                )
                ->filter(
                    static fn (int $id): bool => $id > 0
                )
                ->unique()
                ->values()
                ->all();
    }

    public function clearSelectedParticipantSelection(): void
    {
        $this->selected_employee_ids_for_removal = [];
    }

    public function addSelectedParticipant(int $id): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $employee = $this->availableEmployeeQuery()
            ->where('employees.id', $id)
            ->first();

        if (! $employee) {
            $this->warningToast(
                'Karyawan tidak ditemukan, tidak aktif, atau sudah dipilih.'
            );

            return;
        }

        $this->selected_participants[] =
            $this->participantPayloadFromQueryResult(
                $employee
            );

        $this->available_employee_ids = collect(
            $this->available_employee_ids
        )
            ->reject(
                static fn ($selectedId): bool =>
                    (int) $selectedId === $id
            )
            ->values()
            ->all();

        $this->normalizeAvailableParticipantPage();
        $this->resetValidation('selected_participants');
    }

    public function addCheckedParticipants(): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $requestedIds = collect(
            $this->available_employee_ids
        )
            ->map(
                static fn ($id): int => (int) $id
            )
            ->filter(
                static fn (int $id): bool => $id > 0
            )
            ->unique()
            ->take(self::PARTICIPANT_PAGE_SIZE)
            ->values();

        if ($requestedIds->isEmpty()) {
            $this->warningToast(
                'Pilih minimal satu karyawan.'
            );

            return;
        }

        $employees = $this->availableEmployeeQuery()
            ->whereIn(
                'employees.id',
                $requestedIds->all()
            )
            ->orderBy('employees.name')
            ->get();

        $added = $this->appendParticipants($employees);

        $this->available_employee_ids = [];
        $this->normalizeAvailableParticipantPage();
        $this->resetValidation('selected_participants');

        if ($added === 0) {
            $this->warningToast(
                'Tidak ada karyawan baru yang dapat ditambahkan.'
            );

            return;
        }

        $this->successToast(
            "{$added} karyawan ditambahkan ke daftar peserta.",
            'Peserta ditambahkan'
        );
    }

    public function prepareAddAllFilteredParticipants(): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $count = $this->availableEmployeeCount;

        if ($count === 0) {
            $this->warningToast(
                'Tidak ada karyawan baru pada hasil filter.'
            );

            return;
        }

        $this->pending_bulk_add_count = $count;
        $this->pending_bulk_add_limit = min(
            $count,
            self::PARTICIPANT_BULK_LIMIT
        );
        $this->show_participant_bulk_add_modal = true;
    }

    public function confirmAddAllFilteredParticipants(): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $employees = $this->availableEmployeeQuery()
            ->orderBy('employees.name')
            ->limit(self::PARTICIPANT_BULK_LIMIT)
            ->get();

        $added = $this->appendParticipants($employees);

        $this->show_participant_bulk_add_modal = false;
        $this->pending_bulk_add_count = 0;
        $this->pending_bulk_add_limit = 0;
        $this->available_employee_ids = [];
        $this->available_participant_page = 1;
        $this->selected_participant_page = 1;
        $this->resetValidation('selected_participants');

        if ($added === 0) {
            $this->warningToast(
                'Tidak ada karyawan baru yang dapat ditambahkan.'
            );

            return;
        }

        $this->successToast(
            "{$added} karyawan ditambahkan dari hasil filter.",
            'Peserta ditambahkan'
        );
    }

    public function removeParticipant(int $id): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $exists = collect($this->selected_participants)
            ->contains(
                static fn (array $participant): bool =>
                    (int) $participant['id'] === $id
            );

        if (! $exists) {
            $this->warningToast(
                'Peserta tidak ditemukan dalam daftar pilihan.'
            );

            return;
        }

        $this->selected_participants = collect(
            $this->selected_participants
        )
            ->reject(
                static fn (array $participant): bool =>
                    (int) $participant['id'] === $id
            )
            ->values()
            ->all();

        $this->selected_employee_ids_for_removal =
            collect(
                $this->selected_employee_ids_for_removal
            )
                ->reject(
                    static fn ($selectedId): bool =>
                        (int) $selectedId === $id
                )
                ->values()
                ->all();

        $this->normalizeSelectedParticipantPage();
        $this->resetValidation('selected_participants');
    }

    public function removeCheckedParticipants(): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $ids = collect(
            $this->selected_employee_ids_for_removal
        )
            ->map(
                static fn ($id): int => (int) $id
            )
            ->filter(
                static fn (int $id): bool => $id > 0
            )
            ->unique()
            ->take(self::PARTICIPANT_PAGE_SIZE);

        if ($ids->isEmpty()) {
            $this->warningToast(
                'Pilih minimal satu peserta yang akan dikeluarkan.'
            );

            return;
        }

        $before = count($this->selected_participants);

        $this->selected_participants = collect(
            $this->selected_participants
        )
            ->reject(
                static fn (array $participant): bool =>
                    $ids->contains(
                        (int) $participant['id']
                    )
            )
            ->values()
            ->all();

        $removed = $before
            - count($this->selected_participants);

        $this->selected_employee_ids_for_removal = [];
        $this->normalizeSelectedParticipantPage();
        $this->resetValidation('selected_participants');

        $this->warningToast(
            "{$removed} peserta dikeluarkan dari daftar.",
            'Peserta dikeluarkan'
        );
    }

    public function prepareClearSelectedParticipants(): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        if ($this->selected_participants === []) {
            $this->warningToast(
                'Belum ada peserta yang dipilih.'
            );

            return;
        }

        $this->show_participant_clear_modal = true;
    }

    public function confirmClearSelectedParticipants(): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        $total = count($this->selected_participants);

        $this->selected_participants = [];
        $this->selected_employee_ids_for_removal = [];
        $this->selected_participant_page = 1;
        $this->show_participant_clear_modal = false;
        $this->resetValidation('selected_participants');

        $this->warningToast(
            "{$total} peserta dikeluarkan dari daftar.",
            'Daftar peserta dikosongkan'
        );
    }

    public function saveParticipants(): void
    {
        Gate::authorize(Permissions::UPDATE_TRAINING);

        if (! $this->participantHasChanges) {
            $this->warningToast(
                'Belum ada perubahan peserta untuk disimpan.'
            );

            return;
        }

        $validated = $this->validate(
            $this->participantRules()
        );

        $participantIds = collect(
            $validated['selected_participants'] ?? []
        )
            ->pluck('id')
            ->map(
                static fn ($id): int => (int) $id
            )
            ->unique()
            ->values()
            ->all();

        $changeSummary = $this->participantChangeSummary;

        DB::transaction(
            function () use (
                $validated,
                $participantIds
            ): void {
                $training = Training::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        (int) $validated[
                            'participant_training_id'
                        ]
                    );

                $training->participants()->sync(
                    $participantIds
                );
            }
        );

        $this->successToast(
            "{$changeSummary['added']} ditambahkan, "
            . "{$changeSummary['removed']} dikeluarkan. "
            . count($participantIds)
            . ' peserta tersimpan.',
            'Peserta berhasil disimpan'
        );

        $this->closeParticipantsModal(true);
    }

    public function getAvailableEmployeesProperty(): Collection
    {
        if (
            Gate::denies(
                Permissions::UPDATE_TRAINING
            )
            || ! $this->show_participant_modal
        ) {
            return collect();
        }

        return $this->availableEmployeeQuery()
            ->orderBy('employees.name')
            ->forPage(
                $this->available_participant_page,
                self::PARTICIPANT_PAGE_SIZE
            )
            ->get();
    }

    public function getAvailableEmployeeCountProperty(): int
    {
        if (
            Gate::denies(
                Permissions::UPDATE_TRAINING
            )
            || ! $this->show_participant_modal
        ) {
            return 0;
        }

        return (int) $this
            ->availableEmployeeQuery()
            ->count('employees.id');
    }

    public function getAvailableEmployeeTotalPagesProperty(): int
    {
        return max(
            1,
            (int) ceil(
                $this->availableEmployeeCount
                / self::PARTICIPANT_PAGE_SIZE
            )
        );
    }

    public function getAvailableHasPreviousProperty(): bool
    {
        return $this->available_participant_page > 1;
    }

    public function getAvailableHasNextProperty(): bool
    {
        return $this->available_participant_page
            < $this->availableEmployeeTotalPages;
    }

    public function getVisibleAvailableEmployeeIdsProperty(): array
    {
        return $this->availableEmployees
            ->pluck('id')
            ->map(
                static fn ($id): int => (int) $id
            )
            ->all();
    }

    public function getFilteredSelectedParticipantsProperty(): Collection
    {
        $search = mb_strtolower(
            trim($this->selected_participant_search)
        );

        return collect($this->selected_participants)
            ->filter(
                function (
                    array $participant
                ) use ($search): bool {
                    $name = mb_strtolower(
                        (string) (
                            $participant['name'] ?? ''
                        )
                    );

                    $nik = mb_strtolower(
                        (string) (
                            $participant['nik'] ?? ''
                        )
                    );

                    $matchesSearch = $search === ''
                        || str_contains($name, $search)
                        || str_contains($nik, $search);

                    $matchesDepartment =
                        $this
                            ->selected_participant_department_id
                            === ''
                        || (string) (
                            $participant['org_id'] ?? ''
                        ) === $this
                            ->selected_participant_department_id;

                    $matchesPosition =
                        $this
                            ->selected_participant_position_id
                            === ''
                        || (string) (
                            $participant['position_id'] ?? ''
                        ) === $this
                            ->selected_participant_position_id;

                    return $matchesSearch
                        && $matchesDepartment
                        && $matchesPosition;
                }
            )
            ->sortBy(
                static fn (array $participant): string =>
                    mb_strtolower(
                        (string) (
                            $participant['name'] ?? ''
                        )
                    )
            )
            ->values();
    }

    public function getSelectedParticipantsPageProperty(): Collection
    {
        return $this->filteredSelectedParticipants
            ->slice(
                ($this->selected_participant_page - 1)
                    * self::PARTICIPANT_PAGE_SIZE,
                self::PARTICIPANT_PAGE_SIZE
            )
            ->values();
    }

    public function getSelectedParticipantCountProperty(): int
    {
        return $this->filteredSelectedParticipants->count();
    }

    public function getSelectedParticipantTotalPagesProperty(): int
    {
        return max(
            1,
            (int) ceil(
                $this->selectedParticipantCount
                / self::PARTICIPANT_PAGE_SIZE
            )
        );
    }

    public function getSelectedHasPreviousProperty(): bool
    {
        return $this->selected_participant_page > 1;
    }

    public function getSelectedHasNextProperty(): bool
    {
        return $this->selected_participant_page
            < $this->selectedParticipantTotalPages;
    }

    public function getVisibleSelectedParticipantIdsProperty(): array
    {
        return $this->selectedParticipantsPage
            ->pluck('id')
            ->map(
                static fn ($id): int => (int) $id
            )
            ->all();
    }

    public function getParticipantHasChangesProperty(): bool
    {
        return $this->selectedParticipantIds()
            ->sort()
            ->values()
            ->all()
            !== collect($this->original_participant_ids)
                ->map(
                    static fn ($id): int => (int) $id
                )
                ->sort()
                ->values()
                ->all();
    }

    public function getParticipantChangeSummaryProperty(): array
    {
        $current = $this->selectedParticipantIds();
        $original = collect(
            $this->original_participant_ids
        )
            ->map(
                static fn ($id): int => (int) $id
            )
            ->unique();

        return [
            'added' => $current->diff($original)->count(),
            'removed' => $original->diff($current)->count(),
        ];
    }

    public function importExcel(): void
    {
        Gate::authorize(Permissions::IMPORT_TRAINING);

        $this->validate([
            'excel_file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:10240',
            ],
        ], [
            'excel_file.required' => 'File import wajib dipilih.',
            'excel_file.mimes' => 'Format file harus xlsx, xls, atau csv.',
            'excel_file.max' => 'Ukuran file maksimal 10 MB.',
        ]);

        try {
            Excel::import(
                new UsersImport(),
                $this->excel_file
            );

            $this->reset('excel_file');
            $this->resetValidation('excel_file');
            $this->show_import_modal = false;
            $this->resetPage();

            $this->successToast(
                'Import data training berhasil.',
                'Import Berhasil'
            );
        } catch (ValidationException $exception) {

            $message = collect($exception->errors())
                ->flatten()
                ->filter(
                    static fn(mixed $value): bool =>
                    is_string($value) && trim($value) !== ''
                )
                ->first();

            Log::warning('Training import validation failed.', [
                'user_id' => auth()->id(),
                'file_name' => $this->excel_file?->getClientOriginalName(),
                'errors' => $exception->errors(),
            ]);

            $this->dangerToast(
                $message ?: 'Data pada file import tidak valid.',
                'Import Gagal'
            );
        } catch (ExcelValidationException $exception) {

            $failure = collect($exception->failures())->first();

            $message = $failure
                ? 'Baris ' . $failure->row() . ': '
                . implode(' ', $failure->errors())
                : 'Data pada file import tidak valid.';

            Log::warning('Training Excel row validation failed.', [
                'user_id' => auth()->id(),
                'file_name' => $this->excel_file?->getClientOriginalName(),
                'failures' => collect($exception->failures())
                    ->map(static fn($item): array => [
                        'row' => $item->row(),
                        'attribute' => $item->attribute(),
                        'errors' => $item->errors(),
                        'values' => $item->values(),
                    ])
                    ->all(),
            ]);

            $this->dangerToast(
                $message,
                'Import Gagal'
            );
        } catch (QueryException $exception) {
            $reference = strtoupper(
                substr(bin2hex(random_bytes(6)), 0, 10)
            );

            Log::error('Training import database error.', [
                'reference' => $reference,
                'user_id' => auth()->id(),
                'file_name' => $this->excel_file?->getClientOriginalName(),
                'sql_state' => $exception->errorInfo[0] ?? null,
                'driver_code' => $exception->errorInfo[1] ?? null,
                'exception' => $exception,
            ]);

            $message = app()->isLocal()
                ? $exception->getMessage()
                : "Terjadi kesalahan database. Kode: {$reference}";

            $this->dangerToast(
                $message,
                'Import Gagal'
            );
        } catch (\Throwable $exception) {
            $reference = strtoupper(
                substr(bin2hex(random_bytes(6)), 0, 10)
            );

            Log::error('Unexpected training import error.', [
                'reference' => $reference,
                'user_id' => auth()->id(),
                'file_name' => $this->excel_file?->getClientOriginalName(),
                'exception' => $exception,
            ]);

            $message = app()->isLocal()
                ? $exception->getMessage()
                : "Import gagal diproses. Kode: {$reference}";

            $this->dangerToast(
                $message,
                'Import Gagal'
            );
        }
    }

    public function deleteTraining(int $id): void
    {
        Gate::authorize(Permissions::DELETE_TRAINING);

        DB::transaction(function () use ($id): void {
            $training = Training::query()->findOrFail($id);
            $groupId = $training->training_group_id
                ? (int) $training->training_group_id
                : null;

            if ($groupId !== null) {
                TrainingGroup::query()
                    ->lockForUpdate()
                    ->findOrFail($groupId);
            }

            $training = Training::query()
                ->lockForUpdate()
                ->findOrFail($id);

            $training->participants()->detach();

            // Lepaskan unique key group + batch sebelum soft delete.
            // Row soft-deleted tetap diperhitungkan oleh unique index MySQL.
            if ($groupId !== null) {
                $training->update([
                    'training_group_id' => null,
                    'batch_number' => null,
                    'batch_name' => null,
                ]);
            }

            $training->delete();

            if ($groupId !== null) {
                $remainingSessions = Training::query()
                    ->where('training_group_id', $groupId)
                    ->orderByRaw('COALESCE(batch_number, 999999)')
                    ->orderBy('training_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($remainingSessions->isEmpty()) {
                    TrainingGroup::query()
                        ->find($groupId)
                        ?->delete();
                } else {
                    // Normalisasi dua tahap untuk menghindari benturan
                    // unique (training_group_id, batch_number).
                    foreach ($remainingSessions as $session) {
                        $session->update([
                            'batch_number' => null,
                        ]);
                    }

                    foreach ($remainingSessions as $index => $session) {
                        $batchNumber = $index + 1;
                        $batchName = trim(
                            (string) ($session->batch_name ?? '')
                        );

                        if (
                            $batchName === ''
                            || preg_match(
                                '/^Sesi\s+\d+$/i',
                                $batchName
                            ) === 1
                        ) {
                            $batchName = 'Sesi ' . $batchNumber;
                        }

                        $session->update([
                            'batch_number' => $batchNumber,
                            'batch_name' => $batchName,
                        ]);
                    }
                }
            }
        });

        if ($this->participant_training_id === $id) {
            $this->closeParticipantsModal();
        }

        $this->successToast('Data training berhasil dihapus.');
    }

    public function with(): array
    {
        $canUpdateTraining = Gate::allows(
            Permissions::UPDATE_TRAINING
        );

        return [
            'trainings' => $this->trainingTableQuery()
                ->paginate($this->perPage),

            'departments' => $canUpdateTraining
                ? Organization::query()
                ->orderBy('org_name')
                ->get([
                    'id',
                    'org_name',
                ])
                : collect(),

            'positions' => $canUpdateTraining
                ? Position::query()
                    ->orderBy('position_name')
                    ->get([
                        'id',
                        'position_name',
                    ])
                : collect(),
        ];
    }


    private function trainingTableQuery(): Builder
    {
        $search = trim($this->search);

        return Training::query()

            ->where(function (Builder $displayQuery): void {
                $displayQuery
                    ->whereNull('trainings.training_group_id')
                    ->orWhereRaw(
                        'trainings.id = (
                            SELECT grouped_training.id
                            FROM trainings AS grouped_training
                            WHERE grouped_training.training_group_id
                                = trainings.training_group_id
                              AND grouped_training.deleted_at IS NULL
                            ORDER BY
                                COALESCE(
                                    grouped_training.batch_number,
                                    999999
                                ),
                                CASE
                                    WHEN grouped_training.training_date
                                        IS NULL
                                    THEN 1
                                    ELSE 0
                                END,
                                grouped_training.training_date,
                                grouped_training.id
                            LIMIT 1
                        )'
                    );
            })
            ->with([
                'trainerInternal',
                'certificateTemplate',
                'trainingGroup.trainings' => function ($query): void {
                    $query
                        ->with([
                            'trainerInternal',
                            'certificateTemplate',
                        ])
                        ->withCount('participants')
                        ->orderByRaw('COALESCE(batch_number, 999999)')
                        ->orderBy('training_date')
                        ->orderBy('id');
                },
            ])
            ->withCount('participants')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $sub) use ($search): void {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('held_by', 'like', "%{$search}%")
                        ->orWhere('activity_name', 'like', "%{$search}%")
                        ->orWhere('skill_name', 'like', "%{$search}%")
                        ->orWhere('trainer_external_name', 'like', "%{$search}%")
                        ->orWhereHas('trainerInternal', function (Builder $trainer) use ($search): void {
                            $trainer->where('name', 'like', "%{$search}%")
                                ->orWhere('nik', 'like', "%{$search}%");
                        });
                });
            })
            ->when($this->activity_filter !== '', function (Builder $query): void {
                $query->where('activity_name', $this->activity_filter);
            })
            ->when($this->skill_filter !== '', function (Builder $query): void {
                $query->where('skill_name', $this->skill_filter);
            })
            ->orderBy($this->safeSortBy(), $this->safeSortDirection())
            ->orderByDesc('id');
    }

    private function participantRules(): array
    {
        return [
            'participant_training_id' => [
                'required',
                'integer',
                Rule::exists('trainings', 'id'),
            ],
            'selected_participants' => [
                'array',
            ],
            'selected_participants.*.id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('employees', 'id'),
            ],
        ];
    }

    private function filteredParticipantQuery(): Builder
    {
        $search = trim($this->participant_search);

        return $this->participantLookupQuery()
            ->when(
                $search !== '',
                function (
                    Builder $query
                ) use ($search): void {
                    $query->where(
                        function (
                            Builder $subQuery
                        ) use ($search): void {
                            $subQuery
                                ->where(
                                    'employees.name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'employees.nik',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->when(
                $this->participant_department_id !== '',
                fn (Builder $query) => $query->where(
                    'employees.org_id',
                    (int) $this->participant_department_id
                )
            )
            ->when(
                $this->participant_position_id !== '',
                fn (Builder $query) => $query->where(
                    'employees.position_id',
                    (int) $this->participant_position_id
                )
            );
    }

    private function availableEmployeeQuery(): Builder
    {
        $query = $this->filteredParticipantQuery();
        $selectedIds = $this->selectedParticipantIds();

        if ($selectedIds->isNotEmpty()) {
            $query->whereNotIn(
                'employees.id',
                $selectedIds->all()
            );
        }

        return $query;
    }

    private function participantLookupQuery(): Builder
    {
        return Employee::query()
            ->leftJoin(
                'organizations as o',
                'employees.org_id',
                '=',
                'o.id'
            )
            ->leftJoin(
                'positions as p',
                'employees.position_id',
                '=',
                'p.id'
            )
            ->whereRaw(
                'LOWER(TRIM(employees.status)) = ?',
                ['active']
            )
            ->select([
                'employees.id',
                'employees.name',
                'employees.nik',
                'employees.org_id',
                'employees.position_id',
                'o.org_name',
                'p.position_name',
            ]);
    }

    private function selectedParticipantIds(): Collection
    {
        return collect($this->selected_participants)
            ->pluck('id')
            ->map(
                static fn ($id): int => (int) $id
            )
            ->filter(
                static fn (int $id): bool => $id > 0
            )
            ->unique()
            ->values();
    }

    private function appendParticipants(
        Collection $employees
    ): int {
        $existingIds = $this->selectedParticipantIds();
        $added = 0;

        foreach ($employees as $employee) {
            $employeeId = (int) $employee->id;

            if ($existingIds->contains($employeeId)) {
                continue;
            }

            $this->selected_participants[] =
                $this->participantPayloadFromQueryResult(
                    $employee
                );

            $existingIds->push($employeeId);
            $added++;
        }

        return $added;
    }

    private function participantPayloadFromEmployeeModel(
        Employee $employee
    ): array {
        return [
            'id' => (int) $employee->id,
            'name' => (string) $employee->name,
            'nik' => (string) $employee->nik,
            'org_id' => $employee->org_id !== null
                ? (int) $employee->org_id
                : null,
            'position_id' =>
                $employee->position_id !== null
                    ? (int) $employee->position_id
                    : null,
            'org_name' =>
                $employee->organization->org_name
                ?? 'DEPT TIDAK TERDAFTAR',
            'position_name' =>
                $employee->position->position_name
                ?? '-',
        ];
    }

    private function participantPayloadFromQueryResult(
        object $employee
    ): array {
        return [
            'id' => (int) $employee->id,
            'name' => (string) $employee->name,
            'nik' => (string) $employee->nik,
            'org_id' => $employee->org_id !== null
                ? (int) $employee->org_id
                : null,
            'position_id' =>
                $employee->position_id !== null
                    ? (int) $employee->position_id
                    : null,
            'org_name' =>
                $employee->org_name
                ?? 'DEPT TIDAK TERDAFTAR',
            'position_name' =>
                $employee->position_name
                ?? '-',
        ];
    }

    private function resetAvailableParticipantPage(): void
    {
        $this->available_participant_page = 1;
        $this->available_employee_ids = [];
    }

    private function resetSelectedParticipantPage(): void
    {
        $this->selected_participant_page = 1;
        $this->selected_employee_ids_for_removal = [];
    }

    private function normalizeAvailableParticipantPage(): void
    {
        $this->available_participant_page = min(
            max(1, $this->available_participant_page),
            $this->availableEmployeeTotalPages
        );
    }

    private function normalizeSelectedParticipantPage(): void
    {
        $this->selected_participant_page = min(
            max(1, $this->selected_participant_page),
            $this->selectedParticipantTotalPages
        );
    }

    private function resetParticipantModal(): void
    {
        $this->participant_training_id = null;
        $this->participant_training_title = '';

        $this->participant_search = '';
        $this->participant_department_id = '';
        $this->participant_position_id = '';

        $this->selected_participant_search = '';
        $this->selected_participant_department_id = '';
        $this->selected_participant_position_id = '';

        $this->available_participant_page = 1;
        $this->selected_participant_page = 1;

        $this->available_employee_ids = [];
        $this->selected_employee_ids_for_removal = [];
        $this->selected_participants = [];
        $this->original_participant_ids = [];

        $this->show_participant_discard_modal = false;
        $this->show_participant_bulk_add_modal = false;
        $this->show_participant_clear_modal = false;
        $this->pending_bulk_add_count = 0;
        $this->pending_bulk_add_limit = 0;

        $this->resetValidation();
    }

    /**
     * @return array<int, int>
     */
    private function normalizedStandaloneTrainingIds(): array
    {
        return collect(
            $this->selected_standalone_training_ids
        )
            ->map(
                static fn ($id): int => (int) $id
            )
            ->filter(
                static fn (int $id): bool => $id > 0
            )
            ->unique()
            ->values()
            ->all();
    }

    private function standaloneTrainingsForConversion(
        array $ids,
        bool $lock = false
    ): Builder {
        $query = Training::query()
            ->whereNull('training_group_id')
            ->whereIn('id', $ids)
            ->withCount('participants')
            ->orderByRaw(
                'CASE WHEN training_date IS NULL '
                . 'THEN 1 ELSE 0 END'
            )
            ->orderBy('training_date')
            ->orderByRaw(
                'CASE WHEN start_time IS NULL '
                . 'THEN 1 ELSE 0 END'
            )
            ->orderBy('start_time')
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query;
    }

    private function suggestedTrainingGroupTitle(
        Collection $trainings
    ): string {
        $titles = $trainings
            ->pluck('title')
            ->map(
                static fn ($title): string =>
                    trim((string) $title)
            )
            ->filter()
            ->values();

        if ($titles->isEmpty()) {
            return '';
        }

        return (string) $titles->first();
    }

    /**
     * @return array<int, string>
     */
    private function buildConversionWarnings(
        Collection $trainings
    ): array {
        $warnings = [];

        $fieldWarnings = [
            'title' =>
                'Judul training yang dipilih tidak sama.',
            'held_by' =>
                'Penyelenggara training yang dipilih berbeda.',
            'activity_name' =>
                'Activity training yang dipilih berbeda.',
            'skill_name' =>
                'Skill training yang dipilih berbeda.',
            'is_certified' =>
                'Status sertifikasi training yang dipilih berbeda.',
            'certificate_template_id' =>
                'Template sertifikat yang dipilih berbeda.',
        ];

        foreach (
            $fieldWarnings
            as $field => $message
        ) {
            $values = $trainings
                ->map(
                    static function (
                        Training $training
                    ) use ($field): string {
                        return mb_strtolower(
                            trim(
                                (string) (
                                    $training->{$field} ?? ''
                                )
                            )
                        );
                    }
                )
                ->unique();

            if ($values->count() > 1) {
                $warnings[] = $message;
            }
        }

        $years = $trainings
            ->map(
                static fn (Training $training): ?string =>
                    $training->training_date
                        ? $training->training_date
                            ->format('Y')
                        : null
            )
            ->filter()
            ->unique();

        if ($years->count() > 1) {
            $warnings[] =
                'Training berasal dari tahun yang berbeda.';
        }

        return $warnings;
    }

    private function resetConversionModal(): void
    {
        $this->show_convert_group_modal = false;
        $this->convert_group_title = '';
        $this->conversion_review_rows = [];
        $this->conversion_warnings = [];

        $this->resetValidation([
            'convert_group_title',
            'selected_standalone_training_ids',
        ]);
    }

    private function resetRemoveFromGroupModal(): void
    {
        $this->show_remove_from_group_modal = false;
        $this->remove_from_group_training_id = null;
        $this->remove_from_group_training_title = '';
        $this->remove_from_group_session_label = '';

        $this->resetValidation([
            'remove_from_group_training_id',
        ]);
    }

    private function safeSortBy(): string
    {
        return in_array($this->sortBy, self::SORTABLE_COLUMNS, true)
            ? $this->sortBy
            : 'training_date';
    }

    private function safeSortDirection(): string
    {
        return in_array(strtolower($this->sortDirection), ['asc', 'desc'], true)
            ? strtolower($this->sortDirection)
            : 'desc';
    }

    private function successToast(string $text, string $heading = 'Success'): void
    {
        Flux::toast(
            heading: $heading,
            text: $text,
            variant: 'success',
            duration: 3000,
        );
    }

    private function warningToast(string $text, string $heading = 'Warning'): void
    {
        Flux::toast(
            heading: $heading,
            text: $text,
            variant: 'warning',
            duration: 3000,
        );
    }

    private function dangerToast(string $text, string $heading = 'Failed'): void
    {
        Flux::toast(
            heading: $heading,
            text: $text,
            variant: 'danger',
            duration: 4000,
        );
    }
};
