<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1 – drop the existing user_id FK, then modify + add columns.
        // On SQLite, change() rebuilds the table, so everything in one closure is fine.
        // On MySQL, dropForeign must precede change() on the same column.
        Schema::table('certificates_print', function (Blueprint $table) {
            $table->dropForeign(['user_id']);

            $table->unsignedBigInteger('user_id')->nullable()->change();

            $table->unsignedBigInteger('organisation_id')
                ->nullable()
                ->after('user_id');

            $table->enum('certificate_type', [
                'training_competence',
                'training_attendance',
                'membership',
                'donation',
                'volunteering',
                'organisation_membership',
                'organisation_donation',
            ])->change();
        });

        // Step 2 – re-add FKs after column definitions are settled.
        Schema::table('certificates_print', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');

            $table->foreign('organisation_id')
                ->references('id')->on('organisations')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('certificates_print', function (Blueprint $table) {
            $table->dropForeign(['organisation_id']);
            $table->dropForeign(['user_id']);

            $table->dropColumn('organisation_id');

            $table->unsignedBigInteger('user_id')->nullable(false)->change();

            $table->enum('certificate_type', [
                'training_competence',
                'training_attendance',
                'membership',
                'donation',
                'volunteering',
            ])->change();
        });

        Schema::table('certificates_print', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }
};
