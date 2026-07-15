<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ShiftAllDates extends Command
{
    /**
     * Usage:
     *   php artisan dates:shift 2360
     *   php artisan dates:shift 2360 --dry-run
     *   php artisan dates:shift 2360 --before=2024-01-01
     */
    protected $signature = 'dates:shift
                            {days : Number of days to add (use negative to subtract)}
                            {--dry-run : Show what would be updated without actually running the queries}
                            {--before= : Only shift dates strictly before this date (YYYY-MM-DD). Default: 2025-01-01}';

    protected $description = 'Shift all DATE/DATETIME/TIMESTAMP columns in all tables by a given number of days, up to a cutoff date';

    public function handle(): int
    {
        $days = (int) $this->argument('days');
        $dryRun = (bool) $this->option('dry-run');
        $beforeOption = $this->option('before') ?: '2025-01-01';

        if ($days === 0) {
            $this->error('Days must be non-zero.');
            return self::FAILURE;
        }

        // Validate and normalize the cutoff date
        try {
            $beforeDate = Carbon::createFromFormat('Y-m-d', $beforeOption)
                ->startOfDay()
                ->toDateString(); // YYYY-MM-DD
        } catch (\Exception $e) {
            $this->error("Invalid --before date format. Use YYYY-MM-DD. Given: {$beforeOption}");
            return self::FAILURE;
        }

        $this->warn('âš ï¸  This will shift ALL date/datetime/timestamp columns in the database by '
            . $days . " days, but only for values strictly before {$beforeDate}.");

        if (! $dryRun && ! $this->confirm('Are you absolutely sure you want to continue?', false)) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $databaseName = DB::getDatabaseName();

        $excludedTables = [
            'migrations',
            'failed_jobs',
            'jobs',
            'password_reset_tokens',
            'personal_access_tokens',
            // Add more here if you want to protect them
        ];

        $excludedColumns = [
            // 'created_at',
            // 'updated_at',
            // 'deleted_at',
        ];

        $this->info('Using database: ' . $databaseName);
        $this->info('Fetching tables...');

        $tables = DB::select('SHOW TABLES');

        if (empty($tables)) {
            $this->warn('No tables found.');
            return self::SUCCESS;
        }

        // âœ… Force UTC for THIS SESSION so TIMESTAMP columns won't choke on DST gaps
        // (e.g. Europe/Stockholm: 2024-03-31 02:xx does not exist).
        $originalTimeZone = null;
        try {
            $row = DB::selectOne("SELECT @@session.time_zone AS tz");
            $originalTimeZone = $row->tz ?? null;
        } catch (\Throwable $e) {
            // If we can't read it, we can still set UTC; restore may be skipped.
        }

        try {
            DB::statement("SET time_zone = '+00:00'");
        } catch (\Throwable $e) {
            $this->warn("âš ï¸ Could not set session time_zone to UTC (+00:00). If you have TIMESTAMP columns around DST, you may still hit errors.");
        }

        $tableKey = 'Tables_in_' . $databaseName;
        $totalQueries = 0;

        foreach ($tables as $tableRow) {
            if (! isset($tableRow->$tableKey)) {
                continue;
            }

            $table = $tableRow->$tableKey;

            if (in_array($table, $excludedTables, true)) {
                $this->line("â­  Skipping excluded table: {$table}");
                continue;
            }

            if (! Schema::hasTable($table)) {
                $this->line("â­  Skipping non-schema table: {$table}");
                continue;
            }

            $this->line("ðŸ“„ Inspecting table: {$table}");

            $columns = DB::select("SHOW COLUMNS FROM `{$table}`");
            $dateColumns = [];

            foreach ($columns as $column) {
                $columnName = $column->Field;
                $type = strtolower($column->Type);

                if (in_array($columnName, $excludedColumns, true)) {
                    continue;
                }

                // Type includes precision, e.g. datetime(6), timestamp, date
                if (
                    Str::contains($type, 'date') ||
                    Str::contains($type, 'datetime') ||
                    Str::contains($type, 'timestamp')
                ) {
                    $dateColumns[] = [$columnName, $type];
                }
            }

            if (empty($dateColumns)) {
                $this->line("   â†’ No date/datetime/timestamp columns found.");
                continue;
            }

            $this->info("   â†’ Date-like columns: " . implode(', ', array_map(fn($c) => $c[0], $dateColumns)));

            foreach ($dateColumns as [$columnName, $type]) {

                // âœ… Fix #1 (microseconds-as-text): for datetime/timestamp, normalize to seconds
                // using SUBSTRING(..., 1, 19) => 'YYYY-MM-DD HH:MM:SS'
                //
                // âœ… Fix #2 (DST gaps on TIMESTAMP): we set session time_zone to UTC above.
                //
                // For DATE columns, DATE_ADD works directly.
                $exprForCompareAndAdd = "`{$columnName}`";
                if (Str::contains($type, 'datetime') || Str::contains($type, 'timestamp')) {
                    $exprForCompareAndAdd = "CAST(SUBSTRING(`{$columnName}`, 1, 19) AS DATETIME)";
                }

                $sql = "UPDATE `{$table}`
                        SET `{$columnName}` = DATE_ADD({$exprForCompareAndAdd}, INTERVAL {$days} DAY)
                        WHERE `{$columnName}` IS NOT NULL
                          AND {$exprForCompareAndAdd} < '{$beforeDate}'";

                $this->line("      â€¢ Would run (cutoff {$beforeDate}): {$sql}");

                if (! $dryRun) {
                    $affected = DB::affectingStatement($sql);
                    $this->line("        â†’ Updated {$affected} rows in {$table}.{$columnName}");
                }

                $totalQueries++;
            }
        }

        // Restore original session time_zone if we could read it
        if ($originalTimeZone !== null) {
            try {
                // Quote safely
                DB::statement("SET time_zone = " . DB::getPdo()->quote($originalTimeZone));
            } catch (\Throwable $e) {
                $this->warn("âš ï¸ Could not restore session time_zone back to '{$originalTimeZone}'.");
            }
        }

        if ($dryRun) {
            $this->info("âœ… Dry-run complete. Total UPDATE statements that would be executed: {$totalQueries}");
        } else {
            $this->info("âœ… Done. Total UPDATE statements executed: {$totalQueries}");
        }

        return self::SUCCESS;
    }
}
