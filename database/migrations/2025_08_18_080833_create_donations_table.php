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
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->boolean('in_kind_donation')->nullable()->index();
            $table->string('donation_item', 60)->nullable();
            $table->date('date_donation')->nullable()->index();
            $table->integer('amount')->nullable();
            $table->timestamp('timestamp', 6)->nullable()->useCurrent()->index();
            $table->string('submission_name', 50)->nullable();
            $table->boolean('is_deleted')->nullable()->index();
            $table->string('reference', 45)->nullable();
            $table->unsignedBigInteger('entered_by_user_id')->nullable()->index();
            $table->string('purpose', 60)->nullable();
            $table->boolean('anonymous')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('division_id')->nullable()->index();
            $table->unsignedBigInteger('removed_by_user_id')->nullable();
            $table->date('removed_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
