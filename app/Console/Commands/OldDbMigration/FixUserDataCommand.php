<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixUserDataCommand extends Command
{
    protected $signature = 'fix:userdata';
    protected $description = 'Run various SQL fixes on the users table and related data';

    public function handle()
    {
        $this->info('Starting user data fixesâ€¦');

        DB::beginTransaction();

        try {

            // 1) Fix is_form_registration null â†’ 0
            $this->info('Fixing NULL is_form_registration â†’ 0...');
            DB::update("
                UPDATE users
                SET is_form_registration = 0
                WHERE is_form_registration IS NULL
            ");
            $this->info('âœ” Done');

            // 2) Flag volunteer membership fees
            // Moved ahead of the pending_engagement promotion logic (was step 7):
            // is_volunteer_fee defaults to false on import (migrate:membership-fees
            // never sets it) and step 6 below depends on it being correct, so it
            // must be flagged before anything reads it.
            $this->info('Setting is_volunteer_fee = TRUE for Junior/School/Detachment/Service feesâ€¦');

            DB::update("
                UPDATE membership_fees
                SET is_volunteer_fee = TRUE
                WHERE name LIKE '%Junior%'
                   OR name LIKE '%School%'
                   OR name LIKE '%Detachment%'
                   OR name LIKE '%Service%'
            ");

            $this->info('âœ” Done');

            // 3) Backfill last_activity_at from assigned_rcu_date when missing
            $this->info('Backfilling last_activity_at from assigned_rcu_dateâ€¦');

            DB::update("
                UPDATE users
                SET last_activity_at = assigned_rcu_date
                WHERE last_activity_at IS NULL
                  AND assigned_rcu_date IS NOT NULL
            ");

            $this->info('âœ” Done');


            // 4) Set lifecycle_status = 'pending_engagement' where last_activity_at is NULL
            $this->info('Setting lifecycle_status = pending_engagement for users with no activityâ€¦');

            DB::update("
                    UPDATE users
                    SET lifecycle_status = 'pending_engagement'
                    WHERE last_activity_at IS NULL
                      AND lifecycle_status NOT IN ('archived')
                ");

            $this->info('âœ” Done');

            // 5) Recompute last_activity_at for pending_engagement users from imported
            // records, mirroring User::recalculateLifecycle()'s own aggregation:
            // MAX(approved training_date, approved activity date, approved
            // date_donation, approved payment_date). Only overrides the column when
            // at least one such record exists for the user - otherwise the existing
            // value (legacy persons.LastActivity, possibly backfilled from
            // assigned_rcu_date in step 3 above) is left untouched via COALESCE.
            // Scoped to pending_engagement only, per the promotion rule in step 6.
            $this->info('Recomputing last_activity_at for pending_engagement users from imported records...');

            DB::update("
                UPDATE users u
                LEFT JOIN (
                    SELECT user_id, MAX(activity_date) AS computed_last_activity_at
                    FROM (
                        SELECT user_id, training_date AS activity_date
                        FROM trainings
                        WHERE is_deleted = 0 AND approval_status = 'approved'
                        UNION ALL
                        SELECT user_id, date AS activity_date
                        FROM activities
                        WHERE is_deleted = 0 AND approval_status = 'approved'
                        UNION ALL
                        SELECT user_id, date_donation AS activity_date
                        FROM donations
                        WHERE removed_date IS NULL AND approval_status = 'approved'
                        UNION ALL
                        SELECT user_id, payment_date AS activity_date
                        FROM membership_payments
                        WHERE is_deleted = 0 AND approval_status = 'approved'
                    ) all_records
                    GROUP BY user_id
                ) agg ON agg.user_id = u.id
                SET u.last_activity_at = COALESCE(agg.computed_last_activity_at, u.last_activity_at)
                WHERE u.lifecycle_status = 'pending_engagement'
            ");

            $this->info('Done.');

            // 6) Null out dangling/inactive red_cross_unit_id references before the
            // promotion/classification logic in step 7 relies on it being
            // trustworthy. Two cases: the legacy RedCrossUnitID never resolved to
            // an imported unit at all (confirmed on real data: migrate:users runs
            // before migrate:red-cross-units, and some legacy references point to
            // units that never existed in the old database's own redcrossunits
            // table either - not a pipeline-ordering bug, a pre-existing legacy
            // data gap), or the unit exists but is inactive (not reachable today -
            // migrate:red-cross-units always imports is_active = true, and a unit
            // can't be deactivated while it still has members - kept for
            // correctness/safety regardless). Left unfixed, this doesn't just
            // affect reporting visibility: User::lifecyclePolicyType() treats ANY
            // non-null red_cross_unit_id as 'volunteer' type before checking
            // whether the unit exists or is active, corrupting active/dormant
            // classification for these users.
            $this->info('Nulling red_cross_unit_id for users pointing to a missing or inactive unit...');

            DB::update("
                UPDATE users u
                LEFT JOIN red_cross_units ru ON ru.id = u.red_cross_unit_id
                SET u.red_cross_unit_id = NULL
                WHERE u.red_cross_unit_id IS NOT NULL
                  AND (ru.id IS NULL OR ru.is_active = 0)
            ");

            $this->info('âœ” Done');

            // 7) Promote pending_engagement users who actually qualify, replacing the
            // old blanket "recently active -> active" rule. Promotes ONLY when:
            //   - the user is assigned to a currently-active Red Cross unit, OR
            //   - the user holds a (non-deleted) membership payment on a fee where
            //     is_volunteer_fee = false (a genuine dues payment, not a
            //     volunteer-associated fee like "Detachment")
            // Everyone else stays pending_engagement, untouched.
            //
            // For each qualifying user, active vs. dormant is decided by calling the
            // real User::lifecyclePolicyType() / isDormantByPolicy() methods directly
            // (not reimplemented here), so this can never drift from the live
            // runtime rule. Those methods already read
            // Setting::get('membership.dormant_after_months') internally.
            $this->info('Promoting eligible pending_engagement users (RCU assignment or non-volunteer membership payment)...');

            $promotedActive = 0;
            $promotedDormant = 0;

            \App\Models\User::where('lifecycle_status', 'pending_engagement')
                ->where(function ($query) {
                    $query->where(function ($rcu) {
                        $rcu->whereNotNull('red_cross_unit_id')
                            ->whereHas('redCrossUnit', fn ($q) => $q->where('is_active', true));
                    })->orWhereHas('membershipPayments', function ($mp) {
                        $mp->where('is_deleted', false)
                            ->whereHas('membershipFee', fn ($q) => $q->where('is_volunteer_fee', false));
                    });
                })
                ->chunkById(500, function ($users) use (&$promotedActive, &$promotedDormant) {
                    foreach ($users as $user) {
                        $target = $user->isDormantByPolicy() ? 'dormant' : 'active';
                        $user->forceFill(['lifecycle_status' => $target])->save();
                        $target === 'dormant' ? $promotedDormant++ : $promotedActive++;
                    }
                });

            $this->info("Promoted {$promotedActive} to active, {$promotedDormant} to dormant.");

            // 8) Delete users with missing division and organization
            $this->info('Deleting users with NULL division_id AND NULL organisation_id...');

            $deletedCount = \App\Models\User::where('division_id', null)
                ->where('organisation_id', null)
                ->whereDoesntHave('membershipPayments', fn ($q) =>
                    $q->where('is_deleted', false))
                ->whereDoesntHave('activities', fn ($q) =>
                    $q->where('is_deleted', false))
                ->whereDoesntHave('trainings', fn ($q) =>
                    $q->where('is_deleted', false))
                ->delete();

            $this->info('Fix 8: deleted ' . $deletedCount . ' orphan users (no division, no organisation, no data)');



            // 9) Clear email_verified_at for users with no email
            $this->info('Clearing email_verified_at for users with NULL emailâ€¦');

            DB::update("
                UPDATE users
                SET email_verified_at = NULL
                WHERE email IS NULL
            ");

            $this->info('âœ” Done');

            // 10) Clear can_contribute_member for users who have both flags set (volunteer wins)
            $this->info('Clearing can_contribute_member where both contribution flags are setâ€¦');

            DB::update("
                UPDATE users
                SET can_contribute_member = NULL
                WHERE can_contribute_volunteering = 1
                  AND can_contribute_member = 1
            ");

            $this->info('âœ” Done');

            // 11) Set can_contribute_member = 1 for users with neither flag set
            $this->info('Setting can_contribute_member = 1 for users with no contribution flagâ€¦');

            DB::update("
                UPDATE users
                SET can_contribute_member = 1
                WHERE (can_contribute_volunteering IS NULL OR can_contribute_volunteering <> 1)
                  AND (can_contribute_member IS NULL OR can_contribute_member <> 1)
            ");

            $this->info('âœ” Done');

            // 12) Mark first-aid training types
            $this->info('Setting is_first_aid = 1 for training types matching "First aid"â€¦');

            DB::update("
                UPDATE training_types
                SET is_first_aid = 1
                WHERE name LIKE '%First aid%'
            ");

            $this->info('âœ” Done');

            // -----------------------------------------
            //  Add more SQL patches below as needed!
            // -----------------------------------------

            DB::commit();
            $this->info('All fixes completed successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Error during fixes: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
