<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * trainings.course_id: the course a training record came from (NULL for records
 * entered one by one, which is all existing records). The unique index makes
 * "close course" issue at most one record per person per course, even if it
 * runs twice; MySQL allows any number of (NULL, user_id) rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->after('training_type_id')
                ->constrained('courses')->nullOnDelete();
            $table->unique(['course_id', 'user_id']);
        });
    }

    public function down(): void
    {
        // The foreign key goes first: MySQL uses the unique index as its index.
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropUnique(['course_id', 'user_id']);
            $table->dropColumn('course_id');
        });
    }
};
