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
        Schema::create('task_force_members', function (Blueprint $table) {
            $table->id(); // TaskForceMemberID as primary key
            $table->unsignedBigInteger('task_force_id'); // TaskForceID
            $table->unsignedBigInteger('user_id'); // PersonID -> user_id
            $table->timestamp('timestamp')->default(DB::raw('CURRENT_TIMESTAMP')); // TimeStamp with microseconds
            $table->timestamps();

            // Add indexes for better performance (matching old structure)
            $table->index('task_force_id');
            $table->index('user_id');

            // Unique constraint to prevent duplicate memberships
            $table->unique(['task_force_id', 'user_id']);

            // Foreign key constraints (uncomment when related tables exist)
            // $table->foreign('task_force_id')->references('id')->on('task_forces')->onDelete('cascade');
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_force_members');
    }
};
