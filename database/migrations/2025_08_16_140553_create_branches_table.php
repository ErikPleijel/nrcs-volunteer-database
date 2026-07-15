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
        Schema::create('branches', function (Blueprint $table) {
            $table->id(); // Auto-increment primary key (maps from BranchID)
            $table->string('name', 50)->nullable(); // Maps from 'Branch' field, increased length for flexibility
            $table->string('code', 5)->nullable(); // Maps from 'BranchCode', slightly increased length
            $table->string('zone')->nullable(); // Maps from 'Zone'
            $table->boolean('is_active')->default(true); // Maps from 'Include_in_list', renamed for clarity
            $table->string('physical_address', 150)->nullable(); // Maps from 'Physical_address', increased length
            $table->string('postal_address', 100)->nullable(); // Maps from 'Postal_address', increased length
            $table->string('telephone', 30)->nullable(); // Maps from 'Telephone', increased length
            $table->string('email', 100)->nullable(); // Maps from 'Email', increased length

            // Geographic fields for branch coordinates
            $table->decimal('latitude', 10, 8)->nullable(); // Precision for accurate coordinates
            $table->decimal('longitude', 11, 8)->nullable(); // Precision for accurate coordinates
            $table->tinyInteger('projects')->unsigned()->nullable()->default(null);

            $table->timestamps(); // Laravel standard created_at/updated_at

            // Add indexes for frequently searched fields
            $table->index('name');
            $table->index('code');
            $table->index('zone');
            $table->index('is_active');
            $table->index('email');
            $table->index(['latitude', 'longitude']); // Composite index for geographic queries

            // Unique constraint on code if it should be unique
            $table->unique('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
