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
        Schema::create('organisation_user', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('user_id');

            $table->boolean('is_primary_contact')->default(false);
            $table->timestamp('linked_at')->nullable();
            $table->unsignedBigInteger('linked_by')->nullable();

            $table->timestamps();

            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('linked_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['organisation_id', 'user_id']);
            $table->index('organisation_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisation_user');
    }
};
