<?php

namespace App\Console\Commands\OldDbMigration;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateLastAdminActivity extends Command
{
    protected $signature = 'migrate:last-admin-activity
                            {--chunk=200 : Number of users with roles to process per chunk}
                            {--dry-run : Run without writing changes to the users table}';

    protected $description = 'Backfill users.last_admin_activity_at from old_db activity tables for users that have roles';

    public function handle(): int
    {
        $chunk  = (int) $this->option('chunk');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('ðŸ§­ Starting backfill of last_admin_activity_at for users with roles');

        if ($dryRun) {
            $this->warn('DRY RUN MODE â€“ no changes will be written to the database');
        }

        // âœ”ï¸ Basic sanity checks
        if (!Schema::hasTable('users')) {
            $this->error('âŒ users table does not exist in the new database.');
            return self::FAILURE;
        }

        // Check that the old_db has the expected tables
        $oldTables = [
            'activities',
            'donations',
            'membershippayments',
            'persons',
            'trainings',
        ];

        foreach ($oldTables as $table) {
            if (!Schema::connection('old_db')->hasTable($table)) {
                $this->error("âŒ Missing table '{$table}' in old_db connection.");
                return self::FAILURE;
            }
        }

        // Base query: all distinct users that have at least one role
        $baseQuery = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->select('model_id')
            ->distinct();

        $total = (clone $baseQuery)->count();

        if ($total === 0) {
            $this->warn('âš ï¸ No users with roles found in model_has_roles.');
            return self::SUCCESS;
        }

        $this->info("ðŸ‘¥ Found {$total} users with roles to inspect.");

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        $updatedCount        = 0;
        $noActivityCount     = 0;
        $missingUserCount    = 0;
        $alreadySameCount    = 0;
        $errorCount          = 0;

        // Process in chunks of model_ids
        (clone $baseQuery)
            ->orderBy('model_id')
            ->chunk($chunk, function ($rows) use (
                $dryRun,
                $progressBar,
                &$updatedCount,
                &$noActivityCount,
                &$missingUserCount,
                &$alreadySameCount,
                &$errorCount
            ) {
                foreach ($rows as $row) {
                    $userId = (int) $row->model_id;

                    try {
                        $user = DB::table('users')->where('id', $userId)->first();

                        if (!$user) {
                            $missingUserCount++;
                            $progressBar->advance();
                            continue;
                        }

                        // Get latest admin activity from old_db
                        $latestActivity = $this->getLatestAdminActivityFromOldDb($userId);

                        if (!$latestActivity) {
                            // No activity found in old tables
                            $noActivityCount++;
                            $progressBar->advance();
                            continue;
                        }

                        // Compare with existing value (if any)
                        $current = $user->last_admin_activity_at
                            ? Carbon::parse($user->last_admin_activity_at)
                            : null;

                        // If current is already >= latestActivity, no need to update
                        if ($current && $current->greaterThanOrEqualTo($latestActivity)) {
                            $alreadySameCount++;
                            $progressBar->advance();
                            continue;
                        }

                        if (!$dryRun) {
                            DB::table('users')
                                ->where('id', $userId)
                                ->update([
                                    'last_admin_activity_at' => $latestActivity->toDateTimeString(),
                                    'updated_at'             => now(),
                                ]);
                        }

                        $updatedCount++;
                    } catch (\Throwable $e) {
                        $errorCount++;
                        $this->error("Error processing user_id {$userId}: {$e->getMessage()}");
                    }

                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $this->newLine(2);

        $this->info('âœ… Backfill completed. Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total users with roles', $total],
                ['Updated last_admin_activity_at', $updatedCount],
                ['No activity found in old_db', $noActivityCount],
                ['User missing in users table', $missingUserCount],
                ['Already up-to-date / newer', $alreadySameCount],
                ['Errors', $errorCount],
            ]
        );

        if ($dryRun) {
            $this->warn('This was a DRY RUN â€“ no data was written to users.last_admin_activity_at');
        }

        return self::SUCCESS;
    }

    /**
     * Look in old_db tables and return the latest timestamp for a given userId.
     *
     * Tables / columns:
     *  - activities:        SubmissionID, Timestamp
     *  - donations:         SubmissionID, Timestamp
     *  - membershippayments:SubmissionID, Timestamp
     *  - persons:           AssignRcuID, AssignRcuDate
     *  - trainings:         SubmissionID, Timestamp
     */
    private function getLatestAdminActivityFromOldDb(int $userId): ?Carbon
    {
        $connection = DB::connection('old_db');

        $timestamps = [];

        // activities.Timestamp
        $activityTs = $connection->table('activities')
            ->where('SubmissionID', $userId)
            ->max('Timestamp');
        if ($activityTs) {
            $timestamps[] = $activityTs;
        }

        // donations.Timestamp
        $donationTs = $connection->table('donations')
            ->where('SubmissionID', $userId)
            ->max('Timestamp');
        if ($donationTs) {
            $timestamps[] = $donationTs;
        }

        // membershippayments.Timestamp
        $membershipTs = $connection->table('membershippayments')
            ->where('SubmissionID', $userId)
            ->max('Timestamp');
        if ($membershipTs) {
            $timestamps[] = $membershipTs;
        }

        // persons.AssignRcuDate (using AssignRcuID)
        $assignTs = $connection->table('persons')
            ->where('AssignRcuID', $userId)
            ->max('AssignRcuDate');
        if ($assignTs) {
            $timestamps[] = $assignTs;
        }

        // trainings.Timestamp
        $trainingTs = $connection->table('trainings')
            ->where('SubmissionID', $userId)
            ->max('Timestamp');
        if ($trainingTs) {
            $timestamps[] = $trainingTs;
        }

        if (empty($timestamps)) {
            return null;
        }

        // Normalize to Carbon and return the latest one
        return collect($timestamps)
            ->filter()
            ->map(fn ($ts) => Carbon::parse($ts))
            ->max();
    }
}
