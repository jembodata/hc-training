<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GUARD = 'web';

    /**
     * Old permission assignments are copied to their granular replacements
     * before old permission records are removed.
     */
    public function up(): void
    {
        if (! $this->permissionTablesExist()) {
            return;
        }

        $this->createPermissions($this->activePermissions());

        foreach ($this->assignmentMappings() as $legacy => $replacements) {
            $this->copyAssignments($legacy, $replacements);
        }

        DB::table($this->table('permissions'))
            ->where('guard_name', self::GUARD)
            ->whereIn('name', array_keys($this->legacyMappings()))
            ->delete();

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (! $this->permissionTablesExist()) {
            return;
        }

        $legacyNames = array_keys($this->legacyMappings());
        $this->createPermissions($legacyNames);

        foreach ($this->reverseMappings() as $current => $legacyNames) {
            $this->copyAssignments($current, $legacyNames);
        }

        $newOnlyPermissions = array_values(array_diff(
            $this->activePermissions(),
            [
                'view-dashboard',
                'view-training',
                'update-training',
                'create-training',
                'delete-training',
                'view-certificate-template',
                'update-certificate-template',
                'create-certificate-template',
                'archive-certificate-template',
                'view-certificate',
                'download-certificate',
                'revoke-certificate',
                'issue-certificate',
                'reissue-certificate',
                'view-average-training',
                'view-training-penetration',
                'view-employee',
                'update-employee',
                'create-employee',
                'delete-employee',
                'import-employee',
                'export-employee',
            ]
        ));

        DB::table($this->table('permissions'))
            ->where('guard_name', self::GUARD)
            ->whereIn('name', $newOnlyPermissions)
            ->delete();

        $this->forgetPermissionCache();
    }

    /** @param list<string> $names */
    private function createPermissions(array $names): void
    {
        $now = now();

        foreach ($names as $name) {
            DB::table($this->table('permissions'))->insertOrIgnore([
                'name' => $name,
                'guard_name' => self::GUARD,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Copy both role assignments and direct model assignments.
     *
     * @param list<string> $replacementNames
     */
    private function copyAssignments(
        string $sourceName,
        array $replacementNames
    ): void {
        $sourceId = DB::table($this->table('permissions'))
            ->where('name', $sourceName)
            ->where('guard_name', self::GUARD)
            ->value('id');

        if ($sourceId === null) {
            return;
        }

        $replacementIds = DB::table($this->table('permissions'))
            ->where('guard_name', self::GUARD)
            ->whereIn('name', $replacementNames)
            ->pluck('id');

        foreach ($replacementIds as $replacementId) {
            $roles = DB::table($this->table('role_has_permissions'))
                ->where($this->permissionPivot(), $sourceId)
                ->pluck($this->rolePivot());

            foreach ($roles as $roleId) {
                DB::table($this->table('role_has_permissions'))
                    ->insertOrIgnore([
                        $this->permissionPivot() => $replacementId,
                        $this->rolePivot() => $roleId,
                    ]);
            }

            $models = DB::table($this->table('model_has_permissions'))
                ->where($this->permissionPivot(), $sourceId)
                ->get();

            foreach ($models as $model) {
                $record = (array) $model;
                $record[$this->permissionPivot()] = $replacementId;

                DB::table($this->table('model_has_permissions'))
                    ->insertOrIgnore($record);
            }
        }
    }

    /** @return array<string, list<string>> */
    private function assignmentMappings(): array
    {
        return [
            // Import previously shared create-training.
            'create-training' => [
                'import-training',
            ],
            ...$this->legacyMappings(),
        ];
    }

    /** @return array<string, list<string>> */
    private function legacyMappings(): array
    {
        return [
            'view-training-report' => [
                'view-training-detail',
            ],
            'update-training-score' => [
                'update-training-detail-nilai',
            ],
            'export-training-report' => [
                'export-average-training',
                'export-training-detail',
                'export-training-penetration',
                'export-training-contribution',
            ],
            'view-trainer-contribution' => [
                'view-training-contribution',
            ],
            'view-management-data' => [
                'view-department-position-data',
            ],
            'manage-attribute-management' => [
                'create-department-position-data',
                'update-department-position-data',
                'delete-department-position-data',
            ],
            'manage-users' => [
                'create-user',
                'update-user',
                'view-user',
                'delete-user',
            ],
            'manage-roles' => [
                'create-role',
                'update-role',
                'view-role',
                'delete-role',
            ],
            'view-history' => [],
        ];
    }

    /** @return array<string, list<string>> */
    private function reverseMappings(): array
    {
        $reverse = [];

        foreach ($this->assignmentMappings() as $legacy => $currentNames) {
            foreach ($currentNames as $current) {
                if ($current === $legacy) {
                    continue;
                }

                $reverse[$current] ??= [];
                $reverse[$current][] = $legacy;
            }
        }

        return $reverse;
    }

    /** @return list<string> */
    private function activePermissions(): array
    {
        return [
            'view-dashboard',

            'view-training',
            'update-training',
            'create-training',
            'delete-training',
            'import-training',

            'view-certificate-template',
            'update-certificate-template',
            'create-certificate-template',
            'archive-certificate-template',

            'view-certificate',
            'download-certificate',
            'revoke-certificate',
            'issue-certificate',
            'reissue-certificate',

            'view-average-training',
            'export-average-training',

            'view-training-detail',
            'update-training-detail-nilai',
            'export-training-detail',

            'view-training-penetration',
            'export-training-penetration',

            'view-training-contribution',
            'export-training-contribution',

            'view-employee',
            'update-employee',
            'create-employee',
            'delete-employee',
            'import-employee',
            'export-employee',

            'create-user',
            'update-user',
            'view-user',
            'delete-user',

            'create-role',
            'update-role',
            'view-role',
            'delete-role',

            'view-department-position-data',
            'update-department-position-data',
            'create-department-position-data',
            'delete-department-position-data',
        ];
    }

    private function permissionTablesExist(): bool
    {
        return Schema::hasTable($this->table('permissions'))
            && Schema::hasTable($this->table('role_has_permissions'))
            && Schema::hasTable($this->table('model_has_permissions'));
    }

    private function table(string $key): string
    {
        return (string) config("permission.table_names.{$key}");
    }

    private function rolePivot(): string
    {
        return (string) (
            config('permission.column_names.role_pivot_key')
            ?: 'role_id'
        );
    }

    private function permissionPivot(): string
    {
        return (string) (
            config('permission.column_names.permission_pivot_key')
            ?: 'permission_id'
        );
    }

    private function forgetPermissionCache(): void
    {
        app('cache')
            ->store(
                config('permission.cache.store') !== 'default'
                    ? config('permission.cache.store')
                    : null
            )
            ->forget(config('permission.cache.key'));
    }
};
