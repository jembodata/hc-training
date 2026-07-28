@props([
    'options' => [],
    'selectedValue' => null,
    'selectedLabel' => null,
    'searchModel' => null,
    'selectMethod' => 'selectOption',
    'clearMethod' => 'clearOption',
    'placeholder' => 'Pilih opsi',
    'searchPlaceholder' => 'Cari...',
    'emptyText' => 'Data tidak ditemukan.',
    'clearLabel' => 'Bersihkan',
    'valueKey' => 'id',
    'labelKey' => 'name',
    'descriptionKey' => null,
    'id' => null,
    'disabled' => false,
    'size' => 'md',
])

@php
    $wireModel = $attributes->wire('model')->value();

    if (!$wireModel) {
        throw new InvalidArgumentException(
            'Komponen server-searchable-select wajib menggunakan wire:model.'
        );
    }

    if (!$searchModel) {
        throw new InvalidArgumentException(
            'Komponen server-searchable-select wajib memiliki prop search-model.'
        );
    }

    $baseId = $id ?: 'server-searchable-select-' . substr(
        sha1($wireModel . '|' . $searchModel),
        0,
        12
    );

    $triggerId = $baseId . '-trigger';
    $panelId = $baseId . '-panel';

    $normalizedOptions = collect($options)
        ->map(function ($option) use (
            $valueKey,
            $labelKey,
            $descriptionKey
        ): ?array {
            $value = data_get($option, $valueKey);

            if (!is_numeric($value) || (int) $value <= 0) {
                return null;
            }

            return [
                'value' => (int) $value,
                'label' => trim(
                    (string) data_get(
                        $option,
                        $labelKey,
                        ''
                    )
                ),
                'description' => $descriptionKey
                    ? trim(
                        (string) data_get(
                            $option,
                            $descriptionKey,
                            ''
                        )
                    )
                    : '',
            ];
        })
        ->filter()
        ->values();

    $hasSelection = filled($selectedLabel);

    $triggerClasses = implode(' ', array_filter([
        'flex w-full items-center justify-between gap-3 rounded-lg border text-left shadow-sm transition duration-150 focus:outline-none focus:ring-2',
        $size === 'sm'
            ? 'min-h-8 px-2.5 py-1.5 text-xs'
            : 'min-h-10 px-3 py-2 text-sm',
        $disabled
            ? 'cursor-not-allowed border-zinc-200 bg-zinc-100 text-zinc-400 opacity-70 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-500'
            : 'border-zinc-200 bg-white hover:border-zinc-300 focus:border-zinc-400 focus:ring-zinc-950/10 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600 dark:focus:ring-white/10',
    ]));

    $labelClasses = $hasSelection
        ? 'min-w-0 flex-1 truncate font-medium text-zinc-900 dark:text-zinc-100'
        : 'min-w-0 flex-1 truncate font-medium text-zinc-400 dark:text-zinc-500';

    $triggerDisabled = $disabled ? 'disabled' : '';
    $clearDisabled = (!$hasSelection || $disabled)
        ? 'disabled'
        : '';
@endphp

<div
    wire:key="{{ $baseId }}"
    x-data="{
        open: false,
        openAbove: false,
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

        openDropdown() {
            if (@js((bool) $disabled)) {
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
                    .querySelector('[data-server-search-input]')
                    ?.focus();
            });
        },

        closeDropdown() {
            const panel = this.$refs.panel;

            this.open = false;

            if (this.closeTimer) {
                window.clearTimeout(this.closeTimer);
            }

            this.closeTimer = window.setTimeout(() => {
                if (
                    panel &&
                    typeof panel.hidePopover === 'function' &&
                    panel.matches(':popover-open')
                ) {
                    panel.hidePopover();
                }

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

            this.openAbove =
                availableBelow < 260 &&
                availableAbove > availableBelow;

            const availableSpace = this.openAbove
                ? availableAbove
                : availableBelow;

            const panelHeight = Math.max(
                minimumHeight,
                Math.min(
                    preferredHeight,
                    availableSpace
                )
            );

            const desiredTop = this.openAbove
                ? rect.top - panelHeight - gap
                : rect.bottom + gap;

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
                window.innerWidth -
                (viewportPadding * 2)
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
    ]) }}
