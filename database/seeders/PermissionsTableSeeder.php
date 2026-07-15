<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsTableSeeder extends Seeder
{

    // php artisan db:seed --class="Database\Seeders\PermissionsTableSeeder"
    public function run(): void
    {
        $this->command->info('🧹 Clearing old role permissions...');
        Schema::disableForeignKeyConstraints();
        DB::table('role_has_permissions')->truncate();
        Schema::enableForeignKeyConstraints();
        $this->command->info('✅ Old role permissions cleared.');

        /**
         * ------------------------------------------------------------
         * Permission “building blocks”
         * ------------------------------------------------------------
         * (kept small & obvious; the REAL readability win is the map below)
         */
        $dbAdminCore = [
            'edit_branch_information',
            'edit_division_information',
            'view_branch_information',
            'view_division_information',

            'view_log',
            'campaign_request_create',

            'add_user',
            'edit_user',
            'view_user',
            'remove_user',

            'add_red_cross_unit',
            'edit_red_cross_unit',
            'view_red_cross_unit',
            'remove_red_cross_unit',

            'add_task_force',
            'edit_task_force',
            'view_task_force',
            'remove_task_force',

            'add_organisation',
            'edit_organisation',
            'view_organisation',
            'archive_organisation',
            'restore_organisation',

            'view_reports',

            'add_payments',
            'edit_payments',
            'view_payments',
            'remove_payments',

            'add_volunteering',
            'edit_volunteering',
            'view_volunteering',
            'remove_volunteering',

            'add_trainings',
            'edit_trainings',
            'view_trainings',
            'remove_trainings',

            'add_donations',
            'edit_donations',
            'view_donations',
            'remove_donations',
        ];

        /**
         * ------------------------------------------------------------
         * If you want to know what a role has, look here.
         */
        $rolePermissions = [

            // 👑 Super-admin handled separately later (sync ALL permissions)

            'national_db_administrator' => array_values(array_unique(array_merge(
                [
                    'manage-admin-panel',
                    'change_settings',
                    'use_archive_tool',

                    // special

                    'campaign_request_approve',
                    'print_idcards',
                    'view_idcards',
                    'print_certificates',
                    'view_certificates',

                    // approval (national escalation — mirrors the branch grant)
                    'approve_donations',
                    'approve_payments',
                    'approve_volunteering',
                    'approve_training',

                    // authorization
                    'authorize_branch_secretary',
                    'authorize_branch_db_administrator',
                    'authorize_national_db_assistant',
                    'authorize_observer_national_level',

                    // meta
                    'manage_roles_and_permissions',
                ],
                $dbAdminCore
            ))),

            'national_db_assistant' => array_values(array_unique(array_merge(
                [
                    'manage-admin-panel',
                    'view_idcards',
                    'view_certificates',
                    'use_archive_tool',
                ],
                $dbAdminCore
            ))),

            'branch_secretary' => array_values(array_unique(array_merge(
                [
                    'manage-admin-panel',
                    'view_idcards',
                    'view_certificates',
                    'print_certificates',
                    'use_archive_tool',
                    'campaign_request_create',

                    // approval (two-step workflow)
                    'approve_donations',
                    'approve_payments',
                    'approve_volunteering',
                    'approve_training',

                    // authorization
                    'authorize_branch_db_assistant',
                    'authorize_division_db_assistant_finance',
                    'authorize_division_db_assistant_operations',

                    // meta
                    'manage_roles_and_permissions',
                ],
                $dbAdminCore
            ))),

            'branch_db_administrator' => array_values(array_unique(array_merge(
                [
                    'manage-admin-panel',
                    'view_idcards',
                    'view_certificates',
                    'print_certificates',
                    'use_archive_tool',
                    'campaign_request_create',

                    // approval (two-step workflow)
                    'approve_donations',
                    'approve_payments',
                    'approve_volunteering',
                    'approve_training',

                    // authorization
                    'authorize_branch_db_assistant',
                    'authorize_division_db_assistant_finance',
                    'authorize_division_db_assistant_operations',

                    // meta
                    'manage_roles_and_permissions',
                ],
                $dbAdminCore
            ))),

            'branch_db_assistant' => [
                'manage-admin-panel',
                'view_idcards',
                'view_certificates',

                'add_user',
                'edit_user',
                'view_user',

                'view_red_cross_unit',
                'view_task_force',
                'view_reports',

                'add_organisation',
                'edit_organisation',
                'view_organisation',

                'add_payments',
                'edit_payments',
                'view_payments',
                'remove_payments',

                'add_volunteering',
                'edit_volunteering',
                'view_volunteering',
                'remove_volunteering',

                'add_trainings',
                'edit_trainings',
                'view_trainings',
                'remove_trainings',

                'add_donations',
                'edit_donations',
                'view_donations',
                'remove_donations',
            ],

            // ✅ New: Division ops (no money)
            'division_db_assistant_operations' => [
                'manage-admin-panel',
                'view_idcards',
                'view_certificates',

                'view_user',
                'view_red_cross_unit',
                'view_task_force',
                'view_reports',
                'view_branch_information',
                'view_division_information',

                'add_volunteering',
                'edit_volunteering',
                'view_volunteering',
                'remove_volunteering',

                'add_trainings',
                'edit_trainings',
                'view_trainings',
                'remove_trainings',
            ],

            // ✅ New: Division finance (includes money + ops)
            'division_db_assistant_finance' => [
                'manage-admin-panel',
                'view_idcards',
                'view_certificates',

                'view_user',
                'view_red_cross_unit',
                'view_task_force',
                'view_reports',
                'view_branch_information',
                'view_division_information',

                'add_payments',
                'edit_payments',
                'view_payments',
                'remove_payments',

                'add_donations',
                'edit_donations',
                'view_donations',
                'remove_donations',

                'add_volunteering',
                'edit_volunteering',
                'view_volunteering',
                'remove_volunteering',

                'add_trainings',
                'edit_trainings',
                'view_trainings',
                'remove_trainings',
            ],

            'observer_national_level' => [
                'manage-admin-panel',
                'view_idcards',
                'view_certificates',

                'view_user',
                'view_red_cross_unit',
                'view_task_force',
                'view_reports',
                'view_log',

                'view_trainings',
                'view_volunteering',
                'view_payments',
                'view_donations',
            ],

            'super-admin' => [
                'manage-admin-panel',
                'view_idcards',
                'view_certificates',

                'view_user',
                'view_red_cross_unit',
                'view_task_force',
                'view_reports',

                'view_trainings',
                'view_volunteering',
                'view_payments',
                'view_donations',

                // The single special capability:
                'manage_roles_and_permissions',
                'authorize_national_db_administrator',
            ],
        ];

        /**
         * ------------------------------------------------------------
         * Create/ensure ALL permissions used anywhere
         * ------------------------------------------------------------
         */
        $this->command->info('🔑 Ensuring permissions exist...');

        $allPermissionNames = [];
        foreach ($rolePermissions as $roleName => $perms) {
            $allPermissionNames = array_merge($allPermissionNames, $perms);
        }
        $allPermissionNames = array_values(array_unique($allPermissionNames));

        foreach ($allPermissionNames as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
            $this->command->line("  ✅ Ensured permission: {$permissionName}");
        }

        /**
         * ------------------------------------------------------------
         * Assign permissions to roles (sync = clean & deterministic)
         * ------------------------------------------------------------
         */
        $this->command->info('🧩 Assigning permissions to roles...');

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->first();

            if (!$role) {
                $this->command->warn("  ⚠️ Role '{$roleName}' not found, skipping.");
                continue;
            }

            $permissionModels = collect($permissions)
                ->map(fn ($p) => Permission::findOrCreate($p, 'web'));

            $role->syncPermissions($permissionModels);

            $this->command->line("  ✅ Synced " . count($permissions) . " permissions to role: {$roleName}");
        }

        $this->command->info('✅ Permission seeding completed.');
    }
}
