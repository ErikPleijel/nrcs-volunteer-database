<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Course management:
 * - view_courses: see courses in one's own area
 * - add_courses / edit_courses: create, edit, cancel and close a course
 *   (national and branch administrator roles)
 * - manage_course_participants: add/remove participants and facilitators and
 *   record results (also division assistants, in their own division)
 * Matches PermissionsTableSeeder, so existing databases get it without
 * re-running the seeder. Idempotent; truncates nothing.
 */
return new class extends Migration
{
    private const MANAGERS = [
        'national_db_administrator',
        'national_db_assistant',
        'branch_secretary',
        'branch_db_administrator',
    ];

    private const GRANTS = [
        'view_courses' => [
            ...self::MANAGERS,
            'branch_db_assistant',
            'division_db_assistant_operations',
            'division_db_assistant_finance',
            'observer_national_level',
            'super-admin',
        ],
        'add_courses' => self::MANAGERS,
        'edit_courses' => self::MANAGERS,
        'manage_course_participants' => [
            ...self::MANAGERS,
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
