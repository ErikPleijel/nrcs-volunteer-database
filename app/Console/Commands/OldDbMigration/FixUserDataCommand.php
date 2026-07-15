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

            // 2) Set lifecycle_status = 'active' if last_activity_at < 12 months old
            $this->info('Setting lifecycle_status = active for recently active usersâ€¦');

            DB::update("
                UPDATE users
                SET lifecycle_status = 'active'
                WHERE last_activity_at IS NOT NULL
                  AND last_activity_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                  AND lifecycle_status NOT IN ('archived')
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

            // 5) Delete users with missing division and organization
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

            $this->info('Fix 5: deleted ' . $deletedCount . ' orphan users (no division, no organisation, no data)');



            // 6) Clear email_verified_at for users with no email
            $this->info('Clearing email_verified_at for users with NULL emailâ€¦');

            DB::update("
                UPDATE users
                SET email_verified_at = NULL
                WHERE email IS NULL
            ");

            $this->info('âœ” Done');

            // 7) Flag volunteer membership fees
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

            // 8) Clear can_contribute_member for users who have both flags set (volunteer wins)
            $this->info('Clearing can_contribute_member where both contribution flags are setâ€¦');

            DB::update("
                UPDATE users
                SET can_contribute_member = NULL
                WHERE can_contribute_volunteering = 1
                  AND can_contribute_member = 1
            ");

            $this->info('âœ” Done');

            // 9) Set can_contribute_member = 1 for users with neither flag set
            $this->info('Setting can_contribute_member = 1 for users with no contribution flagâ€¦');

            DB::update("
                UPDATE users
                SET can_contribute_member = 1
                WHERE (can_contribute_volunteering IS NULL OR can_contribute_volunteering <> 1)
                  AND (can_contribute_member IS NULL OR can_contribute_member <> 1)
            ");

            $this->info('âœ” Done');

            // 10) Mark first-aid training types
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
