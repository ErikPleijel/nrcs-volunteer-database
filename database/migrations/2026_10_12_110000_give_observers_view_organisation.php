<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * observer_national_level gets view_organisation (read-only: list and
 * organisation pages, no add/edit/archive/restore), matching
 * PermissionsTableSeeder, so existing databases get it without re-running
 * the seeder. The organisation routes are gated on these permissions since
 * 2026-10-11; super-admin stays without organisation access.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('view_organisation', 'web');

        Role::where('name', 'observer_national_level')
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'observer_national_level')
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role) => $role->revokePermissionTo('view_organisation'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
