<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * national_db_administrator may REMOVE (or change) the three branch/division
 * assistant roles, but still not appoint them — appointing stays branch-level.
 * revoke_<role> is the remove-only counterpart of authorize_<role>
 * (User::getRemovableRoles()). Matches PermissionsTableSeeder, so existing
 * databases get it without re-running the seeder. Idempotent; truncates nothing.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'revoke_branch_db_assistant',
        'revoke_division_db_assistant_finance',
        'revoke_division_db_assistant_operations',
    ];

    public function up(): void
    {
        $permissions = collect(self::PERMISSIONS)
            ->map(fn (string $name) => Permission::findOrCreate($name, 'web'));

        Role::where('name', 'national_db_administrator')
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Permission $permission) => $permission->delete());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
