<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * view_income_distribution: the Income Distribution report (how online
 * membership income is shared between HQ, branches and divisions).
 * Granted to national_db_administrator only, matching PermissionsTableSeeder,
 * so existing databases get it without re-running the seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('view_income_distribution', 'web');

        Role::where('name', 'national_db_administrator')
            ->where('guard_name', 'web')
            ->first()
            ?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'view_income_distribution')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
