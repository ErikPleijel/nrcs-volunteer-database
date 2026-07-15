<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Exception;

class MigrateBranches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:branches {--dry-run : Run without making changes} {--chunk=100 : Number of records to process at once}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import branches from old DB. WARNING: deletes and re-inserts all branch rows â€” always run db:seed --class=BranchSeeder immediately afterward to restore latitude/longitude coordinates.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->input->isInteractive() && ! $this->confirm(
            'WARNING: This will DELETE all branch rows and re-insert them ' .
            'without coordinates. BranchSeeder will run automatically afterward ' .
            'to restore coordinates. Continue?'
        )) {
            $this->info('Aborted.');
            return;
        }

        $dryRun = $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');

        $this->info('Starting branches migration...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made to the database');
        }

        try {
            // Get count of records to migrate
            $totalRecords = DB::connection('old_db')->table('branches')->count();
            $this->info("Found {$totalRecords} branches to migrate");

            if ($totalRecords === 0) {
                $this->warn('No branches found to migrate');
                return Command::SUCCESS;
            }

            // Clear existing data if not dry run
            if (!$dryRun) {
                DB::table('branches')->delete();
                $this->info('Cleared existing branches data');
            }

            $bar = $this->output->createProgressBar($totalRecords);
            $bar->start();

            $processedCount = 0;
            $errorCount = 0;

            // Process branches in chunks
            DB::connection('old_db')
                ->table('branches')
                ->orderBy('BranchID')
                ->chunk($chunkSize, function ($branches) use (&$processedCount, &$errorCount, $dryRun, $bar) {

                    $branchData = [];

                    foreach ($branches as $oldBranch) {
                        try {
                            $newBranch = [
                                'id' => $oldBranch->BranchID, // Preserve original ID
                                'name' => $this->cleanString($oldBranch->Branch),
                                'code' => $this->cleanString($oldBranch->BranchCode),
                                'zone' => $this->cleanString($oldBranch->Zone),
                                'is_active' => $this->convertBoolean($oldBranch->Include_in_list),
                                'physical_address' => $this->cleanString($oldBranch->Physical_address),
                                'postal_address' => $this->cleanString($oldBranch->Postal_address),
                                'telephone' => $this->cleanString($oldBranch->Telephone),
                                'email' => $this->cleanEmail($oldBranch->Email),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            $branchData[] = $newBranch;
                            $processedCount++;

                        } catch (Exception $e) {
                            $errorCount++;
                            $this->error("Error processing branch ID {$oldBranch->BranchID}: " . $e->getMessage());
                        }

                        $bar->advance();
                    }

                    // Insert batch if not dry run
                    if (!$dryRun && !empty($branchData)) {
                        DB::table('branches')->insert($branchData);
                    }
                });

            $bar->finish();
            $this->newLine();

            // Reset auto-increment to continue from the highest ID + 1
            if (!$dryRun) {
                $maxId = DB::table('branches')->max('id') ?? 0;
                DB::statement("ALTER TABLE branches AUTO_INCREMENT = " . ($maxId + 1));
                $this->info("Set AUTO_INCREMENT to " . ($maxId + 1));
            }

            // Summary
            $this->newLine();
            $this->info("Migration completed!");
            $this->table(['Metric', 'Count'], [
                ['Total Records', $totalRecords],
                ['Successfully Processed', $processedCount],
                ['Errors', $errorCount],
                ['Success Rate', $totalRecords > 0 ? round(($processedCount / $totalRecords) * 100, 2) . '%' : '0%']
            ]);

            if ($dryRun) {
                $this->warn('This was a DRY RUN - no data was actually migrated');
                $this->info('Run without --dry-run to perform the actual migration');
            }

            if (! $dryRun) {
                // Always re-seed coordinates after branch import (lat/lng not in old DB)
                $this->info('Re-seeding branch coordinates via BranchSeeder...');
                $this->call('db:seed', ['--class' => 'BranchSeeder', '--force' => true]);
                $this->info('Branch coordinates restored. Verify any NULL rows above.');
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Clean and trim string values
     */
    private function cleanString(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * Convert various boolean representations to proper boolean
     */
    private function convertBoolean($value): bool
    {
        if ($value === null) {
            return true; // Default to active if null
        }

        // Handle various boolean representations
        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['1', 'true', 'yes', 'y', 'on']);
        }

        return (bool) $value;
    }

    /**
     * Clean and validate email addresses
     */
    private function cleanEmail(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        $cleanedEmail = trim($email);

        // Basic email validation
        if (filter_var($cleanedEmail, FILTER_VALIDATE_EMAIL)) {
            return $cleanedEmail;
        }

        // Log invalid emails but don't fail the migration
        $this->warn("Invalid email format: {$cleanedEmail}");
        return $cleanedEmail; // Keep original for manual review
    }
}
