<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * "Can approve financial records" becomes a per-person tick box for branch DB
 * administrators (App\Support\SpecialPermissions): approve_payments and
 * approve_donations move from the branch_db_administrator ROLE to each branch
 * DB administrator DIRECTLY, so HQ can turn it off for one person.
 *
 * Grant and removal run in one transaction (grant first), so no branch DB
 * administrator is ever without approval. A permission is only handed out
 * while the role still holds it: running this again grants nothing, so it
 * never re-ticks a box HQ has since unticked.
 *
 * Also creates upload_user_images (the division assistants' "Can upload
 * photos and signatures" tick box). add_user ("Can register new users")
 * already exists.
 */
return new class extends Migration
{
    private const MOVED = ['approve_payments', 'approve_donations'];

    public function up(): void
    {
        DB::transaction(function () {
            Permission::findOrCreate('upload_user_images', 'web');
            Permission::findOrCreate('add_user', 'web');

            $role = Role::where('name', 'branch_db_administrator')->where('guard_name', 'web')->first();
            if (! $role) {
                return;
            }

            $morphClass = (new User)->getMorphClass();
            $userIds = DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('model_type', $morphClass)
                ->pluck('model_id');

            foreach (self::MOVED as $name) {
                $permission = Permission::findOrCreate($name, 'web');

                $onRole = DB::table('role_has_permissions')
                    ->where('role_id', $role->id)
                    ->where('permission_id', $permission->id);

                if (! $onRole->exists()) {
                    continue; // already moved
                }

                foreach ($userIds->chunk(500) as $chunk) {
                    DB::table('model_has_permissions')->insertOrIgnore($chunk->map(fn ($id) => [
                        'permission_id' => $permission->id,
                        'model_type' => $morphClass,
                        'model_id' => $id,
                    ])->values()->all());
                }

                $onRole->delete();
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::transaction(function () {
            $role = Role::where('name', 'branch_db_administrator')->where('guard_name', 'web')->first();
            if (! $role) {
                return;
            }

            $permissions = Permission::whereIn('name', self::MOVED)->where('guard_name', 'web')->get();
            $role->givePermissionTo($permissions);

            $userIds = DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('model_type', (new User)->getMorphClass())
                ->pluck('model_id');

            DB::table('model_has_permissions')
                ->where('model_type', (new User)->getMorphClass())
                ->whereIn('model_id', $userIds)
                ->whereIn('permission_id', $permissions->pluck('id'))
                ->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
