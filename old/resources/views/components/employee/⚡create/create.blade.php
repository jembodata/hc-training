@php
    use App\Support\Auth\Permissions;

    $canCreateEmployee = auth()->user()->can(
        Permissions::CREATE_EMPLOYEE
    );

    $canUpdateEmployee = auth()->user()->can(
        Permissions::UPDATE_EMPLOYEE
    );

    $canDeleteEmployee = auth()->user()->can(
        Permissions::DELETE_EMPLOYEE
    );

    $canImportEmployee = auth()->user()->can(
        Permissions::IMPORT_EMPLOYEE
    );

    $canExportEmployee = auth()->user()->can(
        Permissions::EXPORT_EMPLOYEE
    );

    $hasEmployeeActions =
        $canUpdateEmployee || $canDeleteEmployee;
@endphp

<div class="relative w-full">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <flux:heading size="xl" level="1">
                Employee Management
            </flux:heading>

            <flux:subheading size="lg" class="mb-6">
                Database Master Karyawan Jembo Cable
            </flux:subheading>
        </div>

        @if (
            $canCreateEmployee
            || $canImportEmployee
            || $canExportEmployee
        )
            <div class="flex flex-wrap items-center gap-2 lg:flex-shrink-0">
                @if ($canCreateEmployee)
                    <flux:button
                        wire:click="openCreateModal"
                        wire:loading.attr="disabled"
                        wire:target="openCreateModal"
                        variant="primary"
                        icon="user-plus"
                        size="sm"
                        class="font-bold text-xs uppercase"
                    >
                        Add Employee
                    </flux:button>
                @endif

                @if ($canImportEmployee || $canExportEmployee)
                    <flux:button.group>
                        @if ($canImportEmployee)
                            <flux:button
                                wire:click="openImportModal"
                                wire:loading.attr="disabled"
                                wire:target="openImportModal"
                                variant="filled"
                                icon="arrow-up-tray"
                                size="sm"
                                class="font-bold text-xs uppercase"
                            >
                                Import
                            </flux:button>
                        @endif

                        @if ($canExportEmployee)
                            <flux:button
                                wire:click="exportExcel"
                                wire:loading.attr="disabled"
                                wire:target="exportExcel"
                                variant="filled"
                                icon="arrow-down-tray"
                                size="sm"
                                class="font-bold text-xs uppercase"
                            >
                                <span
                                    wire:loading.remove
                                    wire:target="exportExcel"
                                >
                                    Export
                                </span>

                                <span
                                    wire:loading
                                    wire:target="exportExcel"
                                >
                                    Exporting...
                                </span>
                            </flux:button>
                        @endif
                    </flux:button.group>
                @endif
            </div>
        @endif
    </div>

    <flux:separator variant="subtle" />

    <flux:card class="mt-6 space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <div class="w-full">
                    <flux:select
                        wire:model.live="filter_type"
                        size="sm"
                        placeholder="Pilih Type Employee..."
                    >
                        <flux:select.option value="all">
                            Semua Type
                        </flux:select.option>

                        <flux:select.option value="Permanent">
                            Permanent
                        </flux:select.option>

                        <flux:select.option value="Contract">
                            Contract
                        </flux:select.option>

                        <flux:select.option value="Probation">
                            Probation
                        </flux:select.option>

                        <flux:select.option value="Harian Lepas">
                            Harian Lepas
                        </flux:select.option>

                        <flux:select.option value="Management Trainee">
                            Management Trainee
                        </flux:select.option>
                    </flux:select>
                </div>

                @if ($filter_type !== 'all' || $search !== '')
                    <flux:button
                        type="button"
                        variant="subtle"
                        size="sm"
                        wire:click="clearFilters"
                        class="font-black text-xs uppercase"
                    >
                        Reset
                    </flux:button>
                @endif
            </div>

            <div class="w-full lg:w-[320px]">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari NIK atau Nama"
                    icon="magnifying-glass"
                    clearable
                    size="sm"
                    class="text-xs"
                />
            </div>
        </div>

        <flux:table :paginate="$this->employees">
            <flux:table.columns>
                <flux:table.column
                    class="text-xs font-black uppercase"
                    align="center"
                >
                    No.
                </flux:table.column>

                <flux:table.column
                    sortable
                    :sorted="$sortBy === 'nik'"
                    :direction="$sortDirection"
                    wire:click="sort('nik')"
                    class="text-xs font-black uppercase"
                >
                    NIK
                </flux:table.column>

                <flux:table.column
                    sortable
                    :sorted="$sortBy === 'name'"
                    :direction="$sortDirection"
                    wire:click="sort('name')"
                    class="text-xs font-black uppercase"
                >
                    Name
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase">
                    Organization / Dept
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase">
                    Position / Jabatan
                </flux:table.column>

                <flux:table.column
                    sortable
                    :sorted="$sortBy === 'status_employee'"
                    :direction="$sortDirection"
                    wire:click="sort('status_employee')"
                    class="text-xs font-black uppercase"
                >
                    Type
                </flux:table.column>

                <flux:table.column
                    sortable
                    :sorted="$sortBy === 'status'"
                    :direction="$sortDirection"
                    wire:click="sort('status')"
                    class="text-xs font-black uppercase"
                >
                    Status
                </flux:table.column>

                @if ($hasEmployeeActions)
                    <flux:table.column
                        class="text-xs font-black uppercase"
                        align="center"
                    >
                        Aksi
                    </flux:table.column>
                @endif
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->employees as $employee)
                    <flux:table.row :key="$employee->id">
                        <flux:table.cell class="text-center font-semibold text-xs tabular-nums">
                            {{ $this->employees->firstItem() + $loop->index }}
                        </flux:table.cell>

                        <flux:table.cell class="font-semibold tracking-tight text-xs">
                            {{ $employee->nik }}
                        </flux:table.cell>

                        <flux:table.cell class="font-semibold uppercase text-xs">
                            {{ $employee->name }}
                        </flux:table.cell>

                        <flux:table.cell class="font-semibold uppercase text-xs">
                            {{ $employee->organization?->org_name ?? '-' }}
                        </flux:table.cell>

                        <flux:table.cell class="font-semibold uppercase text-xs">
                            {{ $employee->position?->position_name ?? '-' }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">
                                {{ $employee->status_employee }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge
                                size="sm"
                                :color="$employee->status === 'Active'
                                    ? 'emerald'
                                    : 'rose'"
                            >
                                {{ $employee->status }}
                            </flux:badge>
                        </flux:table.cell>

                        @if ($hasEmployeeActions)
                            <flux:table.cell>
                                <div class="flex items-center justify-center gap-1">
                                    @if ($canUpdateEmployee)
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="pencil-square"
                                            wire:click="edit({{ $employee->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="edit({{ $employee->id }})"
                                            inset="top bottom"
                                            class="text-slate-500 hover:text-blue-600"
                                            title="Edit Data"
                                        />
                                    @endif

                                    @if ($canDeleteEmployee)
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="trash"
                                            wire:click="confirmDelete({{ $employee->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="confirmDelete({{ $employee->id }})"
                                            inset="top bottom"
                                            class="text-slate-500 hover:text-rose-600"
                                            title="Hapus Data"
                                        />
                                    @endif
                                </div>
                            </flux:table.cell>
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell
                            colspan="{{ $hasEmployeeActions ? 8 : 7 }}"
                            class="py-16 text-center font-black uppercase opacity-40"
                        >
                            Belum Ada Data Master Employee
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal
        wire:model.self="show_form_modal"
        class="md:w-[32rem] -translate-y-20"
        :dismissible="false"
    >
        <div class="space-y-6">
            <div>
                <flux:heading
                    size="lg"
                    class="flex items-center gap-2 font-black uppercase tracking-tight"
                >
                    <flux:icon.user class="h-5 w-5 text-blue-600" />

                    {{ $editingId
                        ? 'Update Informasi Employee'
                        : 'Registrasi Employee Baru' }}
                </flux:heading>

                <flux:text class="mt-1 font-bold uppercase tracking-wider text-slate-400 text-xs dark:text-slate-500">
                    Silakan isi dan sesuaikan data karyawan di bawah ini.
                </flux:text>
            </div>

            <form wire:submit.prevent="save" class="space-y-6">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <flux:field>
                        <flux:label class="font-black uppercase text-xs">
                            NIK Karyawan
                        </flux:label>

                        <flux:input
                            wire:model.live.debounce.150ms="nik"
                            type="text"
                            inputmode="numeric"
                            maxlength="4"
                            placeholder="4 Digit NIK"
                            class="font-bold uppercase text-xs"
                            x-on:input="$el.value = $el.value.replace(/[^0-9]/g, '').slice(0, 4)"
                        />

                        <flux:error name="nik" />
                    </flux:field>

                    <flux:field>
                        <flux:label class="font-black uppercase text-xs">
                            Nama Lengkap
                        </flux:label>

                        <flux:input
                            wire:model="name"
                            type="text"
                            placeholder="Nama Lengkap Karyawan..."
                            class="font-bold uppercase text-xs"
                        />

                        <flux:error name="name" />
                    </flux:field>

                    <flux:field wire:key="modal-select-org">
                        <flux:label class="font-black uppercase text-xs">
                            Organization / Dept
                        </flux:label>

                        <flux:select
                            wire:model="org_id"
                            size="sm"
                            placeholder="Pilih Departemen..."
                            class="font-bold uppercase text-xs"
                        >
                            @foreach ($this->orgs as $organization)
                                <flux:select.option value="{{ $organization->id }}">
                                    {{ $organization->org_name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:error name="org_id" />
                    </flux:field>

                    <flux:field wire:key="modal-select-position">
                        <flux:label class="font-black uppercase text-xs">
                            Position / Jabatan
                        </flux:label>

                        <flux:select
                            wire:model="position_id"
                            size="sm"
                            placeholder="Pilih Jabatan..."
                            class="font-bold uppercase text-xs"
                        >
                            @foreach ($this->positions as $position)
                                <flux:select.option value="{{ $position->id }}">
                                    {{ $position->position_name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:error name="position_id" />
                    </flux:field>

                    <flux:field wire:key="modal-select-employee-status">
                        <flux:label class="font-black uppercase text-xs">
                            Status Karyawan
                        </flux:label>

                        <flux:select
                            wire:model="status_employee"
                            size="sm"
                            placeholder="Pilih Status Hubungan Kerja..."
                            class="uppercase text-xs"
                        >
                            <flux:select.option value="Permanent">
                                Permanent
                            </flux:select.option>

                            <flux:select.option value="Contract">
                                Contract
                            </flux:select.option>

                            <flux:select.option value="Probation">
                                Probation
                            </flux:select.option>

                            <flux:select.option value="Harian Lepas">
                                Harian Lepas
                            </flux:select.option>

                            <flux:select.option value="Management Trainee">
                                Management Trainee
                            </flux:select.option>
                        </flux:select>

                        <flux:error name="status_employee" />
                    </flux:field>

                    <flux:field wire:key="modal-select-system-status">
                        <flux:label class="font-black uppercase text-xs">
                            System Status
                        </flux:label>

                        <flux:select
                            wire:model="status"
                            size="sm"
                            class="font-bold uppercase text-xs"
                        >
                            <flux:select.option value="Active">
                                Active
                            </flux:select.option>

                            <flux:select.option value="Inactive">
                                Inactive
                            </flux:select.option>
                        </flux:select>

                        <flux:error name="status" />
                    </flux:field>
                </div>

                <div class="flex gap-2 pt-2">
                    <flux:spacer />

                    <flux:button
                        type="button"
                        variant="ghost"
                        wire:click="resetForm"
                        class="font-black uppercase text-xs"
                    >
                        Batal
                    </flux:button>

                    <flux:button
                        type="submit"
                        variant="primary"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="font-black uppercase text-xs"
                    >
                        <span wire:loading.remove wire:target="save">
                            Simpan Data
                        </span>

                        <span wire:loading wire:target="save">
                            Menyimpan...
                        </span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    @if ($canDeleteEmployee)
        <flux:modal
            wire:model.self="show_delete_modal"
            class="md:w-[32rem] -translate-y-28"
            :dismissible="false"
        >
            <div class="space-y-6">
                <div>
                    <flux:heading
                        size="lg"
                        class="flex items-center gap-2 font-black uppercase tracking-tight text-rose-600 dark:text-rose-400"
                    >
                        <flux:icon.trash
                            class="h-5 w-5 text-rose-500"
                            variant="outline"
                        />

                        Hapus Data Karyawan?
                    </flux:heading>

                    <flux:text class="mt-3 leading-relaxed text-slate-500 text-xs dark:text-slate-400">
                        Anda akan menghapus data karyawan bernama
                        <span class="font-black text-slate-800 dark:text-slate-200">
                            “{{ $name }}”
                        </span>
                        dari daftar aktif.
                    </flux:text>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <flux:button
                        type="button"
                        variant="ghost"
                        wire:click="resetForm"
                        class="font-black uppercase tracking-widest text-xs"
                    >
                        Batal
                    </flux:button>

                    <flux:button
                        type="button"
                        variant="danger"
                        wire:click="delete"
                        wire:loading.attr="disabled"
                        wire:target="delete"
                        class="font-black uppercase tracking-widest text-xs"
                    >
                        <span wire:loading.remove wire:target="delete">
                            Ya, Hapus Data
                        </span>

                        <span wire:loading wire:target="delete">
                            Menghapus...
                        </span>
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    @if ($canImportEmployee)
        <flux:modal
            wire:model.self="show_import_modal"
            class="md:w-[32rem] -translate-y-28"
            :dismissible="false"
        >
            <div class="space-y-6">
                <div>
                    <flux:heading
                        size="lg"
                        class="flex items-center gap-2 font-black uppercase tracking-tight"
                    >
                        <flux:icon.arrow-up-tray class="h-5 w-5 text-indigo-500" />

                        Import Employee Data
                    </flux:heading>

                    <flux:text class="mt-1 font-bold uppercase leading-relaxed tracking-wider text-slate-400 text-xs dark:text-slate-500">
                        Format berkas: XLSX, XLS, atau CSV.

                        <a
                            href="{{ asset('Template Data Employee.xlsx') }}"
                            download
                            class="font-black text-blue-600 hover:underline dark:text-blue-400"
                        >
                            Download Template Excel
                        </a>
                    </flux:text>
                </div>

                <form wire:submit.prevent="importExcel" class="space-y-6">
                    <flux:field>
                        <flux:label class="font-black uppercase tracking-widest text-xs">
                            Pilih Berkas Excel
                        </flux:label>

                        <flux:input
                            wire:model="excel_file"
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            class="font-bold text-xs"
                        />

                        <div
                            wire:loading
                            wire:target="excel_file"
                            class="mt-2 text-blue-600 text-xs"
                        >
                            Menyiapkan berkas...
                        </div>

                        <flux:error name="excel_file" />
                    </flux:field>

                    <div class="flex gap-2">
                        <flux:spacer />

                        <flux:button
                            type="button"
                            variant="ghost"
                            wire:click="closeImportModal"
                            class="font-black uppercase tracking-widest text-xs"
                        >
                            Tutup
                        </flux:button>

                        <flux:button
                            type="submit"
                            variant="primary"
                            wire:loading.attr="disabled"
                            wire:target="importExcel,excel_file"
                            class="font-black uppercase tracking-widest text-xs"
                        >
                            <span wire:loading.remove wire:target="importExcel">
                                Start Import
                            </span>

                            <span wire:loading wire:target="importExcel">
                                Importing...
                            </span>
                        </flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>
    @endif
</div>