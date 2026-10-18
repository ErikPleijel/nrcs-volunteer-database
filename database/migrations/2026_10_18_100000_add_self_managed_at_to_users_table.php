<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * self_managed_at: when the person first logged in themselves (or registered
 * themselves). From then on staff can no longer change their personal details
 * on users/edit — see User::staffLockedFields().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('self_managed_at')->nullable()->after('last_login_at');
        });

        // Anyone with a recorded login (this system or the old one's Lastlogin).
        DB::update('UPDATE users SET self_managed_at = last_login_at WHERE last_login_at IS NOT NULL');

        // Self-registration logged the person in without setting last_login_at,
        // and consent given by the person themselves (self-registration or the
        // confirmation at login) means they were logged in.
        DB::update('UPDATE users SET self_managed_at = COALESCE(consent_obtained_at, created_at)
            WHERE self_managed_at IS NULL AND consent_obtained_by_id = id');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('self_managed_at');
        });
    }
};
