<?php

namespace App\Observers;

use App\Models\Log as AuditLog;
use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $this->assignSuperAdminRole($user);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Only re-check and assign/remove role if the email has changed
        if ($user->isDirty('email')) {
            $this->assignSuperAdminRole($user);
            $this->removeSuperAdminRoleIfNeeded($user);
        }
    }

    /**
     * Assigns the 'super-admin' role if the user's email is in the configured list.
     */
    protected function assignSuperAdminRole(User $user): void
    {
        $superAdminEmails = config('app.super_admin_emails', []);

        if (in_array($user->email, $superAdminEmails) && !$user->hasRole('super-admin')) {
            $user->assignRole('super-admin');

            AuditLog::write(
                'super_admin_auto_assigned',
                $user,
                ['branch_id' => $user->branch_id, 'division_id' => $user->division_id],
                null,
                ['role' => 'super-admin'],
                "super-admin role automatically assigned to {$user->email} (email matched configured super-admin list) — not a manual grant."
            );
        }
    }

    /**
     * Removes the 'super-admin' role if the user's email is no longer in the configured list.
     */
    protected function removeSuperAdminRoleIfNeeded(User $user): void
    {
        $superAdminEmails = config('app.super_admin_emails', []);

        if (!in_array($user->email, $superAdminEmails) && $user->hasRole('super-admin')) {
            $user->removeRole('super-admin');
        }
    }
}
