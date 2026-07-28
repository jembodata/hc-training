<div id="training-detail-content" class="w-full space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <flux:heading size="xl" level="1">
                Training detail report
            </flux:heading>

            <flux:subheading class="mt-1">
                Riwayat training, kehadiran, durasi belajar, score, dan biaya karyawan.
            </flux:subheading>
        </div>

        @can(\App\Support\Auth\Permissions::EXPORT_TRAINING_DETAIL)
            <div class="flex flex-wrap items-center gap-2">
                <flux:button wire:click="exportRekap" wire:loading.attr="disabled" wire:target="exportRekap"
                    size="sm">
                    <span wire:loading.remove wire:target="exportRekap">
                        Export rekap jam
                    </span>

                    <span wire:loading wire:target="exportRekap">
                        Menyiapkan...
                    </span>
                </flux:button>

                <flux:button wire:click="exportExcel" wire:loading.attr="disabled" wire:target="exportExcel"
                    variant="primary" size="sm">
                    <span wire:loading.remove wire:target="exportExcel">
                        Export detail
                    </span>

                    <span wire:loading wire:target="exportExcel">
                        Menyiapkan...
                    </span>
                </flux:button>
            </div>
        @endcan
    </div>

    <flux:separator variant="subtle" />

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <flux:card class="space-y-2">
            <flux:text class="text-xs font-semibold uppercase">
                Total kehadiran
            </flux:text>

            <flux:heading size="xl">
                {{ number_format($total_attendances) }}
            </flux:heading>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:text class="text-xs font-semibold uppercase">
                Training
            </flux:text>

            <flux:heading size="xl">
                {{ number_format($total_unique_trainings) }}
            </flux:heading>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:text class="text-xs font-semibold uppercase">
                Durasi belajar
            </flux:text>

            <flux:heading size="xl">
                 {{ $total_hours }}
                 <span class="text-sm text-zinc-500">
                    jam
                </span>
            </flux:heading>
        </flux:card>
    </div>

    {{-- <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <flux:card class="space-y-2">
            <flux:text class="text-sm font-medium">
                Total kehadiran
            </flux:text>

            <div class="text-3xl font-semibold tabular-nums text-zinc-950 dark:text-white">
                {{ number_format($total_attendances) }}
            </div>

            <flux:text class="text-xs">
                Jumlah kehadiran pada hasil filter.
            </flux:text>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:text class="text-sm font-medium">
                Training unik
            </flux:text>

            <div class="text-3xl font-semibold tabular-nums text-zinc-950 dark:text-white">
                {{ number_format($total_unique_trainings) }}
            </div>

            <flux:text class="text-xs">
                Jumlah judul training yang berbeda.
            </flux:text>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:text class="text-sm font-medium">
                Durasi belajar
            </flux:text>

            <div class="flex items-baseline gap-2">
                <div class="text-3xl font-semibold tabular-nums text-zinc-950 dark:text-white">
                    {{ $total_hours }}
                </div>

                <span class="text-sm text-zinc-500">
                    jam
                </span>
            </div>

            <flux:text class="text-xs">
                Akumulasi durasi training pada hasil filter.
            </flux:text>
        </flux:card>
    </div> --}}

    <flux:card class="space-y-5 overflow-visible">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <flux:heading size="lg">
                    Riwayat training
                </flux:heading>

                <flux:text class="mt-1 text-xs">
                    Detail peserta, training, trainer, jadwal, score, dan biaya.
                </flux:text>
            </div>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="w-full sm:w-64">
                <flux:field>
                    <flux:label class="text-xs font-medium">
                        Nama, NIK, atau department
                    </flux:label>

                    <flux:input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari peserta..."
                        icon="magnifying-glass" clearable size="sm" class="text-xs" />
                </flux:field>
            </div>

            <div class="w-full sm:w-72">
                <flux:field>
                    <flux:label class="text-xs font-medium">
                        Judul training
                    </flux:label>

                    <x-ui.searchable-multi-select wire:model="title_filter" :options="$allTitles"
                        placeholder="Semua training" search-placeholder="Cari judul training..."
                        empty-text="Training tidak ditemukan." clear-label="Bersihkan" :single="true"
                        :max="1" value-key="title" label-key="title" size="sm"
                        id="training-detail-title-filter" />
                </flux:field>
            </div>

            <div class="w-full sm:w-64">
                <flux:field>
                    <flux:label class="text-xs font-medium">
                        Trainer
                    </flux:label>

                    <x-ui.searchable-multi-select wire:model="trainer_filter" :options="$trainerList"
                        placeholder="Semua trainer" search-placeholder="Cari trainer..."
                        empty-text="Trainer tidak ditemukan." clear-label="Bersihkan" :single="true"
                        :max="1" value-key="name" label-key="name" size="sm"
                        id="training-detail-trainer-filter" />
                </flux:field>
            </div>

            <div class="w-full sm:w-40">
                <flux:field>
                    <flux:label class="text-xs font-medium">
                        Mulai
                    </flux:label>

                    <flux:input wire:model.live="date_from" type="date" size="sm" class="text-xs" />

                    <flux:error name="date_from" />
                </flux:field>
            </div>

            <div class="w-full sm:w-40">
                <flux:field>
                    <flux:label class="text-xs font-medium">
                        Sampai
                    </flux:label>

                    <flux:input wire:model.live="date_to" type="date" size="sm" class="text-xs" />

                    <flux:error name="date_to" />
                </flux:field>
            </div>

            @if ($search !== '' || $title_filter !== [] || $trainer_filter !== [] || $date_from || $date_to)
                <div class="flex w-full items-end sm:w-auto">
                    <flux:button type="button" wire:click="resetFilters" wire:loading.attr="disabled"
                        wire:target="resetFilters" variant="subtle" size="sm" class="w-full sm:w-auto">
                        Reset filter
                    </flux:button>
                </div>
            @endif
        </div>

        @if ($search !== '' || $title_filter !== [] || $trainer_filter !== [] || $date_from || $date_to)
            <div class="flex flex-wrap items-center gap-2">
                <flux:text class="text-xs">
                    Filter aktif:
                </flux:text>

                @if ($search !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ $search }}
                    </flux:badge>
                @endif

                @if (($title_filter[0] ?? '') !== '')
                    <flux:badge size="sm" color="blue">
                        {{ $title_filter[0] }}
                    </flux:badge>
                @endif

                @if (($trainer_filter[0] ?? '') !== '')
                    <flux:badge size="sm" color="emerald">
                        {{ $trainer_filter[0] }}
                    </flux:badge>
                @endif

                @if ($date_from || $date_to)
                    <flux:badge size="sm" color="amber">
                        {{ $date_from ?: 'Awal' }}
                        –
                        {{ $date_to ?: 'Sekarang' }}
                    </flux:badge>
                @endif
            </div>
        @endif

        <flux:separator variant="subtle" />

        <flux:table :paginate="$rows" pagination:scroll-to="#training-detail-content">
            <flux:table.columns>
                <flux:table.column class="text-xs font-semibold">
                    Peserta
                </flux:table.column>

                <flux:table.column class="text-xs font-semibold">
                    Training
                </flux:table.column>

                <flux:table.column class="text-xs font-semibold">
                    Trainer
                </flux:table.column>

                <flux:table.column class="text-xs font-semibold">
                    Jadwal
                </flux:table.column>

                <flux:table.column class="text-xs font-semibold" align="center">
                    Score
                </flux:table.column>

                <flux:table.column class="text-xs font-semibold" align="right">
                    Biaya dan status
                </flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($rows as $row)
                    <flux:table.row :key="$row->participant_id">
                        <flux:table.cell>
                            <div class="min-w-44">
                                <div class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $row->employee_name ?? '-' }}
                                </div>

                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    <flux:badge size="sm" color="blue">
                                        {{ $row->nik ?? '-' }}
                                    </flux:badge>

                                    <span class="text-xs text-zinc-500">
                                        {{ $row->department ?? 'Tanpa department' }}
                                    </span>
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="min-w-56 whitespace-normal">
                                <div class="text-sm font-medium leading-snug text-zinc-900 dark:text-zinc-100">
                                    {{ $row->title ?? '-' }}
                                </div>

                                <div class="mt-1 text-xs text-zinc-500">
                                    {{ $row->held_by ?? '-' }}
                                </div>

                                @if ($row->activity_name || $row->skill_name)
                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        @if ($row->activity_name)
                                            <flux:badge size="sm" color="blue">
                                                {{ $row->activity_name }}
                                            </flux:badge>
                                        @endif

                                        @if ($row->skill_name)
                                            <flux:badge size="sm" color="indigo">
                                                {{ $row->skill_name }}
                                            </flux:badge>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="min-w-40">
                                @if ($row->trainer_internal_nik)
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $row->trainer_internal_name }}
                                    </div>

                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        <flux:badge size="sm" color="emerald">
                                            Internal
                                        </flux:badge>

                                        <span class="text-xs tabular-nums text-zinc-500">
                                            {{ $row->trainer_internal_nik }}
                                        </span>
                                    </div>
                                @elseif ($row->trainer_external_name)
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $row->trainer_external_name }}
                                    </div>

                                    <div class="mt-2">
                                        <flux:badge size="sm" color="sky">
                                            External
                                        </flux:badge>
                                    </div>
                                @else
                                    <span class="text-xs text-zinc-400">
                                        Belum ada trainer
                                    </span>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="min-w-28">
                                <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
                                    {{ $row->training_date ? \Carbon\Carbon::parse($row->training_date)->format('d M Y') : '-' }}
                                </div>

                                <div class="mt-1 text-xs tabular-nums text-zinc-500">
                                    {{ $row->start_time ? \Carbon\Carbon::parse($row->start_time)->format('H:i') : '--:--' }}
                                    –
                                    {{ $row->finish_time ? \Carbon\Carbon::parse($row->finish_time)->format('H:i') : '--:--' }}
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell align="center">
                            @can(\App\Support\Auth\Permissions::UPDATE_TRAINING_DETAIL_NILAI)
                                <div class="flex flex-col items-center gap-1">
                                    <flux:input type="number" min="0" max="100" step="1"
                                        inputmode="numeric"
                                        wire:model.live.debounce.500ms="scores.{{ $row->participant_id }}"
                                        wire:blur="saveScore({{ $row->participant_id }})"
                                        wire:keydown.enter.prevent="saveScore({{ $row->participant_id }})"
                                        wire:loading.attr="disabled" wire:target="saveScore({{ $row->participant_id }})"
                                        size="sm" class="w-20 text-center text-xs tabular-nums" />

                                    <flux:error name="scores.{{ $row->participant_id }}" />
                                </div>
                            @else
                                <flux:badge size="sm" color="zinc">
                                    {{ $row->score ?? '-' }}
                                </flux:badge>
                            @endcan
                        </flux:table.cell>

                        <flux:table.cell align="right">
                            <div class="min-w-28">
                                <div class="text-sm font-medium tabular-nums text-zinc-900 dark:text-zinc-100">
                                    Rp{{ number_format($row->fee ?? 0, 0, ',', '.') }}
                                </div>

                                <div class="mt-2">
                                    <flux:badge size="sm"
                                        :color="$row->is_certified === 'Yes' ? 'emerald' : 'zinc'">
                                        {{ $row->is_certified === 'Yes' ? 'Certified' : 'No certificate' }}
                                    </flux:badge>
                                </div>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-16 text-center">
                            <div class="space-y-1">
                                <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
                                    Data tidak ditemukan
                                </div>

                                <flux:text class="text-xs">
                                    Ubah pencarian atau filter untuk melihat data lain.
                                </flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
