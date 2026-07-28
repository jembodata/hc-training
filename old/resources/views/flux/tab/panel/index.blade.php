@props([
    'name' => null,
    'selected' => false,
])

<div
    x-show="isTabActive(@js($name))"
    x-bind:data-selected="isTabActive(@js($name)) ? '' : null"
    data-flux-tab-panel
    {{ $attributes->class('outline-none') }}
>
    {{ $slot }}
</div>