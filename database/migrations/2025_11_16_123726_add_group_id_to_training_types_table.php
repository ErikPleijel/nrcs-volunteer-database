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
        Schema::table('training_types', function (Blueprint $table) {
            // Add the foreign key column, unsigned to match the 'training_groups' primary key
            $table->foreignId('group_id')
                ->nullable() // Allow null temporarily if you haven't linked all data yet
                ->constrained('training_groups')
                ->onDelete('restrict'); // Prevents accidental deletion of a group
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_types', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropConstrainedForeignId('group_id');
        });
    }
};
