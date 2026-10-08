<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When, and by whom, an archived account was anonymized (UserAnonymizer;
 * Decisions.md 2026-10-08). Permanent: there is no way back. No FK on the
 * _by_id column, like the other *_by_id columns; null means the scheduled
 * job (users:anonymize-expired).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable()->after('archived_by_id')->index();
            $table->unsignedBigInteger('anonymized_by_id')->nullable()->after('anonymized_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['anonymized_at']);
            $table->dropColumn(['anonymized_at', 'anonymized_by_id']);
        });
    }
};
