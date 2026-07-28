<?php

use App\Models\User;
use App\Support\Auth\Permissions;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

new class extends Component
{
    use WithPagination;

    private const GUARD = 'web';
    private const PROTECTED_ROLE = 'super-admin';
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public string $activeTab = 'users';
    public string $search = '';
    public int $perPage = 10;

    public ?int $userId = null;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public array $selectedRoles = [];
    public bool $isUserModalOpen = false;

    public ?int $roleId = null;
    public string $roleName = '';
    public array $rolePermissions = [];
    public bool $isRoleModalOpen = false;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless(
            $user && (
                $user->can(Permissions::MANAGE_USERS)
                || $user->can(Permissions::MANAGE_ROLES)
            ),
            403
        );

        if (! $user->can(Permissions::MANAGE_USERS)) {
            $this->activeTab = 'roles';
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(mixed $value): void
    {
        $value = (int) $value;

        $this->perPage = in_array($value, self::PER_PAGE_OPTIONS, true)
            ? $value
            : 10;

        $this->resetPage();
    }

    public function updatedActiveTab(string $value): void
    {
        $user = Auth::user();

        if ($value === 'users' && ! $user?->can(Permissions::MANAGE_USERS)) {
            $this->activeTab = 'roles';
            return;
        }

        if ($value === 'roles' && ! $user?->can(Permissions::MANAGE_ROLES)) {
            $this->activeTab = 'users';
            return;
        }

        if (! in_array($value, ['users', 'roles'], true)) {
            $this->activeTab = $user?->can(Permissions::MANAGE_USERS)
                ? 'users'
                : 'roles';
        }

        $this->resetPage();
    }

    public function create(): void
    {
        Gate::authorize(Permissions::MANAGE_USERS);

        $this->resetUserForm();
        $this->isUserModalOpen = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize(Permissions::MANAGE_USERS);

        $user = User::query()
            ->with('roles')
            ->findOrFail($id);

        $this->authorizeSuperAdminAccountAccess($user);
        $this->resetUserForm();

        $this->userId = (int) $user->id;
        $this->name = (string) $user->name;
        $this->email = (string) $user->email;
        $this->selectedRoles = $user->roles->pluck('name')->values()->all();
        $this->isUserModalOpen = true;
    }

    public function save(): void
    {
        Gate::authorize(Permissions::MANAGE_USERS);

        $this->name = trim($this->name);
        $this->email = Str::lower(trim($this->email));
        $this->selectedRoles = array_values(array_unique($this->selectedRoles));

        $targetUser = $this->userId
            ? User::query()->with('roles')->findOrFail($this->userId)
            : null;

        if ($targetUser) {
            $this->authorizeSuperAdminAccountAccess($targetUser);
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => [
                'required',
                'email:rfc,dns',
                'max:255',
                Rule::unique('users', 'email')
                    ->whereNull('deleted_at')
                    ->ignore($this->userId),
            ],
            'password' => $this->userId
                ? ['nullable', 'string', 'min:8', 'max:255']
                : ['required', 'string', 'min:8', 'max:255'],
            'selectedRoles' => ['present', 'array'],
            'selectedRoles.*' => [
                'string',
                'distinct',
                Rule::in($this->assignableRoleNames()),
                Rule::exists('roles', 'name')->where(
                    fn ($query) => $query->where('guard_name', self::GUARD)
                ),
            ],
        ], [
            'name.required' => 'Nama user wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'selectedRoles.*.in' => 'Role tersebut tidak dapat diberikan.',
            'selectedRoles.*.exists' => 'Role yang dipilih tidak tersedia.',
        ]);

        if (
            in_array(self::PROTECTED_ROLE, $validated['selectedRoles'], true)
            && ! $this->currentUserIsSuperAdmin()
        ) {
            abort(403);
        }

        $wasUpdating = $targetUser !== null;

        DB::transaction(function () use ($validated, $targetUser): void {
            $user = $targetUser
                ? User::query()->lockForUpdate()->findOrFail($targetUser->id)
                : new User();

            if ($user->exists) {
                $user->load('roles');
                $this->ensureLastSuperAdminIsPreserved(
                    $user,
                    $validated['selectedRoles']
                );
            }

            $user->name = $validated['name'];
            $user->email = $validated['email'];

            if (filled($validated['password'] ?? null)) {
                $user->password = Hash::make($validated['password']);
            }

            $user->save();
            $user->syncRoles($validated['selectedRoles']);
        }, attempts: 3);

        $this->forgetPermissionCache();
        $this->successToast(
            $wasUpdating
                ? 'User berhasil diperbarui.'
                : 'User baru berhasil ditambahkan.'
        );
        $this->closeUserModal();
    }

    public function delete(int $id): void
    {
        Gate::authorize(Permissions::MANAGE_USERS);

        $user = User::query()->with('roles')->findOrFail($id);

        if ($user->id === Auth::id()) {
            $this->warningToast('User yang sedang login tidak bisa dihapus.');
            return;
        }

        if ($user->hasRole(self::PROTECTED_ROLE)) {
            $this->warningToast('User super-admin tidak boleh dihapus dari UI.');
            return;
        }

        DB::transaction(function () use ($user): void {
            $user->syncRoles([]);
            $user->delete();
        }, attempts: 3);

        $this->forgetPermissionCache();
        $this->successToast('User telah dihapus.');
    }

    public function closeUserModal(): void
    {
        $this->isUserModalOpen = false;
        $this->resetUserForm();
    }

    public function createRole(): void
    {
        Gate::authorize(Permissions::MANAGE_ROLES);

        $this->resetRoleForm();
        $this->isRoleModalOpen = true;
    }

    public function editRole(int $id): void
    {
        Gate::authorize(Permissions::MANAGE_ROLES);

        $role = Role::query()
            ->where('guard_name', self::GUARD)
            ->with(['permissions' => fn ($query) => $query
                ->where('guard_name', self::GUARD)
                ->whereIn('name', Permissions::all())
                ->orderBy('name')])
            ->findOrFail($id);

        if ($role->name === self::PROTECTED_ROLE) {
            $this->warningToast('Role super-admin tidak boleh diedit dari UI.');
            return;
        }

        $this->resetRoleForm();
        $this->roleId = (int) $role->id;
        $this->roleName = (string) $role->name;
        $this->rolePermissions = $role->permissions->pluck('name')->values()->all();
        $this->isRoleModalOpen = true;
    }

    public function saveRole(): void
    {
        Gate::authorize(Permissions::MANAGE_ROLES);

        $this->roleName = Str::of($this->roleName)
            ->lower()
            ->trim()
            ->replaceMatches('/\s+/', '-')
            ->toString();

        $this->rolePermissions = array_values(array_unique($this->rolePermissions));

        $role = $this->roleId
            ? Role::query()
                ->where('guard_name', self::GUARD)
                ->findOrFail($this->roleId)
            : new Role(['guard_name' => self::GUARD]);

        if ($role->exists && $role->name === self::PROTECTED_ROLE) {
            $this->warningToast('Role super-admin tidak boleh diedit dari UI.');
            return;
        }

        $validated = $this->validate([
            'roleName' => [
                'required',
                'string',
                'min:3',
                'max:100',
                'regex:/^[a-z0-9_-]+$/',
                Rule::notIn([self::PROTECTED_ROLE]),
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('guard_name', self::GUARD))
                    ->ignore($role->id),
            ],
            'rolePermissions' => ['present', 'array'],
            'rolePermissions.*' => [
                'string',
                'distinct',
                Rule::in(Permissions::all()),
                Rule::exists('permissions', 'name')->where(
                    fn ($query) => $query->where('guard_name', self::GUARD)
                ),
            ],
        ], [
            'roleName.required' => 'Nama role wajib diisi.',
            'roleName.regex' => 'Gunakan huruf kecil, angka, strip, atau underscore.',
            'roleName.unique' => 'Nama role sudah digunakan.',
            'roleName.not_in' => 'Nama super-admin dilindungi.',
            'rolePermissions.*.in' => 'Permission tidak terdaftar pada aplikasi.',
            'rolePermissions.*.exists' => 'Permission tidak tersedia di database.',
        ]);

        $wasUpdating = $role->exists;

        DB::transaction(function () use ($role, $validated): void {
            $role->name = $validated['roleName'];
            $role->guard_name = self::GUARD;
            $role->save();
            $role->syncPermissions($validated['rolePermissions']);
        }, attempts: 3);

        $this->forgetPermissionCache();
        $this->successToast(
            $wasUpdating
                ? 'Role berhasil diperbarui.'
                : 'Role baru berhasil dibuat.'
        );
        $this->closeRoleModal();
    }

    public function deleteRole(int $id): void
    {
        Gate::authorize(Permissions::MANAGE_ROLES);

        $role = Role::query()
            ->where('guard_name', self::GUARD)
            ->findOrFail($id);

        if ($role->name === self::PROTECTED_ROLE) {
            $this->warningToast('Role super-admin tidak boleh dihapus.');
            return;
        }

        if (DB::table('model_has_roles')->where('role_id', $role->id)->exists()) {
            $this->warningToast(
                'Role masih dipakai user. Lepaskan role terlebih dahulu.'
            );
            return;
        }

        DB::transaction(function () use ($role): void {
            $role->syncPermissions([]);
            $role->delete();
        }, attempts: 3);

        $this->forgetPermissionCache();
        $this->successToast('Role berhasil dihapus.');
    }

    public function closeRoleModal(): void
    {
        $this->isRoleModalOpen = false;
        $this->resetRoleForm();
    }

    public function with(): array
    {
        $user = Auth::user();
        $search = trim($this->search);
        $canManageUsers = (bool) $user?->can(Permissions::MANAGE_USERS);
        $canManageRoles = (bool) $user?->can(Permissions::MANAGE_ROLES);
        $isSuperAdmin = $this->currentUserIsSuperAdmin();

        $users = User::query()
            ->with(['roles' => fn ($query) => $query
                ->where('guard_name', self::GUARD)
                ->orderBy('name')])
            ->when(! $canManageUsers, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($canManageUsers && $search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('roles', fn ($roleQuery) => $roleQuery
                            ->where('roles.guard_name', self::GUARD)
                            ->where('roles.name', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate($this->perPage);

        $roles = Role::query()
            ->where('guard_name', self::GUARD)
            ->with(['permissions' => fn ($query) => $query
                ->where('guard_name', self::GUARD)
                ->whereIn('name', Permissions::all())
                ->orderBy('name')])
            ->withCount(['permissions' => fn ($query) => $query
                ->where('guard_name', self::GUARD)
                ->whereIn('name', Permissions::all())])
            ->when(! $canManageRoles, fn ($query) => $query->whereRaw('1 = 0'))
            ->when(
                $canManageRoles && $this->activeTab === 'roles' && $search !== '',
                fn ($query) => $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhereHas('permissions', fn ($permissionQuery) => $permissionQuery
                            ->where('permissions.guard_name', self::GUARD)
                            ->whereIn('permissions.name', Permissions::all())
                            ->where('permissions.name', 'like', "%{$search}%"));
                })
            )
            ->orderByRaw(
                'CASE WHEN name = ? THEN 0 ELSE 1 END',
                [self::PROTECTED_ROLE]
            )
            ->orderBy('name')
            ->get();

        $assignableRoles = Role::query()
            ->where('guard_name', self::GUARD)
            ->when(
                ! $isSuperAdmin,
                fn ($query) => $query->where('name', '!=', self::PROTECTED_ROLE)
            )
            ->orderByRaw(
                'CASE WHEN name = ? THEN 0 ELSE 1 END',
                [self::PROTECTED_ROLE]
            )
            ->orderBy('name')
            ->get();

        return [
            'users' => $users,
            'roles' => $roles,
            'assignableRoles' => $assignableRoles,
            'permissionGroups' => $canManageRoles ? $this->permissionGroups() : [],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'isSuperAdmin' => $isSuperAdmin,
        ];
    }

    private function permissionGroups(): array
    {
        $permissions = Permission::query()
            ->where('guard_name', self::GUARD)
            ->whereIn('name', Permissions::all())
            ->get()
            ->keyBy('name');

        return collect($this->permissionGroupDefinitions())
            ->map(function (array $group) use ($permissions): array {
                $names = $group['permissions'];
                $group['names'] = $names;
                $group['permissions'] = collect($names)
                    ->map(fn (string $name) => $permissions->get($name))
                    ->filter()
                    ->values();

                return $group;
            })
            ->all();
    }

    private function permissionGroupDefinitions(): array
    {
        return [
            ['sequence' => '01', 'label' => 'Dashboard', 'description' => 'Akses halaman ringkasan utama.', 'permissions' => [
                Permissions::VIEW_DASHBOARD,
            ]],
            ['sequence' => '02', 'label' => 'Training', 'description' => 'Kelola data dan batch training.', 'permissions' => [
                Permissions::VIEW_TRAINING,
                Permissions::CREATE_TRAINING,
                Permissions::UPDATE_TRAINING,
                Permissions::DELETE_TRAINING,
            ]],
            ['sequence' => '03', 'label' => 'Training Report', 'description' => 'Laporan, export, dan nilai training.', 'permissions' => [
                Permissions::VIEW_TRAINING_REPORT,
                Permissions::EXPORT_TRAINING_REPORT,
                Permissions::UPDATE_TRAINING_SCORE,
            ]],
            ['sequence' => '04', 'label' => 'Employee', 'description' => 'Kelola data employee.', 'permissions' => [
                Permissions::VIEW_EMPLOYEE,
                Permissions::CREATE_EMPLOYEE,
                Permissions::UPDATE_EMPLOYEE,
                Permissions::DELETE_EMPLOYEE,
                Permissions::IMPORT_EMPLOYEE,
                Permissions::EXPORT_EMPLOYEE,
            ]],
            ['sequence' => '05', 'label' => 'Management Data', 'description' => 'Kelola data referensi organisasi dan struktur pendukung.', 'permissions' => [
                Permissions::VIEW_MANAGEMENT_DATA,
            ]],
            ['sequence' => '06', 'label' => 'Analytics', 'description' => 'Dashboard analitik dan laporan ringkas.', 'permissions' => [
                Permissions::VIEW_TRAINER_CONTRIBUTION,
                Permissions::VIEW_AVERAGE_TRAINING,
                Permissions::VIEW_TRAINING_PENETRATION,
            ]],
            ['sequence' => '07', 'label' => 'Administration', 'description' => 'Kelola user dan role.', 'permissions' => [
                Permissions::MANAGE_USERS,
                Permissions::MANAGE_ROLES,
            ]],
            ['sequence' => '08', 'label' => 'Certificate Templates', 'description' => 'Kelola template sertifikat.', 'permissions' => [
                Permissions::VIEW_CERTIFICATE_TEMPLATE,
                Permissions::CREATE_CERTIFICATE_TEMPLATE,
                Permissions::UPDATE_CERTIFICATE_TEMPLATE,
                Permissions::ARCHIVE_CERTIFICATE_TEMPLATE,
            ]],
            ['sequence' => '09', 'label' => 'Issued Certificates', 'description' => 'Penerbitan dan pengelolaan sertifikat.', 'permissions' => [
                Permissions::VIEW_CERTIFICATE,
                Permissions::ISSUE_CERTIFICATE,
                Permissions::DOWNLOAD_CERTIFICATE,
                Permissions::REISSUE_CERTIFICATE,
                Permissions::REVOKE_CERTIFICATE,
            ]],
        ];
    }

    private function assignableRoleNames(): array
    {
        return Role::query()
            ->where('guard_name', self::GUARD)
            ->when(
                ! $this->currentUserIsSuperAdmin(),
                fn ($query) => $query->where('name', '!=', self::PROTECTED_ROLE)
            )
            ->pluck('name')
            ->all();
    }

    private function authorizeSuperAdminAccountAccess(User $user): void
    {
        if (
            $user->hasRole(self::PROTECTED_ROLE)
            && ! $this->currentUserIsSuperAdmin()
        ) {
            abort(403);
        }
    }

    private function ensureLastSuperAdminIsPreserved(
        User $user,
        array $selectedRoles
    ): void {
        if (
            ! $user->hasRole(self::PROTECTED_ROLE)
            || in_array(self::PROTECTED_ROLE, $selectedRoles, true)
        ) {
            return;
        }

        Role::query()
            ->where('guard_name', self::GUARD)
            ->where('name', self::PROTECTED_ROLE)
            ->lockForUpdate()
            ->firstOrFail();

        if (User::query()->role(self::PROTECTED_ROLE, self::GUARD)->count() <= 1) {
            throw ValidationException::withMessages([
                'selectedRoles' => 'Role super-admin terakhir tidak boleh dilepas.',
            ]);
        }
    }

    private function currentUserIsSuperAdmin(): bool
    {
        return (bool) Auth::user()?->hasRole(self::PROTECTED_ROLE);
    }

    private function resetUserForm(): void
    {
        $this->userId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->selectedRoles = [];
        $this->resetValidation();
    }

    private function resetRoleForm(): void
    {
        $this->roleId = null;
        $this->roleName = '';
        $this->rolePermissions = [];
        $this->resetValidation();
    }

    private function forgetPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function successToast(string $text): void
    {
        Flux::toast(
            heading: 'Success',
            text: $text,
            variant: 'success',
            duration: 3000,
        );
    }

    private function warningToast(string $text): void
    {
        Flux::toast(
            heading: 'Warning',
            text: $text,
            variant: 'warning',
            duration: 3500,
        );
    }
};