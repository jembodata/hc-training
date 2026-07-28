<?php

use Livewire\Component;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Illuminate\Validation\Rule;
use Flux\Flux;
use App\Models\TrainingAttributes;

new class extends Component
{
    use WithPagination;

    #[Url]
    public $tab = 'activities';

    public $search = '';

    public $show_form_modal = false;
    public $show_delete_modal = false;

    public $selected_id = null;
    public $new_name = '';

    public $deleting_id = null;
    public $deleting_type = null;
    public $deleting_name = '';

    public function mount()
    {
        if (! in_array($this->tab, ['activities', 'skills'], true)) {
            $this->tab = 'activities';
        }
    }

    private function currentType(): string
    {
        return $this->tab === 'skills' ? 'skill' : 'activity';
    }

    private function typeFromTab(string $tab): string
    {
        return $tab === 'skills' ? 'skill' : 'activity';
    }

    private function normalizeName(string $name): string
    {
        return strtoupper(trim($name));
    }

    #[Computed]
    public function items()
    {
        return TrainingAttributes::query()
            ->where('type', $this->currentType())
            ->when($this->search !== '', function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->orderBy('id', 'asc')
            ->paginate(10);
    }

    public function updatedSearch()
    {
        $this->resetPage();

        unset($this->items);
    }

    public function updatedTab($value)
    {
        if (! in_array($value, ['activities', 'skills'], true)) {
            $this->tab = 'activities';
        }

        $this->reset([
            'search',
            'selected_id',
            'new_name',
            'show_form_modal',
            'show_delete_modal',
            'deleting_id',
            'deleting_type',
            'deleting_name',
        ]);

        $this->resetPage();

        unset($this->items);
    }

    public function resetForm()
    {
        $this->reset([
            'selected_id',
            'new_name',
            'show_form_modal',
        ]);

        $this->resetValidation();
    }

    public function openCreateModal()
    {
        $this->resetForm();

        $this->show_form_modal = true;
    }

    public function save()
    {
        $type = $this->currentType();

        $this->new_name = $this->normalizeName($this->new_name);

        $this->validate([
            'new_name' => [
                'required',
                'min:2',
                'string',
                Rule::unique('training_attributes', 'name')
                    ->where('type', $type)
                    ->ignore($this->selected_id),
            ],
        ], [], [
            'new_name' => 'Nama ' . ($this->tab === 'activities' ? 'Activity' : 'Skill'),
        ]);

        TrainingAttributes::updateOrCreate(
            ['id' => $this->selected_id],
            [
                'type' => $type,
                'name' => $this->new_name,
                'is_active' => true,
            ]
        );

        $message = $this->selected_id
            ? 'Atribut berhasil diperbarui.'
            : 'Atribut baru berhasil ditambahkan.';

        $this->resetForm();
        $this->resetPage();

        unset($this->items);

        Flux::toast(
            duration: 2000,
            heading: 'Success',
            text: $message,
            variant: 'success',
        );
    }

    public function edit($id, $tab)
    {
        $this->resetValidation();

        if (! in_array($tab, ['activities', 'skills'], true)) {
            return;
        }

        $type = $this->typeFromTab($tab);

        $attribute = TrainingAttributes::query()
            ->where('id', $id)
            ->where('type', $type)
            ->first();

        if (! $attribute) {
            Flux::toast(
                duration: 2000,
                heading: 'Data tidak ditemukan',
                text: 'Data yang dipilih tidak tersedia.',
                variant: 'danger',
            );

            return;
        }

        $this->tab = $tab;
        $this->selected_id = $attribute->id;
        $this->new_name = $attribute->name;
        $this->show_form_modal = true;
    }

    public function confirmDelete($id, $tab)
    {
        if (! in_array($tab, ['activities', 'skills'], true)) {
            return;
        }

        $type = $this->typeFromTab($tab);

        $attribute = TrainingAttributes::query()
            ->where('id', $id)
            ->where('type', $type)
            ->first();

        if (! $attribute) {
            Flux::toast(
                duration: 2000,
                heading: 'Data tidak ditemukan',
                text: 'Data yang dipilih tidak tersedia.',
                variant: 'danger',
            );

            return;
        }

        $this->deleting_id = $attribute->id;
        $this->deleting_type = $tab;
        $this->deleting_name = $attribute->name;
        $this->show_delete_modal = true;
    }

    public function delete()
    {
        if (! $this->deleting_id) {
            return;
        }

        $attribute = TrainingAttributes::find($this->deleting_id);

        if (! $attribute) {
            $this->cancelDelete();

            Flux::toast(
                duration: 2000,
                heading: 'Data tidak ditemukan',
                text: 'Data yang dipilih sudah tidak tersedia.',
                variant: 'danger',
            );

            return;
        }

        $attribute->delete();

        $this->cancelDelete();
        $this->resetPage();

        unset($this->items);

        Flux::toast(
            duration: 2000,
            heading: 'Deleted',
            text: 'Data berhasil dihapus dari database.',
            variant: 'success',
        );
    }

    public function cancelDelete()
    {
        $this->reset([
            'deleting_id',
            'deleting_type',
            'deleting_name',
            'show_delete_modal',
        ]);
    }

    public function cancel()
    {
        $this->resetForm();
    }
};