<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * courses: a planned training course (one date range, one place) that people
 * attend. Not the same as `trainings`, which holds one person's training record;
 * closing a course creates a `trainings` row for each participant who passed.
 *
 * branch_id NULL = national course. division_id is optional (a branch course
 * may be organised by one division). Status is planned / closed / cancelled
 * only; "ongoing" is worked out from the dates (see Course::isOngoing()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('training_type_id')->constrained('training_types')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('place', 191)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('division_id')->nullable()->constrained('divisions')->restrictOnDelete();
            $table->text('external_facilitators')->nullable();
            $table->string('status', 20)->default('planned');

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
