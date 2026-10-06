<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * national_id_number and personal_info use the `encrypted` cast, which only
 * skips NULL — an empty string reaches decrypt() and throws "The payload is
 * invalid." A NIN blanked before the User saving hook existed was stored as ''.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('national_id_number', '')
            ->update(['national_id_number' => null, 'national_id_number_hash' => null]);

        DB::table('users')
            ->where('personal_info', '')
            ->update(['personal_info' => null]);
    }

    public function down(): void
    {
        // Irreversible data fix: '' and NULL both mean "no value".
    }
};
