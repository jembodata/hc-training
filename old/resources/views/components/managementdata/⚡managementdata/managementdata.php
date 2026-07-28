<?php

use App\Models\Organization;
use App\Models\Position;
use App\Support\Auth\Permissions;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    private const TAB_ORGANIZATION = 'org';
    private const TAB_POSITION = 'pos';

    private const DEFAULT_PER_PAGE = 10;
    private const ALLOWED_PER_PAGE = [10, 25, 50];

    public string $activeTab = self::TAB_ORGANIZATION;
    public string $search = '';
    public int $perPage = self::DEFAULT_PER_PAGE;

    public ?int $editingId = null;
    public string $name = '';
    public bool $show_form_modal = false;

    public ?int $deleteId = null;
    public string $deleteType = '';
    public string $deleteName = '';
    public bool $show_delete_modal = false;

    public function mount(): void
    {
        Gate::authorize(Permissions::VIEW_MANAGEMENT_DATA);
    }

    public function updatedActiveTab(string $value): void
    {
        $this->activeTab = in_array(
            $value,
            [self::TAB_ORGANIZATION, self::TAB_POSITION],
            true
        )
            ? $value
            : self::TAB_ORGANIZATION;

        $this->search = '';
        $this->resetForm();
        $this->resetDeleteModal();
        $this->resetPage();

        unset($this->masterData);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        unset($this->masterData);
    }

    public function updatedPerPage(mixed $value): void
    {
        $perPage = (int) $value;

        $this->perPage = in_array($perPage, self::ALLOWED_PER_PAGE, true)
            ? $perPage
            : self::DEFAULT_PER_PAGE;

        $this->resetPage();
        unset($this->masterData);
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();

        unset($this->masterData);
    }

    public function openCreateModal(): void
    {
        Gate::authorize(Permissions::MANAGE_ATTRIBUTE_MANAGEMENT);

        $this->resetForm(closeModal: false);
        $this->show_form_modal = true;
    }

    public function openEditModal(int $id): void
    {
        Gate::authorize(Permissions::MANAGE_ATTRIBUTE_MANAGEMENT);

        $this->resetForm(closeModal: false);

        if ($this->activeTab === self::TAB_ORGANIZATION) {
            $organization = Organization::query()->findOrFail($id);

            $this->editingId = (int) $organization->id;
            $this->name = (string) $organization->org_name;
        } else {
            $position = Position::query()->findOrFail($id);

            $this->editingId = (int) $position->id;
            $this->name = (string) $position->position_name;
        }

        $this->show_form_modal = true;
    }

    public function save(): void
    {
        Gate::authorize(Permissions::MANAGE_ATTRIBUTE_MANAGEMENT);

        $this->normalizeName();

        $validated = $this->validate(
            $this->rules(),
            $this->validationMessages()
        );

        $isUpdating = $this->editingId !== null;

        try {
            DB::transaction(function () use ($validated): void {
                if ($this->activeTab === self::TAB_ORGANIZATION) {
                    $organization = $this->editingId
                        ? Organization::query()->findOrFail($this->editingId)
                        : new Organization();

                    $organization->org_name = $validated['name'];
                    $organization->save();

                    return;
                }

                $position = $this->editingId
                    ? Position::query()->findOrFail($this->editingId)
                    : new Position();

                $position->position_name = $validated['name'];
                $position->save();
            });
        } catch (\Throwable $exception) {
            report($exception);

            $this->dangerToast(
                'Data master gagal disimpan. Silakan coba kembali.'
            );

            return;
        }

        if (! $isUpdating) {
            $this->resetPage();
        }

        unset($this->masterData);

        $this->successToast(
            $isUpdating
                ? 'Data master berhasil diperbarui.'
                : 'Data master baru berhasil ditambahkan.'
        );

        $this->resetForm();
    }

    public function confirmDelete(int $id): void
    {
        Gate::authorize(Permissions::MANAGE_ATTRIBUTE_MANAGEMENT);

        $this->resetDeleteModal(closeModal: false);

        if ($this->activeTab === self::TAB_ORGANIZATION) {
            $organization = Organization::query()->findOrFail($id);

            $this->deleteId = (int) $organization->id;
            $this->deleteType = self::TAB_ORGANIZATION;
            $this->deleteName = (string) $organization->org_name;
        } else {
            $position = Position::query()->findOrFail($id);

            $this->deleteId = (int) $position->id;
            $this->deleteType = self::TAB_POSITION;
            $this->deleteName = (string) $position->position_name;
        }

        $this->show_delete_modal = true;
    }

    public function deleteMasterData(): void
    {
        Gate::authorize(Permissions::MANAGE_ATTRIBUTE_MANAGEMENT);

        if (
            $this->deleteId === null ||
            ! in_array(
                $this->deleteType,
                [self::TAB_ORGANIZATION, self::TAB_POSITION],
                true
            )
        ) {
            $this->warningToast('Data yang akan dihapus tidak valid.');
            $this->resetDeleteModal();

            return;
        }

        if ($this->isMasterDataInUse($this->deleteType, $this->deleteId)) {
            $label = $this->deleteType === self::TAB_ORGANIZATION
                ? 'organization'
                : 'position';

            $this->warningToast(
                "Data {$label} masih digunakan oleh employee dan tidak dapat dihapus."
            );

            $this->resetDeleteModal();

            return;
        }

        try {
            DB::transaction(function (): void {
                if ($this->deleteType === self::TAB_ORGANIZATION) {
                    Organization::query()
                        ->findOrFail($this->deleteId)
                        ->delete();

                    return;
                }

                Position::query()
                    ->findOrFail($this->deleteId)
                    ->delete();
            });
        } catch (\Throwable $exception) {
            report($exception);

            $this->dangerToast(
                'Data gagal dihapus. Periksa apakah data masih digunakan.'
            );

            return;
        }

        $this->resetPage();
        unset($this->masterData);

        $this->successToast('Data master berhasil dihapus.');
        $this->resetDeleteModal();
    }

    public function resetForm(bool $closeModal = true): void
    {
        $this->editingId = null;
        $this->name = '';

        if ($closeModal) {
            $this->show_form_modal = false;
        }

        $this->resetValidation('name');
    }

    public function resetDeleteModal(bool $closeModal = true): void
    {
        $this->deleteId = null;
        $this->deleteType = '';
        $this->deleteName = '';

        if ($closeModal) {
            $this->show_delete_modal = false;
        }
    }

    #[Computed]
    public function masterData(): LengthAwarePaginator
    {
        $search = trim($this->search);

        if ($this->activeTab === self::TAB_ORGANIZATION) {
            return Organization::query()
                ->select(['id', 'org_name'])
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where('org_name', 'like', "%{$search}%");
                })
                ->orderBy('org_name')
                ->paginate(10);
        }

        return Position::query()
            ->select(['id', 'position_name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('position_name', 'like', "%{$search}%");
            })
            ->orderBy('position_name')
            ->paginate(10);
    }

    private function rules(): array
    {
        $table = $this->activeTab === self::TAB_ORGANIZATION
            ? 'organizations'
            : 'positions';

        $column = $this->activeTab === self::TAB_ORGANIZATION
            ? 'org_name'
            : 'position_name';

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:150',
                Rule::unique($table, $column)->ignore($this->editingId),
            ],
        ];
    }

    private function validationMessages(): array
    {
        $label = $this->activeTab === self::TAB_ORGANIZATION
            ? 'organization'
            : 'position';

        return [
            'name.required' => "Nama {$label} wajib diisi.",
            'name.min' => "Nama {$label} minimal 2 karakter.",
            'name.max' => "Nama {$label} maksimal 150 karakter.",
            'name.unique' => "Nama {$label} sudah tersedia.",
        ];
    }

    private function normalizeName(): void
    {
        $this->name = preg_replace(
            '/\s+/u',
            ' ',
            trim($this->name)
        ) ?? trim($this->name);
    }

    private function isMasterDataInUse(string $type, int $id): bool
    {
        return $type === self::TAB_ORGANIZATION
            ? DB::table('employees')->where('org_id', $id)->exists()
            : DB::table('employees')->where('position_id', $id)->exists();
    }

    private function successToast(
        string $text,
        string $heading = 'Success'
    ): void {
        Flux::toast(
            heading: $heading,
            text: $text,
            variant: 'success',
            duration: 3000,
        );
    }

    private function warningToast(
        string $text,
        string $heading = 'Warning'
    ): void {
        Flux::toast(
            heading: $heading,
            text: $text,
            variant: 'warning',
            duration: 3500,
        );
    }

    private function dangerToast(
        string $text,
        string $heading = 'Failed'
    ): void {
        Flux::toast(
            heading: $heading,
            text: $text,
            variant: 'danger',
            duration: 4000,
        );
    }
};
