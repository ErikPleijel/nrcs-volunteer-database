<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * optout_token: the token in campaign unsubscribe links (/u/{token}/email,
 * /u/{token}/sms). Separate from id_check_token, which is printed on ID cards
 * and certificates, and shorter (16 instead of 32) to save SMS characters.
 * Case-sensitive collation, so all 62 letters/digits count when looking it up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $column = $table->string('optout_token', 16)->nullable()->unique()->after('id_check_token');
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $column->charset('utf8mb4')->collation('utf8mb4_bin');
            }
        });

        // Existing users. On a fresh database there are none yet:
        // migrate:old-db runs the same command after the users import.
        Artisan::call('users:backfill-optout-tokens');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['optout_token']);
            $table->dropColumn('optout_token');
        });
    }
};
