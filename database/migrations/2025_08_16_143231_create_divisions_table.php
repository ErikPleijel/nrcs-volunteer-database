<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new
class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->id(); // Auto-increment primary key (maps from DivisionID)
            $table->string('name', 50)->nullable(); // Maps from 'Division' field, increased length for flexibility
            $table->unsignedBigInteger('branch_id')->nullable(); // Maps from 'BranchID', foreign key reference
            $table->boolean('is_active')->default(true); // Maps from 'Include_in_list', renamed for clarity

            // Geographic fields for Nigerian LGA coordinates
            $table->decimal('latitude', 10, 8)->nullable(); // Precision for accurate coordinates
            $table->decimal('longitude', 11, 8)->nullable(); // Precision for accurate coordinates

            $table->timestamps(); // Laravel standard created_at/updated_at

            // Add indexes for frequently searched fields
            $table->index('name');
            $table->index('branch_id');
            $table->index('is_active');
            $table->index(['latitude', 'longitude']); // Composite index for geographic queries

            // Foreign key constraint (optional - can be enabled later)
            // $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};