>
    <button
        id="{{ $triggerId }}"
        x-ref="trigger"
        type="button"
        x-on:click="toggleDropdown()"
        x-bind:aria-expanded="open"
        aria-haspopup="listbox"
        aria-controls="{{ $panelId }}"
        aria-disabled="{{ $disabled ? 'true' : 'false' }}"
        {{ $triggerDisabled }}
        class="{{ $triggerClasses }}"
    >
        <span class="{{ $labelClasses }}">
            {{ $hasSelection ? $selectedLabel : $placeholder }}
        </span>

        <span class="flex shrink-0 items-center gap-2">
            <span
                wire:loading
                wire:target="{{ $searchModel }}"
                class="size-4 animate-spin rounded-full border-2 border-zinc-300 border-t-zinc-700 dark:border-zinc-700 dark:border-t-zinc-200"
            ></span>

            <svg
                wire:loading.remove
                wire:target="{{ $searchModel }}"
                x-bind:class="{ 'rotate-180': open }"
                class="{{ $size === 'sm' ? 'h-3.5 w-3.5' : 'h-4 w-4' }} text-zinc-500 transition-transform duration-150"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="m19 9-7 7-7-7"
                />
            </svg>
        </span>
    </button>

    <div
        id="{{ $panelId }}"
        x-ref="panel"
        x-cloak
        x-show="open"
        popover="manual"
        x-bind:style="panelStyle"
        x-bind:class="openAbove ? 'origin-bottom' : 'origin-top'"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
        role="listbox"
        class="m-0 flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white p-3 shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
    >
        <div class="shrink-0">
            <div class="relative">
                <svg
                    class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <circle cx="11" cy="11" r="8" />
                    <path
                        stroke-linecap="round"
                        d="m21 21-4.35-4.35"
                    />
                </svg>

                <input
                    data-server-search-input
                    type="search"
                    wire:model.live.debounce.300ms="{{ $searchModel }}"
                    x-on:keydown.enter.prevent
                    placeholder="{{ $searchPlaceholder }}"
                    autocomplete="off"
                    class="min-h-8 w-full rounded-lg border border-zinc-200 bg-white py-1.5 pl-8 pr-3 text-xs text-zinc-900 outline-none transition focus:border-zinc-400 focus:ring-2 focus:ring-zinc-950/10 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:ring-white/10"
                >
            </div>

            <div class="mt-3 flex items-center justify-between border-b border-zinc-200 pb-2 dark:border-zinc-700">
                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                    Hasil pencarian
                </span>

                <button
                    type="button"
                    wire:click="{{ $clearMethod }}"
                    x-on:click="closeDropdown()"
                    {{ $clearDisabled }}
                    class="rounded-lg px-2 py-1 text-xs font-semibold text-zinc-600 transition hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-zinc-300 dark:hover:bg-zinc-800"
                >
                    {{ $clearLabel }}
                </button>
            </div>
        </div>

        <div
            class="relative mt-2 min-h-0 flex-1 space-y-1 overflow-y-auto overscroll-contain"
            wire:loading.class="opacity-50"
            wire:target="{{ $searchModel }}"
        >
            @if ($normalizedOptions->isEmpty())
                <div class="px-3 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $emptyText }}
                </div>
            @else
                @foreach ($normalizedOptions as $option)
                    @php
                        $isSelected =
                            (string) $selectedValue ===
                            (string) $option['value'];

                        $optionIndicatorClasses = $isSelected
                            ? 'flex size-4 shrink-0 items-center justify-center rounded-full border border-zinc-900 bg-zinc-900 text-white transition dark:border-white dark:bg-white dark:text-zinc-950'
                            : 'flex size-4 shrink-0 items-center justify-center rounded-full border border-zinc-300 bg-white transition dark:border-zinc-600 dark:bg-zinc-950';
                    @endphp

                    <button
                        wire:key="{{ $baseId }}-option-{{ $option['value'] }}"
                        type="button"
                        role="option"
                        aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                        wire:click="{{ $selectMethod }}({{ $option['value'] }})"
                        x-on:click="closeDropdown()"
                        class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left transition duration-150 hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950/10 dark:hover:bg-zinc-800 dark:focus:ring-white/10"
                    >
                        <span class="{{ $optionIndicatorClasses }}">
                            @if ($isSelected)
                                <span class="size-1.5 rounded-full bg-current"></span>
                            @endif
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $option['label'] }}
                            </span>

                            @if ($option['description'] !== '')
                                <span class="mt-0.5 block truncate text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $option['description'] }}
                                </span>
                            @endif
                        </span>
                    </button>
                @endforeach
            @endif

            <div
                wire:loading.flex
                wire:target="{{ $searchModel }}"
                class="absolute inset-0 items-center justify-center bg-white/70 dark:bg-zinc-900/70"
            >
                <span class="size-5 animate-spin rounded-full border-2 border-zinc-300 border-t-zinc-800 dark:border-zinc-700 dark:border-t-white"></span>
            </div>
        </div>
    </div>
</div>