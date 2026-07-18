<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateDonations extends Command
{
    protected $signature = 'migrate:donations
                            {--chunk=500 : Number of records to process per chunk}
                            {--clear : Clear existing donations before migration}
                            {--dry-run : Run without making changes}';

    protected $description = 'Migrate donations from old database, preserving DonationID as id';

    public function handle()
    {
        $chunk = $this->option('chunk');
        $clear = $this->option('clear');
        $dryRun = $this->option('dry-run');

        $this->info('ðŸ’° Starting donations migration...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made to the database');
        }

        // Clear existing data if requested
        if ($clear && !$dryRun && $this->confirm('This will delete all existing donations. Continue?')) {
            DB::table('donations')->delete();
            $this->info('âœ… Existing donations cleared');
        }

        // Check if donations table exists
        if (!Schema::hasTable('donations')) {
            $this->error('âŒ Donations table does not exist. Please run migrations first.');
            return Command::FAILURE;
        }

        try {
            $rawTotalCount = DB::connection('old_db')->table('donations')->count();

            // Organisations are re-entered fresh by NRCS admins going forward (see
            // MigrateOrganisations.php) — old-system donations attributed to an
            // organisation rather than a person have no reliable way to be
            // re-linked to the freshly-registered organisation records, so they
            // are excluded here rather than imported as orphaned/misattributed
            // rows. Excluded only when the donor PersonID is flagged
            // IsOrganisation=1 in the old persons table; a PersonID with no
            // matching person row at all is treated as personal (not excluded),
            // matching the tolerant NULL-handling already used elsewhere in this
            // migration pipeline (see MigrateOrganisations's $inactiveCol handling).
            $donationsQuery = fn () => DB::connection('old_db')
                ->table('donations')
                ->leftJoin('persons', 'persons.PersonID', '=', 'donations.PersonID')
                ->where(function ($q) {
                    $q->where('persons.IsOrganisation', '<>', 1)
                        ->orWhereNull('persons.IsOrganisation');
                })
                ->select('donations.*');

            $totalCount = $donationsQuery()->count();
            $excludedOrgCount = $rawTotalCount - $totalCount;
            $migratedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            if ($totalCount === 0) {
                $this->warn('âš ï¸ No donations found in old database');
                return Command::SUCCESS;
            }

            $this->info("Found {$totalCount} donations to migrate ({$excludedOrgCount} excluded: organisation-attributed in old data)");

            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            // Process in chunks
            $donationsQuery()
                ->orderBy('donations.DonationID')
                ->chunk($chunk, function ($donations) use (&$migratedCount, &$skippedCount, &$errorCount, $progressBar, $dryRun) {

                    $donationData = [];

                    foreach ($donations as $donation) {
                        try {
                            // Check if donation already exists
                            if (!$dryRun) {
                                $exists = DB::table('donations')
                                    ->where('id', $donation->DonationID) // Check against the 'id' column
                                    ->exists();

                                if ($exists) {
                                    $skippedCount++;
                                    $progressBar->advance();
                                    continue;
                                }
                            }

                            $newDonation = [
                                'id' => $donation->DonationID, // Explicitly set the 'id' from old DonationID
                                'user_id' => $donation->PersonID,
                                'in_kind_donation' => $this->convertBoolean($donation->In_kind_donation),
                                'donation_item' => $this->cleanString($donation->Donation_item),
                                'date_donation' => $donation->Date_donation,
                                'amount' => $donation->Amount,
                                'timestamp' => $donation->Timestamp,
                                'submission_name' => $this->cleanString($donation->SubmissionName),
                                'is_deleted' => $this->convertBoolean($donation->IsDeleted),
                                'reference' => $this->cleanString($donation->Reference),
                                'entered_by_user_id' => $donation->SubmissionID,
                                'purpose' => $this->cleanString($donation->Purpose),
                                'anonymous' => $this->convertBoolean($donation->Anonymous),
                                'branch_id' => $donation->BranchID,
                                'division_id' => $donation->DivisionID,
                                'removed_by_user_id' => $donation->RemovedID,
                                'removed_date' => $donation->RemovedDate,
                                // Legacy records are pre-approved (no approval step existed).
                                'approval_status' => 'approved',
                                'decided_at' => $donation->Date_donation ?? $donation->Timestamp,
                                'decided_by_user_id' => null,
                            ];

                            $donationData[] = $newDonation;
                            $migratedCount++;

                        } catch (\Exception $e) {
                            $errorCount++;
                            $this->error("Failed to process donation {$donation->DonationID}: " . $e->getMessage());
                        }

                        $progressBar->advance();
                    }

                    // Insert batch if not dry run
                    if (!$dryRun && !empty($donationData)) {
                        DB::table('donations')->insert($donationData);
                    }
                });

            $progressBar->finish();
            $this->newLine(2);

            // Reset auto-increment to continue from the highest ID + 1,
            // matching the behavior in MigrateDivisions.php
            if (!$dryRun) {
                $maxId = DB::table('donations')->max('id') ?? 0;
                DB::statement("ALTER TABLE donations AUTO_INCREMENT = " . ($maxId + 1));
                $this->info("Set AUTO_INCREMENT to " . ($maxId + 1));
            }

            // Show results
            $this->info('ðŸŽ‰ Donation migration completed!');
            $this->table(['Metric', 'Count'], [
                ['Total Records In Old DB', $rawTotalCount],
                ['Excluded (Organisation-Attributed)', $excludedOrgCount],
                ['Eligible For Migration', $totalCount],
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

    private function convertBoolean($value): ?bool
    {
        if ($value === null) {
            return null;
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
        $totalDonations = DB::table('donations')->count();
        $inKindDonations = DB::table('donations')->where('in_kind_donation', true)->count();
        $cashDonations = DB::table('donations')->where('in_kind_donation', false)->count();
        $deletedDonations = DB::table('donations')->where('is_deleted', true)->count();
        $anonymousDonations = DB::table('donations')->where('anonymous', true)->count();
        $totalAmount = DB::table('donations')->where('is_deleted', false)->sum('amount') ?? 0;

        $this->info('ðŸ“Š Donation Statistics:');
        $this->line("  - Total donations: {$totalDonations}");
        $this->line("  - In-kind donations: {$inKindDonations}");
        $this->line("  - Cash donations: {$cashDonations}");
        $this->line("  - Deleted donations: {$deletedDonations}");
        $this->line("  - Anonymous donations: {$anonymousDonations}");
        $this->line("  - Total amount (active donations): " . number_format($totalAmount));
    }
}
