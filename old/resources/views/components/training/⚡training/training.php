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
        Gate::authorize(Permissions::CREATE_TRAINING);

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

            $training->participants()->detach();
            $training->delete();

            if (
                $groupId !== null
                && ! Training::query()
                    ->where('training_group_id', $groupId)
                    ->exists()
            ) {
                TrainingGroup::query()
                    ->find($groupId)
                    ?->delete();
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
                            SELECT MIN(grouped_training.id)
                            FROM trainings AS grouped_training
                            WHERE grouped_training.training_group_id
                                = trainings.training_group_id
                              AND grouped_training.deleted_at IS NULL
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
