<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When, and by whom, an account was archived. Set by User::markArchived()
 * on every archive path and cleared when the account leaves 'archived'.
 * archived_at starts the 7-year anonymization clock (Decisions.md
 * 2026-10-08). No FK on the _by_id column, matching consent_obtained_by_id,
 * signature_rejected_by_id etc.; null means archived by the system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('lifecycle_status')->index();
            $table->unsignedBigInteger('archived_by_id')->nullable()->after('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn(['archived_at', 'archived_by_id']);
        });
    }
};
