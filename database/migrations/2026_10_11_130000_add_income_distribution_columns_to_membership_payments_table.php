<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How an online (Paystack) membership payment's net income is shared
 * between HQ, branch and division, worked out once by
 * IncomeDistributionCalculator when the webhook fulfils the payment and
 * never recalculated, so a closed quarter never changes. All money in kobo,
 * percentages in basis points (25% = 2500). Nullable: manual payments
 * leave them empty.
 *
 * No backfill: Paystack payments fulfilled before this migration keep these
 * columns null and are listed in the output for a manual decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->dateTime('paid_at')->nullable()->after('gateway_response')
                ->comment("Paystack's paid_at, in Africa/Lagos time; decides the quarter");
            $table->unsignedBigInteger('amount_paid_kobo')->nullable()->after('paid_at');
            $table->unsignedBigInteger('paystack_fee_kobo')->nullable()->after('amount_paid_kobo');
            $table->unsignedBigInteger('net_kobo')->nullable()->after('paystack_fee_kobo');
            $table->unsignedBigInteger('hq_share_kobo')->nullable()->after('net_kobo');
            $table->unsignedBigInteger('branch_share_kobo')->nullable()->after('hq_share_kobo');
            $table->unsignedBigInteger('division_share_kobo')->nullable()->after('branch_share_kobo');
            $table->unsignedSmallInteger('hq_pct_bp')->nullable()->after('division_share_kobo');
            $table->unsignedSmallInteger('branch_pct_bp')->nullable()->after('hq_pct_bp');
            $table->unsignedSmallInteger('division_pct_bp')->nullable()->after('branch_pct_bp');
            $table->string('distribution_rule', 20)->nullable()->after('division_pct_bp')
                ->comment('standard, branch_level or hq_only');

            $table->index(['payment_channel', 'paid_at'], 'membership_payments_channel_paid_at_index');
        });

        $unsplit = DB::table('membership_payments')
            ->where('payment_channel', 'paystack')
            ->pluck('id');

        if ($unsplit->isNotEmpty()) {
            echo sprintf(
                "  %d existing Paystack payment(s) left without income distribution figures (ids: %s).\n",
                $unsplit->count(),
                $unsplit->implode(', ')
            );
        }
    }

    public function down(): void
    {
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->dropIndex('membership_payments_channel_paid_at_index');
            $table->dropColumn([
                'paid_at',
                'amount_paid_kobo',
                'paystack_fee_kobo',
                'net_kobo',
                'hq_share_kobo',
                'branch_share_kobo',
                'division_share_kobo',
                'hq_pct_bp',
                'branch_pct_bp',
                'division_pct_bp',
                'distribution_rule',
            ]);
        });
    }
};
