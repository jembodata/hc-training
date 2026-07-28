@blaze(fold: true)

@props([
    'variant' => 'default',
    'size' => 'base',
    'scrollable' => false,
])

@php
    $wireModel = null;

    foreach ($attributes->getAttributes() as $key => $attributeValue) {
        if (str_starts_with($key, 'wire:model')) {
            $wireModel = $attributeValue;
            break;
        }
    }

    $scrollbar = $attributes->get('scrollable:scrollbar');
    $fade = $attributes->get('scrollable:fade');

    $isScrollable = filter_var($scrollable, FILTER_VALIDATE_BOOLEAN) || $scrollable === true || $scrollable === '';
    $hideScrollbar = $scrollbar === 'hide' || $scrollbar === true || $scrollbar === '';
    $withFade = $fade === true || $fade === '' || $fade === 'true';

    $cleanAttributes = $attributes->filter(function ($value, $key) {
        return ! in_array($key, [
            'scrollable:scrollbar',
            'scrollable:fade',
        ], true) && ! str_starts_with($key, 'wire:model');
    });

    $classes = Flux::classes()
        ->add('relative')
        ->add(match ($variant) {
            'segmented' => 'inline-flex items-center gap-1 rounded-lg bg-zinc-100 p-1 dark:bg-white/10',
            'pills' => 'flex items-center gap-1',
            default => 'flex items-center gap-6 border-b border-zinc-200 dark:border-white/10',
        })
        ->add($isScrollable ? 'overflow-x-auto whitespace-nowrap' : '')
        ->add($isScrollable && $hideScrollbar ? 'flux-tabs-scrollbar-hide' : '')
        ->add($isScrollable && $withFade ? 'flux-tabs-fade' : '');
@endphp

<div
    x-data="typeof isTabActive === 'function'
        ? {}
        : {
            activeTab: null,
            wireModel: null,

            registerTabsModel(model) {
                this.wireModel = model

                if (model && this.$wire && typeof this.$wire.get === 'function') {
                    const value = this.$wire.get(model)

                    if (value) {
                        this.activeTab = value
                    }
                }
            },

            initTab(name, selected = false) {
                if (selected || this.activeTab === null || this.activeTab === '') {
                    this.activeTab = name
                }
            },

            selectTab(name) {
                this.activeTab = name

                if (this.wireModel && this.$wire && typeof this.$wire.set === 'function') {
                    this.$wire.set(this.wireModel, name)
                }
            },

            isTabActive(name) {
                return String(this.activeTab) === String(name)
            },
        }"
    @if ($wireModel)
        x-init="registerTabsModel(@js($wireModel))"
    @endif
    role="tablist"
    {{ $cleanAttributes->class($classes) }}
    data-flux-tabs
    @if($withFade) data-flux-tabs-fade @endif
    @if($hideScrollbar) data-flux-tabs-scrollbar-hide @endif
>
    {{ $slot }}
</div>