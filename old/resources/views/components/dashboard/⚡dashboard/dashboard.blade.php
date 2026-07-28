<div class="relative w-full">
    <flux:heading size="xl" level="1">Executive Summary</flux:heading>
    <flux:subheading size="lg" class="mb-6"> Human Capital Performance Overview | Periode:
        <span class="font-bold">
            {{ $filter_month === 'all' ? "Tahun $filter_year" : ($months[$filter_month] ?? '') . " $filter_year" }}
        </span>
    </flux:subheading>
    <flux:separator variant="subtle" />

    <div class="mt-6">
        <div class="ml-auto flex flex-row items-center gap-2 flex-shrink-0 mb-6">

            {{-- Filter Department --}}
            <flux:button.group>
                <div class="w-[200px]">
                    <flux:select wire:model.live="filter_org" size="sm" placeholder="Pilih Dept..."
                        wire:key="flux-select-org">
                        <flux:select.option value="all">Semua Dept</flux:select.option>

                        @foreach ($orgs_master as $o)
                            <flux:select.option value="{{ $o->id }}">
                                {{ $o->org_name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                {{-- Filter Month --}}
                <div class="w-[145px]">
                    <flux:select wire:model.live="filter_month" size="sm" placeholder="Pilih Bulan..."
                        wire:key="flux-select-month">
                        <flux:select.option value="all">Semua Bulan</flux:select.option>

                        @foreach ($months as $num => $name)
                            <flux:select.option value="{{ $num }}">
                                {{ $name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                {{-- Filter Year --}}
                <div class="w-[95px]">
                    <flux:select wire:model.live="filter_year" size="sm" placeholder="Tahun..."
                        wire:key="flux-select-year">
                        @for ($y = date('Y'); $y >= 2026; $y--)
                            <flux:select.option value="{{ $y }}">
                                {{ $y }}
                            </flux:select.option>
                        @endfor
                    </flux:select>
                </div>
            </flux:button.group>
        </div>

        {{-- KPI CARDS --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-6"
            wire:key="kpi-grid-{{ $filter_year }}-{{ $filter_month }}-{{ $filter_org }}">

            {{-- KPI 1: Total Jam Training --}}
            <flux:card class="group space-y-2" wire:key="kpi-hours-{{ $total_hours }}">
                <div class="flex justify-between items-start">
                    <div>
                        <flux:text class="text-sm font-medium uppercase tracking-wide">
                            Total Jam Training
                        </flux:text>

                        <flux:text class="mt-1 text-xs font-semibold">
                            Berdasarkan peserta training
                        </flux:text>
                    </div>

                    <flux:icon.clock class="w-5 h-5 text-indigo-600 dark:text-indigo-400" variant="outline" />
                </div>

                <div class="flex items-baseline gap-2" x-data="{
                    current: '0',
                    target: {{ floatval($total_hours) }},
                    init() {
                        let start = null;
                
                        const step = (timestamp) => {
                            if (!start) start = timestamp;
                
                            const progress = Math.min((timestamp - start) / 1000, 1);
                            const ease = 1 - Math.pow(1 - progress, 3);
                
                            this.current = new Intl.NumberFormat('en-US', {
                                minimumFractionDigits: 1,
                                maximumFractionDigits: 1
                            }).format(ease * this.target);
                
                            if (progress < 1) {
                                window.requestAnimationFrame(step);
                            }
                        };
                
                        window.requestAnimationFrame(step);
                    }
                }">
                    <span class="text-4xl lg:text-5xl font-black text-[#2A3C8E] dark:text-[#6D7FF2] tracking-tight leading-none"
                        x-text="current">
                        0
                    </span>

                    <span class="text-slate-400 font-extrabold text-xs uppercase tracking-wider flex-shrink-0">
                        Hrs
                    </span>
                </div>
            </flux:card>

            {{-- KPI 2: Avg Hours / Person --}}
            <flux:card class="group space-y-2" wire:key="kpi-avg-{{ $avg_training_hours }}">
                <div class="flex justify-between items-start">
                    <div>
                        <flux:text class="text-sm font-medium uppercase tracking-wide">
                            Avg. Hours / Person
                        </flux:text>

                        <flux:text class="mt-1 text-xs font-semibold">
                            Total jam / karyawan aktif
                        </flux:text>
                    </div>

                    <flux:icon.academic-cap class="w-5 h-5 text-[#283B91] dark:text-indigo-400" variant="outline" />
                </div>

                <div class="flex items-baseline gap-2" x-data="{
                    current: '0',
                    target: {{ floatval($avg_training_hours) }},
                    init() {
                        let start = null;
                
                        const step = (timestamp) => {
                            if (!start) start = timestamp;
                
                            const progress = Math.min((timestamp - start) / 1000, 1);
                            const ease = 1 - Math.pow(1 - progress, 3);
                
                            this.current = new Intl.NumberFormat('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            }).format(ease * this.target);
                
                            if (progress < 1) {
                                window.requestAnimationFrame(step);
                            }
                        };
                
                        window.requestAnimationFrame(step);
                    }
                }">
                    <span class="text-4xl lg:text-5xl font-black text-[#2A3C8E] dark:text-[#6D7FF2] tracking-tight leading-none"
                        x-text="current">
                        0
                    </span>

                    <span class="text-slate-400 font-extrabold text-xs uppercase tracking-wider flex-shrink-0">
                        Hrs
                    </span>
                </div>
            </flux:card>

            {{-- KPI 3: Karyawan Aktif --}}
            <flux:card class="group space-y-2" wire:key="kpi-emp-{{ $total_employees }}">
                <div class="flex justify-between items-start">
                    <div>
                        <flux:text class="text-sm font-medium uppercase tracking-wide">
                            Karyawan Aktif
                        </flux:text>

                        <flux:text class="mt-1 text-xs font-semibold">
                            Tidak termasuk Harian Lepas
                        </flux:text>
                    </div>

                    <flux:icon.users class="w-5 h-5 text-emerald-500 dark:text-emerald-400" variant="outline" />
                </div>

                <div class="flex items-baseline gap-2" x-data="{
                    current: '0',
                    target: {{ intval($total_employees) }},
                    init() {
                        let start = null;
                
                        const step = (timestamp) => {
                            if (!start) start = timestamp;
                
                            const progress = Math.min((timestamp - start) / 1000, 1);
                            const ease = 1 - Math.pow(1 - progress, 3);
                
                            this.current = new Intl.NumberFormat('en-US', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            }).format(ease * this.target);
                
                            if (progress < 1) {
                                window.requestAnimationFrame(step);
                            }
                        };
                
                        window.requestAnimationFrame(step);
                    }
                }">
                    <span
                        class="text-4xl lg:text-5xl font-black text-emerald-500 dark:text-emerald-400 tracking-tight leading-none"
                        x-text="current">
                        0
                    </span>

                    <span class="text-slate-400 font-extrabold text-xs uppercase tracking-wider flex-shrink-0">
                        Employees
                    </span>
                </div>
            </flux:card>
        </div>

        {{-- Chart & Penetrasi --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- AREA CHART --}}
            <flux:card class="lg:col-span-2 space-y-2">
                <div>
                    <flux:heading size="lg" class="font-black uppercase tracking-tight flex items-center gap-2">
                        <flux:icon.chart-bar class="w-5 h-5 text-indigo-600 dark:text-indigo-400" variant="outline" />
                        <flux:text class="text-sm font-medium uppercase tracking-wide">
                            Trend Total Jam Pelatihan Bulanan ({{ $filter_year }})
                        </flux:text>

                    </flux:heading>
                    <flux:text class="mt-1 text-xs font-semibold">
                        Total jam training per bulan.
                    </flux:text>
                </div>

                <div wire:ignore class="p-0" x-data="{
                    chart: null,
                    observer: null,
                
                    isDark() {
                        return document.documentElement.classList.contains('dark');
                    },
                
                    chartTheme() {
                        const dark = this.isDark();
                
                        return {
                            theme: {
                                mode: dark ? 'dark' : 'light'
                            },
                            chart: {
                                background: 'transparent',
                                foreColor: dark ? '#cbd5e1' : '#334155'
                            },
                            tooltip: {
                                theme: dark ? 'dark' : 'light',
                                y: {
                                    formatter: function(value) {
                                        if (value === null || value === undefined) {
                                            return '-';
                                        }
                
                                        return value.toFixed(1) + ' Hrs';
                                    }
                                }
                            },
                            grid: {
                                borderColor: dark ? '#334155' : '#e2e8f0'
                            },
                            xaxis: {
                                labels: {
                                    style: {
                                        colors: dark ? '#cbd5e1' : '#334155'
                                    }
                                },
                                axisBorder: {
                                    show: false
                                },
                                axisTicks: {
                                    show: false
                                }
                            },
                            yaxis: {
                                labels: {
                                    style: {
                                        colors: dark ? '#cbd5e1' : '#334155'
                                    },
                                    formatter: function(value) {
                                        return value.toFixed(1);
                                    }
                                }
                            }
                        };
                    },
                
                    init() {
                        this.chart = new ApexCharts(this.$el.querySelector('#pureComboChart'), {
                            chart: {
                                type: 'line',
                                height: 320,
                                toolbar: { show: false },
                                zoom: { enabled: false },
                                background: 'transparent',
                                foreColor: this.isDark() ? '#cbd5e1' : '#334155'
                            },
                
                            theme: {
                                mode: this.isDark() ? 'dark' : 'light'
                            },
                
                            series: [{
                                    name: 'Total Jam',
                                    type: 'area',
                                    data: @json($actualHoursData)
                                },
                                {
                                    name: 'Trend Line',
                                    type: 'line',
                                    data: @json($trendHoursData)
                                }
                            ],
                
                            stroke: {
                                curve: 'smooth',
                                width: [4, 2],
                                dashArray: [0, 6]
                            },
                
                            fill: {
                                type: ['gradient', 'solid'],
                                gradient: {
                                    shade: 'light',
                                    type: 'vertical',
                                    shadeIntensity: 0,
                                    inverseColors: false,
                                    opacityFrom: 0.28,
                                    opacityTo: 0.03,
                                    stops: [0, 90, 100],
                                    gradientToColors: ['#283B91']
                                }
                            },
                
                            colors: ['#2A3C8E', '#eab308'],
                
                            markers: {
                                size: 0
                            },
                
                            xaxis: {
                                categories: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                                labels: {
                                    style: {
                                        colors: this.isDark() ? '#cbd5e1' : '#334155'
                                    }
                                },
                                axisBorder: {
                                    show: false
                                },
                                axisTicks: {
                                    show: false
                                }
                            },
                
                            yaxis: {
                                labels: {
                                    style: {
                                        colors: this.isDark() ? '#cbd5e1' : '#334155'
                                    },
                                    formatter: function(value) {
                                        return value.toFixed(1);
                                    }
                                }
                            },
                
                            tooltip: {
                                theme: this.isDark() ? 'dark' : 'light',
                                y: {
                                    formatter: function(value) {
                                        if (value === null || value === undefined) {
                                            return '-';
                                        }
                
                                        return value.toFixed(1) + ' Hrs';
                                    }
                                }
                            },
                
                            grid: {
                                borderColor: this.isDark() ? '#334155' : '#e2e8f0'
                            }
                        });
                
                        this.chart.render();
                
                        this.observer = new MutationObserver(() => {
                            if (!this.chart) return;
                
                            this.chart.updateOptions(this.chartTheme(), false, true);
                        });
                
                        this.observer.observe(document.documentElement, {
                            attributes: true,
                            attributeFilter: ['class']
                        });
                    },
                
                    destroy() {
                        if (this.observer) {
                            this.observer.disconnect();
                        }
                
                        if (this.chart) {
                            this.chart.destroy();
                        }
                    }
                }"
                    @update-chart.window="
                    if (chart) {
                        let data = $event.detail.chartData;

                        chart.updateOptions({
                            series: [
                                {
                                    name: 'Total Jam',
                                    type: 'area',
                                    data: data.actualData
                                },
                                {
                                    name: 'Trend Line',
                                    type: 'line',
                                    data: data.trendData
                                }
                            ]
                        });
                    }
                ">
                    <div id="pureComboChart"></div>
                </div>
            </flux:card>

            {{-- PENETRATION LIST --}}
            <flux:card class="space-y-2 flex flex-col">
                <div>
                    <flux:heading size="lg" class="font-black uppercase tracking-tight flex items-center gap-2">
                        <flux:icon.presentation-chart-line class="w-5 h-5 text-emerald-500 dark:text-emerald-400"
                            variant="outline" />

                        <flux:text class="text-sm font-medium uppercase tracking-wide">
                            Penetrasi Training
                        </flux:text>
                    </flux:heading>

                    <flux:text class="mt-1 mb-4 text-xs font-semibold">
                        Karyawan pernah ikut training / karyawan aktif.
                    </flux:text>
                </div>

                <div class="max-h-[380px] overflow-y-auto pr-2 space-y-4 custom-scrollbar flex-1">
                    @forelse($penetration_list as $pen)
                        @php
                            $pct = $pen->total_emp > 0 ? round(($pen->trained_emp / $pen->total_emp) * 100) : 0;

                            $barColor = match (true) {
                                $pct >= 80 => 'bg-emerald-500',
                                $pct >= 50 => 'bg-amber-500',
                                $pct > 0 => 'bg-rose-500',
                                default => 'bg-zinc-300 dark:bg-zinc-700',
                            };

                            $badgeColor = match (true) {
                                $pct >= 80 => 'emerald',
                                $pct >= 50 => 'amber',
                                $pct > 0 => 'rose',
                                default => 'zinc',
                            };
                        @endphp

                        <div class="group"
                            wire:key="pen-{{ $pen->id }}-{{ $filter_year }}-{{ $filter_month }}-{{ $filter_org }}">
                            <div class="flex justify-between items-center text-sm mb-1.5 font-medium uppercase">
                                <div class="min-w-0">
                                    <flux:text class="truncate block w-40 text-sm font-semibold">
                                        {{ $pen->org_name }}
                                    </flux:text>

                                    <flux:text
                                        class="mt-1 text-xs font-normal normal-case tracking-normal subpixel-antialiased">
                                        {{ $pen->trained_emp }} dari {{ $pen->total_emp }} karyawan
                                    </flux:text>
                                </div>

                                <flux:badge size="sm" :color="$badgeColor">
                                    {{ $pct }}%
                                </flux:badge>
                            </div>

                            <div
                                class="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden shadow-inner">
                                <div class="{{ $barColor }} h-full rounded-full transition-all duration-1000 ease-out"
                                    x-data="{ width: 0 }" x-init="setTimeout(() => { width = {{ $pct }} }, 50)" :style="`width: ${width}%`"></div>
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center">
                            <flux:text
                                class="text-[11px] font-black uppercase tracking-widest text-slate-300 dark:text-slate-700">
                                Tidak ada data penetrasi training.
                            </flux:text>
                        </div>
                    @endforelse
                </div>
            </flux:card>
        </div>

    </div>
</div>
