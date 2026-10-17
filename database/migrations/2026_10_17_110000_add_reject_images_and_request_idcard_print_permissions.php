<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * reject_images: reject (and undo) a photo or signature on the bulk ID card
 * page, within the admin's own branch/division.
 * request_idcard_print: "Mark as ready to print" for staff who cannot print.
 * Matches PermissionsTableSeeder, so existing databases get it without
 * re-running the seeder. Idempotent; truncates nothing.
 */
return new class extends Migration
{
    private const GRANTS = [
        'reject_images' => [
            'national_db_administrator',
            'national_db_assistant',
            'branch_secretary',
            'branch_db_administrator',
            'branch_db_assistant',
            'division_db_assistant_operations',
            'division_db_assistant_finance',
        ],
        'request_idcard_print' => [
            'national_db_assistant',
            'branch_secretary',
            'branch_db_administrator',
            'branch_db_assistant',
            'division_db_assistant_operations',
            'division_db_assistant_finance',
        ],
    ];

    public function up(): void
    {
        foreach (self::GRANTS as $permissionName => $roleNames) {
            $permission = Permission::findOrCreate($permissionName, 'web');

            Role::whereIn('name', $roleNames)
                ->where('guard_name', 'web')
                ->get()
                ->each(fn (Role $role) => $role->givePermissionTo($permission));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', array_keys(self::GRANTS))
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Permission $permission) => $permission->delete());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
