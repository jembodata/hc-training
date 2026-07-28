<div id="management-data-content" class="relative w-full">
    {{-- PAGE HEADER --}}
    <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <flux:heading size="xl" level="1">
                Management Master Data
            </flux:heading>

            <flux:subheading size="lg" class="mb-6">
                Kelola data organization dan position untuk kebutuhan sistem training.
            </flux:subheading>
        </div>

        @can(\App\Support\Auth\Permissions::CREATE_DEPARTMENT_POSITION_DATA)
            <div class="flex flex-wrap items-center gap-2 lg:flex-shrink-0">
                <flux:button type="button" wire:click="openCreateModal" variant="primary" icon="plus" size="sm"
                    class="font-bold uppercase text-xs">
                    Tambah {{ $activeTab === 'org' ? 'Organization' : 'Position' }}
                </flux:button>
            </div>
        @endcan
    </div>

    <flux:separator variant="subtle" />

    {{-- MAIN CARD --}}
    <flux:card class="mt-6 space-y-6">
        {{-- FILTER & SEARCH --}}
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <flux:tab.group :default="$activeTab" class="space-y-0">
                    <flux:tabs wire:model.live="activeTab" variant="segmented" size="sm">
                        <flux:tab name="org" icon="building-office-2" :selected="$activeTab === 'org'">
                            Organization
                        </flux:tab>

                        <flux:tab name="pos" icon="briefcase" :selected="$activeTab === 'pos'">
                            Position
                        </flux:tab>
                    </flux:tabs>
                </flux:tab.group>

                @if ($search !== '')
                    <flux:button type="button" variant="subtle" size="sm" wire:click="clearSearch"
                        class="font-black uppercase text-[11px]">
                        Reset
                    </flux:button>
                @endif
            </div>

            <div class="w-full lg:w-[320px]">
                <flux:input wire:model.live.debounce.300ms="search"
                    placeholder="{{ $activeTab === 'org' ? 'Cari nama organization' : 'Cari nama position' }}"
                    icon="magnifying-glass" clearable size="sm" class="text-xs" />
            </div>
        </div>

        {{-- TABLE --}}
        <flux:table :paginate="$this->masterData">
            <flux:table.columns>
                <flux:table.column class="text-xs font-black uppercase" align="center">
                    No.
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase">
                    {{ $activeTab === 'org' ? 'Organization / Department' : 'Position / Jabatan' }}
                </flux:table.column>

                <flux:table.column class="text-xs font-black uppercase" align="center">
                    Aksi
                </flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->masterData as $row)
                    <flux:table.row :key="$activeTab . '-' . $row->id">
                        <flux:table.cell class="text-center font-semibold text-xs tabular-nums">
                            {{ $this->masterData->firstItem() + $loop->index }}
                        </flux:table.cell>

                        <flux:table.cell class="font-semibold uppercase text-xs">
                            {{ $activeTab === 'org' ? $row->org_name : $row->position_name }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex items-center justify-center gap-1">
                                @can(\App\Support\Auth\Permissions::UPDATE_DEPARTMENT_POSITION_DATA)
                                    <flux:button type="button" variant="ghost" size="sm" icon="pencil-square"
                                        wire:click="openEditModal({{ $row->id }})" inset="top bottom"
                                        class="text-slate-500 hover:text-blue-600" title="Edit Data" />
                                @endcan

                                @can(\App\Support\Auth\Permissions::DELETE_DEPARTMENT_POSITION_DATA)
                                    <flux:button type="button" variant="ghost" size="sm" icon="trash"
                                        wire:click="confirmDelete({{ $row->id }})" inset="top bottom"
                                        class="text-slate-500 hover:text-rose-600" title="Hapus Data" />
                                @endcan

                                @cannot(\App\Support\Auth\Permissions::UPDATE_DEPARTMENT_POSITION_DATA)
                                    @cannot(\App\Support\Auth\Permissions::DELETE_DEPARTMENT_POSITION_DATA)
                                        <span class="text-xs text-slate-400">
                                            -
                                        </span>
                                    @endcannot
                                @endcannot
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="text-center py-16 font-black uppercase opacity-40">
                            Belum Ada Data Master
                            {{ $activeTab === 'org' ? 'Organization' : 'Position' }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- CREATE / EDIT MODAL --}}
    <flux:modal wire:model.self="show_form_modal" wire:close="resetForm" class="md:w-[34rem] -translate-y-16"
        :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="flex items-center gap-2 font-black uppercase">
                    @if ($editingId)
                        <flux:icon.pencil-square class="h-5 w-5 text-indigo-600" />
                    @else
                        <flux:icon.plus-circle class="h-5 w-5 text-indigo-600" />
                    @endif

                    {{ $editingId ? 'Update' : 'Tambah' }}
                    {{ $activeTab === 'org' ? 'Organization' : 'Position' }}
                </flux:heading>

                <flux:text
                    class="mt-1 text-[11px] font-bold uppercase
                           text-slate-400 dark:text-slate-500">
                    Lengkapi nama data master lalu simpan perubahan.
                </flux:text>
            </div>

            <form wire:submit.prevent="save" class="space-y-6">
                <flux:field>
                    <flux:label class="text-[10px] font-black uppercase">
                        {{ $activeTab === 'org' ? 'Nama Organization' : 'Nama Position' }}
                    </flux:label>

                    <flux:input wire:model="name"
                        placeholder="Masukkan {{ $activeTab === 'org' ? 'nama organization' : 'nama position' }}"
                        maxlength="150" autofocus class="font-bold uppercase text-xs" />

                    <flux:error name="name" />
                </flux:field>

                <div class="flex gap-2 border-t border-slate-100 pt-4
                           dark:border-slate-800">
                    <flux:spacer />

                    <flux:button type="button" wire:click="resetForm" variant="ghost" wire:loading.attr="disabled"
                        wire:target="save" class="font-black uppercase text-xs">
                        Batal
                    </flux:button>

                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save"
                        class="font-black uppercase text-xs">
                        <span wire:loading.remove wire:target="save">
                            {{ $editingId ? 'Simpan Perubahan' : 'Simpan Data' }}
                        </span>

                        <span wire:loading wire:target="save">
                            Saving...
                        </span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- DELETE CONFIRMATION MODAL --}}
    <flux:modal wire:model.self="show_delete_modal" wire:close="resetDeleteModal" class="min-w-[22rem] -translate-y-20"
        :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="text-rose-600 dark:text-rose-400">
                    Hapus {{ $deleteType === 'org' ? 'Organization' : 'Position' }}?
                </flux:heading>

                <flux:text class="mt-2">
                    Anda akan menghapus
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $deleteName ?: 'data master ini' }}
                    </span>.
                    <br>
                    Tindakan ini tidak bisa dibatalkan.
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:button type="button" wire:click="resetDeleteModal" variant="ghost"
                    wire:loading.attr="disabled" wire:target="deleteMasterData">
                    Batal
                </flux:button>

                <flux:button type="button" wire:click="deleteMasterData" variant="danger"
                    wire:loading.attr="disabled" wire:target="deleteMasterData">
                    <span wire:loading.remove wire:target="deleteMasterData">
                        Hapus Data
                    </span>

                    <span wire:loading wire:target="deleteMasterData">
                        Deleting...
                    </span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
