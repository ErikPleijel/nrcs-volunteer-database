<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RCU membership certificates: a print record can belong to a Red Cross Unit
 * (red_cross_unit_id, alongside organisation_id), with its own
 * certificate_type value. Mirrors
 * 2026_05_18_000001_add_organisation_support_to_certificate_prints_table.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates_print', function (Blueprint $table) {
            $table->unsignedBigInteger('red_cross_unit_id')
                ->nullable()
                ->after('organisation_id');

            $table->enum('certificate_type', [
                'training_competence',
                'training_attendance',
                'membership',
                'donation',
                'volunteering',
                'organisation_membership',
                'organisation_donation',
                'rcu_membership',
            ])->change();
        });

        Schema::table('certificates_print', function (Blueprint $table) {
            $table->foreign('red_cross_unit_id')
                ->references('id')->on('red_cross_units')
                ->onDelete('set null');

            $table->index(['red_cross_unit_id', 'certificate_type']);
        });
    }

    public function down(): void
    {
        // RCU print records can't survive the enum losing their type (MySQL
        // strict mode rejects the change otherwise) and lose their unit
        // column anyway, so they go with it.
        DB::table('certificates_print')->where('certificate_type', 'rcu_membership')->delete();

        Schema::table('certificates_print', function (Blueprint $table) {
            $table->dropForeign(['red_cross_unit_id']);
            $table->dropIndex(['red_cross_unit_id', 'certificate_type']);
        });

        Schema::table('certificates_print', function (Blueprint $table) {
            $table->dropColumn('red_cross_unit_id');

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
    }
};
