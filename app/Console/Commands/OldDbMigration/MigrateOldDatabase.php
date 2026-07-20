<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class MigrateOldDatabase extends Command
{
    protected $signature = 'migrate:old-db
                            {--table= : Migrate specific table (users, branches, divisions, red-cross-units, donations, activity-types, activities, organisations, positions, signature-titles, membership-fees, membership-payments, training-types, trainings, task-force-types, task-forces, task-force-members, id-card-prints, public-contacts, all)}
                            {--limit=0 : Limit number of records}
                            {--batch-size=1000 : Batch size for processing}
                            {--clear : Clear existing data before migration}';

    protected $description = 'Master command to migrate data from old database';

    public function handle()
    {
        $table = $this->option('table');
        $limit = $this->option('limit');
        $batchSize = $this->option('batch-size');
        $clear = $this->option('clear');

        if ($table && $table !== 'all') {
            $this->migrateSpecificTable($table, $limit, $batchSize, $clear);
        } else {
            $this->migrateAllTables($limit, $batchSize, $clear);
        }

        return Command::SUCCESS;
    }

    private function migrateAllTables($limit, $batchSize, $clear)
    {
        $this->info('ðŸš€ Starting complete database migration from old system...');

        $this->info('Seeding roles...');
        Artisan::call('db:seed', ['--class' => 'RolesTableSeeder']);
        $this->line(Artisan::output());

        // Migrate basic structure tables
        $this->info('Migrating branches...');
        Artisan::call('migrate:branches', ['--chunk' => $batchSize]);
        $this->line(Artisan::output());

        $this->info('Migrating divisions...');
        Artisan::call('migrate:divisions', ['--chunk' => $batchSize]);
        $this->line(Artisan::output());

        // Lookup / reference tables
        $this->info('Migrating positions...');
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }
        Artisan::call('migrate:positions', $options);
        $this->line(Artisan::output());

        $this->info('Migrating signature titles...');
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }
        Artisan::call('migrate:signature-titles', $options);
        $this->line(Artisan::output());

        $this->info('Migrating activity types...');
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }
        Artisan::call('migrate:activity-types', $options);
        $this->line(Artisan::output());

        $this->info('Migrating training types...');
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }
        Artisan::call('migrate:training-types', $options);
        $this->line(Artisan::output());

        $this->info('Migrating task force types...');
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }
        Artisan::call('migrate:task-force-types', $options);
        $this->line(Artisan::output());

        $this->info('Migrating membership fees...');
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }
        Artisan::call('migrate:membership-fees', $options);
        $this->line(Artisan::output());

        // USERS
        $this->info('Migrating users...');
        $userOptions = ['--limit' => $limit];
        if ($clear) {
            $userOptions['--clear'] = true;
        }
        Artisan::call('migrate:users', $userOptions);
        $this->line(Artisan::output());

        // ROLE ASSIGNMENT ORDER IS LOAD-BEARING â€” DO NOT REORDER:
        // 1. seed:legacy-roles            â†’ base role from legacy_role (syncRoles = replace)
        // 2. permissions:migrate-legacy-from-old â†’ upgrades assistants to division roles
        //    (supersede/skip logic needs base roles to already exist)
        // 3. db:seed (SuperAdminSeeder)   â†’ re-asserts super-admin after any syncRoles replace
        // Reversing 1 and 2 wipes division roles and defeats the supersede/skip rules.

        $this->info('Seeding base roles from legacy_role...');
        Artisan::call('seed:legacy-roles');
        $this->line(Artisan::output());

        $this->info('Upgrading eligible assistants to division roles...');
        Artisan::call('permissions:migrate-legacy-from-old');
        $this->line(Artisan::output());

        // ID card prints
        $this->info('Migrating ID card prints...');
        Artisan::call('migrate:id-card-prints');
        $this->line(Artisan::output());

        // Public contacts
        $this->info('Migrating public contacts...');
        Artisan::call('migrate:public-contacts');
        $this->line(Artisan::output());

        // Organisations ARE migrated (real org records: name/address/email/branch).
        // Their old-system "contact person" is NOT carried over as a linked user
        // anymore — the old data doesn't support reliably re-establishing that
        // link, so organisation contacts are re-registered fresh by NRCS admins
        // after migration, via OrganisationController::linkUser().
        // Position note: this no longer creates users, so it has no ordering
        // dependency on migrate:users (or vice versa) — it only needs Branches
        // migrated first (for branch_id), which already happens above.
        $this->info('Migrating organisations...');
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }
        Artisan::call('migrate:organisations', $options);
        $this->line(Artisan::output());

        // Red Cross units
        $this->info('Migrating red cross units...');
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }
        Artisan::call('migrate:red-cross-units', $options);
        $this->line(Artisan::output());

        // Task forces
        $this->info('Migrating task forces...');
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }
        Artisan::call('migrate:task-forces', $options);
        $this->line(Artisan::output());

        $this->info('Migrating task force members...');
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }
        Artisan::call('migrate:task-force-members', $options);
        $this->line(Artisan::output());

        // Activities
        $this->info('Migrating activities...');
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }
        Artisan::call('migrate:activities', $options);
        $this->line(Artisan::output());

        // Trainings
        $this->info('Migrating trainings...');
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }
        Artisan::call('migrate:trainings', $options);
        $this->line(Artisan::output());

        // Donations
        $this->info('Migrating donations...');
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }
        Artisan::call('migrate:donations', $options);
        $this->line(Artisan::output());

        // Membership payments
        $this->info('Migrating membership payments...');
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }
        Artisan::call('migrate:membership-payments', $options);
        $this->line(Artisan::output());

        $this->info('Running main database seeder...');
        Artisan::call('db:seed');
        $this->line(Artisan::output());

        $this->info('Resetting permission cache...');
        Artisan::call('permission:cache-reset');
        $this->line(Artisan::output());

        /*$this->info('Importing division geo data...');
        Artisan::call('divisions:import-geo', ['file' => 'storage/app/lgas.csv']);
        $this->line(Artisan::output());*/

        $this->info('Migrating last admin activity...');
        Artisan::call('migrate:last-admin-activity');
        $this->line(Artisan::output());

        // users:mark-dormant-from-activity used to run here. Removed 2026-07-20:
        // it read the raw, not-yet-corrected last_activity_at, unconditionally
        // included pending_engagement in its scope (an undocumented pending ->
        // dormant transition, bypassing the RCU/membership-fee promotion gate),
        // and ignored the 'member' policy branch entirely. Its job is now done
        // correctly by fix:userdata's steps 5 & 6 below plus the two
        // lifecycle:reconcile --apply calls. See Decisions.md.
        $this->info('Promoting active users via lifecycle:reconcile...');
        Artisan::call('lifecycle:reconcile', ['--apply' => true]);
        $this->line(Artisan::output());

        $this->info('Running fix:userdata (includes orphan user cleanup)...');
        Artisan::call('fix:userdata');
        $this->line(Artisan::output());

        // fix:userdata's raw-SQL activity patch (step 4) is blind to
        // membership/unit/ghost status and can overwrite what the reconcile pass
        // above just got right. Run it again, last, so the shared, policy-aware
        // classification (lifecyclePolicyType()/isDormantByPolicy()) has the final word.
        $this->info('Re-running lifecycle:reconcile so it has the final word over fix:userdata...');
        Artisan::call('lifecycle:reconcile', ['--apply' => true]);
        $this->line(Artisan::output());

        // Trainings are imported and committed, and db:seed has run
        // (FirstAidTrainingTypesSeeder flags is_first_aid) â€” safe to backfill now.
        $this->backfillLastFirstAidAt();

        $this->info('ðŸŽ‰ Complete database migration finished successfully!');
        $this->showOverallStatistics();
    }

    private function migrateSpecificTable($table, $limit, $batchSize, $clear)
    {
        match ($table) {
            'users' => $this->migrateUsers($limit, $clear),
            'branches' => $this->migrateBranches($batchSize),
            'divisions' => $this->migrateDivisions($batchSize),
            'red-cross-units' => $this->migrateRedCrossUnits($batchSize, $clear),
            'activity-types' => $this->migrateActivityTypes($batchSize, $clear),
            'activities' => $this->migrateActivities($batchSize, $clear),
            'donations' => $this->migrateDonations($batchSize, $clear),
            'organisations' => $this->migrateOrganisations($batchSize, $clear),
            'positions' => $this->migratePositions($batchSize, $clear),
            'signature-titles' => $this->migrateSignatureTitles($batchSize, $clear),
            'membership-fees' => $this->migrateMembershipFees($batchSize, $clear),
            'membership-payments' => $this->migrateMembershipPayments($batchSize, $clear),
            'training-types' => $this->migrateTrainingTypes($batchSize, $clear),
            'trainings' => $this->migrateTrainings($batchSize, $clear),
            'task-force-types' => $this->migrateTaskForceTypes($batchSize, $clear),
            'task-forces' => $this->migrateTaskForces($batchSize, $clear),
            'task-force-members' => $this->migrateTaskForceMembers($batchSize, $clear),
            'id-card-prints' => $this->migrateIdCardPrints(),
            'public-contacts' => $this->migratePublicContacts(),
            default => $this->error("Unknown table: {$table}.")
        };

        // Backfill only when this invocation imported trainings (not on unrelated single-table runs).
        if ($table === 'trainings') {
            $this->backfillLastFirstAidAt();
        }
    }

    private function migrateUsers($limit, $clear)
    {
        $options = ['--limit' => $limit];
        if ($clear) {
            $options['--clear'] = true;
        }

        Artisan::call('migrate:users', $options);
        $this->line(Artisan::output());
    }

    private function migrateBranches($batchSize)
    {
        Artisan::call('migrate:branches', ['--chunk' => $batchSize]);
        $this->line(Artisan::output());
    }

    private function migrateDivisions($batchSize)
    {
        Artisan::call('migrate:divisions', ['--chunk' => $batchSize]);
        $this->line(Artisan::output());
    }

    private function migrateRedCrossUnits($batchSize, $clear)
    {
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }

        Artisan::call('migrate:red-cross-units', $options);
        $this->line(Artisan::output());
    }

    private function migrateActivityTypes($batchSize, $clear)
    {
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }

        Artisan::call('migrate:activity-types', $options);
        $this->line(Artisan::output());
    }

    private function migrateActivities($batchSize, $clear)
    {
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }

        Artisan::call('migrate:activities', $options);
        $this->line(Artisan::output());
    }

    private function migrateDonations($batchSize, $clear)
    {
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }

        Artisan::call('migrate:donations', $options);
        $this->line(Artisan::output());
    }

    private function migrateOrganisations($batchSize, $clear)
    {
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }

        Artisan::call('migrate:organisations', $options);
        $this->line(Artisan::output());
    }

    private function migratePositions($batchSize, $clear)
    {
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }

        Artisan::call('migrate:positions', $options);
        $this->line(Artisan::output());
    }

    private function migrateSignatureTitles($batchSize, $clear)
    {
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }

        Artisan::call('migrate:signature-titles', $options);
        $this->line(Artisan::output());
    }

    private function migrateMembershipFees($batchSize, $clear)
    {
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }

        Artisan::call('migrate:membership-fees', $options);
        $this->line(Artisan::output());
    }

    private function migrateMembershipPayments($batchSize, $clear)
    {
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }

        Artisan::call('migrate:membership-payments', $options);
        $this->line(Artisan::output());
    }

    private function migrateTrainingTypes($batchSize, $clear)
    {
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }

        Artisan::call('migrate:training-types', $options);
        $this->line(Artisan::output());
    }

    private function migrateTrainings($batchSize, $clear)
    {
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }

        Artisan::call('migrate:trainings', $options);
        $this->line(Artisan::output());
    }

    private function migrateTaskForceTypes($batchSize, $clear)
    {
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }

        Artisan::call('migrate:task-force-types', $options);
        $this->line(Artisan::output());
    }

    private function migrateTaskForces($batchSize, $clear)
    {
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }

        Artisan::call('migrate:task-forces', $options);
        $this->line(Artisan::output());
    }

    private function migrateTaskForceMembers($batchSize, $clear)
    {
        $options = ['--chunk' => $batchSize];
        if ($clear) {
            $options['--clear'] = true;
        }

        Artisan::call('migrate:task-force-members', $options);
        $this->line(Artisan::output());
    }

    private function migrateIdCardPrints()
    {
        Artisan::call('migrate:id-card-prints');
        $this->line(Artisan::output());
    }

    private function migratePublicContacts()
    {
        Artisan::call('migrate:public-contacts');
        $this->line(Artisan::output());
    }

    private function backfillLastFirstAidAt()
    {
        // Backfill users.last_first_aid_at from imported trainings (and refresh the FA map).
        // Depends on training_types.is_first_aid being flagged; skip loudly if it isn't, to avoid
        // writing an all-NULL column and a blank FA map at cutover.
        $anyFirstAidFlagged = DB::table('training_types')->where('is_first_aid', true)->exists();

        if ($anyFirstAidFlagged) {
            $this->info('Backfilling users.last_first_aid_at and recomputing FA freshness...');
            $this->call('firstaid:recalculate');
        } else {
            $this->warn('Skipped users.last_first_aid_at backfill: no training_types have is_first_aid = 1.');
            $this->warn('Run FirstAidTrainingTypesSeeder (flag is_first_aid), then: php artisan firstaid:recalculate');
        }
    }

    private function showOverallStatistics()
    {
        $this->info('ðŸ“Š Overall Migration Statistics:');

        // count everything
        $userCount = DB::table('users')->count();
        $branchCount = DB::table('branches')->count();
        $divisionCount = DB::table('divisions')->count();
        $redCrossUnitCount = DB::table('red_cross_units')->count();
        $activityTypeCount = DB::table('activity_types')->count();
        $activityCount = DB::table('activities')->count();
        $donationCount = DB::table('donations')->count();
        $organisationCount = DB::table('organisations')->count();
        $positionCount = DB::table('positions')->count();
        $signatureTitleCount = DB::table('signature_titles')->count();
        $membershipFeeCount = DB::table('membership_fees')->count();
        $membershipPaymentCount = DB::table('membership_payments')->count();
        $trainingTypeCount = DB::table('training_types')->count();
        $trainingCount = DB::table('trainings')->count();
        $taskForceTypeCount = DB::table('task_force_types')->count();
        $taskForceCount = DB::table('task_forces')->count();
        $taskForceMemberCount = DB::table('task_force_members')->count();
        $idCardPrintCount = DB::table('id_card_prints')->count();
        $branchPublicContactCount = DB::table('branches')->whereNotNull('public_contact_user_id_1')->count();

        $totalRecords =
            $userCount + $branchCount + $divisionCount + $redCrossUnitCount +
            $activityTypeCount + $activityCount + $donationCount + $organisationCount +
            $positionCount + $signatureTitleCount + $membershipFeeCount + $membershipPaymentCount +
            $trainingTypeCount + $trainingCount + $taskForceTypeCount + $taskForceCount +
            $taskForceMemberCount + $idCardPrintCount;

        $this->line("  - Users migrated: {$userCount}");
        $this->line("  - Branches migrated: {$branchCount}");
        $this->line("  - Divisions migrated: {$divisionCount}");
        $this->line("  - Red Cross Units migrated: {$redCrossUnitCount}");
        $this->line("  - Activity Types migrated: {$activityTypeCount}");
        $this->line("  - Activities migrated: {$activityCount}");
        $this->line("  - Donations migrated: {$donationCount}");
        $this->line("  - Organisations migrated: {$organisationCount}");
        $this->line("  - Positions migrated: {$positionCount}");
        $this->line("  - Signature Titles migrated: {$signatureTitleCount}");
        $this->line("  - Membership Fees migrated: {$membershipFeeCount}");
        $this->line("  - Membership Payments migrated: {$membershipPaymentCount}");
        $this->line("  - Training Types migrated: {$trainingTypeCount}");
        $this->line("  - Trainings migrated: {$trainingCount}");
        $this->line("  - Task Force Types migrated: {$taskForceTypeCount}");
        $this->line("  - Task Forces migrated: {$taskForceCount}");
        $this->line("  - Task Force Members migrated: {$taskForceMemberCount}");
        $this->line("  - ID Card Prints migrated: {$idCardPrintCount}");
        $this->line("  - Branches with public contacts: {$branchPublicContactCount}");
        $this->line("  - Total records migrated: {$totalRecords}");

        // user stats
        if ($userCount > 0) {
            $this->info('ðŸ‘¥ User Statistics:');
            $active = DB::table('users')->where('is_inactive', false)->count();
            $activated = DB::table('users')->where('is_account_activated', true)->count();
            $withUnit = DB::table('users')->whereNotNull('red_cross_unit_id')->count();

            $this->line("  - Active users: {$active}");
            $this->line("  - Activated accounts: {$activated}");
            $this->line("  - Users with red cross unit: {$withUnit}");
        }

        // task forces
        if ($taskForceCount > 0) {
            $this->info('âš¡ Task Force Statistics:');
            $active = DB::table('task_forces')->where('inactive', false)->count();
            $leaders = DB::table('task_forces')->whereNotNull('team_leader_user_id')->count();

            $this->line("  - Active task forces: {$active}");
            $this->line("  - With team leader: {$leaders}");
            $this->line("  - Total task force members: {$taskForceMemberCount}");
        }

        // trainings
        if ($trainingCount > 0) {
            $this->info('ðŸŽ“ Training Statistics:');
            // Phase 2: only approved records are real. No-op at import time (importer
            // stamps everything 'approved'), added for correctness/consistency.
            $active = DB::table('trainings')->where('is_deleted', false)->where('approval_status', 'approved')->count();
            $hours = DB::table('trainings')->where('is_deleted', false)->where('approval_status', 'approved')->sum('duration') ?? 0;

            $this->line("  - Active trainings: {$active}");
            $this->line('  - Total training duration: '.number_format($hours).' hours');
        }

        // activities
        if ($activityCount > 0) {
            $this->info('ðŸƒâ€â™‚ï¸ Activity Statistics:');
            // Phase 2: only approved records are real (no-op at import time).
            $active = DB::table('activities')->where('is_deleted', false)->where('approval_status', 'approved')->count();
            $hours = DB::table('activities')->where('is_deleted', false)->where('approval_status', 'approved')->sum('hours') ?? 0;

            $this->line("  - Active activities: {$active}");
            $this->line('  - Total volunteer hours: '.number_format($hours));
        }

        // membership
        if ($membershipPaymentCount > 0) {
            $this->info('ðŸ’³ Membership Statistics:');
            // Phase 2: only approved records are real (no-op at import time).
            $active = DB::table('membership_payments')->where('is_deleted', false)->where('approval_status', 'approved')->count();
            $withId = DB::table('membership_payments')->where('id_card_included', true)->count();

            $this->line("  - Active membership payments: {$active}");
            $this->line("  - Payments with ID card: {$withId}");
        }

        // donations
        if ($donationCount > 0) {
            $this->info('ðŸ’° Donation Statistics:');
            // Phase 2: only approved records are real (no-op at import time).
            $active = DB::table('donations')->where('is_deleted', false)->where('approval_status', 'approved')->count();
            $amount = DB::table('donations')->where('is_deleted', false)->where('approval_status', 'approved')->sum('amount') ?? 0;

            $this->line("  - Active donations: {$active}");
            $this->line('  - Total donation amount: '.number_format($amount));
        }

        // ID card prints
        if ($idCardPrintCount > 0) {
            $this->info('ðŸªª ID Card Print Statistics:');
            $expired = DB::table('id_card_prints')->where('expiry_date', '<', now())->count();

            $this->line("  - Total ID card prints: {$idCardPrintCount}");
            $this->line("  - Expired ID card prints: {$expired}");
        }

        // public contacts
        if ($branchPublicContactCount > 0) {
            $this->info('ðŸ‘¤ Public Contacts Statistics:');

            $totalSlots = 0;
            for ($i = 1; $i <= 6; $i++) {
                $slotCount = DB::table('branches')->whereNotNull("public_contact_user_id_{$i}")->count();
                $totalSlots += $slotCount;
                $this->line("  - Branches using contact slot #{$i}: {$slotCount}");
            }

            $this->line("  - Total public contact slots filled: {$totalSlots}");
        }
    }
}
