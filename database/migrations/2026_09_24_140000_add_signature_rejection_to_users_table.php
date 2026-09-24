<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-flagged "signature needs re-upload" state. Null = fine; set =
     * rejected on the ID card bulk-print page and awaiting a new upload.
     * Cleared automatically by User::booted() when the signature changes.
     * No FK on the _by_id column, matching consent_obtained_by_id etc.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('signature_rejected_at')->nullable()->after('signature')->index();
            $table->unsignedBigInteger('signature_rejected_by_id')->nullable()->after('signature_rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['signature_rejected_at']);
            $table->dropColumn(['signature_rejected_at', 'signature_rejected_by_id']);
        });
    }
};
