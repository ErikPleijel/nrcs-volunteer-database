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
        Schema::create('membership_fees', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->index()->comment('Membership type name');
            $table->decimal('amount', 10, 2)->nullable()->comment('Membership fee amount');
            $table->decimal('id_card_fee', 10, 2)->default(0)->comment('ID card fee amount');
            $table->unsignedTinyInteger('validity_years')->default(1)->comment('Number of years membership is valid');
            $table->boolean('for_organizations')->default(false)->comment('Whether this membership type is for organizations');
            $table->boolean('is_active')->default(true)->index()->comment('Whether this membership type is currently available');
            $table->text('description')->nullable()->comment('Optional description of membership type');
            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'for_organizations']);
            $table->index('validity_years');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_fees');
    }
};
