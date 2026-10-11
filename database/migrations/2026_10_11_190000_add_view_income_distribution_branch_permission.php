<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * view_income_distribution_branch: the Income Distribution report's manual
 * payments tab (Tab 2) for the user's own branch only. Granted to
 * branch_secretary and branch_db_administrator, matching
 * PermissionsTableSeeder, so existing databases get it without re-running
 * the seeder. Division roles do not get it.
 */
return new class extends Migration
{
    private const ROLES = ['branch_secretary', 'branch_db_administrator'];

    public function up(): void
    {
        $permission = Permission::findOrCreate('view_income_distribution_branch', 'web');

        Role::whereIn('name', self::ROLES)
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'view_income_distribution_branch')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
