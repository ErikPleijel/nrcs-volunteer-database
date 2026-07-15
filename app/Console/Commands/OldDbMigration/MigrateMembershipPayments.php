<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;



class MigrateMembershipPayments extends Command
{
    protected $signature = 'migrate:membership-payments
                            {--chunk=500 : Number of records to process per chunk}
                            {--clear : Clear existing membership payments before migration}
                            {--dry-run : Run without making changes}';

    protected $description = 'Migrate membership payments from old database, preserving PaymentID as id';

    public function handle()
    {
        $chunk = $this->option('chunk');
        $clear = $this->option('clear');
        $dryRun = $this->option('dry-run');

        $this->info('ðŸ’³ Starting membership payments migration...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made to the database');
        }

        // Clear existing data if requested
        if ($clear && !$dryRun && $this->confirm('This will delete all existing membership payments. Continue?')) {
            DB::table('membership_payments')->delete();
            $this->info('âœ… Existing membership payments cleared');
        }

        // Check if membership_payments table exists
        if (!Schema::hasTable('membership_payments')) {
            $this->error('âŒ Membership payments table does not exist. Please run migrations first.');
            return Command::FAILURE;
        }

        try {
            $totalCount = DB::connection('old_db')->table('membershippayments')->count();
            $migratedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            if ($totalCount === 0) {
                $this->warn('âš ï¸ No membership payments found in old database');
                return Command::SUCCESS;
            }

            $this->info("ðŸ“Š Found {$totalCount} membership payments to migrate");

            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            // Process in chunks
            DB::connection('old_db')
                ->table('membershippayments')
                ->orderBy('PaymentID')
                ->chunk($chunk, function ($payments) use (&$migratedCount, &$skippedCount, &$errorCount, $progressBar, $dryRun) {

                    $paymentData = [];

                    foreach ($payments as $payment) {
                        try {
                            // Check if payment already exists by ID
                            if (!$dryRun) {
                                $exists = DB::table('membership_payments')
                                    ->where('id', $payment->PaymentID)
                                    ->exists();

                                if ($exists) {
                                    $skippedCount++;
                                    $progressBar->advance();
                                    continue;
                                }
                            }

                            $newPayment = [
                                'id' => $payment->PaymentID, // Preserve original ID
                                'user_id' => $payment->PersonID,
                                'payment_date' => $payment->PaymentDate,
                                'expiry_date' => $payment->ExpiryDate,
                                'membership_fee_id' => $payment->MembershipFeeID,
                                'is_deleted' => $this->convertBoolean($payment->IsDeleted),
                                'submitted_at' => $payment->Timestamp,
                                'submission_name' => $this->cleanString($payment->SubmissionName),
                                'reference' => $this->cleanString($payment->Reference),
                                'submitted_by_user_id' => $payment->SubmissionID,
                                'branch_id' => $payment->BranchID,
                                'division_id' => $payment->DivisionID,
                                'id_card_included' => $this->convertBoolean($payment->IDCardIncluded),
                                'created_at' => now(),
                                'updated_at' => now(),
                                // Legacy records are pre-approved (no approval step existed).
                                'approval_status' => 'approved',
                                'decided_at' => $payment->PaymentDate ?? $payment->Timestamp ?? now(),
                                'decided_by_user_id' => null,
                            ];

                            $paymentData[] = $newPayment;
                            $migratedCount++;

                        } catch (\Exception $e) {
                            $errorCount++;
                            $this->error("Failed to process payment {$payment->PaymentID}: " . $e->getMessage());
                        }

                        $progressBar->advance();
                    }

                    // Insert batch if not dry run
                    if (!$dryRun && !empty($paymentData)) {
                        DB::table('membership_payments')->insert($paymentData);
                    }
                });

            $progressBar->finish();
            $this->newLine(2);

            // Reset auto-increment to continue from the highest ID + 1
            if (!$dryRun) {
                $maxId = DB::table('membership_payments')->max('id') ?? 0;
                DB::statement("ALTER TABLE membership_payments AUTO_INCREMENT = " . ($maxId + 1));
                $this->info("Set AUTO_INCREMENT to " . ($maxId + 1));
            }

            // Show results
            $this->info('ðŸŽ‰ Membership payments migration completed!');
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
        $totalPayments = DB::table('membership_payments')->count();
        $activePayments = DB::table('membership_payments')->where('is_deleted', false)->count();
        $withIdCard = DB::table('membership_payments')->where('id_card_included', true)->count();
        $withExpiry = DB::table('membership_payments')->whereNotNull('expiry_date')->count();

        $this->info('ðŸ“Š Membership Payments Statistics:');
        $this->line("  - Total payments: {$totalPayments}");
        $this->line("  - Active payments: {$activePayments}");
        $this->line("  - With ID card: {$withIdCard}");
        $this->line("  - With expiry date: {$withExpiry}");
    }
}
