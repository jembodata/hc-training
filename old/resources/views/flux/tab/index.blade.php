@blaze(fold: true)

@aware([
    'variant' => 'default',
    'size' => 'base',
])

@props([
    'name' => null,
    'icon' => null,
    'selected' => false,
    'action' => false,
    'accent' => false,
    'disabled' => false,
])

@php
    $name = $name ?: \Illuminate\Support\Str::slug(trim(strip_tags($slot)));

    $iconTrailing = $attributes->get('icon:trailing');
    $iconVariant = $attributes->get('icon:variant', 'mini');

    $cleanAttributes = $attributes->filter(function ($value, $key) {
        return ! in_array($key, [
            'icon:trailing',
            'icon:variant',
        ], true);
    });

    $baseSize = match ($size) {
        'sm' => 'h-8 px-2.5 text-xs',
        default => 'h-10 px-3 text-sm',
    };

    $baseClasses = Flux::classes()
        ->add('inline-flex items-center justify-center gap-2')
        ->add('font-semibold whitespace-nowrap outline-none transition-colors')
        ->add('disabled:pointer-events-none disabled:opacity-50')
        ->add($baseSize)
        ->add(match ($variant) {
            'segmented' => 'rounded-md',
            'pills' => 'rounded-lg',
            default => '-mb-px border-b-2 border-transparent px-1',
        });

    $actionClasses = 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white';

    $activeClasses = match (true) {
        $variant === 'segmented' => 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white',
        $variant === 'pills' && $accent => 'bg-accent text-accent-foreground',
        $variant === 'pills' => 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900',
        $accent => 'border-accent text-accent',
        default => 'border-zinc-900 text-zinc-900 dark:border-white dark:text-white',
    };

    $inactiveClasses = match ($variant) {
        'segmented' => 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white',
        'pills' => 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white',
        default => 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-800 dark:text-zinc-400 dark:hover:border-zinc-600 dark:hover:text-zinc-200',
    };
@endphp

<button
    type="button"
    role="tab"

    x-init="if (! @js($action) && ! @js($disabled)) initTab(@js($name), @js($selected))"

    @if (! $action && ! $disabled)
        x-on:click="selectTab(@js($name))"
        x-bind:aria-selected="isTabActive(@js($name)) ? 'true' : 'false'"
        x-bind:data-selected="isTabActive(@js($name)) ? '' : null"
        x-bind:class="isTabActive(@js($name)) ? @js($activeClasses) : @js($inactiveClasses)"
    @endif

    @disabled($disabled)
    {{ $cleanAttributes->class([
        $baseClasses,
        $action ? $actionClasses : '',
    ]) }}
    data-flux-tab
>
    @if ($icon)
        <flux:icon :name="$icon" :variant="$iconVariant" class="size-4" />
    @endif

    <span>{{ $slot }}</span>

    @if ($iconTrailing)
        <flux:icon :name="$iconTrailing" :variant="$iconVariant" class="size-4" />
    @endif
</button>