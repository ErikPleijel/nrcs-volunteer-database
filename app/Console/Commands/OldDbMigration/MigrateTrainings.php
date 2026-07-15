<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateTrainings extends Command
{
    protected $signature = 'migrate:trainings
                            {--chunk=500 : Number of records to process per chunk}
                            {--clear : Clear existing trainings before migration}
                            {--dry-run : Run without making changes}';

    protected $description = 'Migrate trainings from old database, preserving TrainingID as id';

    public function handle()
    {
        $chunk = $this->option('chunk');
        $clear = $this->option('clear');
        $dryRun = $this->option('dry-run');

        $this->info('ðŸŽ“ Starting trainings migration...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made to the database');
        }

        // Clear existing data if requested
        if ($clear && !$dryRun && $this->confirm('This will delete all existing trainings. Continue?')) {
            DB::table('trainings')->delete();
            $this->info('âœ… Existing trainings cleared');
        }

        // Check if trainings table exists
        if (!Schema::hasTable('trainings')) {
            $this->error('âŒ Trainings table does not exist. Please run migrations first.');
            return Command::FAILURE;
        }

        try {
            $totalCount = DB::connection('old_db')->table('trainings')->count();
            $migratedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            if ($totalCount === 0) {
                $this->warn('âš ï¸ No trainings found in old database');
                return Command::SUCCESS;
            }

            $this->info("ðŸ“Š Found {$totalCount} trainings to migrate");

            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            // Process in chunks
            DB::connection('old_db')
                ->table('trainings')
                ->orderBy('TrainingID')
                ->chunk($chunk, function ($trainings) use (&$migratedCount, &$skippedCount, &$errorCount, $progressBar, $dryRun) {

                    $trainingData = [];

                    foreach ($trainings as $training) {
                        try {
                            // Check if training already exists
                            if (!$dryRun) {
                                $exists = DB::table('trainings')
                                    ->where('id', $training->TrainingID)
                                    ->exists();

                                if ($exists) {
                                    $skippedCount++;
                                    $progressBar->advance();
                                    continue;
                                }
                            }

                            $newTraining = [
                                'id' => $training->TrainingID, // Preserve original ID
                                'user_id' => $training->PersonID,
                                'training_type_id' => $training->TrainingTypeID,
                                'training_date' => $training->TrainingDate,
                                'duration' => $training->Duration,
                                'valid_years' => $training->ValidYears,
                                'submitted_at' => $training->Timestamp,
                                'submission_name' => $this->cleanString($training->SubmissionName),
                                'is_deleted' => $this->convertBoolean($training->IsDeleted),
                                'reference' => $this->cleanString($training->Reference),
                                'submitted_by_user_id' => $training->SubmissionID,
                                'branch_id' => $training->BranchID,
                                'division_id' => $training->DivisionID,
                                'created_at' => now(),
                                'updated_at' => now(),
                                // Legacy records are pre-approved (no approval step existed).
                                'approval_status' => 'approved',
                                'decided_at' => $training->TrainingDate ?? $training->Timestamp ?? now(),
                                'decided_by_user_id' => null,
                            ];

                            $trainingData[] = $newTraining;
                            $migratedCount++;

                        } catch (\Exception $e) {
                            $errorCount++;
                            $this->error("Failed to process training {$training->TrainingID}: " . $e->getMessage());
                        }

                        $progressBar->advance();
                    }

                    // Insert batch if not dry run
                    if (!$dryRun && !empty($trainingData)) {
                        DB::table('trainings')->insert($trainingData);
                    }
                });

            $progressBar->finish();
            $this->newLine(2);

            // Reset auto-increment to continue from the highest ID + 1
            if (!$dryRun) {
                $maxId = DB::table('trainings')->max('id') ?? 0;
                DB::statement("ALTER TABLE trainings AUTO_INCREMENT = " . ($maxId + 1));
                $this->info("Set AUTO_INCREMENT to " . ($maxId + 1));
            }

            // Show results
            $this->info('ðŸŽ‰ Trainings migration completed!');
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
        $totalTrainings = DB::table('trainings')->count();
        $activeTrainings = DB::table('trainings')->where('is_deleted', false)->count();
        $withDuration = DB::table('trainings')->whereNotNull('duration')->count();
        $withValidYears = DB::table('trainings')->whereNotNull('valid_years')->count();

        $this->info('ðŸ“Š Training Statistics:');
        $this->line("  - Total trainings: {$totalTrainings}");
        $this->line("  - Active trainings: {$activeTrainings}");
        $this->line("  - With duration: {$withDuration}");
        $this->line("  - With validity period: {$withValidYears}");
    }
}
