<?php

namespace App\Console\Commands\OldDbMigration;

use App\Console\Commands\OldDbMigration\Concerns\SanitizesOldDbDates;
use Illuminate\Console\Command;
use App\Models\User;
use App\Models\IdCardPrint; // Assuming you create a model for id_card_prints

class MigrateIdCardPrints extends Command
{
    use SanitizesOldDbDates;

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
        $skipped = 0;
        foreach ($usersToMigrate as $user) {
            // users.id_card_timestamp is a DATE column (1000-9999 valid range),
            // but id_card_prints.printed_at is a narrower TIMESTAMP column
            // (1970-2038 valid range). A value can already be range-valid for
            // DATE (e.g. year 2926) and still be invalid once cast into a
            // TIMESTAMP, so it needs its own range check here rather than a
            // bare Carbon::parse()/try-catch.
            $printedAt = $this->sanitizeOldDbDate(
                $user->id_card_timestamp, 'timestamp', 'users', $user->id, 'id_card_timestamp'
            );

            if (!$printedAt) {
                // id_card_prints.printed_at is NOT NULL, so an invalid/out-of-range
                // value can't just be nulled out like the other migrated date
                // fields — skip creating the row entirely instead.
                $skipped++;
                continue;
            }

            $validityMonths = $user->id_card_valid_years * 12;

            // expiry_date is also a TIMESTAMP column, computed from printed_at +
            // validity_months. Even with a valid printed_at, a large
            // validity_months can push the computed value past 2038 and hit the
            // same class of error. expiry_date IS nullable though, so an
            // out-of-range result is nulled rather than skipping the whole row.
            $expiryDate = $this->sanitizeOldDbDate(
                $printedAt->copy()->addMonths($validityMonths)->toDateTimeString(),
                'timestamp',
                'users',
                $user->id,
                'computed expiry_date (id_card_timestamp + id_card_valid_years)'
            );

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

        $this->info("Migration complete. {$count} ID card print records created."
            . ($skipped > 0 ? " {$skipped} skipped due to invalid/out-of-range id_card_timestamp (see warnings above)." : ''));
    }
}
