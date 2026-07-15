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
        Schema::create('trainings', function (Blueprint $table) {
            $table->id(); // TrainingID as primary key
            $table->unsignedBigInteger('user_id')->nullable()->index(); // PersonID
            $table->unsignedInteger('training_type_id')->nullable()->index(); // TrainingTypeID
            $table->date('training_date')->nullable()->index(); // TrainingDate
            $table->unsignedSmallInteger('duration')->nullable(); // Duration - changed to smallInteger (up to 65535)
            $table->unsignedTinyInteger('valid_years')->nullable()->index(); // ValidYears
            $table->timestamp('submitted_at', 6)->nullable()->useCurrent()->index(); // Timestamp
            $table->string('submission_name', 50)->nullable(); // SubmissionName
            $table->boolean('is_deleted')->default(false)->index(); // IsDeleted
            $table->string('reference', 45)->nullable(); // Reference
            $table->unsignedBigInteger('submitted_by_user_id')->nullable()->index(); // SubmissionID
            $table->unsignedBigInteger('branch_id')->nullable()->index(); // BranchID
            $table->unsignedBigInteger('division_id')->index(); // DivisionID
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};
