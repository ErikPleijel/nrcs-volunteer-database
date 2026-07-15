<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\IdCardPrint; // Assuming you create a model for id_card_prints
use Carbon\Carbon;

class MigrateIdCardPrints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:id-card-prints';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate ID card print records from existing users data.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting migration of ID card print records...');

        // Fetch users who have ID card timestamp and valid years set
        $usersToMigrate = User::whereNotNull('id_card_timestamp')
                               ->whereNotNull('id_card_valid_years')
                               ->get();

        $count = 0;
        foreach ($usersToMigrate as $user) {
            // Ensure id_card_timestamp is a valid date before proceeding
            try {
                $printedAt = Carbon::parse($user->id_card_timestamp);
            } catch (\Exception $e) {
                $this->warn("Skipping user ID {$user->id} due to invalid id_card_timestamp: {$user->id_card_timestamp}");
                continue;
            }

            $validityMonths = $user->id_card_valid_years * 12;
            $expiryDate = $printedAt->copy()->addMonths($validityMonths);

            // Create a new IdCardPrint record
            IdCardPrint::create([
                'user_id' => $user->id,
                'printed_by_user_id' => null, // Assuming no specific user tracked this in the old system
                'printed_at' => $printedAt,
                'status' => 'Printed',
                'validity_months' => $validityMonths,
                'expiry_date' => $expiryDate,
                'notes' => 'Record migrated from old database user data.',
            ]);
            $count++;
        }

        $this->info("Migration complete. {$count} ID card print records created.");
    }
}
