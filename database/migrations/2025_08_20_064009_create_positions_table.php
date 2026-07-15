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
        Schema::create('positions', function (Blueprint $table) {
            $table->id(); // PositionID as primary key
            $table->string('name', 80); // PositionName
            $table->unsignedTinyInteger('level')->nullable(); // Level (tinyint 2)
            $table->boolean('include_in_list')->default(true); // Include_in_list
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
        Schema::dropIfExists('positions');
    }
};
