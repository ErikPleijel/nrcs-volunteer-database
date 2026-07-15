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
        Schema::create('task_force_types', function (Blueprint $table) {
            $table->id(); // TaskForceTypeID as primary key
            $table->string('name'); // TaskForceTypeName - defaults to 255
            $table->unsignedTinyInteger('level'); // Level (no default in old structure)
            $table->boolean('include_in_list')->default(true); // Include_in_list (default 1)
            $table->timestamps();

            // Add indexes for better performance
            $table->index('level');
            $table->index('include_in_list');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_force_types');
    }
};
