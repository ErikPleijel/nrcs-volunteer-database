<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateSignatureTitles extends Command
{
    protected $signature = 'migrate:signature-titles
                            {--chunk=500 : Number of records to process per chunk}
                            {--clear : Clear existing signature titles before migration}
                            {--dry-run : Run without making changes}';

    protected $description = 'Migrate signature titles from old database, preserving SignatureTitleID as id';

    public function handle()
    {
        $chunk = $this->option('chunk');
        $clear = $this->option('clear');
        $dryRun = $this->option('dry-run');

        $this->info('âœï¸ Starting signature titles migration...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made to the database');
        }

        // Clear existing data if requested
        if ($clear && !$dryRun && $this->confirm('This will delete all existing signature titles. Continue?')) {
            DB::table('signature_titles')->delete();
            $this->info('âœ… Existing signature titles cleared');
        }

        // Check if signature_titles table exists
        if (!Schema::hasTable('signature_titles')) {
            $this->error('âŒ Signature titles table does not exist. Please run migrations first.');
            return Command::FAILURE;
        }

        try {
            $totalCount = DB::connection('old_db')->table('signaturetitles')->count();
            $migratedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            if ($totalCount === 0) {
                $this->warn('âš ï¸ No signature titles found in old database');
                return Command::SUCCESS;
            }

            $this->info("ðŸ“Š Found {$totalCount} signature titles to migrate");

            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            // Process in chunks
            DB::connection('old_db')
                ->table('signaturetitles')
                ->orderBy('SignatureTitleID')
                ->chunk($chunk, function ($signatureTitles) use (&$migratedCount, &$skippedCount, &$errorCount, $progressBar, $dryRun) {

                    $titleData = [];

                    foreach ($signatureTitles as $title) {
                        try {
                            // Check if signature title already exists
                            if (!$dryRun) {
                                $exists = DB::table('signature_titles')
                                    ->where('id', $title->SignatureTitleID)
                                    ->exists();

                                if ($exists) {
                                    $skippedCount++;
                                    $progressBar->advance();
                                    continue;
                                }
                            }

                            $newTitle = [
                                'id' => $title->SignatureTitleID, // Preserve original ID
                                'name' => $this->cleanString($title->SignatureTitleName),
                                'level' => (int) $title->Level,
                                'include_in_list' => $this->convertBoolean($title->Include_in_list),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            $titleData[] = $newTitle;
                            $migratedCount++;

                        } catch (\Exception $e) {
                            $errorCount++;
                            $this->error("Failed to process signature title {$title->SignatureTitleID}: " . $e->getMessage());
                        }

                        $progressBar->advance();
                    }

                    // Insert batch if not dry run
                    if (!$dryRun && !empty($titleData)) {
                        DB::table('signature_titles')->insert($titleData);
                    }
                });

            $progressBar->finish();
            $this->newLine(2);

            // Reset auto-increment to continue from the highest ID + 1
            if (!$dryRun) {
                $maxId = DB::table('signature_titles')->max('id') ?? 0;
                DB::statement("ALTER TABLE signature_titles AUTO_INCREMENT = " . ($maxId + 1));
                $this->info("Set AUTO_INCREMENT to " . ($maxId + 1));
            }

            // Show results
            $this->info('ðŸŽ‰ Signature titles migration completed!');
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
        $totalTitles = DB::table('signature_titles')->count();
        $includedInList = DB::table('signature_titles')->where('include_in_list', true)->count();
        $byLevel = DB::table('signature_titles')
            ->select('level', DB::raw('COUNT(*) as count'))
            ->groupBy('level')
            ->orderBy('level')
            ->get();

        $this->info('ðŸ“Š Signature Titles Statistics:');
        $this->line("  - Total signature titles: {$totalTitles}");
        $this->line("  - Included in list: {$includedInList}");

        if ($byLevel->isNotEmpty()) {
            $this->line("  - Level distribution:");
            foreach ($byLevel as $level) {
                $this->line("    â€¢ Level {$level->level}: {$level->count} titles");
            }
        }

        // Show sample titles
        $sampleTitles = DB::table('signature_titles')
            ->select('name', 'level', 'include_in_list')
            ->orderBy('name')
            ->limit(10)
            ->get();

        if ($sampleTitles->isNotEmpty()) {
            $this->line("  - Sample signature titles:");
            foreach ($sampleTitles as $title) {
                $included = $title->include_in_list ? ' [Listed]' : ' [Hidden]';
                $this->line("    â€¢ {$title->name} (Level {$title->level}){$included}");
            }
        }
    }
}
