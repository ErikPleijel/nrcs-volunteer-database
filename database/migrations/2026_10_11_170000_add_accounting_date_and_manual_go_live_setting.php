<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Income Distribution, Tab 2 (manual payments):
 *
 * - membership_payments.accounting_date: the day a manual payment counts in
 *   the report (its quarter). The approval date for payments approved in
 *   this system; the payment date for legacy imports, which had no
 *   approval step (their decided_at is unreliable). Filled once, together
 *   with the income shares; null until then and for Paystack payments,
 *   which use paid_at.
 *
 * - setting fee_distribution.manual_go_live_date (empty): from that day
 *   manual payments are shared out for real; earlier quarters are shown as
 *   estimates for comparison. Edited on the Fee distribution page.
 */
return new class extends Migration
{
    private const GO_LIVE_KEY = 'fee_distribution.manual_go_live_date';

    public function up(): void
    {
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->date('accounting_date')->nullable()->after('paid_at')
                ->comment('Manual payments: the day it counts in the Income Distribution report');

            $table->index(['payment_channel', 'accounting_date'], 'membership_payments_channel_accounting_date_index');
        });

        Setting::firstOrCreate(['key' => self::GO_LIVE_KEY], [
            'type' => 'date',
            'group' => 'fee_distribution',
            'label' => 'Manual payments go-live date',
            'description' => 'From this day manual (branch-collected) payments are shared out for real. Earlier quarters are estimates for comparison. Empty: not live yet.',
            'value' => '',
            'autoload' => true,
        ]);

        // Setting::get() caches a missing row's default forever.
        Cache::forget('setting.'.self::GO_LIVE_KEY);
    }

    public function down(): void
    {
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->dropIndex('membership_payments_channel_accounting_date_index');
            $table->dropColumn('accounting_date');
        });

        Setting::where('key', self::GO_LIVE_KEY)->delete();
        Cache::forget('setting.'.self::GO_LIVE_KEY);
    }
};
