<?php

namespace App\Observers;

use App\Models\Log as AuditLog;
use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "updated" event.
     *
     * Super-admin is only ever GRANTED by SuperAdminSeeder (from config('superadmin.emails')),
     * which sets both the role and is_super_admin. This observer no longer grants it: granting
     * on "created" let anyone who self-registered with a listed email become super-admin
     * before verifying that address. It only revokes, when a super-admin's email changes to
     * one that is not on the list — role and flag together, so the two never disagree.
     */
    public function updated(User $user): void
    {
        if ($user->isDirty('email')) {
            $this->revokeSuperAdminIfEmailNotListed($user);
        }
    }

    protected function revokeSuperAdminIfEmailNotListed(User $user): void
    {
        $listed = in_array(strtolower(trim((string) $user->email)), config('superadmin.emails', []), true);

        if ($listed || (! $user->hasRole('super-admin') && ! $user->is_super_admin)) {
            return;
        }

        $user->removeRole('super-admin');
        $user->forceFill(['is_super_admin' => false])->saveQuietly();

        AuditLog::write(
            'super_admin_auto_revoked',
            $user,
            ['branch_id' => $user->branch_id, 'division_id' => $user->division_id],
            ['role' => 'super-admin', 'is_super_admin' => true],
            ['role' => null, 'is_super_admin' => false],
            "super-admin removed from user #{$user->id}: email changed to one not in SUPER_ADMIN_EMAILS."
        );
    }
}
