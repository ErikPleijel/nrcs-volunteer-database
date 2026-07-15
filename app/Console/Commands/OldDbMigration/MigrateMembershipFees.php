<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateMembershipFees extends Command
{
    protected $signature = 'migrate:membership-fees
                            {--chunk=500 : Number of records to process per chunk}
                            {--clear : Clear existing membership fees before migration}
                            {--dry-run : Run without making changes}';

    protected $description = 'Migrate membership fees from old database, preserving MembershipFeeID as id';

    public function handle()
    {
        $chunk = $this->option('chunk');
        $clear = $this->option('clear');
        $dryRun = $this->option('dry-run');

        $this->info('ðŸ’° Starting membership fees migration...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made to the database');
        }

        // Clear existing data if requested
        if ($clear && !$dryRun && $this->confirm('This will delete all existing membership fees. Continue?')) {
            DB::table('membership_fees')->delete();
            $this->info('âœ… Existing membership fees cleared');
        }

        // Check if membership_fees table exists
        if (!Schema::hasTable('membership_fees')) {
            $this->error('âŒ Membership fees table does not exist. Please run migrations first.');
            return Command::FAILURE;
        }

        try {
            $totalCount = DB::connection('old_db')->table('membershipfees')->count();
            $migratedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            if ($totalCount === 0) {
                $this->warn('âš ï¸ No membership fees found in old database');
                return Command::SUCCESS;
            }

            $this->info("ðŸ“Š Found {$totalCount} membership fees to migrate");

            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            // Process in chunks
            DB::connection('old_db')
                ->table('membershipfees')
                ->orderBy('MembershipFeeID')
                ->chunk($chunk, function ($membershipFees) use (&$migratedCount, &$skippedCount, &$errorCount, $progressBar, $dryRun) {

                    $feeData = [];

                    foreach ($membershipFees as $fee) {
                        try {
                            // Check if membership fee already exists
                            if (!$dryRun) {
                                $exists = DB::table('membership_fees')
                                    ->where('id', $fee->MembershipFeeID)
                                    ->exists();

                                if ($exists) {
                                    $skippedCount++;
                                    $progressBar->advance();
                                    continue;
                                }
                            }

                            $newFee = [
                                'id' => $fee->MembershipFeeID, // Preserve original ID
                                'name' => $this->cleanString($fee->MembershipName),
                                'amount' => $this->convertAmount($fee->MembershipAmount),
                                'id_card_fee' => $this->convertAmount($fee->IDcard_fee),
                                'validity_years' => (int) $fee->Valid_years,
                                'for_organizations' => $this->convertBoolean($fee->Organisations),
                                'is_active' => $this->convertBoolean($fee->Include_in_list, true), // Default to true if null
                                'description' => null, // New field, no old data
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            $feeData[] = $newFee;
                            $migratedCount++;

                        } catch (\Exception $e) {
                            $errorCount++;
                            $this->error("Failed to process membership fee {$fee->MembershipFeeID}: " . $e->getMessage());
                        }

                        $progressBar->advance();
                    }

                    // Insert batch if not dry run
                    if (!$dryRun && !empty($feeData)) {
                        DB::table('membership_fees')->insert($feeData);
                    }
                });

            $progressBar->finish();
            $this->newLine(2);

            // Reset auto-increment to continue from the highest ID + 1
            if (!$dryRun) {
                $maxId = DB::table('membership_fees')->max('id') ?? 0;
                DB::statement("ALTER TABLE membership_fees AUTO_INCREMENT = " . ($maxId + 1));
                $this->info("Set AUTO_INCREMENT to " . ($maxId + 1));
            }

            // Show results
            $this->info('ðŸŽ‰ Membership fees migration completed!');
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

    private function convertAmount($amount)
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        // Return the amount as-is since it's already in the correct currency format
        return number_format($amount, 2, '.', '');
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
        $totalFees = DB::table('membership_fees')->count();
        $activeFees = DB::table('membership_fees')->where('is_active', true)->count();
        $organizationFees = DB::table('membership_fees')->where('for_organizations', true)->count();
        $individualFees = DB::table('membership_fees')->where('for_organizations', false)->count();

        $this->info('ðŸ“Š Membership Fees Statistics:');
        $this->line("  - Total membership fees: {$totalFees}");
        $this->line("  - Active membership fees: {$activeFees}");
        $this->line("  - For organizations: {$organizationFees}");
        $this->line("  - For individuals: {$individualFees}");

        // Show validity year breakdown
        $validityStats = DB::table('membership_fees')
            ->select('validity_years', DB::raw('COUNT(*) as count'))
            ->groupBy('validity_years')
            ->orderBy('validity_years')
            ->get();

        if ($validityStats->isNotEmpty()) {
            $this->line("  - By validity years:");
            foreach ($validityStats as $stat) {
                $this->line("    â€¢ {$stat->validity_years} year(s): {$stat->count}");
            }
        }

        // Show amount statistics
        $avgAmount = DB::table('membership_fees')->whereNotNull('amount')->avg('amount');
        $minAmount = DB::table('membership_fees')->whereNotNull('amount')->min('amount');
        $maxAmount = DB::table('membership_fees')->whereNotNull('amount')->max('amount');

        if ($avgAmount) {
            $this->line("  - Amount range: " . number_format($minAmount, 2) . " - " . number_format($maxAmount, 2));
            $this->line("  - Average amount: " . number_format($avgAmount, 2));
        }
    }
}
