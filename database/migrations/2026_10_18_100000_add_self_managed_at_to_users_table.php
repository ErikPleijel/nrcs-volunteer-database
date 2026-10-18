<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
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

        // Existing users. On a fresh database there are none yet:
        // migrate:old-db runs the same command after the users import.
        Artisan::call('users:backfill-self-managed');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('self_managed_at');
        });
    }
};
