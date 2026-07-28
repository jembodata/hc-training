<div class="min-h-screen bg-white p-4 lg:p-8" x-data="{ tab: 'employee' }">
    <div class="max-w-7xl mx-auto space-y-10">

        {{-- HEADER & TAB SWITCHER --}}
        <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100 flex flex-col md:flex-row justify-between items-center gap-6">
            <div>
                <h2 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Recycle Bin</h2>
                <p class="text-[10px] font-bold text-black-500 uppercase tracking-widest mt-1 italic animate-pulse">
                    ✨ Data tersimpan selamanya sampai dihapus manual
                </p>
            </div>

            <div class="flex bg-slate-100 p-1.5 rounded-[1.5rem] w-full md:w-auto">
                <button @click="tab = 'employee'"
                    :class="tab === 'employee' ? 'bg-white shadow-md text-slate-800' : 'text-slate-400'"
                    class="flex-1 md:flex-none px-6 py-2.5 rounded-xl text-[10px] font-black uppercase transition-all duration-200">
                    Employees
                </button>
                <button @click="tab = 'training'"
                    :class="tab === 'training' ? 'bg-white shadow-md text-slate-800' : 'text-slate-400'"
                    class="flex-1 md:flex-none px-6 py-2.5 rounded-xl text-[10px] font-black uppercase transition-all duration-200">
                    Trainings
                </button>
                <button @click="tab = 'user'"
                    :class="tab === 'user' ? 'bg-white shadow-md text-slate-800' : 'text-slate-400'"
                    class="flex-1 md:flex-none px-6 py-2.5 rounded-xl text-[10px] font-black uppercase transition-all duration-200">
                    Users
                </button>
            </div>
        </div>

        {{-- NOTIFIKASI --}}
        @if (session()->has('success'))
        <div class="p-4 bg-emerald-50 text-emerald-600 rounded-2xl font-black text-[10px] uppercase tracking-widest border border-emerald-100 mb-4 animate-bounce">
            ✨ {{ session('success') }}
        </div>
        @endif
        @if (session()->has('error'))
        <div class="p-4 bg-red-50 text-red-600 rounded-2xl font-black text-[10px] uppercase tracking-widest border border-red-100 mb-4">
            {{ session('error') }}
        </div>
        @endif

        <div class="mt-40 pt-10">
            {{-- TAB EMPLOYEES --}}
            <div x-show="tab === 'employee'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4">
                <div class="pt-12 pb-6 px-6 text-center md:text-left">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.3em] italic">
                        Deleted Employees
                    </h3>
                </div>
                <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
                    <table class="w-full border-collapse">
                        <thead class="bg-slate-100">
                            <tr>
                                {{-- Judul di Kiri (text-left) --}}
                                <th class="px-12 py-5 text-[10px] font-black text-black uppercase tracking-widest text-left border-r border-white">Karyawan</th>
                                {{-- Judul di Tengah (text-center) --}}
                                <th class="px-8 py-5 text-[10px] font-black text-black uppercase tracking-widest text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @forelse($deletedEmployees as $emp)
                            <tr wire:key="emp-{{ $emp->id }}" class="hover:bg-slate-50 transition-all">
                                {{-- Konten di Kiri (text-left) --}}
                                <td class="px-12 py-5 text-xs font-black text-slate-700 uppercase text-left">
                                    {{ $emp->name }}
                                    <div class="text-[9px] font-bold text-slate-400 italic">Dihapus pada: {{ $emp->deleted_at->format('d M Y - H:i') }}</div>
                                </td>
                                <td class="px-8 py-5">
                                    <div class="flex justify-center gap-2">
                                        <button wire:click="restore('employee', {{ $emp->id }})" class="px-4 py-1.5 bg-emerald-50 text-emerald-600 rounded-xl text-[9px] font-black uppercase hover:bg-emerald-100 transition-colors shadow-sm">Restore</button>
                                        <button onclick="confirm('Hapus permanen?') || event.stopImmediatePropagation()" wire:click="forceDelete('employee', {{ $emp->id }})" class="px-4 py-1.5 bg-red-50 text-red-500 rounded-xl text-[9px] font-black uppercase hover:bg-red-100 transition-colors shadow-sm">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="2" class="p-20 text-center text-[10px] font-black text-slate-300 uppercase italic">Trash is empty</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="flex flex-col md:flex-row justify-between items-center px-8 py-6 bg-slate-50/50 border-t border-slate-100 gap-4">
                        <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest italic text-center md:text-left">
                            Showing {{ $deletedEmployees->firstItem() ?? 0 }} to {{ $deletedEmployees->lastItem() ?? 0 }} Results
                        </div>
                        <div class="flex gap-2">
                            <button wire:click="previousPage('empPage')" @disabled($deletedEmployees->onFirstPage()) class="px-4 py-2 bg-white border border-slate-200 text-slate-800 rounded-xl text-[10px] font-black uppercase hover:bg-slate-50 disabled:opacity-50 transition-all">« Previous</button>
                            <button wire:click="nextPage('empPage')" @disabled(!$deletedEmployees->hasMorePages()) class="px-4 py-2 bg-white border border-slate-200 text-slate-800 rounded-xl text-[10px] font-black uppercase hover:bg-slate-50 disabled:opacity-50 transition-all">Next »</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TAB TRAININGS --}}
            <div x-show="tab === 'training'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4">
                <div class="pt-12 pb-6 px-6 text-center md:text-left">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.3em] italic">
                        Deleted Trainings
                    </h3>
                </div>
                <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
                    <table class="w-full border-collapse">
                        <thead class="bg-slate-100">
                            <tr>
                                <th class="px-12 py-5 text-[10px] font-black text-black uppercase tracking-widest text-left border-r border-white">Pelatihan</th>
                                <th class="px-8 py-5 text-[10px] font-black text-black uppercase tracking-widest text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @forelse($deletedTrainings as $train)
                            <tr wire:key="train-{{ $train->id }}" class="hover:bg-slate-50 transition-all">
                                <td class="px-12 py-5 text-xs font-black text-slate-700 uppercase text-left">
                                    {{ $train->title }}
                                    <div class="text-[9px] font-bold text-slate-400 italic">Dihapus pada: {{ $train->deleted_at->format('d M Y') }}</div>
                                </td>
                                <td class="px-8 py-5">
                                    <div class="flex justify-center gap-2">
                                        <button wire:click="restore('training', {{ $train->id }})" class="px-4 py-1.5 bg-emerald-50 text-emerald-600 rounded-xl text-[9px] font-black uppercase hover:bg-emerald-100 transition-colors shadow-sm">Restore</button>
                                        <button onclick="confirm('Hapus permanen?') || event.stopImmediatePropagation()" wire:click="forceDelete('training', {{ $train->id }})" class="px-4 py-1.5 bg-red-50 text-red-500 rounded-xl text-[9px] font-black uppercase hover:bg-red-100 transition-colors shadow-sm">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="2" class="p-20 text-center text-[10px] font-black text-slate-300 uppercase italic">Trash is empty</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="flex flex-col md:flex-row justify-between items-center px-8 py-6 bg-slate-50/50 border-t border-slate-100 gap-4">
                        <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest italic text-center md:text-left">
                            Showing {{ $deletedTrainings->firstItem() ?? 0 }} to {{ $deletedTrainings->lastItem() ?? 0 }} Results
                        </div>
                        <div class="flex gap-2">
                            <button wire:click="previousPage('trainPage')" @disabled($deletedTrainings->onFirstPage()) class="px-4 py-2 bg-white border border-slate-200 text-slate-800 rounded-xl text-[10px] font-black uppercase hover:bg-slate-50 disabled:opacity-50 transition-all">« Previous</button>
                            <button wire:click="nextPage('trainPage')" @disabled(!$deletedTrainings->hasMorePages()) class="px-4 py-2 bg-white border border-slate-200 text-slate-800 rounded-xl text-[10px] font-black uppercase hover:bg-slate-50 disabled:opacity-50 transition-all">Next »</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TAB USERS --}}
            <div x-show="tab === 'user'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4">
                <div class="pt-12 pb-6 px-6 text-center md:text-left">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.3em] italic">
                        Deleted Users
                    </h3>
                </div>
                <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
                    <table class="w-full border-collapse">
                        <thead class="bg-slate-100">
                            <tr>
                                <th class="px-12 py-5 text-[10px] font-black text-black uppercase tracking-widest text-left border-r border-white">Akun</th>
                                <th class="px-8 py-5 text-[10px] font-black text-black uppercase tracking-widest text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @forelse($deletedUsers as $user)
                            <tr wire:key="user-{{ $user->id }}" class="hover:bg-slate-50 transition-all">
                                <td class="px-12 py-5 text-xs font-black text-slate-700 uppercase text-left">
                                    {{ $user->name }}
                                    <div class="text-[9px] font-bold text-slate-400 italic">Dihapus pada: {{ $user->email }}</div>
                                </td>
                                <td class="px-8 py-5">
                                    <div class="flex justify-center gap-2">
                                        <button wire:click="restore('user', {{ $user->id }})" class="px-4 py-1.5 bg-emerald-50 text-emerald-600 rounded-xl text-[9px] font-black uppercase hover:bg-emerald-100 transition-colors shadow-sm">Restore</button>
                                        <button onclick="confirm('Hapus permanen?') || event.stopImmediatePropagation()" wire:click="forceDelete('user', {{ $user->id }})" class="px-4 py-1.5 bg-red-50 text-red-500 rounded-xl text-[9px] font-black uppercase hover:bg-red-100 transition-colors shadow-sm">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="2" class="p-20 text-center text-[10px] font-black text-slate-300 uppercase italic">Trash is empty</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="flex flex-col md:flex-row justify-between items-center px-8 py-6 bg-slate-50/50 border-t border-slate-100 gap-4">
                        <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest italic text-center md:text-left">
                            Showing {{ $deletedUsers->firstItem() ?? 0 }} to {{ $deletedUsers->lastItem() ?? 0 }} Results
                        </div>
                        <div class="flex gap-2">
                            <button wire:click="previousPage('userPage')" @disabled($deletedUsers->onFirstPage()) class="px-4 py-2 bg-white border border-slate-200 text-slate-800 rounded-xl text-[10px] font-black uppercase hover:bg-slate-50 disabled:opacity-50 transition-all">« Previous</button>
                            <button wire:click="nextPage('userPage')" @disabled(!$deletedUsers->hasMorePages()) class="px-4 py-2 bg-white border border-slate-200 text-slate-800 rounded-xl text-[10px] font-black uppercase hover:bg-slate-50 disabled:opacity-50 transition-all">Next »</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>