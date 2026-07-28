@props([
    'options' => [],
    'placeholder' => 'Pilih opsi',
    'searchPlaceholder' => 'Cari...',
    'emptyText' => 'Data tidak ditemukan.',
    'selectAllLabel' => 'Pilih semua',
    'clearLabel' => 'Bersihkan',
    'selectedSuffix' => 'dipilih',
    'max' => 100,
    'valueKey' => 'id',
    'labelKey' => 'name',
    'descriptionKey' => null,
    'id' => null,
    'disabled' => false,
    'single' => false,
    'size' => 'md',
])

@php
    $wireModel = $attributes->wire('model')->value();

    if (!$wireModel) {
        throw new InvalidArgumentException('Komponen searchable-multi-select wajib menggunakan wire:model.');
    }

    $baseId = $id ?: 'searchable-multi-select-' . substr(sha1($wireModel), 0, 12);

    $triggerId = $baseId . '-trigger';
    $panelId = $baseId . '-panel';

    $normalizedOptions = collect($options)
        ->map(function ($option) use ($valueKey, $labelKey, $descriptionKey): array {
            return [
                'value' => data_get($option, $valueKey),
                'label' => (string) data_get($option, $labelKey, ''),
                'description' => $descriptionKey ? (string) data_get($option, $descriptionKey, '') : '',
            ];
        })
        ->values();

    /*
     * Alpine menyimpan options saat pertama kali dibuat. Versi ini membuat
     * komponen diinisialisasi ulang apabila daftar option dari Livewire berubah.
     */
    $optionsVersion = substr(sha1($normalizedOptions->toJson()), 0, 12);
@endphp

