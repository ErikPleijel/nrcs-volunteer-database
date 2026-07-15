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
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();

            // Core info
            $table->string('name'); // require a name
            $table->string('short_name')->nullable(); // optional abbreviation
            $table->string('registration_number')->nullable(); // CAC etc.
            $table->string('address')->nullable();
            $table->string('description')->nullable();

            // Contact info
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Branch that "owns" or manages this organisation
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();

            $table->timestamps();

            // Indexes
            $table->index('name');
            $table->index('registration_number');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
