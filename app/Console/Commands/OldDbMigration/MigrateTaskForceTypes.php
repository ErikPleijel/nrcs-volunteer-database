<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateTaskForceTypes extends Command
{
    protected $signature = 'migrate:task-force-types
                            {--chunk=500 : Number of records to process per chunk}
                            {--clear : Clear existing task force types before migration}
                            {--dry-run : Run without making changes}';

    protected $description = 'Migrate task force types from old database, preserving TaskForceTypeID as id';

    public function handle()
    {
        $chunk = $this->option('chunk');
        $clear = $this->option('clear');
        $dryRun = $this->option('dry-run');

        $this->info('ðŸš€ Starting task force types migration...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made to the database');
        }

        // Clear existing data if requested
        if ($clear && !$dryRun && $this->confirm('This will delete all existing task force types. Continue?')) {
            DB::table('task_force_types')->delete();
            $this->info('âœ… Existing task force types cleared');
        }

        // Check if task_force_types table exists
        if (!Schema::hasTable('task_force_types')) {
            $this->error('âŒ Task force types table does not exist. Please run migrations first.');
            return Command::FAILURE;
        }

        try {
            $totalCount = DB::connection('old_db')->table('taskforcetypes')->count();
            $migratedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            if ($totalCount === 0) {
                $this->warn('âš ï¸ No task force types found in old database');
                return Command::SUCCESS;
            }

            $this->info("ðŸ“Š Found {$totalCount} task force types to migrate");

            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            // Process in chunks
            DB::connection('old_db')
                ->table('taskforcetypes')
                ->orderBy('TaskForceTypeID')
                ->chunk($chunk, function ($taskForceTypes) use (&$migratedCount, &$skippedCount, &$errorCount, $progressBar, $dryRun) {

                    $typeData = [];

                    foreach ($taskForceTypes as $type) {
                        try {
                            // Check if task force type already exists
                            if (!$dryRun) {
                                $exists = DB::table('task_force_types')
                                    ->where('id', $type->TaskForceTypeID)
                                    ->exists();

                                if ($exists) {
                                    $skippedCount++;
                                    $progressBar->advance();
                                    continue;
                                }
                            }

                            $newType = [
                                'id' => $type->TaskForceTypeID, // Preserve original ID
                                'name' => $this->cleanString($type->TaskForceTypeName),
                                'level' => (int) $type->Level,
                                'include_in_list' => $this->convertBoolean($type->Include_in_list),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            $typeData[] = $newType;
                            $migratedCount++;

                        } catch (\Exception $e) {
                            $errorCount++;
                            $this->error("Failed to process task force type {$type->TaskForceTypeID}: " . $e->getMessage());
                        }

                        $progressBar->advance();
                    }

                    // Insert batch if not dry run
                    if (!$dryRun && !empty($typeData)) {
                        DB::table('task_force_types')->insert($typeData);
                    }
                });

            $progressBar->finish();
            $this->newLine(2);

            // Reset auto-increment to continue from the highest ID + 1
            if (!$dryRun) {
                $maxId = DB::table('task_force_types')->max('id') ?? 0;
                DB::statement("ALTER TABLE task_force_types AUTO_INCREMENT = " . ($maxId + 1));
                $this->info("âœ… Set AUTO_INCREMENT to " . ($maxId + 1));
            }

            // Show results
            $this->info('ðŸŽ‰ Task force types migration completed!');
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

    private function showStatistics()
    {
        $totalTypes = DB::table('task_force_types')->count();
        $includedInList = DB::table('task_force_types')->where('include_in_list', true)->count();
        $byLevel = DB::table('task_force_types')
            ->select('level', DB::raw('COUNT(*) as count'))
            ->groupBy('level')
            ->orderBy('level')
            ->get();

        $this->info('ðŸ“ˆ Task Force Types Statistics:');
        $this->line("  - Total task force types: {$totalTypes}");
        $this->line("  - Included in list: {$includedInList}");

        if ($byLevel->isNotEmpty()) {
            $this->line("  - Level distribution:");
            foreach ($byLevel as $level) {
                $this->line("    â€¢ Level {$level->level}: {$level->count} types");
            }
        }

        // Show sample types
        $sampleTypes = DB::table('task_force_types')
            ->select('name', 'level', 'include_in_list')
            ->orderBy('name')
            ->limit(10)
            ->get();

        if ($sampleTypes->isNotEmpty()) {
            $this->line("  - Sample task force types:");
            foreach ($sampleTypes as $type) {
                $included = $type->include_in_list ? ' [Listed]' : ' [Hidden]';
                $this->line("    â€¢ {$type->name} (Level {$type->level}){$included}");
            }
        }
    }
}
