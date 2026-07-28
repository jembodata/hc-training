<div id="user-management-content" class="relative w-full">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <flux:heading size="xl" level="1">
                User Management
            </flux:heading>

            <flux:subheading size="lg" class="mb-6">
                Kelola user, role, dan permission akses internal HC.
            </flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2 lg:flex-shrink-0">
            @if ($activeTab === 'users')
                @can(\App\Support\Auth\Permissions::MANAGE_USERS)
                    <flux:button
                        wire:click="create"
                        variant="primary"
                        icon="user-plus"
                        size="sm"
                        class="font-bold text-xs uppercase"
                    >
                        User Baru
                    </flux:button>
                @endcan
            @endif

            @if ($activeTab === 'roles')
                @can(\App\Support\Auth\Permissions::MANAGE_ROLES)
                    <flux:button
                        wire:click="createRole"
                        variant="primary"
                        icon="shield-check"
                        size="sm"
                        class="font-bold text-xs uppercase"
                    >
                        Role Baru
                    </flux:button>
                @endcan
            @endif
        </div>
    </div>

    <flux:separator variant="subtle" />

    <flux:card class="mt-6 space-y-6">
        <flux:tab.group>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <flux:tabs
                    wire:model="activeTab"
                    variant="segmented"
                    size="sm"
                    scrollable
                >
                    @can(\App\Support\Auth\Permissions::MANAGE_USERS)
                        <flux:tab name="users" icon="users">
                            Users
                        </flux:tab>
                    @endcan

                    @can(\App\Support\Auth\Permissions::MANAGE_ROLES)
                        <flux:tab name="roles" icon="shield-check">
                            Roles & Permissions
                        </flux:tab>
                    @endcan
                </flux:tabs>

                <div class="flex w-full flex-col gap-2 sm:flex-row lg:w-auto">
                    @if ($activeTab === 'users')
                        <div class="w-full sm:w-28">
                            <flux:select
                                wire:model.live="perPage"
                                size="sm"
                                class="text-xs"
                            >
                                @foreach ($perPageOptions as $option)
                                    <flux:select.option value="{{ $option }}">
                                        {{ $option }} / page
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    @endif

                    <div class="w-full lg:w-[320px]">
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            placeholder="{{ $activeTab === 'roles'
                                ? 'Cari role atau permission'
                                : 'Cari user, email, atau role' }}"
                            icon="magnifying-glass"
                            clearable
                            size="sm"
                            class="text-xs"
                        />
                    </div>
                </div>
            </div>

            @can(\App\Support\Auth\Permissions::MANAGE_USERS)
                <flux:tab.panel name="users">
                    <div class="space-y-4">
                        <div>
                            <flux:heading size="lg">
                                Users
                            </flux:heading>

                            <flux:text class="mt-1 text-xs">
                                Daftar akun dan role yang terpasang pada user.
                            </flux:text>
                        </div>

                        <flux:table
                            :paginate="$users"
                            pagination:scroll-to="#user-management-content"
                        >
                            <flux:table.columns>
                                <flux:table.column
                                    class="text-xs font-black uppercase"
                                    align="center"
                                >
                                    No.
                                </flux:table.column>

                                <flux:table.column class="text-xs font-black uppercase">
                                    Nama User
                                </flux:table.column>

                                <flux:table.column class="text-xs font-black uppercase">
                                    Email Akun
                                </flux:table.column>

                                <flux:table.column class="text-xs font-black uppercase">
                                    Role
                                </flux:table.column>

                                <flux:table.column
                                    class="text-xs font-black uppercase"
                                    align="center"
                                >
                                    Aksi
                                </flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @forelse ($users as $user)
                                    @php
                                        $targetIsSuperAdmin = $user->roles
                                            ->contains(
                                                'name',
                                                'super-admin'
                                            );

                                        $mayEditTarget =
                                            ! $targetIsSuperAdmin
                                            || $isSuperAdmin;
                                    @endphp

                                    <flux:table.row :key="$user->id">
                                        <flux:table.cell
                                            class="text-center font-semibold text-xs tabular-nums"
                                        >
                                            {{ $users->firstItem() + $loop->index }}
                                        </flux:table.cell>

                                        <flux:table.cell class="font-semibold uppercase text-xs">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span>
                                                    {{ $user->name }}
                                                </span>

                                                @if ($user->id === auth()->id())
                                                    <flux:badge size="sm" color="blue">
                                                        Current
                                                    </flux:badge>
                                                @endif
                                            </div>
                                        </flux:table.cell>

                                        <flux:table.cell class="font-semibold text-xs">
                                            {{ $user->email }}
                                        </flux:table.cell>

                                        <flux:table.cell>
                                            <div class="flex flex-wrap gap-1.5">
                                                @forelse ($user->roles as $role)
                                                    <flux:badge
                                                        size="sm"
                                                        color="{{ $role->name === 'super-admin'
                                                            ? 'emerald'
                                                            : 'blue' }}"
                                                    >
                                                        {{ $role->name }}
                                                    </flux:badge>
                                                @empty
                                                    <flux:badge size="sm" color="zinc">
                                                        No Role
                                                    </flux:badge>
                                                @endforelse
                                            </div>
                                        </flux:table.cell>

                                        <flux:table.cell>
                                            <div class="flex items-center justify-center gap-1">
                                                @if ($mayEditTarget)
                                                    <flux:button
                                                        variant="ghost"
                                                        size="sm"
                                                        icon="pencil-square"
                                                        wire:click="edit({{ $user->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="edit({{ $user->id }})"
                                                        inset="top bottom"
                                                        class="text-slate-500 hover:text-blue-600"
                                                        title="Edit User"
                                                    />
                                                @else
                                                    <flux:badge size="sm" color="emerald">
                                                        Protected
                                                    </flux:badge>
                                                @endif

                                                @if (
                                                    ! $targetIsSuperAdmin
                                                    && $user->id !== auth()->id()
                                                )
                                                    <flux:button
                                                        variant="ghost"
                                                        size="sm"
                                                        icon="trash"
                                                        wire:click="delete({{ $user->id }})"
                                                        wire:confirm="Hapus user {{ $user->name }}? Tindakan ini tidak dapat dibatalkan."
                                                        wire:loading.attr="disabled"
                                                        wire:target="delete({{ $user->id }})"
                                                        inset="top bottom"
                                                        class="text-slate-500 hover:text-rose-600"
                                                        title="Hapus User"
                                                    />
                                                @endif
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell
                                            colspan="5"
                                            class="py-16 text-center font-black uppercase opacity-40"
                                        >
                                            Data user tidak ditemukan.
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </div>
                </flux:tab.panel>
            @endcan

            @can(\App\Support\Auth\Permissions::MANAGE_ROLES)
                <flux:tab.panel name="roles">
                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                        <div class="space-y-4 xl:col-span-2">
                            <div>
                                <flux:heading size="lg">
                                    Roles
                                </flux:heading>

                                <flux:text class="mt-1 text-xs">
                                    Kelola role dan permission. Hanya
                                    super-admin yang dilindungi.
                                </flux:text>
                            </div>

                            <flux:table>
                                <flux:table.columns>
                                    <flux:table.column class="text-xs font-black uppercase">
                                        Role
                                    </flux:table.column>

                                    <flux:table.column class="text-xs font-black uppercase">
                                        Permission Groups
                                    </flux:table.column>

                                    <flux:table.column
                                        class="text-xs font-black uppercase"
                                        align="center"
                                    >
                                        Aksi
                                    </flux:table.column>
                                </flux:table.columns>

                                <flux:table.rows>
                                    @forelse ($roles as $role)
                                        <flux:table.row :key="$role->id">
                                            <flux:table.cell>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <div class="font-semibold uppercase text-xs">
                                                        {{ $role->name }}
                                                    </div>

                                                    @if ($role->name === 'super-admin')
                                                        <flux:badge size="sm" color="emerald">
                                                            Protected
                                                        </flux:badge>
                                                    @endif
                                                </div>

                                                <flux:text class="mt-1 text-xs">
                                                    {{ $role->permissions_count }}
                                                    permissions
                                                </flux:text>
                                            </flux:table.cell>

                                            <flux:table.cell>
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach ($permissionGroups as $group)
                                                        @php
                                                            $groupCount =
                                                                $role->permissions
                                                                    ->whereIn(
                                                                        'name',
                                                                        $group['names']
                                                                    )
                                                                    ->count();
                                                        @endphp

                                                        @if ($groupCount > 0)
                                                            <flux:badge size="sm" color="zinc">
                                                                {{ $group['label'] }}
                                                                ·
                                                                {{ $group['sequence'] }}
                                                                ({{ $groupCount }})
                                                            </flux:badge>
                                                        @endif
                                                    @endforeach

                                                    @if ($role->permissions_count === 0)
                                                        <flux:badge size="sm" color="zinc">
                                                            No Permission
                                                        </flux:badge>
                                                    @endif
                                                </div>
                                            </flux:table.cell>

                                            <flux:table.cell>
                                                <div class="flex items-center justify-center gap-1">
                                                    @if ($role->name !== 'super-admin')
                                                        <flux:button
                                                            variant="ghost"
                                                            size="sm"
                                                            icon="pencil-square"
                                                            wire:click="editRole({{ $role->id }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="editRole({{ $role->id }})"
                                                            inset="top bottom"
                                                            class="text-slate-500 hover:text-blue-600"
                                                            title="Edit Role"
                                                        />

                                                        <flux:button
                                                            variant="ghost"
                                                            size="sm"
                                                            icon="trash"
                                                            wire:click="deleteRole({{ $role->id }})"
                                                            wire:confirm="Hapus role {{ $role->name }}? Role hanya dapat dihapus jika belum dipakai user."
                                                            wire:loading.attr="disabled"
                                                            wire:target="deleteRole({{ $role->id }})"
                                                            inset="top bottom"
                                                            class="text-slate-500 hover:text-rose-600"
                                                            title="Hapus Role"
                                                        />
                                                    @else
                                                        <flux:badge size="sm" color="emerald">
                                                            Protected
                                                        </flux:badge>
                                                    @endif
                                                </div>
                                            </flux:table.cell>
                                        </flux:table.row>
                                    @empty
                                        <flux:table.row>
                                            <flux:table.cell
                                                colspan="3"
                                                class="py-16 text-center font-black uppercase opacity-40"
                                            >
                                                Role belum tersedia.
                                            </flux:table.cell>
                                        </flux:table.row>
                                    @endforelse
                                </flux:table.rows>
                            </flux:table>
                        </div>

                        <flux:card class="space-y-4">
                            <div>
                                <flux:heading size="lg">
                                    Permission Master
                                </flux:heading>

                                <flux:text class="mt-1 text-xs">
                                    Read-only, dikelompokkan berdasarkan modul.
                                </flux:text>
                            </div>

                            <div class="max-h-[680px] space-y-3 overflow-y-auto pr-1">
                                @forelse ($permissionGroups as $group)
                                    <flux:card
                                        wire:key="permission-group-{{ $group['sequence'] }}"
                                        class="space-y-3 p-4"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="font-bold text-sm">
                                                    {{ $group['label'] }}
                                                </div>

                                                <flux:text class="mt-1 text-xs">
                                                    {{ $group['description'] }}
                                                </flux:text>
                                            </div>

                                            <flux:badge size="sm" color="blue">
                                                {{ $group['sequence'] }}
                                            </flux:badge>
                                        </div>

                                        <div class="space-y-2">
                                            @forelse ($group['permissions'] as $permission)
                                                <div
                                                    wire:key="permission-master-{{ $permission->id }}"
                                                    class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700"
                                                >
                                                    <flux:text class="min-w-0 break-all font-semibold text-xs">
                                                        {{ $permission->name }}
                                                    </flux:text>

                                                    <flux:badge size="sm" color="zinc">
                                                        {{ $group['sequence'] }}.{{ str_pad(
                                                            (string) $loop->iteration,
                                                            2,
                                                            '0',
                                                            STR_PAD_LEFT
                                                        ) }}
                                                    </flux:badge>
                                                </div>
                                            @empty
                                                <flux:text class="text-xs text-amber-600">
                                                    Permission group belum tersedia
                                                    di database.
                                                </flux:text>
                                            @endforelse
                                        </div>
                                    </flux:card>
                                @empty
                                    <div class="py-10 text-center">
                                        <flux:text>
                                            Permission belum tersedia.
                                        </flux:text>
                                    </div>
                                @endforelse
                            </div>
                        </flux:card>
                    </div>
                </flux:tab.panel>
            @endcan
        </flux:tab.group>
    </flux:card>

    {{-- USER FORM MODAL --}}
    <flux:modal
        wire:model.self="isUserModalOpen"
        wire:close="closeUserModal"
        class="md:w-[32rem]"
        :dismissible="false"
    >
        <div class="space-y-6">
            <div>
                <flux:heading
                    size="lg"
                    class="flex items-center gap-2 font-black uppercase"
                >
                    <flux:icon.user class="h-5 w-5 text-blue-600" />

                    {{ $userId
                        ? 'Update Informasi User'
                        : 'Registrasi User Baru' }}
                </flux:heading>

                <flux:text class="mt-1 text-xs font-bold uppercase text-slate-400 dark:text-slate-500">
                    Lengkapi data akun dan tentukan role akses user.
                </flux:text>
            </div>

            <form wire:submit.prevent="save" class="space-y-6">
                <div class="space-y-5">
                    <flux:field>
                        <flux:label class="text-xs font-black uppercase">
                            Nama Lengkap
                        </flux:label>

                        <flux:input
                            wire:model="name"
                            type="text"
                            placeholder="Nama user"
                            class="font-bold uppercase text-xs"
                        />

                        <flux:error name="name" />
                    </flux:field>

                    <flux:field>
                        <flux:label class="text-xs font-black uppercase">
                            Email
                        </flux:label>

                        <flux:input
                            wire:model="email"
                            type="email"
                            placeholder="email@domain.com"
                            class="font-bold text-xs"
                        />

                        <flux:error name="email" />
                    </flux:field>

                    <flux:field>
                        <flux:label class="text-xs font-black uppercase">
                            Password {{ $userId ? '(Optional)' : '' }}
                        </flux:label>

                        <flux:input
                            wire:model="password"
                            type="password"
                            placeholder="{{ $userId
                                ? 'Kosongkan jika tidak ingin mengubah password'
                                : 'Minimal 8 karakter' }}"
                            class="font-bold text-xs"
                        />

                        <flux:error name="password" />
                    </flux:field>

                    <flux:field>
                        <flux:label class="text-xs font-black uppercase">
                            Roles
                        </flux:label>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @forelse ($assignableRoles as $role)
                                <div
                                    wire:key="assignable-role-{{ $role->id }}"
                                    class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700"
                                >
                                    <flux:checkbox
                                        wire:model="selectedRoles"
                                        value="{{ $role->name }}"
                                        label="{{ $role->name }}"
                                    />

                                    <flux:badge
                                        size="sm"
                                        color="{{ $role->name === 'super-admin'
                                            ? 'emerald'
                                            : 'zinc' }}"
                                    >
                                        R{{ str_pad(
                                            (string) $loop->iteration,
                                            2,
                                            '0',
                                            STR_PAD_LEFT
                                        ) }}
                                    </flux:badge>
                                </div>
                            @empty
                                <flux:text>
                                    Role belum tersedia.
                                </flux:text>
                            @endforelse
                        </div>

                        <flux:error name="selectedRoles" />
                        <flux:error name="selectedRoles.*" />
                    </flux:field>
                </div>

                <div class="flex gap-2 pt-2">
                    <flux:spacer />

                    <flux:button
                        type="button"
                        variant="ghost"
                        wire:click="closeUserModal"
                        class="font-black uppercase text-xs"
                    >
                        Batal
                    </flux:button>

                    <flux:button
                        type="submit"
                        variant="primary"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="font-black uppercase text-xs"
                    >
                        <span wire:loading.remove wire:target="save">
                            Simpan Data
                        </span>

                        <span wire:loading wire:target="save">
                            Menyimpan...
                        </span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- ROLE FORM MODAL --}}
    <flux:modal
        wire:model.self="isRoleModalOpen"
        wire:close="closeRoleModal"
        class="md:w-[48rem]"
        scroll="body"
        :dismissible="false"
    >
        <div class="space-y-6">
            <div>
                <flux:heading
                    size="lg"
                    class="flex items-center gap-2 font-black uppercase"
                >
                    <flux:icon.shield-check class="h-5 w-5 text-blue-600" />

                    {{ $roleId ? 'Update Role' : 'Create Role' }}
                </flux:heading>

                <flux:text class="mt-1 text-xs font-bold uppercase text-slate-400 dark:text-slate-500">
                    Tentukan nama role dan permission per group.
                </flux:text>
            </div>

            <form wire:submit.prevent="saveRole" class="space-y-6">
                <flux:field>
                    <flux:label class="text-xs font-black uppercase">
                        Nama Role
                    </flux:label>

                    <flux:input
                        wire:model="roleName"
                        type="text"
                        placeholder="training-admin"
                        class="font-bold lowercase text-xs"
                    />

                    <flux:error name="roleName" />
                </flux:field>

                <flux:field>
                    <flux:label class="text-xs font-black uppercase">
                        Permissions
                    </flux:label>

                    <div class="max-h-[520px] space-y-4 overflow-y-auto pr-1">
                        @forelse ($permissionGroups as $group)
                            @php
                                $selectedInGroup = count(
                                    array_intersect(
                                        $rolePermissions,
                                        $group['names']
                                    )
                                );
                            @endphp

                            <flux:card
                                wire:key="role-form-group-{{ $group['sequence'] }}"
                                class="space-y-4 p-4"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <div class="font-bold text-sm">
                                            {{ $group['label'] }}
                                        </div>

                                        <flux:text class="mt-1 text-xs">
                                            {{ $group['description'] }}
                                        </flux:text>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <flux:badge size="sm" color="zinc">
                                            {{ $selectedInGroup }}/{{ count($group['names']) }}
                                        </flux:badge>

                                        <flux:badge size="sm" color="blue">
                                            {{ $group['sequence'] }}
                                        </flux:badge>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                    @forelse ($group['permissions'] as $permission)
                                        <div
                                            wire:key="role-permission-{{ $permission->id }}"
                                            class="flex items-start justify-between gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700"
                                        >
                                            <flux:checkbox
                                                wire:model.live="rolePermissions"
                                                value="{{ $permission->name }}"
                                                label="{{ $permission->name }}"
                                            />

                                            <flux:badge size="sm" color="zinc">
                                                {{ $group['sequence'] }}.{{ str_pad(
                                                    (string) $loop->iteration,
                                                    2,
                                                    '0',
                                                    STR_PAD_LEFT
                                                ) }}
                                            </flux:badge>
                                        </div>
                                    @empty
                                        <flux:text class="text-xs text-amber-600">
                                            Permission group belum tersedia
                                            di database.
                                        </flux:text>
                                    @endforelse
                                </div>
                            </flux:card>
                        @empty
                            <flux:text>
                                Permission belum tersedia.
                            </flux:text>
                        @endforelse
                    </div>

                    <flux:error name="rolePermissions" />
                    <flux:error name="rolePermissions.*" />
                </flux:field>

                <div class="flex gap-2 pt-2">
                    <flux:spacer />

                    <flux:button
                        type="button"
                        variant="ghost"
                        wire:click="closeRoleModal"
                        class="font-black uppercase text-xs"
                    >
                        Batal
                    </flux:button>

                    <flux:button
                        type="submit"
                        variant="primary"
                        wire:loading.attr="disabled"
                        wire:target="saveRole"
                        class="font-black uppercase text-xs"
                    >
                        <span wire:loading.remove wire:target="saveRole">
                            Simpan Data
                        </span>

                        <span wire:loading wire:target="saveRole">
                            Menyimpan...
                        </span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>