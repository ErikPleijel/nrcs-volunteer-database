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
        Schema::create('id_card_prints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // The user whose ID card was printed
            $table->foreignId('printed_by_user_id')->nullable()->constrained('users')->onDelete('set null'); // The admin/user who initiated the print
            $table->timestamp('printed_at')->useCurrent(); // When the ID card was printed
            $table->string('status')->default('printed'); // e.g., 'printed', 'reprinted', 'error'
            $table->integer('validity_months')->nullable(); // Number of months the ID card is valid for
            $table->timestamp('expiry_date')->nullable(); // The calculated expiry date of the ID card

            $table->text('notes')->nullable(); // Any additional notes about the print job
            $table->timestamps(); // Adds created_at and updated_at columns
            $table->softDeletes(); // Adds the deleted_at column for soft deletes
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('id_card_prints');
    }
};
