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
        Schema::create('activity_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->index()->comment('Activity type name');
            $table->boolean('is_active')->default(true)->index()->comment('Whether this activity type is currently available');
            $table->text('description')->nullable()->comment('Optional description of activity type');
            $table->timestamps();

            // Additional indexes
            $table->index(['is_active', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_types');
    }
};
