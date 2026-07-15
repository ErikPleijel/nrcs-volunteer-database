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
        Schema::table('users', function (Blueprint $table) {
            // 1. Add the column (unsignedBigInteger is what foreignId creates)
            // It is placed after a logical preceding column like 'red_cross_unit_id'.
            // If you need it in a specific position, you can use ->after('column_name')
            // now that you are MODIFYING an existing table.
            $table->unsignedBigInteger('organisation_id')
                ->nullable()
                ->after('red_cross_unit_id');

            // 2. Add the foreign key constraint
            $table->foreign('organisation_id')
                ->references('id')
                ->on('organisations')
                ->nullOnDelete();

            // 3. Add an index for the new column (recommended)
            $table->index('organisation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['organisation_id']);

            // Drop the column and its index
            $table->dropColumn('organisation_id');
        });
    }
};
