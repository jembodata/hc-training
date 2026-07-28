<div class="pt-[10px] px-4 pb-4 lg:px-8 lg:pb-8 transition-colors duration-300">

    <div class="space-y-6 relative z-10">

        {{-- HEADER SECTION --}}
        <flux:card class="space-y-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">

                <div class="min-w-0">
                    <flux:heading size="xl" class="font-black uppercase tracking-tight">
                        Atribut Management
                    </flux:heading>

                    <flux:text
                        class="mt-1 text-[11px] font-extrabold uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">
                        Kelola Kategori Aktivitas dan Tipe Skill
                    </flux:text>
                </div>

                <div class="flex flex-wrap items-center gap-2 lg:flex-shrink-0">
                    <flux:button wire:click="openCreateModal" variant="primary" icon="plus"
                        class="font-black uppercase text-[10px] tracking-widest">
                        Add New
                    </flux:button>
                </div>

            </div>
        </flux:card>

        {{-- ATTRIBUTE TABLE CARD --}}
        <flux:card class="space-y-4">

            <flux:tab.group model="tab" :value="$tab">

                {{-- FILTER / TAB / SEARCH --}}
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">

                    <div class="flex flex-col sm:flex-row sm:items-center gap-3">

                        <flux:tabs model="tab" :value="$tab" variant="segmented" size="sm">
                            <flux:tab name="activities" icon="clipboard-document-list">
                                Activities
                            </flux:tab>

                            <flux:tab name="skills" icon="academic-cap">
                                Skills
                            </flux:tab>
                        </flux:tabs>

                        <div>
                            {{-- <flux:heading size="lg" class="font-black uppercase tracking-tight">
                                {{ $tab === 'activities' ? 'Activities' : 'Skills' }}
                            </flux:heading> --}}

                            {{-- <flux:text
                                class="mt-1 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                Master data {{ $tab === 'activities' ? 'kategori aktivitas' : 'tipe skill' }}
                            </flux:text> --}}
                        </div>
                    </div>

                    <div class="w-full lg:w-[320px]">
                        <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari data..."
                            icon="magnifying-glass" clearable size="sm" />
                    </div>

                </div>

                {{-- PANEL ACTIVITIES --}}
                <flux:tab.panel name="activities">
                    @if ($tab === 'activities')
                        <flux:table :paginate="$this->items">
                            <flux:table.columns>
                                <flux:table.column class="text-xs font-black uppercase tracking-wider">
                                    No.
                                </flux:table.column>

                                <flux:table.column class="text-xs font-black uppercase tracking-wider">
                                    Nama Activities
                                </flux:table.column>

                                <flux:table.column class="text-xs font-black uppercase tracking-wider">
                                    Aksi
                                </flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @forelse ($this->items as $item)
                                    <flux:table.row :key="'activities-'.$item->id">
                                        <flux:table.cell
                                            class="font-black text-slate-400 dark:text-slate-500 text-xs tabular-nums">
                                            {{ $this->items->firstItem() + $loop->index }}
                                        </flux:table.cell>

                                        <flux:table.cell
                                            class="font-bold text-slate-600 dark:text-slate-400 uppercase text-xs">
                                            {{ $item->name }}
                                        </flux:table.cell>

                                        <flux:table.cell>
                                            <div class="flex items-center gap-1">
                                                <flux:button variant="ghost" size="sm" icon="pencil-square"
                                                    wire:click="edit({{ $item->id }}, 'activities')"
                                                    inset="top bottom" title="Edit Data" />

                                                <flux:button variant="ghost" size="sm" icon="trash"
                                                    wire:click="confirmDelete({{ $item->id }}, 'activities')"
                                                    inset="top bottom"
                                                    class="text-rose-500 hover:text-rose-600 dark:text-rose-400"
                                                    title="Hapus Data" />
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="3"
                                            class="text-center py-16 text-slate-300 dark:text-slate-700 font-black uppercase tracking-widest opacity-40">
                                            Belum Ada Data Activities
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    @endif
                </flux:tab.panel>

                {{-- PANEL SKILLS --}}
                <flux:tab.panel name="skills">
                    @if ($tab === 'skills')
                        <flux:table :paginate="$this->items">
                            <flux:table.columns>
                                <flux:table.column class="text-xs font-black uppercase tracking-wider ">
                                    No.
                                </flux:table.column>

                                <flux:table.column class="text-xs font-black uppercase tracking-wider">
                                    Nama Skills
                                </flux:table.column>

                                <flux:table.column class="text-xs font-black uppercase tracking-wider ">
                                    Aksi
                                </flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @forelse ($this->items as $item)
                                    <flux:table.row :key="'skills-'.$item->id">
                                        <flux:table.cell
                                            class=" font-black text-slate-400 dark:text-slate-500 text-xs tabular-nums">
                                            {{ $this->items->firstItem() + $loop->index }}
                                        </flux:table.cell>

                                        <flux:table.cell
                                            class="font-bold text-slate-600 dark:text-slate-400 uppercase text-xs">
                                            {{ $item->name }}
                                        </flux:table.cell>

                                        <flux:table.cell>
                                            <div class="flex items-center gap-1">
                                                <flux:button variant="ghost" size="sm" icon="pencil-square"
                                                    wire:click="edit({{ $item->id }}, 'skills')" inset="top bottom"
                                                    title="Edit Data" />

                                                <flux:button variant="ghost" size="sm" icon="trash"
                                                    wire:click="confirmDelete({{ $item->id }}, 'skills')"
                                                    inset="top bottom"
                                                    class="text-rose-500 hover:text-rose-600 dark:text-rose-400"
                                                    title="Hapus Data" />
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="3"
                                            class="text-center py-16 text-slate-300 dark:text-slate-700 font-black uppercase tracking-widest opacity-40">
                                            Belum Ada Data Skills
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    @endif
                </flux:tab.panel>

            </flux:tab.group>

        </flux:card>
    </div>

    {{-- FORM INPUT / EDIT MODAL --}}
    <flux:modal wire:model.self="show_form_modal" class="md:w-[32rem] -translate-y-20" :dismissible="false">
        <div class="space-y-6">

            <div>
                <flux:heading size="lg" class="font-black uppercase tracking-tight flex items-center gap-2">
                    <flux:icon.plus class="w-5 h-5 text-blue-600" />
                    {{ $selected_id ? 'Update Attribute' : 'Add New Attribute' }}
                </flux:heading>

                <flux:text
                    class="mt-1 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ $selected_id
                        ? 'Perbarui data ' . ($tab === 'activities' ? 'activity' : 'skill') . ' yang dipilih.'
                        : 'Tambahkan data baru untuk ' . ($tab === 'activities' ? 'activity' : 'skill') . '.' }}
                </flux:text>
            </div>

            <form wire:submit.prevent="save" class="space-y-6">
                <flux:field>
                    <flux:label class="text-[10px] font-black uppercase tracking-widest">
                        Nama {{ $tab === 'activities' ? 'Activity' : 'Skill' }}
                    </flux:label>

                    <flux:input wire:model="new_name" type="text" placeholder="Ketik nama di sini..."
                        class="font-bold uppercase text-xs" />

                    <flux:error name="new_name" />
                </flux:field>

                <div class="flex gap-2 pt-2">
                    <flux:spacer />

                    <flux:button type="button" variant="ghost" wire:click="cancel"
                        class="font-black uppercase text-[10px] tracking-widest">
                        Batal
                    </flux:button>

                    <flux:button type="submit" variant="primary"
                        class="font-black uppercase text-[10px] tracking-widest">
                        Simpan Data
                    </flux:button>
                </div>
            </form>

        </div>
    </flux:modal>

    {{-- DELETE CONFIRMATION MODAL --}}
    <flux:modal wire:model.self="show_delete_modal" class="md:w-[32rem] -translate-y-28" :dismissible="false">
        <div class="space-y-6">

            <div>
                <flux:heading size="lg"
                    class="font-black uppercase tracking-tight text-rose-600 dark:text-rose-400 flex items-center gap-2">
                    <flux:icon.trash class="w-5 h-5 text-rose-500" variant="outline" />
                    Hapus Data {{ $deleting_type === 'activities' ? 'Activity' : 'Skill' }}?
                </flux:heading>

                <flux:text class="mt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                    Anda akan menghapus data
                    <span class="font-black text-slate-800 dark:text-slate-200">
                        "{{ $deleting_name }}"
                    </span>
                    dari sistem.
                    <br>
                    Tindakan ini tidak bisa dibatalkan.
                </flux:text>
            </div>

            <div class="flex gap-2 justify-end border-t border-slate-100 dark:border-slate-800 pt-4">
                <flux:button type="button" variant="ghost" wire:click="cancelDelete"
                    class="font-black uppercase text-[10px] tracking-widest">
                    Batal
                </flux:button>

                <flux:button type="button" variant="danger" wire:click="delete"
                    class="font-black uppercase text-[10px] tracking-widest">
                    Ya, Hapus Data
                </flux:button>
            </div>

        </div>
    </flux:modal>

</div>
