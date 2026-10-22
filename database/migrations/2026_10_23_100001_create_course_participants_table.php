<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * course_participants: the people on a course, as participant or facilitator
 * (facilitators are database users; others go in courses.external_facilitators).
 * A person is on a course once, in one role.
 *
 * result (participants only): passed / failed / absent, NULL until recorded.
 * Only "passed" creates a training record when the course is closed; that
 * record's id is kept in training_id. Failed/absent stay here only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20)->default('participant');
            $table->string('result', 10)->nullable();
            $table->foreignId('result_recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('result_recorded_at')->nullable();
            $table->foreignId('training_id')->nullable()->constrained('trainings')->nullOnDelete();
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_participants');
    }
};
