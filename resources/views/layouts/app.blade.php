<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="min-h-screen flex flex-col">
        <div class="flex-1">
            {{ $slot }}
            <flux:separator variant="subtle" class="mt-6" />
        </div>

        <footer>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <flux:brand href="#" name="Human Capital Training Management System">
                    {{-- <x-slot name="logo" class="size-6 rounded-full bg-cyan-500 text-white text-xs font-bold">
                        <flux:icon name="rocket-launch" variant="micro" />
                    </x-slot> --}}
                </flux:brand>

                <flux:brand href="#" name="Managed by IT">
                    <x-slot name="logo" class="size-6 rounded-full bg-cyan-500 text-white text-xs font-bold">
                        <flux:icon name="rocket-launch" variant="micro" />
                    </x-slot>
                </flux:brand>
            </div>
        </footer>        
    </flux:main>
</x-layouts::app.sidebar>
