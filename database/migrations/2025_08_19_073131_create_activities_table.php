<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('activity_type_id')->nullable()->index()
                ->comment('FK → activity_types.id');

            $table->unsignedBigInteger('user_id')->nullable()->index()
                ->comment('FK → users.id');

            $table->date('date')->nullable()->index()
                ->comment('Date when activity was performed');

            $table->unsignedSmallInteger('hours')->nullable()
                ->comment('Number of hours spent on activity');

            $table->boolean('is_deleted')->default(false)->index()
                ->comment('Soft delete flag');

            $table->timestamp('submitted_at', 6)->nullable()->index()
                ->comment('When the activity was submitted');

            $table->string('submission_name', 100)->nullable()
                ->comment('Name of person who submitted');

            $table->string('reference', 100)->nullable()
                ->comment('Reference or activity identifier');

            $table->unsignedBigInteger('submitted_by_user_id')->nullable()->index()
                ->comment('FK → users.id (submitter)');

            $table->unsignedInteger('branch_id')->nullable()->index()
                ->comment('Branch ID');

            $table->unsignedInteger('division_id')->index()
                ->comment('Division ID - required field');

            // 🔸 Polymorphic pair replaces red_cross_unit_id + is_red_cross_unit
            $table->unsignedBigInteger('assignable_id')->nullable()
                ->comment('ID of related record (depends on assignable_type)');

            $table->string('assignable_type', 191)->nullable()->index()
                ->comment('Polymorphic target: App\\Models\\RedCrossUnit or App\\Models\\TaskForce');

            // Helpful composite index for lookups by target
            $table->index(['assignable_type', 'assignable_id'], 'activities_assignable_idx');

            $table->timestamps();

            // Foreign keys (enable when tables are present)
            // $table->foreign('activity_type_id')->references('id')->on('activity_types')->nullOnDelete();
            // $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            // $table->foreign('submitted_by_user_id')->references('id')->on('users')->nullOnDelete();

            // Additional composite indexes for performance
            $table->index(['user_id', 'date']);
            $table->index(['activity_type_id', 'date']);
            $table->index(['is_deleted', 'date']);
            $table->index(['branch_id', 'division_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
