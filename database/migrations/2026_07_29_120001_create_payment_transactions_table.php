<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks a Paystack transaction end-to-end, from initiation through
 * verification/fulfilment. donation_id/membership_payment_id are filled in
 * once the transaction is fulfilled into the corresponding record; meta
 * carries whatever the initiating form needs to remember in between.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users');

            $table->unsignedBigInteger('organisation_id')->nullable()->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->nullOnDelete();

            $table->string('payable_type'); // 'donation' or 'membership_payment'

            $table->string('reference')->unique(); // Paystack's transaction reference

            $table->integer('amount');

            $table->string('status')->default('initiated')->index(); // initiated, success, failed, abandoned

            $table->json('meta')->nullable();
            $table->json('raw_payload')->nullable();

            $table->unsignedBigInteger('donation_id')->nullable();
            $table->foreign('donation_id')->references('id')->on('donations')->nullOnDelete();

            $table->unsignedBigInteger('membership_payment_id')->nullable();
            $table->foreign('membership_payment_id')->references('id')->on('membership_payments')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
