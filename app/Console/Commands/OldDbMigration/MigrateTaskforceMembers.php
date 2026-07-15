<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class MigrateTaskForceMembers extends Command
{
    protected $signature = 'migrate:task-force-members
                            {--chunk=1000 : Number of records to process per chunk}
                            {--clear : Clear existing task force members before migration}
                            {--dry-run : Run without making changes}';

    protected $description = 'Migrate task force members from old database, preserving TaskForceMemberID as id';

    public function handle()
    {
        $chunk = $this->option('chunk');
        $clear = $this->option('clear');
        $dryRun = $this->option('dry-run');

        $this->info('ðŸ‘¥ Starting task force members migration...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made to the database');
        }

        // Clear existing data if requested
        if ($clear && !$dryRun && $this->confirm('This will delete all existing task force members. Continue?')) {
            DB::table('task_force_members')->delete();
            $this->info('âœ… Existing task force members cleared');
        }

        // Check if task_force_members table exists
        if (!Schema::hasTable('task_force_members')) {
            $this->error('âŒ Task force members table does not exist. Please run migrations first.');
            return Command::FAILURE;
        }

        try {
            // Check if old table exists first
            $oldTableExists = DB::connection('old_db')->getSchemaBuilder()->hasTable('taskforcemembers');

            if (!$oldTableExists) {
                // Try alternative table names
                $alternativeNames = ['taskforce_members', 'TaskForceMembers', 'taskforcemembers'];
                $foundTable = null;

                foreach ($alternativeNames as $tableName) {
                    if (DB::connection('old_db')->getSchemaBuilder()->hasTable($tableName)) {
                        $foundTable = $tableName;
                        break;
                    }
                }

                if (!$foundTable) {
                    $this->error('âŒ Could not find taskforce members table in old database. Tried: taskforcemembers, taskforce_members, TaskForceMembers');
                    return Command::FAILURE;
                }

                $oldTableName = $foundTable;
            } else {
                $oldTableName = 'taskforcemembers';
            }

            $this->info("ðŸ“‹ Using old table: {$oldTableName}");

            $totalCount = DB::connection('old_db')->table($oldTableName)->count();
            $migratedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            $duplicateCount = 0;

            if ($totalCount === 0) {
                $this->warn('âš ï¸ No task force members found in old database');
                return Command::SUCCESS;
            }

            $this->info("ðŸ“Š Found {$totalCount} task force members to migrate");

            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            // Process in chunks
            DB::connection('old_db')
                ->table($oldTableName)
                ->orderBy('TaskForceMemberID')
                ->chunk($chunk, function ($members) use (&$migratedCount, &$skippedCount, &$errorCount, &$duplicateCount, $progressBar, $dryRun) {

                    $memberData = [];

                    foreach ($members as $member) {
                        try {
                            // Check if task force member already exists
                            if (!$dryRun) {
                                $exists = DB::table('task_force_members')
                                    ->where('id', $member->TaskForceMemberID)
                                    ->exists();

                                if ($exists) {
                                    $skippedCount++;
                                    $progressBar->advance();
                                    continue;
                                }

                                // Check for duplicate membership (same task force + user)
                                $duplicateExists = DB::table('task_force_members')
                                    ->where('task_force_id', $member->TaskForceID)
                                    ->where('user_id', $member->PersonID)
                                    ->exists();

                                if ($duplicateExists) {
                                    $duplicateCount++;
                                    $progressBar->advance();
                                    continue;
                                }
                            }

                            $newMember = [
                                'id' => $member->TaskForceMemberID, // Preserve original ID
                                'task_force_id' => (int) $member->TaskForceID,
                                'user_id' => (int) $member->PersonID,
                                'timestamp' => $this->convertTimestamp($member->TimeStamp),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            $memberData[] = $newMember;
                            $migratedCount++;

                        } catch (\Exception $e) {
                            $errorCount++;
                            $this->error("Failed to process task force member {$member->TaskForceMemberID}: " . $e->getMessage());
                        }

                        $progressBar->advance();
                    }

                    // Insert batch if not dry run
                    if (!$dryRun && !empty($memberData)) {
                        try {
                            DB::table('task_force_members')->insert($memberData);
                        } catch (\Exception $e) {
                            $this->error("Failed to insert batch: " . $e->getMessage());
                            $errorCount += count($memberData);
                        }
                    }
                });

            $progressBar->finish();
            $this->newLine(2);

            // Reset auto-increment to continue from the highest ID + 1
            if (!$dryRun) {
                $maxId = DB::table('task_force_members')->max('id') ?? 0;
                DB::statement("ALTER TABLE task_force_members AUTO_INCREMENT = " . ($maxId + 1));
                $this->info("âœ… Set AUTO_INCREMENT to " . ($maxId + 1));
            }

            // Show results
            $this->info('ðŸŽ‰ Task force members migration completed!');
            $this->table(['Metric', 'Count'], [
                ['Total Records', $totalCount],
                ['Successfully Processed', $migratedCount],
                ['Skipped (Already Exist)', $skippedCount],
                ['Duplicate Memberships', $duplicateCount],
                ['Errors', $errorCount],
                ['Success Rate', $totalCount > 0 ? round(($migratedCount / $totalCount) * 100, 2) . '%' : '0%']
            ]);

            if ($dryRun) {
                $this->warn('This was a DRY RUN - no data was actually migrated');
            }

            $this->showStatistics();

        } catch (\Exception $e) {
            $this->error('âŒ Migration failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function convertTimestamp($timestamp)
    {
        if ($timestamp === null) {
            return now();
        }

        try {
            return Carbon::parse($timestamp);
        } catch (\Exception $e) {
            return now();
        }
    }

    private function showStatistics()
    {
        $totalMembers = DB::table('task_force_members')->count();

        $byTaskForce = DB::table('task_force_members')
            ->select('task_force_id', DB::raw('COUNT(*) as member_count'))
            ->groupBy('task_force_id')
            ->orderBy('member_count', 'desc')
            ->limit(10)
            ->get();

        $uniqueUsers = DB::table('task_force_members')
            ->distinct('user_id')
            ->count('user_id');

        $uniqueTaskForces = DB::table('task_force_members')
            ->distinct('task_force_id')
            ->count('task_force_id');

        // Users with multiple memberships
        $multipleMembers = DB::table('task_force_members')
            ->select('user_id', DB::raw('COUNT(*) as membership_count'))
            ->groupBy('user_id')
            ->having('membership_count', '>', 1)
            ->orderBy('membership_count', 'desc')
            ->limit(5)
            ->get();

        $this->info('ðŸ“ˆ Task Force Members Statistics:');
        $this->line("  - Total memberships: {$totalMembers}");
        $this->line("  - Unique users: {$uniqueUsers}");
        $this->line("  - Unique task forces: {$uniqueTaskForces}");

        if ($totalMembers > 0 && $uniqueUsers > 0) {
            $avgMemberships = round($totalMembers / $uniqueUsers, 2);
            $this->line("  - Average memberships per user: {$avgMemberships}");
        }

        if ($byTaskForce->isNotEmpty()) {
            $this->line("  - Top task forces by member count:");
            foreach ($byTaskForce as $tf) {
                $this->line("    â€¢ Task Force ID {$tf->task_force_id}: {$tf->member_count} members");
            }
        }

        if ($multipleMembers->isNotEmpty()) {
            $this->line("  - Users with multiple memberships:");
            foreach ($multipleMembers as $user) {
                $this->line("    â€¢ User ID {$user->user_id}: {$user->membership_count} memberships");
            }
        }

        // Recent memberships
        $recentMembers = DB::table('task_force_members')
            ->select('task_force_id', 'user_id', 'timestamp')
            ->orderBy('timestamp', 'desc')
            ->limit(5)
            ->get();

        if ($recentMembers->isNotEmpty()) {
            $this->line("  - Most recent memberships:");
            foreach ($recentMembers as $member) {
                $this->line("    â€¢ User {$member->user_id} joined Task Force {$member->task_force_id} at {$member->timestamp}");
            }
        }
    }
}
