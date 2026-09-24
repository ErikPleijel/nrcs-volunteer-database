<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Associate" belongs with the volunteer-and-member fee group (Junior Unit,
 * School Unit, Detachment, Service Group), not the supporting-member group
 * (Bronze..Platinum). fix:userdata's LIKE list never matched it, so it was
 * left at is_volunteer_fee = 0 on import.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('membership_fees')
            ->where('name', 'Associate')
            ->update(['is_volunteer_fee' => true]);
    }

    public function down(): void
    {
        DB::table('membership_fees')
            ->where('name', 'Associate')
            ->update(['is_volunteer_fee' => false]);
    }
};
