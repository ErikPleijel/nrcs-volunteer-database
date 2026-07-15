<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigratePositions extends Command
{
    protected $signature = 'migrate:positions
                            {--chunk=500 : Number of records to process per chunk}
                            {--clear : Clear existing positions before migration}
                            {--dry-run : Run without making changes}';

    protected $description = 'Migrate positions from old database, preserving PositionID as id';

    public function handle()
    {
        $chunk = $this->option('chunk');
        $clear = $this->option('clear');
        $dryRun = $this->option('dry-run');

        $this->info('ðŸ¢ Starting positions migration...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made to the database');
        }

        // Clear existing data if requested
        if ($clear && !$dryRun && $this->confirm('This will delete all existing positions. Continue?')) {
            DB::table('positions')->delete();
            $this->info('âœ… Existing positions cleared');
        }

        // Check if positions table exists
        if (!Schema::hasTable('positions')) {
            $this->error('âŒ Positions table does not exist. Please run migrations first.');
            return Command::FAILURE;
        }

        try {
            $totalCount = DB::connection('old_db')->table('positions')->count();
            $migratedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            if ($totalCount === 0) {
                $this->warn('âš ï¸ No positions found in old database');
                return Command::SUCCESS;
            }

            $this->info("ðŸ“Š Found {$totalCount} positions to migrate");

            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            // Process in chunks
            DB::connection('old_db')
                ->table('positions')
                ->orderBy('PositionID')
                ->chunk($chunk, function ($positions) use (&$migratedCount, &$skippedCount, &$errorCount, $progressBar, $dryRun) {

                    $positionData = [];

                    foreach ($positions as $position) {
                        try {
                            // Check if position already exists
                            if (!$dryRun) {
                                $exists = DB::table('positions')
                                    ->where('id', $position->PositionID)
                                    ->exists();

                                if ($exists) {
                                    $skippedCount++;
                                    $progressBar->advance();
                                    continue;
                                }
                            }

                            $newPosition = [
                                'id' => $position->PositionID, // Preserve original ID
                                'name' => $this->cleanString($position->PositionName),
                                'level' => $position->Level,
                                'include_in_list' => $this->convertBoolean($position->Include_in_list),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            $positionData[] = $newPosition;
                            $migratedCount++;

                        } catch (\Exception $e) {
                            $errorCount++;
                            $this->error("Failed to process position {$position->PositionID}: " . $e->getMessage());
                        }

                        $progressBar->advance();
                    }

                    // Insert batch if not dry run
                    if (!$dryRun && !empty($positionData)) {
                        DB::table('positions')->insert($positionData);
                    }
                });

            $progressBar->finish();
            $this->newLine(2);

            // Reset auto-increment to continue from the highest ID + 1
            if (!$dryRun) {
                $maxId = DB::table('positions')->max('id') ?? 0;
                DB::statement("ALTER TABLE positions AUTO_INCREMENT = " . ($maxId + 1));
                $this->info("Set AUTO_INCREMENT to " . ($maxId + 1));
            }

            // Show results
            $this->info('ðŸŽ‰ Positions migration completed!');
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
        $totalPositions = DB::table('positions')->count();
        $includedInList = DB::table('positions')->where('include_in_list', true)->count();
        $withLevels = DB::table('positions')->whereNotNull('level')->count();

        $this->info('ðŸ“Š Positions Statistics:');
        $this->line("  - Total positions: {$totalPositions}");
        $this->line("  - Included in list: {$includedInList}");
        $this->line("  - With level assigned: {$withLevels}");

        // Show level distribution
        $levelDistribution = DB::table('positions')
            ->select('level', DB::raw('COUNT(*) as count'))
            ->whereNotNull('level')
            ->groupBy('level')
            ->orderBy('level')
            ->get();

        if ($levelDistribution->isNotEmpty()) {
            $this->line("  - Level distribution:");
            foreach ($levelDistribution as $dist) {
                $this->line("    â€¢ Level {$dist->level}: {$dist->count} positions");
            }
        }

        // Show sample positions
        $samplePositions = DB::table('positions')
            ->select('name', 'level', 'include_in_list')
            ->orderBy('name')
            ->limit(10)
            ->get();

        if ($samplePositions->isNotEmpty()) {
            $this->line("  - Sample positions:");
            foreach ($samplePositions as $pos) {
                $level = $pos->level ? " (Level {$pos->level})" : '';
                $included = $pos->include_in_list ? ' [Listed]' : ' [Hidden]';
                $this->line("    â€¢ {$pos->name}{$level}{$included}");
            }
        }
    }
}
