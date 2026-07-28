@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="TRMS" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-xl bg-white shadow-sm border border-zinc-200 overflow-hidden">
            {{-- Langsung panggil logo Jembo di sini --}}
            <img src="{{ asset('Favicon.svg') }}" alt="Logo" class="size-6 object-contain">
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="TRMS" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-10 items-center justify-center rounded-2xl bg-white shadow-md border border-zinc-100 overflow-hidden">
            <img src="{{ asset('Favicon.svg') }}" alt="Logo" class="size-8 object-contain">
        </x-slot>
    </flux:brand>
@endif