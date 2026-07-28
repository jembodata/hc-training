<div id="training-penetration-content" class="w-full space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <flux:heading size="xl" level="1">
                Training Penetration Report
            </flux:heading>

            <flux:subheading class="mt-1">
                Persentase jangkauan training untuk employee aktif per department.
            </flux:subheading>
        </div>

        @can(\App\Support\Auth\Permissions::EXPORT_TRAINING_PENETRATION)
            <flux:button wire:click="exportExcel" wire:loading.attr="disabled" wire:target="exportExcel" variant="primary"
                icon="arrow-down-tray">
                <span wire:loading.remove wire:target="exportExcel">
                    Export Excel
                </span>

                <span wire:loading wire:target="exportExcel">
                    Exporting...
                </span>
            </flux:button>
        @endcan
    </div>

    <flux:separator variant="subtle" />

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <flux:card class="space-y-2">
            <flux:text class="text-xs font-semibold uppercase">
                Total Employee
            </flux:text>

            <flux:heading size="xl">
                {{ number_format($sumTotal) }}
            </flux:heading>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:text class="text-xs font-semibold uppercase">
                Sudah Training
            </flux:text>

            <flux:heading size="xl">
                {{ number_format($sumTrained) }}
            </flux:heading>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:text class="text-xs font-semibold uppercase">
                Total Penetration
            </flux:text>

            <flux:heading size="xl">
                {{ number_format($totalPct, 2) }}%
            </flux:heading>
        </flux:card>
    </div>

    <flux:card class="space-y-5 overflow-visible">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <flux:heading size="lg">
                    Penetration by Department
                </flux:heading>

                <flux:text class="mt-1 text-xs">
                    Employee Harian Lepas dan employee nonaktif tidak dihitung.
                </flux:text>
            </div>

            @if ($selectedTrainingTitle !== '')
                <flux:badge size="sm" color="blue">
                    {{ $selectedTrainingTitle }}
                </flux:badge>
            @endif
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="w-full sm:w-56">
                <flux:field>
                    <flux:label class="font-bold text-xs">
                        Department
                    </flux:label>

                    <flux:select wire:model.live="departmentId" size="sm" class="text-xs">
                        <flux:select.option value="">
                            Semua Department
                        </flux:select.option>

                        @foreach ($allOrganizations as $organization)
                            <flux:select.option wire:key="organization-option-{{ $organization->id }}"
                                value="{{ $organization->id }}">
                                {{ $organization->org_name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:error name="departmentId" />
                </flux:field>
            </div>

            <div class="w-full sm:w-40">
                <flux:field>
                    <flux:label class="font-bold text-xs">
                        Mulai
                    </flux:label>

                    <flux:input type="date" wire:model.live="dateFrom" size="sm" class="text-xs" />

                    <flux:error name="dateFrom" />
                </flux:field>
            </div>

            <div class="w-full sm:w-40">
                <flux:field>
                    <flux:label class="font-bold text-xs">
                        Sampai
                    </flux:label>

                    <flux:input type="date" wire:model.live="dateTo" size="sm" class="text-xs" />

                    <flux:error name="dateTo" />
                </flux:field>
            </div>

            <div class="w-full sm:w-80">
                <flux:field>
                    <flux:label class="font-bold text-xs">
                        Judul Training
                    </flux:label>

                    <x-ui.server-searchable-select wire:model="selectedTrainingId" search-model="trainingSearch"
                        :options="$trainingOptions" :selected-value="$selectedTrainingId" :selected-label="$selectedTrainingTitle" placeholder="Semua Training"
                        search-placeholder="Cari judul training..." empty-text="Training tidak ditemukan."
                        clear-label="Bersihkan" select-method="selectTraining" clear-method="clearTraining"
                        value-key="id" label-key="title" description-key="description" size="sm"
                        id="training-penetration-title-filter" />

                    <flux:error name="selectedTrainingId" />
                    <flux:error name="trainingSearch" />
                </flux:field>
            </div>

            <div class="flex w-full items-end sm:w-auto">
                <flux:button type="button" wire:click="resetFilters" wire:loading.attr="disabled"
                    wire:target="resetFilters" variant="subtle" size="sm" icon="arrow-path"
                    class="w-full whitespace-nowrap font-black uppercase text-xs sm:w-auto">
                    Reset
                </flux:button>
            </div>
        </div>

        <flux:separator variant="subtle" />

        <flux:table>
            <flux:table.columns>
                <flux:table.column class="text-xs font-black uppercase">
                    Department
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase" align="center">
                    Employee
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase" align="center">
                    Sudah Training
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase" align="center">
                    Belum Training
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase">
                    Penetration
                </flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($results as $row)
                    <flux:table.row wire:key="penetration-row-{{ $row->org_id }}">
                        <flux:table.cell class="font-semibold uppercase text-xs">
                            {{ $row->org_name }}
                        </flux:table.cell>

                        <flux:table.cell class="text-center text-xs font-semibold tabular-nums">
                            {{ number_format($row->total_emp) }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex items-center justify-center gap-2">
                                <flux:badge size="sm" color="blue">
                                    {{ number_format($row->trained) }}
                                </flux:badge>

                                <flux:button type="button" wire:click="showDetail('trained', {{ $row->org_id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="showDetail('trained', {{ $row->org_id }})" size="sm"
                                    variant="ghost" icon="eye" title="Employee sudah training" />
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex items-center justify-center gap-2">
                                <flux:badge size="sm" color="rose">
                                    {{ number_format($row->untrained) }}
                                </flux:badge>

                                <flux:button type="button" wire:click="showDetail('untrained', {{ $row->org_id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="showDetail('untrained', {{ $row->org_id }})" size="sm"
                                    variant="ghost" icon="eye" title="Employee belum training" />
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex min-w-48 items-center gap-3">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                    <div class="h-full rounded-full bg-blue-600"
                                        style="width: {{ min(max($row->percentage, 0), 100) }}%"></div>
                                </div>

                                <span class="text-xs font-semibold tabular-nums">
                                    {{ number_format($row->percentage, 2) }}%
                                </span>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-16 text-center font-black uppercase opacity-40">
                            Data tidak ditemukan.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model.self="showDetailModal" wire:close="closeDetail" class="md:w-[38rem]"
        :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    Daftar Employee
                </flux:heading>

                <flux:text class="mt-1 text-xs font-semibold uppercase">
                    {{ $selectedType === 'trained' ? 'Sudah Training' : 'Belum Training' }}
                    · {{ $selectedDepartmentName }}
                </flux:text>
            </div>

            <div class="max-h-[420px] space-y-2 overflow-y-auto pr-1">
                @forelse ($employeeList as $employee)
                    <div wire:key="penetration-employee-{{ $employee['id'] }}"
                        class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                        <flux:avatar :name="$employee['name']" size="sm" />

                        <div class="min-w-0">
                            <div class="truncate font-semibold uppercase text-xs">
                                {{ $employee['name'] }}
                            </div>

                            <flux:text class="mt-1 text-xs">
                                NIK: {{ $employee['nik'] }}
                            </flux:text>
                        </div>
                    </div>
                @empty
                    <div class="py-10 text-center">
                        <flux:text>
                            Data employee tidak ditemukan.
                        </flux:text>
                    </div>
                @endforelse
            </div>

            <div class="flex justify-end">
                <flux:button type="button" wire:click="closeDetail" variant="ghost">
                    Close
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
