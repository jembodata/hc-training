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

        $this->removeObsoletePermissions();

        $superAdmin = Role::query()->firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => self::GUARD,
        ]);

        /*
         * Super Admin selalu mengikuti seluruh permission aktif.
         */
        $superAdmin->syncPermissions($permissions);

        $hrdManager = Role::query()->firstOrCreate([
            'name' => 'hrd-manager',
            'guard_name' => self::GUARD,
        ]);

        if ($hrdManager->wasRecentlyCreated) {
            $hrdManager->syncPermissions(
                $this->hrdManagerPermissions()
            );
        }

        $trainingAdmin = Role::query()->firstOrCreate([
            'name' => 'training-admin',
            'guard_name' => self::GUARD,
        ]);

        if ($trainingAdmin->wasRecentlyCreated) {
            $trainingAdmin->syncPermissions(
                $this->trainingAdminPermissions()
            );
        }

        $viewer = Role::query()->firstOrCreate([
            'name' => 'viewer',
            'guard_name' => self::GUARD,
        ]);

        if ($viewer->wasRecentlyCreated) {
            $viewer->syncPermissions(
                $this->viewerPermissions()
            );
        }

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
     * Default awal. Setelah role dibuat, perubahan selanjutnya dikelola UI.
     *
     * @return list<string>
     */
    private function hrdManagerPermissions(): array
    {
        return [
            Permissions::VIEW_DASHBOARD,

            Permissions::VIEW_EMPLOYEE,
            Permissions::CREATE_EMPLOYEE,
            Permissions::UPDATE_EMPLOYEE,
            Permissions::DELETE_EMPLOYEE,
            Permissions::IMPORT_EMPLOYEE,
            Permissions::EXPORT_EMPLOYEE,

            Permissions::VIEW_TRAINING,
            Permissions::CREATE_TRAINING,
            Permissions::UPDATE_TRAINING,
            Permissions::DELETE_TRAINING,

            Permissions::VIEW_MANAGEMENT_DATA,

            Permissions::VIEW_TRAINING_REPORT,
            Permissions::EXPORT_TRAINING_REPORT,
            Permissions::UPDATE_TRAINING_SCORE,

            Permissions::VIEW_TRAINER_CONTRIBUTION,
            Permissions::VIEW_AVERAGE_TRAINING,
            Permissions::VIEW_TRAINING_PENETRATION,

            Permissions::MANAGE_USERS,

            Permissions::VIEW_CERTIFICATE_TEMPLATE,
            Permissions::CREATE_CERTIFICATE_TEMPLATE,
            Permissions::UPDATE_CERTIFICATE_TEMPLATE,
            Permissions::ARCHIVE_CERTIFICATE_TEMPLATE,

            Permissions::VIEW_CERTIFICATE,
            Permissions::ISSUE_CERTIFICATE,
            Permissions::DOWNLOAD_CERTIFICATE,
            Permissions::REISSUE_CERTIFICATE,
            Permissions::REVOKE_CERTIFICATE,
        ];
    }

    /** @return list<string> */
    private function trainingAdminPermissions(): array
    {
        return [
            Permissions::VIEW_DASHBOARD,

            Permissions::VIEW_TRAINING,
            Permissions::CREATE_TRAINING,
            Permissions::UPDATE_TRAINING,
            Permissions::DELETE_TRAINING,

            Permissions::VIEW_TRAINING_REPORT,
            Permissions::EXPORT_TRAINING_REPORT,
            Permissions::UPDATE_TRAINING_SCORE,

            Permissions::VIEW_TRAINER_CONTRIBUTION,
            Permissions::VIEW_AVERAGE_TRAINING,
            Permissions::VIEW_TRAINING_PENETRATION,

            Permissions::VIEW_CERTIFICATE_TEMPLATE,
            Permissions::CREATE_CERTIFICATE_TEMPLATE,
            Permissions::UPDATE_CERTIFICATE_TEMPLATE,
            Permissions::ARCHIVE_CERTIFICATE_TEMPLATE,

            Permissions::VIEW_CERTIFICATE,
            Permissions::ISSUE_CERTIFICATE,
            Permissions::DOWNLOAD_CERTIFICATE,
            Permissions::REISSUE_CERTIFICATE,
            Permissions::REVOKE_CERTIFICATE,
        ];
    }

    /** @return list<string> */
    private function viewerPermissions(): array
    {
        return [
            Permissions::VIEW_DASHBOARD,
            Permissions::VIEW_TRAINING,
            Permissions::VIEW_TRAINING_REPORT,
            Permissions::VIEW_AVERAGE_TRAINING,
            Permissions::VIEW_TRAINING_PENETRATION,
        ];
    }

    private function removeObsoletePermissions(): void
    {
        Permission::query()
            ->where('guard_name', self::GUARD)
            ->whereIn('name', Permissions::obsolete())
            ->get()
            ->each(
                static fn (Permission $permission) =>
                    $permission->delete()
            );
    }

    private function forgetPermissionCache(): void
    {
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}