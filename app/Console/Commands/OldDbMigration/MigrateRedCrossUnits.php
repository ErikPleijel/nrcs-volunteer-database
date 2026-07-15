<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateRedCrossUnits extends Command
{
    protected $signature = 'migrate:red-cross-units
                            {--chunk=500 : Number of records to process per chunk}
                            {--clear : Clear existing red cross units before migration}
                            {--dry-run : Run without making changes}';

    protected $description = 'Migrate red cross units from old database, preserving RedCrossUnitID as id';

    public function handle()
    {
        $chunk = $this->option('chunk');
        $clear = $this->option('clear');
        $dryRun = $this->option('dry-run');

        $this->info('ðŸ¥ Starting red cross units migration...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made to the database');
        }

        // Clear existing data if requested
        if ($clear && !$dryRun && $this->confirm('This will delete all existing red cross units. Continue?')) {
            DB::table('red_cross_units')->delete();
            $this->info('âœ… Existing red cross units cleared');
        }

        // Check if red_cross_units table exists
        if (!Schema::hasTable('red_cross_units')) {
            $this->error('âŒ Red cross units table does not exist. Please run migrations first.');
            return Command::FAILURE;
        }

        try {
            $totalCount = DB::connection('old_db')->table('redcrossunits')->count();
            $migratedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            if ($totalCount === 0) {
                $this->warn('âš ï¸ No red cross units found in old database');
                return Command::SUCCESS;
            }

            $this->info("ðŸ“Š Found {$totalCount} red cross units to migrate");

            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            // Process in chunks
            DB::connection('old_db')
                ->table('redcrossunits')
                ->orderBy('RedCrossUnitID')
                ->chunk($chunk, function ($units) use (&$migratedCount, &$skippedCount, &$errorCount, $progressBar, $dryRun) {

                    $unitData = [];

                    foreach ($units as $unit) {
                        try {
                            // Check if unit already exists
                            if (!$dryRun) {
                                $exists = DB::table('red_cross_units')
                                    ->where('id', $unit->RedCrossUnitID)
                                    ->exists();

                                if ($exists) {
                                    $skippedCount++;
                                    $progressBar->advance();
                                    continue;
                                }
                            }

                            $newUnit = [
                                'id' => $unit->RedCrossUnitID, // Preserve original ID
                                'name' => $this->cleanString($unit->RedCrossUnit),
                                'division_id' => $this->getDivisionId($unit->DivisionID ?? null),
                                'team_leader_user_id' => $this->getUserId($unit->TeamLeaderID ?? null),
                                'assistant_team_leader_user_id' => $this->getUserId($unit->AssistTeamLeaderID ?? null),
                                'is_active' => true, // Default to active
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            $unitData[] = $newUnit;
                            $migratedCount++;

                        } catch (\Exception $e) {
                            $errorCount++;
                            $this->error("Failed to process red cross unit {$unit->RedCrossUnitID}: " . $e->getMessage());
                        }

                        $progressBar->advance();
                    }

                    // Insert batch if not dry run
                    if (!$dryRun && !empty($unitData)) {
                        DB::table('red_cross_units')->insert($unitData);
                    }
                });

            $progressBar->finish();
            $this->newLine(2);

            // Reset auto-increment to continue from the highest ID + 1
            if (!$dryRun) {
                $maxId = DB::table('red_cross_units')->max('id') ?? 0;
                DB::statement("ALTER TABLE red_cross_units AUTO_INCREMENT = " . ($maxId + 1));
                $this->info("Set AUTO_INCREMENT to " . ($maxId + 1));
            }

            // Show results
            $this->info('ðŸŽ‰ Red cross units migration completed!');
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

    private function getDivisionId($oldDivisionId)
    {
        if (!$oldDivisionId) {
            return null;
        }

        // First try by ID (if divisions preserved original IDs)
        $division = DB::table('divisions')->where('id', $oldDivisionId)->first();
        if ($division) {
            return $division->id;
        }

        return null;
    }

    private function getUserId($oldUserId)
    {
        if (!$oldUserId) {
            return null;
        }

        // Check if user exists with this ID
        $user = DB::table('users')->where('id', $oldUserId)->first();
        if ($user) {
            return $user->id;
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

    private function showStatistics()
    {
        $totalUnits = DB::table('red_cross_units')->count();
        $activeUnits = DB::table('red_cross_units')->where('is_active', true)->count();
        $withDivision = DB::table('red_cross_units')->whereNotNull('division_id')->count();
        $withTeamLeader = DB::table('red_cross_units')->whereNotNull('team_leader_user_id')->count();
        $withAssistantLeader = DB::table('red_cross_units')->whereNotNull('assistant_team_leader_user_id')->count();

        $this->info('ðŸ“Š Red Cross Units Statistics:');
        $this->line("  - Total units: {$totalUnits}");
        $this->line("  - Active units: {$activeUnits}");
        $this->line("  - With division assignment: {$withDivision}");
        $this->line("  - With team leader: {$withTeamLeader}");
        $this->line("  - With assistant team leader: {$withAssistantLeader}");
    }
}
