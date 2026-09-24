<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The annual fee a Red Cross Unit pays (via its team leader / assistant team
 * leader), mirroring an organisation's membership fee. Idempotent: skipped if
 * an RCU fee with this name already exists (e.g. created via the admin UI).
 */
return new class extends Migration
{
    private const NAME = 'RCU annual fee';

    public function up(): void
    {
        $exists = DB::table('membership_fees')
            ->where('name', self::NAME)
            ->where('for_red_cross_units', true)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('membership_fees')->insert([
            'name' => self::NAME,
            'amount' => 20000,
            'id_card_fee' => 0,
            'validity_years' => 1,
            'for_organizations' => false,
            'for_red_cross_units' => true,
            'is_active' => true,
            'is_volunteer_fee' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Only remove the seeded row if nothing references it.
        DB::table('membership_fees')
            ->where('name', self::NAME)
            ->where('for_red_cross_units', true)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('membership_payments')
                    ->whereColumn('membership_payments.membership_fee_id', 'membership_fees.id');
            })
            ->delete();
    }
};
