<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateActivityTypes extends Command
{
    protected $signature = 'migrate:activity-types
                            {--chunk=500 : Number of records to process per chunk}
                            {--clear : Clear existing activity types before migration}
                            {--dry-run : Run without making changes}';

    protected $description = 'Migrate activity types from old database, preserving ActivityTypeID as id';

    public function handle()
    {
        $chunk = $this->option('chunk');
        $clear = $this->option('clear');
        $dryRun = $this->option('dry-run');

        $this->info('ðŸŽ¯ Starting activity types migration...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made to the database');
        }

        // Clear existing data if requested
        if ($clear && !$dryRun && $this->confirm('This will delete all existing activity types. Continue?')) {
            DB::table('activity_types')->delete();
            $this->info('âœ… Existing activity types cleared');
        }

        // Check if activity_types table exists
        if (!Schema::hasTable('activity_types')) {
            $this->error('âŒ Activity types table does not exist. Please run migrations first.');
            return Command::FAILURE;
        }

        try {
            $totalCount = DB::connection('old_db')->table('activitytypes')->count();
            $migratedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            if ($totalCount === 0) {
                $this->warn('âš ï¸ No activity types found in old database');
                return Command::SUCCESS;
            }

            $this->info("ðŸ“Š Found {$totalCount} activity types to migrate");

            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            // Process in chunks
            DB::connection('old_db')
                ->table('activitytypes')
                ->orderBy('ActivityTypeID')
                ->chunk($chunk, function ($activityTypes) use (&$migratedCount, &$skippedCount, &$errorCount, $progressBar, $dryRun) {

                    $activityTypeData = [];

                    foreach ($activityTypes as $activityType) {
                        try {
                            // Check if activity type already exists
                            if (!$dryRun) {
                                $exists = DB::table('activity_types')
                                    ->where('id', $activityType->ActivityTypeID)
                                    ->exists();

                                if ($exists) {
                                    $skippedCount++;
                                    $progressBar->advance();
                                    continue;
                                }
                            }

                            $newActivityType = [
                                'id' => $activityType->ActivityTypeID, // Preserve original ID
                                'name' => $this->cleanString($activityType->ActivityName),
                                'is_active' => $this->convertBoolean($activityType->Include_in_list, true),
                                'description' => null, // New field, no old data
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            $activityTypeData[] = $newActivityType;
                            $migratedCount++;

                        } catch (\Exception $e) {
                            $errorCount++;
                            $this->error("Failed to process activity type {$activityType->ActivityTypeID}: " . $e->getMessage());
                        }

                        $progressBar->advance();
                    }

                    // Insert batch if not dry run
                    if (!$dryRun && !empty($activityTypeData)) {
                        DB::table('activity_types')->insert($activityTypeData);
                    }
                });

            $progressBar->finish();
            $this->newLine(2);

            // Reset auto-increment to continue from the highest ID + 1
            if (!$dryRun) {
                $maxId = DB::table('activity_types')->max('id') ?? 0;
                DB::statement("ALTER TABLE activity_types AUTO_INCREMENT = " . ($maxId + 1));
                $this->info("Set AUTO_INCREMENT to " . ($maxId + 1));
            }

            // Show results
            $this->info('ðŸŽ‰ Activity types migration completed!');
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

        // Clean HTML entities and trim
        $cleaned = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $cleaned;
    }

    private function convertBoolean($value, $defaultIfNull = false): bool
    {
        if ($value === null) {
            return $defaultIfNull;
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
        $totalTypes = DB::table('activity_types')->count();
        $activeTypes = DB::table('activity_types')->where('is_active', true)->count();
        $inactiveTypes = DB::table('activity_types')->where('is_active', false)->count();

        $this->info('ðŸ“Š Activity Types Statistics:');
        $this->line("  - Total activity types: {$totalTypes}");
        $this->line("  - Active activity types: {$activeTypes}");
        $this->line("  - Inactive activity types: {$inactiveTypes}");

        // Show some sample activity types
        $sampleTypes = DB::table('activity_types')
            ->select('name', 'is_active')
            ->limit(10)
            ->get();

        if ($sampleTypes->isNotEmpty()) {
            $this->line("  - Sample activity types:");
            foreach ($sampleTypes as $type) {
                $status = $type->is_active ? 'âœ…' : 'âŒ';
                $this->line("    â€¢ {$status} {$type->name}");
            }
        }

        // Show longest activity type names (might need adjustment)
        $longestName = DB::table('activity_types')
            ->selectRaw('name, LENGTH(name) as name_length')
            ->orderByDesc('name_length')
            ->first();

        if ($longestName) {
            $this->line("  - Longest activity type name: {$longestName->name_length} characters");
            if ($longestName->name_length > 100) {
                $this->warn("    âš ï¸ Some names might be truncated (limit: 100 chars)");
            }
        }
    }
}
