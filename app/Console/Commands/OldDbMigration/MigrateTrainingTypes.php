<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateTrainingTypes extends Command
{
    protected $signature = 'migrate:training-types
                            {--chunk=500 : Number of records to process per chunk}
                            {--clear : Clear existing training types before migration}
                            {--dry-run : Run without making changes}';

    protected $description = 'Migrate training types from old database, preserving TrainingTypeID as id';

    public function handle()
    {
        $chunk = $this->option('chunk');
        $clear = $this->option('clear');
        $dryRun = $this->option('dry-run');

        $this->info('ðŸ“š Starting training types migration...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made to the database');
        }

        // Clear existing data if requested
        if ($clear && !$dryRun && $this->confirm('This will delete all existing training types. Continue?')) {
            DB::table('training_types')->delete();
            $this->info('âœ… Existing training types cleared');
        }

        // Check if training_types table exists
        if (!Schema::hasTable('training_types')) {
            $this->error('âŒ Training types table does not exist. Please run migrations first.');
            return Command::FAILURE;
        }

        try {
            $totalCount = DB::connection('old_db')->table('trainingtypes')->count();
            $migratedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            if ($totalCount === 0) {
                $this->warn('âš ï¸ No training types found in old database');
                return Command::SUCCESS;
            }

            $this->info("ðŸ“Š Found {$totalCount} training types to migrate");

            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            // Process in chunks
            DB::connection('old_db')
                ->table('trainingtypes')
                ->orderBy('TrainingTypeID')
                ->chunk($chunk, function ($trainingTypes) use (&$migratedCount, &$skippedCount, &$errorCount, $progressBar, $dryRun) {

                    $trainingTypeData = [];

                    foreach ($trainingTypes as $trainingType) {
                        try {
                            // Check if training type already exists
                            if (!$dryRun) {
                                $exists = DB::table('training_types')
                                    ->where('id', $trainingType->TrainingTypeID)
                                    ->exists();

                                if ($exists) {
                                    $skippedCount++;
                                    $progressBar->advance();
                                    continue;
                                }
                            }

                            $newTrainingType = [
                                'id' => $trainingType->TrainingTypeID, // Preserve original ID
                                'name' => $this->cleanString($trainingType->TrainingName),
                                'is_active' => $this->convertBoolean($trainingType->Include_in_list, true),
                                'validity_years_limit' => $this->convertValidityYears($trainingType->ValidYearsLimit),
                                'certificate_hq_only' => $this->convertBoolean($trainingType->Certificate_HQ_only, true),
                                'description' => null, // New field, no old data
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            $trainingTypeData[] = $newTrainingType;
                            $migratedCount++;

                        } catch (\Exception $e) {
                            $errorCount++;
                            $this->error("Failed to process training type {$trainingType->TrainingTypeID}: " . $e->getMessage());
                        }

                        $progressBar->advance();
                    }

                    // Insert batch if not dry run
                    if (!$dryRun && !empty($trainingTypeData)) {
                        DB::table('training_types')->insert($trainingTypeData);
                    }
                });

            $progressBar->finish();
            $this->newLine(2);

            // Reset auto-increment to continue from the highest ID + 1
            if (!$dryRun) {
                $maxId = DB::table('training_types')->max('id') ?? 0;
                DB::statement("ALTER TABLE training_types AUTO_INCREMENT = " . ($maxId + 1));
                $this->info("Set AUTO_INCREMENT to " . ($maxId + 1));
            }

            // Show results
            $this->info('ðŸŽ‰ Training types migration completed!');
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

    private function convertValidityYears($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $years = (int) $value;

        // Validation: should be reasonable (1-10 years)
        if ($years < 1 || $years > 10) {
            return null;
        }

        return $years;
    }

    private function showStatistics()
    {
        $totalTypes = DB::table('training_types')->count();
        $activeTypes = DB::table('training_types')->where('is_active', true)->count();
        $inactiveTypes = DB::table('training_types')->where('is_active', false)->count();
        $hqOnlyTypes = DB::table('training_types')->where('certificate_hq_only', true)->count();
        $branchTypes = DB::table('training_types')->where('certificate_hq_only', false)->count();

        $this->info('ðŸ“Š Training Types Statistics:');
        $this->line("  - Total training types: {$totalTypes}");
        $this->line("  - Active training types: {$activeTypes}");
        $this->line("  - Inactive training types: {$inactiveTypes}");
        $this->line("  - HQ-only certificates: {$hqOnlyTypes}");
        $this->line("  - Branch certificates: {$branchTypes}");

        // Show validity years distribution
        $validityDistribution = DB::table('training_types')
            ->select('validity_years_limit', DB::raw('COUNT(*) as count'))
            ->groupBy('validity_years_limit')
            ->orderBy('validity_years_limit')
            ->get();

        if ($validityDistribution->isNotEmpty()) {
            $this->line("  - Validity years distribution:");
            foreach ($validityDistribution as $dist) {
                $years = $dist->validity_years_limit ?? 'No limit';
                $this->line("    â€¢ {$years}: {$dist->count} types");
            }
        }

        // Show some sample training types
        $sampleTypes = DB::table('training_types')
            ->select('name', 'is_active', 'validity_years_limit', 'certificate_hq_only')
            ->limit(10)
            ->get();

        if ($sampleTypes->isNotEmpty()) {
            $this->line("  - Sample training types:");
            foreach ($sampleTypes as $type) {
                $status = $type->is_active ? 'âœ…' : 'âŒ';
                $hq = $type->certificate_hq_only ? 'ðŸ¢' : 'ðŸª';
                $validity = $type->validity_years_limit ? "{$type->validity_years_limit}y" : 'N/A';
                $this->line("    â€¢ {$status} {$hq} {$type->name} ({$validity})");
            }
        }

        // Show longest training type names (might need adjustment)
        $longestName = DB::table('training_types')
            ->selectRaw('name, LENGTH(name) as name_length')
            ->orderByDesc('name_length')
            ->first();

        if ($longestName) {
            $this->line("  - Longest training type name: {$longestName->name_length} characters");
            if ($longestName->name_length > 100) {
                $this->warn("    âš ï¸ Some names might be truncated (limit: 100 chars)");
            }
        }
    }
}
