<?php

namespace App\Services\Reports;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;


class DatabaseAccessStatsService
{
    /**
     * Get all role names that imply database / system administration access.
     *
     * @return array
     */
    public function getDatabaseAdminRoleNames(): array
    {
        // Reuse your User constants so the source of truth stays there.
        return array_values(array_unique(array_merge(
            User::NATIONAL_ROLES,
            User::BRANCH_ROLES,
            User::DIVISION_ROLES
        )));
    }

    /**
     * Get a flat list of all users who have any of the "DB admin" roles.
     *
     * Each row includes:
     *  - user fields (User model)
     *  - role_id
     *  - role_name
     *  - role_display_name (formatted)
     *
     * Ordered primarily by roles.id DESC, then by last_name, first_name.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getDatabaseAccessTeam(): Collection
    {
        $roleNames = $this->getDatabaseAdminRoleNames();

        return User::query()
            ->select([
                'users.*',
                'roles.id as role_id',
                'roles.name as role_name',
            ])
            ->join('model_has_roles', function ($join) {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', '=', User::class);
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('roles.name', $roleNames)
            // Optional: filter out archived accounts if you want only active admins
            ->where('users.lifecycle_status', '!=', 'archived')
            ->with(['branch', 'division', 'redCrossUnit'])
            ->orderByDesc('roles.id')
            ->orderBy('users.last_name')
            ->orderBy('users.first_name')
            ->get()
            ->map(function (User $user) {
                // Attach formatted role name for easy use in Blade
                $roleName = $user->role_name ?? null;

                $user->role_display_name_for_list = $roleName
                    ? $user->formatRoleName($roleName)
                    : null;

                return $user;
            });
    }

    /**
     * Get a summary of how many users per DB admin role.
     *
     * Useful for a "Role breakdown" box above the list.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getDatabaseAccessRoleSummary(): Collection
    {
        $roleNames = $this->getDatabaseAdminRoleNames();

        $rows = Role::query()
            ->select([
                'roles.id',
                'roles.name',
                DB::raw('COUNT(DISTINCT model_has_roles.model_id) as user_count'),
            ])
            ->join('model_has_roles', function ($join) {
                $join->on('roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.model_type', '=', User::class);
            })
            ->whereIn('roles.name', $roleNames)
            ->groupBy('roles.id', 'roles.name')
            ->orderByDesc('roles.id')
            ->get();

        return $rows->map(function ($row) {
            $row->display_name = ucwords(str_replace('_', ' ', $row->name));
            return $row;
        });
    }

    /**
     * (Optional) Convenience: get only active email/phone contacts for the team.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getDatabaseAccessContacts(): Collection
    {
        return $this->getDatabaseAccessTeam()
            ->filter(function (User $user) {
                return !empty($user->email) || $user->hasPhoneNumber();
            })
            ->map(function (User $user) {
                return [
                    'user_id'          => $user->id,
                    'full_name'        => $user->full_name,
                    'role'             => $user->role_display_name_for_list,
                    'branch_name'      => optional($user->branch)->name,
                    'division_name'    => optional($user->division)->name,
                    'unit_name'        => optional($user->redCrossUnit)->name,
                    'email'            => $user->email,
                    'primary_phone'    => $user->primary_phone,
                    'secondary_phone'  => $user->secondary_phone,
                    'access_level'     => $user->getAccessLevel(),
                ];
            });
    }

    public function getUsersWithDirectPermissions(): \Illuminate\Support\Collection
    {
        return User::query()
            ->select([
                'users.*',
                'permissions.id as permission_id',
                'permissions.name as permission_name',
            ])
            ->join('model_has_permissions', function ($join) {
                $join->on('model_has_permissions.model_id', '=', 'users.id')
                    ->where('model_has_permissions.model_type', '=', User::class);
            })
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            // adjust this filter to your naming convention
            ->where('permissions.name', 'like', 'authorize_%')
            ->with(['branch', 'division', 'redCrossUnit'])
            ->orderBy('permissions.id')
            ->orderBy('users.last_name')
            ->orderBy('users.first_name')
            ->get()
            ->groupBy('id') // group rows per user
            ->map(function ($rows) {
                /** @var \App\Models\User $user */
                $user = $rows->first();
                $permissionNames = $rows->pluck('permission_name')->unique()->sort()->values();

                // attach a convenient string for Blade
                $user->direct_permission_names = $permissionNames
                    ->map(fn ($name) => ucwords(str_replace('_', ' ', $name)))
                    ->implode(', ');

                return $user;
            })
            ->values();
    }

}
