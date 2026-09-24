<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RCU annual fee payments: a membership payment (and its Paystack staging
 * transaction) can be attributed to a Red Cross Unit, mirroring
 * organisation_id. user_id still holds the person who paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('red_cross_unit_id')->nullable()->after('organisation_id');
            $table->foreign('red_cross_unit_id')->references('id')->on('red_cross_units')->nullOnDelete();
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('red_cross_unit_id')->nullable()->index()->after('organisation_id');
            $table->foreign('red_cross_unit_id')->references('id')->on('red_cross_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropForeign(['red_cross_unit_id']);
            $table->dropColumn('red_cross_unit_id');
        });

        Schema::table('membership_payments', function (Blueprint $table) {
            $table->dropForeign(['red_cross_unit_id']);
            $table->dropColumn('red_cross_unit_id');
        });
    }
};
