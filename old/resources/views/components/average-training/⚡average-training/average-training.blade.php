<div id="average-training-content" class="w-full space-y-6 antialiased">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <flux:heading size="xl" level="1">
                Average Training Hours
            </flux:heading>

            <flux:subheading class="mt-1">
                Monitoring rata-rata jam pelatihan per karyawan secara akumulatif.
            </flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="w-full sm:w-40">
                <flux:select wire:model.live="year" size="sm" placeholder="Pilih Tahun"
                    aria-label="Pilih tahun laporan" class="antialiased">
                    @foreach ($years as $availableYear)
                        <flux:select.option value="{{ $availableYear }}">
                            Tahun {{ $availableYear }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            @can('export-training-report')
                <flux:button type="button" wire:click="openExportModal" variant="primary" size="sm">
                    Export Report
                </flux:button>
            @endcan
        </div>
    </div>

    <flux:separator variant="subtle" />

    {{-- <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <flux:card class="space-y-2">
            <flux:text class="text-xs font-semibold uppercase">
                Average Dept YTD
            </flux:text>

            <flux:heading size="xl">
                {{ number_format($summary['overall_average'], 2) }}
                <span class="text-sm text-zinc-500">
                    hrs/person
                </span>
            </flux:heading>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:text class="text-xs font-semibold uppercase">
                Training Hours YTD
            </flux:text>

            <flux:heading size="xl">
                {{ number_format($summary['training_hours_ytd'], 1) }}
                <span class="text-sm text-zinc-500">
                    hrs/person
                </span>
            </flux:heading>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:text class="text-xs font-semibold uppercase">
                Active Employees
            </flux:text>

            <flux:heading size="xl">
                {{ number_format($summary['active_employee_count']) }}
            </flux:heading>
        </flux:card>
    </div> --}}

    <flux:card class="space-y-5 overflow-visible">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <flux:heading size="lg">
                    Department Performance Matrix
                </flux:heading>

                <flux:text class="mt-1 text-xs">
                    Nilai menunjukkan rata-rata jam training per employee dan terakumulasi dari Januari.
                </flux:text>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:badge size="sm" color="zinc">
                    {{ number_format($summary['department_count']) }} Departments
                </flux:badge>

                <flux:badge size="sm" color="blue">
                    YTD {{ $year }}
                </flux:badge>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:text class="text-xs">
                Reporting period: {{ $summary['period_label'] }}
            </flux:text>

            <flux:text wire:loading wire:target="year" class="text-xs text-zinc-500">
                Updating data...
            </flux:text>
        </div>

        <flux:separator variant="subtle" />

        <div wire:loading.class="opacity-50" wire:target="year"
            class="overflow-x-auto transition-opacity">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column
                        class="sticky left-0 z-20 min-w-[240px] bg-white text-xs font-semibold dark:bg-zinc-900">
                        Department
                    </flux:table.column>

                    @foreach ($months as $monthNumber => $monthName)
                        <flux:table.column align="center"
                            class="min-w-[76px] text-xs font-semibold
                                {{ $monthNumber === $summary['current_month']
                                    ? 'bg-blue-50/70 text-blue-700 dark:bg-blue-950/25 dark:text-blue-300'
                                    : '' }}">
                            {{ $monthName }}
                        </flux:table.column>
                    @endforeach
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($rows as $row)
                        <flux:table.row wire:key="average-training-{{ $row['organization_id'] }}">
                            <flux:table.cell class="sticky left-0 z-10 bg-white dark:bg-zinc-900">
                                <div class="min-w-[220px]">
                                    <div class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $row['organization_name'] }}
                                    </div>

                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        <flux:badge size="sm" color="blue">
                                            {{ number_format($row['employee_count']) }} employees
                                        </flux:badge>
                                    </div>
                                </div>
                            </flux:table.cell>

                            @foreach ($row['averages'] as $monthNumber => $average)
                                <flux:table.cell align="center"
                                    class="text-xs tabular-nums
                                        {{ $monthNumber === $summary['current_month']
                                            ? 'bg-blue-50/50 dark:bg-blue-950/15'
                                            : '' }}">
                                    @if ($average === null)
                                        <span class="text-zinc-300 dark:text-zinc-700">
                                            —
                                        </span>
                                    @elseif ($average > 0)
                                        <span
                                            class="font-medium
                                                {{ $monthNumber === $summary['current_month']
                                                    ? 'text-blue-700 dark:text-blue-300'
                                                    : 'text-zinc-700 dark:text-zinc-200' }}">
                                            {{ number_format($average, 2) }}
                                        </span>
                                    @else
                                        <span class="font-medium text-zinc-400 dark:text-zinc-600">
                                            0.00
                                        </span>
                                    @endif
                                </flux:table.cell>
                            @endforeach
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="{{ count($months) + 1 }}" class="py-16 text-center">
                                <div class="space-y-1">
                                    <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
                                        Data tidak ditemukan
                                    </div>

                                    <flux:text class="text-xs">
                                        Belum ada data training pada tahun {{ $year }}.
                                    </flux:text>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse

                    @if ($rows !== [])
                        <flux:table.row class="bg-zinc-50/80 dark:bg-zinc-800/60">
                            <flux:table.cell
                                class="sticky left-0 z-10 bg-zinc-50 dark:bg-zinc-800">
                                <div class="min-w-[220px]">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        Overall Average
                                    </div>

                                    <flux:text class="mt-1 text-xs">
                                        Average across departments
                                    </flux:text>
                                </div>
                            </flux:table.cell>

                            @foreach ($overallAverages as $monthNumber => $overallAverage)
                                <flux:table.cell align="center"
                                    class="bg-zinc-50 text-xs font-semibold tabular-nums dark:bg-zinc-800
                                        {{ $monthNumber === $summary['current_month']
                                            ? 'text-blue-700 dark:text-blue-300'
                                            : 'text-zinc-700 dark:text-zinc-200' }}">
                                    @if ($overallAverage === null)
                                        <span class="text-zinc-300 dark:text-zinc-700">
                                            —
                                        </span>
                                    @else
                                        {{ number_format($overallAverage, 2) }}
                                    @endif
                                </flux:table.cell>
                            @endforeach
                        </flux:table.row>
                    @endif
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>

    <flux:modal wire:model.self="show_export_modal" wire:close="closeExportModal"
        class="-translate-y-20 antialiased md:w-[34rem]" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    Export Average Training
                </flux:heading>

                <flux:text class="mt-1 text-xs">
                    Review informasi laporan sebelum mengunduh file.
                </flux:text>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <flux:card class="space-y-2">
                    <flux:text class="text-xs font-semibold uppercase">
                        Reporting Period
                    </flux:text>

                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $summary['period_label'] }}
                    </div>
                </flux:card>

                <flux:card class="space-y-2">
                    <flux:text class="text-xs font-semibold uppercase">
                        Data Coverage
                    </flux:text>

                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ number_format($summary['department_count']) }} Departments
                    </div>
                </flux:card>
            </div>

            <flux:callout icon="document-arrow-down">
                <flux:callout.heading>
                    CSV for Microsoft Excel
                </flux:callout.heading>

                <flux:callout.text>
                    File menggunakan format delimiter titik koma dan angka desimal
                    Indonesia agar langsung terbaca dengan baik di Excel.
                </flux:callout.text>
            </flux:callout>

            <div class="flex gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                <flux:spacer />

                <flux:button type="button" wire:click="closeExportModal" variant="ghost"
                    wire:loading.attr="disabled" wire:target="exportExcel">
                    Batal
                </flux:button>

                <flux:button type="button" wire:click="exportExcel" variant="primary"
                    wire:loading.attr="disabled" wire:target="exportExcel">
                    <span wire:loading.remove wire:target="exportExcel">
                        Download Report
                    </span>

                    <span wire:loading wire:target="exportExcel">
                        Preparing...
                    </span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
