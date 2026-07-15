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
        Schema::create('task_forces', function (Blueprint $table) {
            $table->id(); // TaskForceID as primary key
            $table->string('name'); // TaskForceName - defaults to 255
            $table->unsignedBigInteger('task_force_type_id'); // TaskForceTypeID
            $table->unsignedBigInteger('branch_id'); // BranchID
           // $table->unsignedBigInteger('division_id'); // DivisionID
            $table->timestamp('timestamp')->default(DB::raw('CURRENT_TIMESTAMP')); // TimeStamp with microseconds
            $table->unsignedBigInteger('team_leader_user_id')->nullable(); // TeamLeaderID
            $table->unsignedBigInteger('assist_team_leader_user_id')->nullable(); // AssistTeamLeaderID
            $table->boolean('inactive')->default(false); // Inactive (default 0)
            $table->timestamps();

            // Add indexes for better performance (matching old structure)
            $table->index('task_force_type_id');
            $table->index('branch_id');
           // $table->index('division_id');
            $table->index('team_leader_user_id');
            $table->index('assist_team_leader_user_id');
            $table->index('inactive');

            // Foreign key constraints (uncomment when related tables exist)
            // $table->foreign('task_force_type_id')->references('id')->on('task_force_types');
            // $table->foreign('branch_id')->references('id')->on('branches');
            // $table->foreign('division_id')->references('id')->on('divisions');
            // $table->foreign('team_leader_user_id')->references('id')->on('users');
            // $table->foreign('assist_team_leader_user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_forces');
    }
};
