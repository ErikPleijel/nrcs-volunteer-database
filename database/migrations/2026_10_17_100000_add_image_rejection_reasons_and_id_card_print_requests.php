<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1. Photo rejection, matching the existing signature rejection, plus a
 *    reason (pick-list key, see App\Support\ImageRejection) and an optional
 *    note for both images. Existing signature rejections keep a null reason.
 * 2. id_card_print_requests: "Mark as ready to print" by branch/division
 *    staff who cannot print. Kept out of id_card_prints on purpose — every
 *    row there is read as a printed card (expiry, needs-print, reports).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('signature_rejection_reason', 50)->nullable()->after('signature_rejected_by_id');
            $table->string('signature_rejection_note', 500)->nullable()->after('signature_rejection_reason');

            $table->timestamp('picture_rejected_at')->nullable()->after('picture')->index();
            $table->unsignedBigInteger('picture_rejected_by_id')->nullable()->after('picture_rejected_at');
            $table->string('picture_rejection_reason', 50)->nullable()->after('picture_rejected_by_id');
            $table->string('picture_rejection_note', 500)->nullable()->after('picture_rejection_reason');
        });

        Schema::create('id_card_print_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // whose card
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->string('status', 20)->default('pending'); // pending | printed | cancelled
            $table->string('cancel_reason', 30)->nullable(); // removed | image_rejected
            $table->foreignId('id_card_print_id')->nullable()->constrained('id_card_prints')->nullOnDelete();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('id_card_print_requests');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['picture_rejected_at']);
            $table->dropColumn([
                'signature_rejection_reason', 'signature_rejection_note',
                'picture_rejected_at', 'picture_rejected_by_id',
                'picture_rejection_reason', 'picture_rejection_note',
            ]);
        });
    }
};
