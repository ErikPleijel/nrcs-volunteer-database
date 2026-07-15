<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class SeedUserTitlesFromOldDb extends Command
{
    protected $signature = 'seed:user-titles
                            {--connection=old_db : Old database connection name (config/database.php)}
                            {--chunk=500 : Number of records to process per chunk}
                            {--dry-run : Run without making changes}
                            {--overwrite : Overwrite existing users.title values}
                            {--only-missing : Only fill users where title is null/empty (default)}';

    protected $description = 'Seed users.title from old DB persons.TitleID -> titles.Title (match persons.PersonID = users.id)';

    public function handle(): int
    {
        $oldConn = (string) $this->option('connection');
        $chunk   = (int) $this->option('chunk');
        $dryRun  = (bool) $this->option('dry-run');
        $overwrite = (bool) $this->option('overwrite');
        $onlyMissing = (bool) $this->option('only-missing');

        // If both are set, overwrite wins.
        if ($overwrite) {
            $onlyMissing = false;
        }

        $this->info('ðŸ·ï¸  Seeding users.title from old database...');
        $this->line("Old DB connection: <info>{$oldConn}</info>");
        $this->line("Chunk size: <info>{$chunk}</info>");
        $this->line('Mode: ' . ($dryRun ? '<comment>DRY RUN</comment>' : '<info>LIVE</info>'));
        $this->line('Update strategy: ' . ($overwrite ? '<comment>OVERWRITE</comment>' : '<info>ONLY MISSING</info>'));

        // Sanity: check connection works
        try {
            DB::connection($oldConn)->getPdo();
        } catch (\Throwable $e) {
            $this->error("âŒ Could not connect to old DB connection '{$oldConn}': " . $e->getMessage());
            return Command::FAILURE;
        }

        // Count rows available in old DB (only those with a title string)
        $baseQuery = DB::connection($oldConn)
            ->table('persons')
            ->leftJoin('titles', 'persons.TitleID', '=', 'titles.TitleID')
            ->select([
                'persons.PersonID as person_id',
                'titles.Title as title',
            ])
            ->whereNotNull('persons.PersonID')
            ->whereNotNull('titles.Title')
            ->where('titles.Title', '!=', '');

        $total = (clone $baseQuery)->count();

        if ($total === 0) {
            $this->warn('âš ï¸ No matching old records found (persons with non-empty titles). Nothing to do.');
            return Command::SUCCESS;
        }

        $this->info("ðŸ“Š Found {$total} old person records with a title to process.");

        $updated = 0;
        $skippedNoUser = 0;
        $skippedHasTitle = 0;
        $skippedEmptyTitle = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        // Process old records in chunks by PersonID to keep memory stable
        $baseQuery
            ->orderBy('persons.PersonID')
            ->chunk($chunk, function ($rows) use (
                &$updated,
                &$skippedNoUser,
                &$skippedHasTitle,
                &$skippedEmptyTitle,
                &$errors,
                $dryRun,
                $overwrite,
                $bar
            ) {
                foreach ($rows as $row) {
                    try {
                        $personId = (int) ($row->person_id ?? 0);
                        $titleRaw = $row->title ?? null;
                        $title = is_string($titleRaw) ? trim($titleRaw) : '';

                        if ($personId <= 0) {
                            $errors++;
                            $bar->advance();
                            continue;
                        }

                        if ($title === '') {
                            $skippedEmptyTitle++;
                            $bar->advance();
                            continue;
                        }

                        /** @var \App\Models\User|null $user */
                        $user = User::find($personId);

                        if (!$user) {
                            $skippedNoUser++;
                            $bar->advance();
                            continue;
                        }

                        $current = is_string($user->title ?? null) ? trim((string) $user->title) : '';

                        if (!$overwrite && $current !== '') {
                            $skippedHasTitle++;
                            $bar->advance();
                            continue;
                        }

                        if (!$dryRun) {
                            $user->title = $title;
                            $user->save();
                        }

                        $updated++;
                    } catch (\Throwable $e) {
                        $errors++;
                        $this->error("Failed on PersonID {$row->person_id}: " . $e->getMessage());
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->info('âœ… Done seeding titles.');
        $this->table(['Metric', 'Count'], [
            ['Old records processed', $total],
            ['Users updated', $updated],
            ['Skipped (user not found in new DB)', $skippedNoUser],
            ['Skipped (user already had title)', $skippedHasTitle],
            ['Skipped (empty/invalid title in old DB)', $skippedEmptyTitle],
            ['Errors', $errors],
        ]);

        if ($dryRun) {
            $this->warn('DRY RUN: No changes were saved.');
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
