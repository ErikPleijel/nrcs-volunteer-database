<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('membership_payments', function (Blueprint $table) {
            $table->id(); // PaymentID -> id

            $table->unsignedBigInteger('user_id')
                ->index()
                ->comment('Foreign key to users table'); // PersonID -> user_id

            $table->date('payment_date')
                ->index()
                ->comment('Date when payment was made'); // PaymentDate -> payment_date

            $table->date('expiry_date')
                ->nullable()
                ->index()
                ->comment('Membership expiry date'); // ExpiryDate -> expiry_date

            $table->unsignedInteger('membership_fee_id')
                ->index()
                ->comment('Foreign key to membership_fees table'); // MembershipFeeID -> membership_fee_id

            $table->boolean('is_deleted')
                ->nullable()
                ->default(false)
                ->index()
                ->comment('Soft delete flag'); // IsDeleted -> is_deleted

            $table->timestamp('submitted_at', 6)
                ->nullable()
                ->default(DB::raw('CURRENT_TIMESTAMP(6)'))
                ->index()
                ->comment('When the payment was submitted'); // Timestamp -> submitted_at

            $table->string('submission_name', 32)
                ->nullable()
                ->comment('Name of person who submitted'); // SubmissionName -> submission_name

            $table->string('reference', 45)
                ->nullable()
                ->comment('Payment reference or identifier'); // Reference -> reference

            $table->unsignedBigInteger('submitted_by_user_id')
                ->index()
                ->comment('ID of user who submitted this payment'); // SubmissionID -> submitted_by_user_id

            $table->unsignedInteger('branch_id')
                ->nullable()
                ->index()
                ->comment('Branch ID'); // BranchID -> branch_id

            $table->unsignedInteger('division_id')
                ->nullable()
                ->index()
                ->comment('Division ID'); // DivisionID -> division_id

            $table->boolean('id_card_included')
                ->nullable()
                ->comment('Whether ID card was included with payment'); // IDCardIncluded -> id_card_included

            $table->timestamps();

            // Foreign key constraints (uncomment when related tables are ready)
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            // $table->foreign('membership_fee_id')->references('id')->on('membership_fees')->onDelete('restrict');
            // $table->foreign('submitted_by_user_id')->references('id')->on('users')->onDelete('cascade');

            /**
             * Composite indexes for performance
             * ---------------------------------
             * 1) Global "active membership" index:
             *    optimizes queries like:
             *    WHERE is_deleted = 0
             *      AND payment_date <= :snapshot
             *      AND (expiry_date IS NULL OR expiry_date >= :snapshot)
             *    with COUNT(DISTINCT user_id)
             */
            $table->index(
                ['is_deleted', 'payment_date', 'expiry_date', 'user_id'],
                'membership_payments_active_global_index'
            );

            /**
             * 2) Branch + division breakdown:
             *    optimizes queries that group by branch/division over time.
             *    e.g. trend per branch/division.
             */
            $table->index(
                ['branch_id', 'division_id', 'is_deleted', 'payment_date', 'expiry_date', 'user_id'],
                'membership_payments_active_branch_div_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_payments');
    }
};
