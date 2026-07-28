<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Employee;
use App\Models\Training;
use App\Models\User;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] 
class extends Component {
    use WithPagination;

    public function with()
    {
        return [
            'deletedEmployees' => Employee::onlyTrashed()->orderBy('deleted_at', 'desc')->paginate(5, ['*'], 'empPage'),
            'deletedTrainings' => Training::onlyTrashed()->orderBy('deleted_at', 'desc')->paginate(5, ['*'], 'trainPage'),
            'deletedUsers'     => User::onlyTrashed()->orderBy('deleted_at', 'desc')->paginate(5, ['*'], 'userPage'),
        ];
    }

    public function restore($type, $id)
    {
        $model = $this->getModel($type);
        if ($model) {
            $data = $model::withTrashed()->find($id);
            if ($data) {
                $data->restore();
                session()->flash('success', 'Data berhasil dikembalikan.');
            }
        }
    }

    public function forceDelete($type, $id)
    {
        $model = $this->getModel($type);
        if ($model) {
            $data = $model::withTrashed()->find($id);
            if ($data) {
                $data->forceDelete();
                session()->flash('error', 'Data dihapus permanen.');
            }
        }
    }

    private function getModel($type)
    {
        return match ((string)$type) {
            'employee' => Employee::class,
            'training' => Training::class,
            'user'     => User::class,
            default    => null
        };
    }
}; ?>