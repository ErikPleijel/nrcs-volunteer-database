<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An organisation belongs to a division (and through it to a branch), like a
 * person. Nullable like organisations.branch_id: the forms require it, but
 * deleting a division sets it to null rather than deleting organisations.
 * Existing organisations are filled by organisations:fill-divisions (legacy
 * persons.DivisionID); a fresh legacy import sets it itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->foreignId('division_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('divisions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('division_id');
        });
    }
};
