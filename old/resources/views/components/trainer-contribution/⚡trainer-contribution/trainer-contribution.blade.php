<div id="trainer-contribution-content" class="w-full space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <flux:heading size="xl" level="1">
                Trainer Contribution Report
            </flux:heading>

            <flux:subheading class="mt-1">
                Total durasi mengajar trainer internal dan eksternal.
            </flux:subheading>
        </div>

        @can(\App\Support\Auth\Permissions::EXPORT_TRAINING_REPORT)
            <flux:button wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv" variant="primary"
                icon="arrow-down-tray">
                <span wire:loading.remove wire:target="exportCsv">
                    Export CSV
                </span>

                <span wire:loading wire:target="exportCsv">
                    Exporting...
                </span>
            </flux:button>
        @endcan
    </div>

    <flux:separator variant="subtle" />

    <flux:card class="space-y-5">
        <div>
            <flux:heading size="lg">
                Contribution
            </flux:heading>

            <flux:text class="mt-1 text-xs">
                Durasi dihitung dari jam mulai dan selesai setiap training.
            </flux:text>
        </div>

        {{-- TABLE FILTERS --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="w-full sm:w-56">
                <flux:field>
                    <flux:label class="font-bold text-xs">
                        Trainer
                    </flux:label>

                    <flux:select wire:model.live="search" size="sm" class="text-xs">
                        <flux:select.option value="">
                            Semua Trainer
                        </flux:select.option>

                        @foreach ($trainerList as $trainer)
                            <flux:select.option value="{{ $trainer->name }}">
                                {{ $trainer->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

            {{-- <div class="w-full sm:w-72">
                <flux:field>
                    <flux:label class="font-bold text-xs">
                        Jabatan
                    </flux:label>

                    <x-ui.searchable-multi-select wire:model.live="position_filter" :options="$positionList"
                        placeholder="Semua Jabatan" search-placeholder="Cari jabatan..."
                        empty-text="Jabatan tidak ditemukan." select-all-label="Pilih Semua" clear-label="Bersihkan"
                        selected-suffix="dipilih" value-key="position_name" label-key="position_name" :max="500"
                        size="sm" id="trainer-contribution-position-filter" />

                    <flux:error name="position_filter.*" />
                </flux:field>
            </div> --}}

            <div class="w-full sm:w-40">
                <flux:field>
                    <flux:label class="font-bold text-xs">
                        Mulai
                    </flux:label>

                    <flux:input type="date" wire:model.live="date_from" size="sm" class="text-xs" />

                    <flux:error name="date_from" />
                </flux:field>
            </div>

            <div class="w-full sm:w-40">
                <flux:field>
                    <flux:label class="font-bold text-xs">
                        Sampai
                    </flux:label>

                    <flux:input type="date" wire:model.live="date_to" size="sm" class="text-xs" />

                    <flux:error name="date_to" />
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

        <flux:table :paginate="$contributions" pagination:scroll-to="#trainer-contribution-content">
            <flux:table.columns>
                <flux:table.column class="text-xs font-black uppercase" align="center">
                    No.
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase">
                    Trainer
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase">
                    Position
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase">
                    Organization
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase">
                    Activities
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase">
                    Skills
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase" align="center">
                    Total
                </flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($contributions as $row)
                    <flux:table.row :key="$row->trainer_token">
                        <flux:table.cell class="text-center text-xs font-semibold tabular-nums">
                            {{ $contributions->firstItem() + $loop->index }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="font-semibold uppercase text-xs">
                                {{ $row->trainer_name ?: 'Tanpa Nama' }}
                            </div>

                            <flux:text class="mt-1 text-xs">
                                {{ $row->nik ?: 'External Trainer' }}
                            </flux:text>
                        </flux:table.cell>

                        <flux:table.cell class="text-xs font-semibold uppercase">
                            {{ $row->position ?: '-' }}
                        </flux:table.cell>

                        <flux:table.cell class="text-xs font-semibold uppercase">
                            {{ $row->organization ?: '-' }}
                        </flux:table.cell>

                        <flux:table.cell class="max-w-64 whitespace-normal text-xs">
                            {{ $row->activity_name ?: '-' }}
                        </flux:table.cell>

                        <flux:table.cell class="max-w-64 whitespace-normal text-xs">
                            {{ $row->skill_name ?: '-' }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex items-center justify-center gap-2">
                                <flux:badge size="sm" color="blue">
                                    {{ round(((int) $row->total_minutes) / 60, 1) }} jam
                                </flux:badge>

                                <flux:button type="button" wire:click="showDetail('{{ $row->trainer_token }}')"
                                    wire:loading.attr="disabled" wire:target="showDetail('{{ $row->trainer_token }}')"
                                    variant="ghost" size="sm" icon="eye" title="Lihat detail" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-16 text-center font-black uppercase opacity-40">
                            Data tidak ditemukan.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model.self="showDetailModal" wire:close="closeDetail" class="md:w-[42rem]" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    Detail Mengajar
                </flux:heading>

                <flux:text class="mt-1 text-xs font-semibold uppercase">
                    {{ $selectedTrainerName }}
                </flux:text>
            </div>

            <div class="max-h-[420px] space-y-3 overflow-y-auto pr-1">
                @forelse ($trainerDetails as $detail)
                    <flux:card wire:key="trainer-detail-{{ $loop->index }}" class="p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="font-semibold uppercase text-xs">
                                    {{ $detail['title'] }}
                                </div>

                                <flux:text class="mt-2 text-xs">
                                    {{ $detail['training_date'] }} ·
                                    {{ $detail['start_time'] }}–{{ $detail['finish_time'] }}
                                </flux:text>
                            </div>

                            <flux:badge size="sm" color="emerald">
                                {{ round(((int) $detail['minutes']) / 60, 1) }} jam
                            </flux:badge>
                        </div>
                    </flux:card>
                @empty
                    <div class="py-10 text-center">
                        <flux:text>
                            Belum ada data mengajar.
                        </flux:text>
                    </div>
                @endforelse
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button type="button" wire:click="closeDetail" variant="ghost">
                    Close
                </flux:button>

                @can(\App\Support\Auth\Permissions::EXPORT_TRAINING_REPORT)
                    <flux:button type="button" wire:click="exportDetailCsv" wire:loading.attr="disabled"
                        wire:target="exportDetailCsv" variant="primary" icon="arrow-down-tray">
                        <span wire:loading.remove wire:target="exportDetailCsv">
                            Export Detail
                        </span>

                        <span wire:loading wire:target="exportDetailCsv">
                            Exporting...
                        </span>
                    </flux:button>
                @endcan
            </div>
        </div>
    </flux:modal>
</div>
