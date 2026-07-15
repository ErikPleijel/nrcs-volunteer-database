<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('certificates_print', function (Blueprint $table) {
            $table->id();

            // Which user the certificate is about
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // Optional reference to a training (for training certificates)
            $table->foreignId('training_id')
                ->nullable()
                ->constrained('trainings')
                ->onDelete('set null');

            // Who printed it
            $table->foreignId('printed_by_user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // What kind of certificate this is
            $table->enum('certificate_type', [
                'training_competence',
                'training_attendance',
                'membership',
                'donation',
                'volunteering',
            ]);

            // When it was printed (separate from created_at)
            $table->dateTime('printed_at');

            // Optional notes (e.g. reprint reason, batch info)
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes(); // deleted_at

            // Helpful indexes for reporting / lookup
            $table->index(['user_id', 'certificate_type']);
            $table->index('printed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates_print');
    }
};
