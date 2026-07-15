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
        Schema::create('training_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->index()->comment('Training type name');
            $table->boolean('is_active')->default(true)->index()->comment('Whether this training type is currently available');
            $table->unsignedTinyInteger('validity_years_limit')->nullable()->comment('Validity period limit in years');
            $table->boolean('certificate_hq_only')->default(true)->comment('Whether certificate can only be issued by HQ');
            $table->text('description')->nullable()->comment('Optional description of training type');
            $table->timestamps();

            // Additional indexes
            $table->index(['is_active', 'name']);
            $table->index('certificate_hq_only');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_types');
    }
};
