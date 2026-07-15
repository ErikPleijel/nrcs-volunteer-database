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
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedBigInteger('removed_by_user_id')->nullable()->after('is_deleted');
            $table->date('removed_date')->nullable()->after('removed_by_user_id');

            // Add foreign key constraint if you want to enforce referential integrity
            $table->foreign('removed_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });

        Schema::table('trainings', function (Blueprint $table) {
            $table->unsignedBigInteger('removed_by_user_id')->nullable()->after('is_deleted');
            $table->date('removed_date')->nullable()->after('removed_by_user_id');

            // Add foreign key constraint if you want to enforce referential integrity
            $table->foreign('removed_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['removed_by_user_id']);
            $table->dropColumn(['removed_by_user_id', 'removed_date']);
        });

        Schema::table('trainings', function (Blueprint $table) {
            $table->dropForeign(['removed_by_user_id']);
            $table->dropColumn(['removed_by_user_id', 'removed_date']);
        });
    }
};
