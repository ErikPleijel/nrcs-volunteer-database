<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class MigrateTaskForces extends Command
{
    protected $signature = 'migrate:task-forces
                            {--chunk=500 : Number of records to process per chunk}
                            {--clear : Clear existing task forces before migration}
                            {--dry-run : Run without making changes}';

    protected $description = 'Migrate task forces from old database, preserving TaskForceID as id';

    public function handle()
    {
        $chunk = $this->option('chunk');
        $clear = $this->option('clear');
        $dryRun = $this->option('dry-run');

        $this->info('âš¡ Starting task forces migration...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made to the database');
        }

        // Clear existing data if requested
        if ($clear && !$dryRun && $this->confirm('This will delete all existing task forces. Continue?')) {
            DB::table('task_forces')->delete();
            $this->info('âœ… Existing task forces cleared');
        }

        // Check if task_forces table exists
        if (!Schema::hasTable('task_forces')) {
            $this->error('âŒ Task forces table does not exist. Please run migrations first.');
            return Command::FAILURE;
        }

        try {
            $totalCount = DB::connection('old_db')->table('taskforces')->count();
            $migratedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            if ($totalCount === 0) {
                $this->warn('âš ï¸ No task forces found in old database');
                return Command::SUCCESS;
            }

            $this->info("ðŸ“Š Found {$totalCount} task forces to migrate");

            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            // Process in chunks
            DB::connection('old_db')
                ->table('taskforces')
                ->orderBy('TaskForceID')
                ->chunk($chunk, function ($taskForces) use (&$migratedCount, &$skippedCount, &$errorCount, $progressBar, $dryRun) {

                    $forceData = [];

                    foreach ($taskForces as $force) {
                        try {
                            // Check if task force already exists
                            if (!$dryRun) {
                                $exists = DB::table('task_forces')
                                    ->where('id', $force->TaskForceID)
                                    ->exists();

                                if ($exists) {
                                    $skippedCount++;
                                    $progressBar->advance();
                                    continue;
                                }
                            }
                            // Set taskforce managed by HQ to branch = FCT
                            $branchId = (int) $force->BranchID;
                            if ($branchId === 0) {
                                $branchId = 848;
                            }

                            $newForce = [
                                'id' => $force->TaskForceID, // Preserve original ID
                                'name' => $this->cleanString($force->TaskForceName),
                                'task_force_type_id' => (int) $force->TaskForceTypeID,
                                'branch_id' => $branchId,
                                // 'division_id' => (int) $force->DivisionID, // Removed as per request
                                'timestamp' => $this->convertTimestamp($force->TimeStamp),
                                'team_leader_user_id' => $force->TeamLeaderID ? (int) $force->TeamLeaderID : null,
                                'assist_team_leader_user_id' => $force->AssistTeamLeaderID ? (int) $force->AssistTeamLeaderID : null,
                                'inactive' => $this->convertBoolean($force->Inactive),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            $forceData[] = $newForce;
                            $migratedCount++;

                        } catch (\Exception $e) {
                            $errorCount++;
                            $this->error("Failed to process task force {$force->TaskForceID}: " . $e->getMessage());
                        }

                        $progressBar->advance();
                    }

                    // Insert batch if not dry run
                    if (!$dryRun && !empty($forceData)) {
                        DB::table('task_forces')->insert($forceData);
                    }
                });

            $progressBar->finish();
            $this->newLine(2);

            // Reset auto-increment to continue from the highest ID + 1
            if (!$dryRun) {
                $maxId = DB::table('task_forces')->max('id') ?? 0;
                DB::statement("ALTER TABLE task_forces AUTO_INCREMENT = " . ($maxId + 1));
                $this->info("âœ… Set AUTO_INCREMENT to " . ($maxId + 1));
            }

            // Show results
            $this->info('ðŸŽ‰ Task forces migration completed!');
            $this->table(['Metric', 'Count'], [
                ['Total Records', $totalCount],
                ['Successfully Processed', $migratedCount],
                ['Skipped (Already Exist)', $skippedCount],
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

    private function cleanString(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        return trim($value);
    }

    private function convertBoolean($value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['1', 'true', 'yes', 'y', 'on']);
        }

        return (bool) $value;
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
        $totalForces = DB::table('task_forces')->count();
        $activeForces = DB::table('task_forces')->where('inactive', false)->count();
        $inactiveForces = DB::table('task_forces')->where('inactive', true)->count();

        $withTeamLeader = DB::table('task_forces')->whereNotNull('team_leader_user_id')->count();
        $withAssistLeader = DB::table('task_forces')->whereNotNull('assist_team_leader_user_id')->count();

        $byType = DB::table('task_forces')
            ->select('task_force_type_id', DB::raw('COUNT(*) as count'))
            ->groupBy('task_force_type_id')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();

        $this->info('ðŸ“ˆ Task Forces Statistics:');
        $this->line("  - Total task forces: {$totalForces}");
        $this->line("  - Active: {$activeForces}");
        $this->line("  - Inactive: {$inactiveForces}");
        $this->line("  - With team leader: {$withTeamLeader}");
        $this->line("  - With assistant team leader: {$withAssistLeader}");

        if ($byType->isNotEmpty()) {
            $this->line("  - Top task force types:");
            foreach ($byType as $type) {
                $this->line("    â€¢ Type ID {$type->task_force_type_id}: {$type->count} forces");
            }
        }

        // Show sample forces
        $sampleForces = DB::table('task_forces')
            ->select('name', 'task_force_type_id', 'inactive', 'team_leader_user_id')
            ->orderBy('name')
            ->limit(10)
            ->get();

        if ($sampleForces->isNotEmpty()) {
            $this->line("  - Sample task forces:");
            foreach ($sampleForces as $force) {
                $status = $force->inactive ? ' [Inactive]' : ' [Active]';
                $leader = $force->team_leader_user_id ? " (Leader: {$force->team_leader_user_id})" : ' (No Leader)';
                $this->line("    â€¢ {$force->name} (Type: {$force->task_force_type_id}){$status}{$leader}");
            }
        }
    }
}
