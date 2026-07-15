<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateDivisions extends Command
{
    protected $signature = 'migrate:divisions
                            {--chunk=500 : Number of records to process per chunk}
                            {--clear : Clear existing divisions before migration}
                            {--dry-run : Run without making changes}';

    protected $description = 'Migrate divisions from old database, preserving DivisionID as id';

    public function handle()
    {
        $chunk  = $this->option('chunk');
        $clear  = $this->option('clear');
        $dryRun = $this->option('dry-run');

        $this->info('ðŸ¢ Starting divisions migration...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made to the database');
        }

        // Clear existing data if requested
        if ($clear && !$dryRun && $this->confirm('This will delete all existing divisions. Continue?')) {
            DB::table('divisions')->delete();
            $this->info('âœ… Existing divisions cleared');
        }

        // Check if divisions table exists
        if (!Schema::hasTable('divisions')) {
            $this->error('âŒ Divisions table does not exist. Please run migrations first.');
            return Command::FAILURE;
        }

        try {
            // ðŸ”¹ Base query: ONLY include records where Include_in_list is true/1
            $baseQuery = DB::connection('old_db')
                ->table('divisions')
                ->where(function ($q) {
                    // Adjust this to your actual storage form if needed
                    $q->where('Include_in_list', 1)
                        ->orWhere('Include_in_list', true)
                        ->orWhere('Include_in_list', '1');
                });

            // Use the filtered query for total count
            $totalCount = (clone $baseQuery)->count();

            $migratedCount = 0;
            $skippedCount  = 0;
            $errorCount    = 0;

            if ($totalCount === 0) {
                $this->warn('âš ï¸ No divisions found in old database (with Include_in_list = true)');
                return Command::SUCCESS;
            }

            $this->info("ðŸ“Š Found {$totalCount} divisions to migrate (Include_in_list = true)");

            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            // Process in chunks using the SAME filter
            (clone $baseQuery)
                ->orderBy('DivisionID')
                ->chunk($chunk, function ($divisions) use (&$migratedCount, &$skippedCount, &$errorCount, $progressBar, $dryRun) {

                    $divisionData = [];

                    foreach ($divisions as $division) {
                        try {
                            // Check if division already exists
                            if (!$dryRun) {
                                $exists = DB::table('divisions')
                                    ->where('id', $division->DivisionID)
                                    ->exists();

                                if ($exists) {
                                    $skippedCount++;
                                    $progressBar->advance();
                                    continue;
                                }
                            }

                            $newDivision = [
                                'id'         => $division->DivisionID, // Preserve original ID
                                'name'       => $this->cleanString($division->Division),
                                'branch_id'  => $this->getBranchId($division->BranchID ?? null),
                                'is_active'  => $this->convertBoolean($division->Include_in_list),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            $divisionData[] = $newDivision;
                            $migratedCount++;

                        } catch (\Exception $e) {
                            $errorCount++;
                            $this->error("Failed to process division {$division->DivisionID}: " . $e->getMessage());
                        }

                        $progressBar->advance();
                    }

                    // Insert batch if not dry run
                    if (!$dryRun && !empty($divisionData)) {
                        DB::table('divisions')->insert($divisionData);
                    }
                });

            $progressBar->finish();
            $this->newLine(2);

            // Reset auto-increment to continue from the highest ID + 1
            if (!$dryRun) {
                $maxId = DB::table('divisions')->max('id') ?? 0;
                DB::statement("ALTER TABLE divisions AUTO_INCREMENT = " . ($maxId + 1));
                $this->info("Set AUTO_INCREMENT to " . ($maxId + 1));
            }

            // Show results
            $this->info('ðŸŽ‰ Division migration completed!');
            $this->table(['Metric', 'Count'], [
                ['Total Records (Include_in_list = true)', $totalCount],
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

    private function getBranchId($oldBranchId)
    {
        if (!$oldBranchId) {
            return null;
        }

        // First try by ID (if branches preserved original IDs)
        $branch = DB::table('branches')->where('id', $oldBranchId)->first();
        if ($branch) {
            return $branch->id;
        }

        // If not found by ID, try to get the branch name from old database
        // and match by name in new database
        $oldBranch = DB::connection('old_db')->table('branches')
            ->where('BranchID', $oldBranchId)
            ->first();

        if ($oldBranch) {
            $newBranch = DB::table('branches')
                ->where('name', $this->cleanString($oldBranch->BranchName))
                ->first();

            return $newBranch->id ?? null;
        }

        return null;
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
            return true; // Default to active if null
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
        $totalDivisions = DB::table('divisions')->count();
        $activeDivisions = DB::table('divisions')->where('is_active', true)->count();
        $withBranch = DB::table('divisions')->whereNotNull('branch_id')->count();

        $this->info('ðŸ“Š Division Statistics:');
        $this->line("  - Total divisions: {$totalDivisions}");
        $this->line("  - Active divisions: {$activeDivisions}");
        $this->line("  - With branch assignment: {$withBranch}");
    }
}
