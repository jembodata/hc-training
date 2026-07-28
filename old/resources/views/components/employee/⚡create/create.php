<?php

use App\Imports\EmployeeImport;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Position;
use App\Support\Auth\Permissions;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

new class extends Component
{
    use WithFileUploads;
    use WithPagination;

    private const EMPLOYMENT_TYPES = [
        'Permanent',
        'Contract',
        'Probation',
        'Harian Lepas',
        'Management Trainee',
    ];

    private const SYSTEM_STATUSES = [
        'Active',
        'Inactive',
    ];

    private const SORTABLE_COLUMNS = [
        'nik',
        'name',
        'status_employee',
        'status',
    ];

    public string $search = '';

    public string $filter_type = 'all';

    public bool $show_import_modal = false;

    public bool $show_form_modal = false;

    public bool $show_delete_modal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public mixed $excel_file = null;

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public string $nik = '';

    public string $name = '';

    public ?int $org_id = null;

    public ?int $position_id = null;

    public string $status = 'Active';

    public string $status_employee = '';

    public function mount(): void
    {
        Gate::authorize(Permissions::VIEW_EMPLOYEE);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        unset($this->employees);
    }

    public function updatedFilterType(string $value): void
    {
        $this->filter_type = in_array(
            $value,
            self::EMPLOYMENT_TYPES,
            true
        )
            ? $value
            : 'all';

        $this->resetPage();
        unset($this->employees);
    }

    public function updatedExcelFile(): void
    {
        Gate::authorize(Permissions::IMPORT_EMPLOYEE);

        $this->validateOnly(
            'excel_file',
            $this->importRules(),
            $this->validationMessages()
        );
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filter_type = 'all';

        $this->resetPage();
        unset($this->employees);
    }

    public function sort(string $column): void
    {
        if (! in_array($column, self::SORTABLE_COLUMNS, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection =
                $this->sortDirection === 'asc'
                ? 'desc'
                : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
        unset($this->employees);
    }

    public function openCreateModal(): void
    {
        Gate::authorize(Permissions::CREATE_EMPLOYEE);

        $this->resetForm();

        $this->status = 'Active';
        $this->show_form_modal = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize(Permissions::UPDATE_EMPLOYEE);

        $employee = Employee::query()
            ->findOrFail($id);

        $this->resetForm();

        $this->editingId = (int) $employee->id;
        $this->nik = (string) $employee->nik;
        $this->name = (string) $employee->name;
        $this->org_id = $employee->org_id
            ? (int) $employee->org_id
            : null;
        $this->position_id = $employee->position_id
            ? (int) $employee->position_id
            : null;
        $this->status = (string) $employee->status;
        $this->status_employee =
            (string) $employee->status_employee;

        $this->show_form_modal = true;
    }

    public function save(): void
    {
        $isEdit = $this->editingId !== null;

        Gate::authorize(
            $isEdit
                ? Permissions::UPDATE_EMPLOYEE
                : Permissions::CREATE_EMPLOYEE
        );

        $this->normalizeForm();

        $validated = $this->validate(
            $this->employeeRules(),
            $this->validationMessages(),
            $this->validationAttributes()
        );

        DB::transaction(
            function () use ($validated, $isEdit): void {
                $employee = $isEdit
                    ? Employee::query()
                    ->lockForUpdate()
                    ->findOrFail($this->editingId)
                    : new Employee();

                $employee->fill([
                    'nik' => $validated['nik'],
                    'name' => $validated['name'],
                    'org_id' => $validated['org_id'],
                    'position_id' => $validated['position_id'],
                    'status' => $validated['status'],
                    'status_employee' =>
                    $validated['status_employee'],
                ]);

                $employee->save();
            },
            attempts: 3
        );

        $this->resetForm();
        $this->resetPage();
        unset($this->employees);

        Flux::toast(
            duration: 4000,
            heading: 'Success',
            text: $isEdit
                ? 'Data employee berhasil diperbarui.'
                : 'Data employee baru berhasil disimpan.',
            variant: 'success',
        );
    }

    public function confirmDelete(int $id): void
    {
        Gate::authorize(Permissions::DELETE_EMPLOYEE);

        $employee = Employee::query()
            ->findOrFail($id);

        $this->deletingId = (int) $employee->id;
        $this->name = (string) $employee->name;
        $this->show_delete_modal = true;
    }

    public function delete(): void
    {
        Gate::authorize(Permissions::DELETE_EMPLOYEE);

        if ($this->deletingId === null) {
            return;
        }

        DB::transaction(
            function (): void {
                $employee = Employee::query()
                    ->lockForUpdate()
                    ->findOrFail($this->deletingId);

                $employee->delete();
            },
            attempts: 3
        );

        $this->resetForm();
        $this->resetPage();
        unset($this->employees);

        Flux::toast(
            duration: 4000,
            heading: 'Deleted',
            text: 'Data employee telah dihapus dari daftar aktif.',
            variant: 'success',
        );
    }

    public function openImportModal(): void
    {
        Gate::authorize(Permissions::IMPORT_EMPLOYEE);

        $this->resetValidation('excel_file');
        $this->excel_file = null;
        $this->show_import_modal = true;
    }

    public function closeImportModal(): void
    {
        $this->show_import_modal = false;
        $this->excel_file = null;
        $this->resetValidation('excel_file');
    }

    public function importExcel(): void
    {
        Gate::authorize(Permissions::IMPORT_EMPLOYEE);

        $this->validate(
            $this->importRules(),
            $this->validationMessages(),
            $this->validationAttributes()
        );

        try {
            Excel::import(
                new EmployeeImport(),
                $this->excel_file
            );
        } catch (\Throwable $exception) {
            report($exception);

            Flux::toast(
                duration: 5000,
                heading: 'Import gagal',
                text: 'Berkas tidak dapat diproses. Periksa format dan isi data.',
                variant: 'danger',
            );

            return;
        }

        $this->closeImportModal();
        $this->resetPage();
        unset($this->employees);

        Flux::toast(
            duration: 3000,
            heading: 'Import berhasil',
            text: 'Data employee berhasil diimpor.',
            variant: 'success',
        );
    }

    public function exportExcel()
    {
        Gate::authorize(Permissions::EXPORT_EMPLOYEE);

        $fileName = 'Employee_Report_'
            . now()->format('Ymd_His')
            . '.csv';

        $data = $this->employeeQuery()->get();

        return response()->streamDownload(
            function () use ($data): void {
                echo "\xEF\xBB\xBF";
                echo "sep=;\n";
                echo "NIK;Nama Karyawan;Organization;Position;"
                    . "Status Employee;System Status\n";

                foreach ($data as $employee) {
                    $line = [
                        $employee->nik,
                        $employee->name,
                        $employee->organization?->org_name ?? '-',
                        $employee->position?->position_name ?? '-',
                        $employee->status_employee,
                        $employee->status,
                    ];

                    echo collect($line)
                        ->map(
                            fn(mixed $value): string =>
                            $this->csvCell($value)
                        )
                        ->implode(';')
                        . "\n";
                }
            },
            $fileName,
            [
                'Content-Type' =>
                'text/csv; charset=UTF-8',
            ]
        );
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId',
            'deletingId',
            'nik',
            'name',
            'org_id',
            'position_id',
            'status_employee',
            'show_form_modal',
            'show_delete_modal',
        ]);

        $this->status = 'Active';

        $this->resetValidation();
    }

    #[Computed]
    public function employees()
    {
        return $this->employeeQuery()
            ->paginate(10);
    }

    #[Computed]
    public function orgs()
    {
        return Organization::query()
            ->orderBy('org_name')
            ->get();
    }

    #[Computed]
    public function positions()
    {
        return Position::query()
            ->orderBy('position_name')
            ->get();
    }

    public function render()
    {
        return view(
            'components.employee.⚡create.create'
        );
    }

    private function employeeQuery(): Builder
    {
        $search = trim($this->search);

        $sortBy = in_array(
            $this->sortBy,
            self::SORTABLE_COLUMNS,
            true
        )
            ? $this->sortBy
            : 'name';

        $direction = $this->sortDirection === 'desc'
            ? 'desc'
            : 'asc';

        return Employee::query()
            ->with([
                'organization',
                'position',
            ])
            ->when(
                $search !== '',
                fn(Builder $query) => $query->where(
                    function (Builder $subQuery) use ($search): void {
                        $subQuery
                            ->where(
                                'name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'nik',
                                'like',
                                "%{$search}%"
                            );
                    }
                )
            )
            ->when(
                in_array(
                    $this->filter_type,
                    self::EMPLOYMENT_TYPES,
                    true
                ),
                fn(Builder $query) => $query->where(
                    'status_employee',
                    $this->filter_type
                )
            )
            ->orderBy($sortBy, $direction)
            ->orderBy('id');
    }

    private function employeeRules(): array
    {
        return [
            'nik' => [
                'required',
                'digits:4',
                Rule::unique('employees', 'nik')
                    ->ignore($this->editingId),
            ],

            'name' => [
                'required',
                'string',
                'min:3',
                'max:150',
            ],

            'org_id' => [
                'required',
                'integer',
                Rule::exists('organizations', 'id'),
            ],

            'position_id' => [
                'required',
                'integer',
                Rule::exists('positions', 'id'),
            ],

            'status' => [
                'required',
                Rule::in(self::SYSTEM_STATUSES),
            ],

            'status_employee' => [
                'required',
                Rule::in(self::EMPLOYMENT_TYPES),
            ],
        ];
    }

    private function importRules(): array
    {
        return [
            'excel_file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:5120',
            ],
        ];
    }

    private function validationMessages(): array
    {
        return [
            'nik.required' => 'NIK wajib diisi.',
            'nik.digits' => 'NIK harus terdiri dari 4 digit.',
            'nik.unique' => 'NIK sudah digunakan.',
            'name.required' => 'Nama employee wajib diisi.',
            'org_id.required' => 'Organization wajib dipilih.',
            'position_id.required' => 'Position wajib dipilih.',
            'status_employee.required' =>
            'Status employee wajib dipilih.',
            'excel_file.required' =>
            'Berkas Excel wajib dipilih.',
            'excel_file.mimes' =>
            'Berkas harus berformat XLSX, XLS, atau CSV.',
            'excel_file.max' =>
            'Ukuran berkas maksimal 5 MB.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'nik' => 'NIK',
            'name' => 'Nama Lengkap',
            'org_id' => 'Departemen/Organisasi',
            'position_id' => 'Jabatan/Position',
            'status_employee' => 'Status Karyawan',
            'excel_file' => 'Berkas Excel',
        ];
    }

    private function normalizeForm(): void
    {
        $this->nik = substr(
            preg_replace('/\D/', '', $this->nik) ?? '',
            0,
            4
        );

        $this->name = mb_strtoupper(
            trim($this->name)
        );
    }

    private function csvCell(mixed $value): string
    {
        $value = (string) $value;

        /*
         * Mencegah CSV/Formula Injection ketika file dibuka di spreadsheet.
         */
        if (preg_match('/^[\s]*[=+\-@]/u', $value) === 1) {
            $value = "'" . $value;
        }

        return '"'
            . str_replace('"', '""', $value)
            . '"';
    }
};
