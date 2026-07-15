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
        Schema::create('red_cross_units', function (Blueprint $table) {
            $table->id(); // Auto-increment primary key (maps from RedCrossUnitID)
            $table->string('name', 100)->nullable(false); // Maps from 'RedCrossUnit' field, increased length
            $table->unsignedBigInteger('division_id')->nullable(); // Maps from 'DivisionID' - nullable and independent
            $table->unsignedBigInteger('team_leader_user_id')->nullable(); // Maps from 'TeamLeaderID', removed foreign key
            $table->unsignedBigInteger('assistant_team_leader_user_id')->nullable(); // Maps from 'AssistTeamLeaderID', removed foreign key
            $table->boolean('is_active')->default(true); // Added for consistency with other tables
            $table->timestamps(); // Laravel standard created_at/updated_at

            // Add indexes for frequently searched fields
            $table->index('name');
            $table->index('division_id');
            $table->index('team_leader_user_id'); // Add manual index since we removed foreignId()
            $table->index('assistant_team_leader_user_id'); // Add manual index since we removed foreignId()
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('red_cross_units');
    }
};
