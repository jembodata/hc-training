<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Auth\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    private const GUARD = 'web';

    public function run(): void
    {
        $this->forgetPermissionCache();

        $permissions = Permissions::all();

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => self::GUARD,
            ]);
        }

        $superAdmin = Role::query()->firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => self::GUARD,
        ]);

        // Super Admin selalu mengikuti seluruh permission aktif.
        $superAdmin->syncPermissions($permissions);

        $this->createRoleWithDefaults(
            'hrd-manager',
            $this->hrdManagerPermissions()
        );

        $this->createRoleWithDefaults(
            'training-admin',
            $this->trainingAdminPermissions()
        );

        $this->createRoleWithDefaults(
            'viewer',
            $this->viewerPermissions()
        );

        $adminEmail = env('SUPER_ADMIN_EMAIL');

        if ($adminEmail) {
            $admin = User::query()
                ->where('email', $adminEmail)
                ->first();

            $admin?->assignRole($superAdmin);
        }

        $this->forgetPermissionCache();
    }

    /**
     * Permission default hanya diterapkan saat role bawaan pertama dibuat.
     * Role yang sudah ada tetap mengikuti pengaturan administrator di UI;
     * migration granular akan memindahkan assignment permission lamanya.
     *
     * @param list<string> $permissions
     */
    private function createRoleWithDefaults(
        string $name,
        array $permissions
    ): void {
        $role = Role::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => self::GUARD,
        ]);

        if ($role->wasRecentlyCreated) {
            $role->syncPermissions($permissions);
        }
    }

    /** @return list<string> */
    private function hrdManagerPermissions(): array
    {
        return [
            Permissions::VIEW_DASHBOARD,

            Permissions::VIEW_TRAINING,
            Permissions::UPDATE_TRAINING,
            Permissions::CREATE_TRAINING,
            Permissions::DELETE_TRAINING,
            Permissions::IMPORT_TRAINING,

            Permissions::VIEW_CERTIFICATE_TEMPLATE,
            Permissions::UPDATE_CERTIFICATE_TEMPLATE,
            Permissions::CREATE_CERTIFICATE_TEMPLATE,
            Permissions::ARCHIVE_CERTIFICATE_TEMPLATE,

            Permissions::VIEW_CERTIFICATE,
            Permissions::DOWNLOAD_CERTIFICATE,
            Permissions::REVOKE_CERTIFICATE,
            Permissions::ISSUE_CERTIFICATE,
            Permissions::REISSUE_CERTIFICATE,

            Permissions::VIEW_AVERAGE_TRAINING,
            Permissions::EXPORT_AVERAGE_TRAINING,

            Permissions::VIEW_TRAINING_DETAIL,
            Permissions::UPDATE_TRAINING_DETAIL_NILAI,
            Permissions::EXPORT_TRAINING_DETAIL,

            Permissions::VIEW_TRAINING_PENETRATION,
            Permissions::EXPORT_TRAINING_PENETRATION,

            Permissions::VIEW_TRAINING_CONTRIBUTION,
            Permissions::EXPORT_TRAINING_CONTRIBUTION,

            Permissions::VIEW_EMPLOYEE,
            Permissions::UPDATE_EMPLOYEE,
            Permissions::CREATE_EMPLOYEE,
            Permissions::DELETE_EMPLOYEE,
            Permissions::IMPORT_EMPLOYEE,
            Permissions::EXPORT_EMPLOYEE,

            Permissions::CREATE_USER,
            Permissions::UPDATE_USER,
            Permissions::VIEW_USER,
            Permissions::DELETE_USER,

            Permissions::VIEW_DEPARTMENT_POSITION_DATA,
        ];
    }

    /** @return list<string> */
    private function trainingAdminPermissions(): array
    {
        return [
            Permissions::VIEW_DASHBOARD,

            Permissions::VIEW_TRAINING,
            Permissions::UPDATE_TRAINING,
            Permissions::CREATE_TRAINING,
            Permissions::DELETE_TRAINING,
            Permissions::IMPORT_TRAINING,

            Permissions::VIEW_CERTIFICATE_TEMPLATE,
            Permissions::UPDATE_CERTIFICATE_TEMPLATE,
            Permissions::CREATE_CERTIFICATE_TEMPLATE,
            Permissions::ARCHIVE_CERTIFICATE_TEMPLATE,

            Permissions::VIEW_CERTIFICATE,
            Permissions::DOWNLOAD_CERTIFICATE,
            Permissions::REVOKE_CERTIFICATE,
            Permissions::ISSUE_CERTIFICATE,
            Permissions::REISSUE_CERTIFICATE,

            Permissions::VIEW_AVERAGE_TRAINING,
            Permissions::EXPORT_AVERAGE_TRAINING,

            Permissions::VIEW_TRAINING_DETAIL,
            Permissions::UPDATE_TRAINING_DETAIL_NILAI,
            Permissions::EXPORT_TRAINING_DETAIL,

            Permissions::VIEW_TRAINING_PENETRATION,
            Permissions::EXPORT_TRAINING_PENETRATION,

            Permissions::VIEW_TRAINING_CONTRIBUTION,
            Permissions::EXPORT_TRAINING_CONTRIBUTION,
        ];
    }

    /** @return list<string> */
    private function viewerPermissions(): array
    {
        return [
            Permissions::VIEW_DASHBOARD,
            Permissions::VIEW_TRAINING,
            Permissions::VIEW_AVERAGE_TRAINING,
            Permissions::VIEW_TRAINING_DETAIL,
            Permissions::VIEW_TRAINING_PENETRATION,
        ];
    }

    private function forgetPermissionCache(): void
    {
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}
