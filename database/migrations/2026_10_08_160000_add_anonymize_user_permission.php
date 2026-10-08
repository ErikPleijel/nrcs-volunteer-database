<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * anonymize_user: the manual "Anonymize this account" action on users/edit.
 * Granted to national_db_administrator only, matching PermissionsTableSeeder,
 * so existing databases get it without re-running the seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('anonymize_user', 'web');

        Role::where('name', 'national_db_administrator')
            ->where('guard_name', 'web')
            ->first()
            ?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'anonymize_user')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