<div wire:key="{{ $baseId }}-{{ $optionsVersion }}" x-data="{
    open: false,
    openAbove: false,
    search: '',
    selected: $wire.entangle(@js($wireModel)).live,
    options: @js($normalizedOptions),
    max: {{ (int) $max }},
    disabled: @js((bool) $disabled),
    single: @js((bool) $single),
    panelStyle: '',
    outsideHandler: null,
    viewportHandler: null,
    closeTimer: null,

    init() {
        this.outsideHandler = event => {
            if (!this.open) {
                return;
            }

            const trigger = this.$refs.trigger;
            const panel = this.$refs.panel;

            if (
                trigger?.contains(event.target) ||
                panel?.contains(event.target)
            ) {
                return;
            }

            this.closeDropdown();
        };

        this.viewportHandler = () => {
            if (this.open) {
                this.updatePosition();
            }
        };

        document.addEventListener(
            'pointerdown',
            this.outsideHandler,
            true
        );

        window.addEventListener(
            'resize',
            this.viewportHandler
        );

        document.addEventListener(
            'scroll',
            this.viewportHandler,
            true
        );
    },

    destroy() {
        document.removeEventListener(
            'pointerdown',
            this.outsideHandler,
            true
        );

        window.removeEventListener(
            'resize',
            this.viewportHandler
        );

        document.removeEventListener(
            'scroll',
            this.viewportHandler,
            true
        );

        if (this.closeTimer) {
            window.clearTimeout(this.closeTimer);
        }
    },

    selectedValues() {
        return Array.isArray(this.selected) ?
            this.selected :
            [];
    },

    selectedCount() {
        return this.selectedValues().length;
    },

    sameValue(left, right) {
        return String(left) === String(right);
    },

    isSelected(value) {
        return this.selectedValues().some(
            selectedValue => this.sameValue(
                selectedValue,
                value
            )
        );
    },

    selectedOptions() {
        return this.options.filter(option =>
            this.isSelected(option.value)
        );
    },

    triggerLabel() {
        const selectedOptions = this.selectedOptions();

        if (selectedOptions.length === 0) {
            return @js($placeholder);
        }

        if (this.single) {
            return selectedOptions[0]?.label ??
                @js($placeholder);
        }

        return `${selectedOptions.length} ${@js($selectedSuffix)}`;
    },

    toggle(value) {
        if (this.disabled) {
            return;
        }

        const values = this.selectedValues();

        if (this.isSelected(value)) {
            this.selected = values.filter(
                selectedValue => !this.sameValue(
                    selectedValue,
                    value
                )
            );

            return;
        }

        if (this.single) {
            this.selected = [value];
            this.closeDropdown();

            return;
        }

        if (values.length >= this.max) {
            return;
        }

        this.selected = [...values, value];
    },

    clear() {
        if (this.disabled) {
            return;
        }

        this.selected = [];
    },

    selectAll() {
        if (this.disabled || this.single) {
            return;
        }

        this.selected = this.options
            .slice(0, this.max)
            .map(option => option.value);
    },

    get filteredOptions() {
        const needle = this.search
            .trim()
            .toLocaleLowerCase();

        if (needle === '') {
            return this.options;
        }

        return this.options.filter(option =>
            `${option.label} ${option.description}`
            .toLocaleLowerCase()
            .includes(needle)
        );
    },

    openDropdown() {
        if (this.disabled) {
            return;
        }

        const panel = this.$refs.panel;

        if (!panel) {
            return;
        }

        if (this.closeTimer) {
            window.clearTimeout(this.closeTimer);
            this.closeTimer = null;
        }

        this.open = true;

        this.$nextTick(() => {
            if (
                typeof panel.showPopover === 'function' &&
                !panel.matches(':popover-open')
            ) {
                panel.showPopover();
            }

            this.updatePosition();

            panel
                .querySelector('[data-search-input]')
                ?.focus();
        });
    },

    closeDropdown() {
        const panel = this.$refs.panel;

        this.open = false;

        if (this.closeTimer) {
            window.clearTimeout(this.closeTimer);
        }

        /*
         * Beri waktu untuk animasi leave sebelum panel dikeluarkan
         * dari browser top layer.
         */
        this.closeTimer = window.setTimeout(() => {
            if (
                panel &&
                typeof panel.hidePopover === 'function' &&
                panel.matches(':popover-open')
            ) {
                panel.hidePopover();
            }

            this.search = '';
            this.closeTimer = null;
        }, 110);
    },

    toggleDropdown() {
        if (this.open) {
            this.closeDropdown();

            return;
        }

        this.openDropdown();
    },

    updatePosition() {
        const trigger = this.$refs.trigger;

        if (!trigger) {
            return;
        }

        const rect = trigger.getBoundingClientRect();
        const gap = 8;
        const viewportPadding = 12;
        const preferredHeight = 390;
        const minimumHeight = 180;

        const availableBelow = Math.max(
            0,
            window.innerHeight -
            rect.bottom -
            gap -
            viewportPadding
        );

        const availableAbove = Math.max(
            0,
            rect.top -
            gap -
            viewportPadding
        );

        this.openAbove = availableBelow < 260 &&
            availableAbove > availableBelow;

        const availableSpace = this.openAbove ?
            availableAbove :
            availableBelow;

        const panelHeight = Math.max(
            minimumHeight,
            Math.min(preferredHeight, availableSpace)
        );

        const desiredTop = this.openAbove ?
            rect.top - panelHeight - gap :
            rect.bottom + gap;

        const top = Math.max(
            viewportPadding,
            Math.min(
                desiredTop,
                window.innerHeight -
                panelHeight -
                viewportPadding
            )
        );

        const width = Math.min(
            rect.width,
            window.innerWidth - (viewportPadding * 2)
        );

        const left = Math.max(
            viewportPadding,
            Math.min(
                rect.left,
                window.innerWidth -
                width -
                viewportPadding
            )
        );

        this.panelStyle = [
            'position: fixed',
            'inset: auto',
            'margin: 0',
            `left: ${left}px`,
            `top: ${top}px`,
            `width: ${width}px`,
            `height: ${panelHeight}px`,
        ].join(';');
    },
}"
    x-on:keydown.escape.window="closeDropdown()"
    {{ $attributes->except([
        'wire:model',
        'wire:model.live',
        'wire:model.blur',
        'wire:model.change',
        'wire:model.debounce',
    ]) }}>
    <button id="{{ $triggerId }}" x-ref="trigger" type="button" x-on:click="toggleDropdown()"
        x-bind:aria-expanded="open" aria-haspopup="listbox" aria-controls="{{ $panelId }}"
        aria-disabled="{{ $disabled ? 'true' : 'false' }}" @disabled($disabled) @class([
            'flex w-full items-center justify-between gap-3 rounded-lg border text-left shadow-sm transition duration-150 focus:outline-none focus:ring-2',
            'min-h-8 px-2.5 py-1.5 text-xs' => $size === 'sm',
            'min-h-10 px-3 py-2 text-sm' => $size !== 'sm',
            'border-zinc-200 bg-white hover:border-zinc-300 focus:border-zinc-400 focus:ring-zinc-950/10 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600 dark:focus:ring-white/10' => !$disabled,
            'cursor-not-allowed border-zinc-200 bg-zinc-100 text-zinc-400 opacity-70 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-500' => $disabled,
        ])>
        <span class="min-w-0 flex-1 truncate font-medium"
            x-bind:class="selectedCount() === 0 ?
                'text-zinc-400 dark:text-zinc-500' :
                'text-zinc-900 dark:text-zinc-100'"
            x-text="triggerLabel()"></span>

        <span class="flex shrink-0 items-center gap-2">
            <span x-show="! single && selectedCount() > 0" x-transition.opacity.duration.100ms
                @class([
                    'rounded-full bg-zinc-100 py-0.5 font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200',
                    'px-1.5 text-[10px]' => $size === 'sm',
                    'px-2 text-xs' => $size !== 'sm',
                ]) x-text="selectedCount()"></span>

            <flux:icon.chevron-down @class([
                'text-zinc-500 transition-transform duration-150',
                'h-3.5 w-3.5' => $size === 'sm',
                'h-4 w-4' => $size !== 'sm',
            ]) x-bind:class="open ? 'rotate-180' : ''" />
        </span>
    </button>

    <div id="{{ $panelId }}" x-ref="panel" x-cloak x-show="open" popover="manual" x-bind:style="panelStyle"
        x-bind:class="openAbove ? 'origin-bottom' : 'origin-top'" x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 -translate-y-1" role="listbox"
        aria-multiselectable="{{ $single ? 'false' : 'true' }}"
        class="m-0 flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white p-3 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
        <div class="shrink-0">
            <flux:input data-search-input type="search" x-model.debounce.150ms="search" x-on:keydown.enter.prevent
                placeholder="{{ $searchPlaceholder }}" icon="magnifying-glass" size="sm" class="text-xs" />

            <div class="mt-3 flex items-center justify-between border-b border-zinc-200 pb-2 dark:border-zinc-700">
                @unless ($single)
                    <flux:button type="button" size="sm" variant="ghost" x-on:click="selectAll()"
                        x-bind:disabled="disabled || options.length === 0" class="font-semibold text-xs">
                        {{ $selectAllLabel }}
                    </flux:button>
                @endunless

                <flux:button type="button" size="sm" variant="ghost" x-on:click="clear()"
                    x-bind:disabled="disabled || selectedCount() === 0" @class(['font-semibold text-xs', 'ml-auto' => $single])>
                    {{ $clearLabel }}
                </flux:button>
            </div>
        </div>

        <div class="mt-2 min-h-0 flex-1 space-y-1 overflow-y-auto overscroll-contain">
            <template x-for="option in filteredOptions" x-bind:key="String(option.value)">
                <button type="button" role="option" x-on:click="toggle(option.value)"
                    x-bind:aria-selected="isSelected(option.value)"
                    class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left transition duration-150 hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950/10 dark:hover:bg-zinc-800 dark:focus:ring-white/10">
                    <span class="flex size-4 shrink-0 items-center justify-center border transition"
                        x-bind:class="[
                            single ? 'rounded-full' : 'rounded',
                            isSelected(option.value) ?
                            'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-950' :
                            'border-zinc-300 bg-white dark:border-zinc-600 dark:bg-zinc-950'
                        ]">
                        <span x-show="single && isSelected(option.value)"
                            class="size-1.5 rounded-full bg-current"></span>

                        <flux:icon.check x-show="! single && isSelected(option.value)" class="size-3" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-zinc-900 dark:text-zinc-100"
                            x-text="option.label"></span>

                        <span x-show="option.description !== ''"
                            class="mt-0.5 block truncate text-xs text-zinc-500 dark:text-zinc-400"
                            x-text="option.description"></span>
                    </span>
                </button>
            </template>

            <div x-show="filteredOptions.length === 0"
                class="px-3 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                {{ $emptyText }}
            </div>
        </div>
    </div>
</div>
