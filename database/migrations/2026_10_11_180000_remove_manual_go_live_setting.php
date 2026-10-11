<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * The manual payments go-live date moved from Settings to .env
 * (MANUAL_GO_LIVE_DATE, read through config/income.php): it is set once on
 * go-live day, not edited by admins. Removes the setting row added by
 * 2026_10_11_170000_add_accounting_date_and_manual_go_live_setting.
 */
return new class extends Migration
{
    private const GO_LIVE_KEY = 'fee_distribution.manual_go_live_date';

    public function up(): void
    {
        Setting::where('key', self::GO_LIVE_KEY)->delete();
        Cache::forget('setting.'.self::GO_LIVE_KEY);
    }

    public function down(): void
    {
        Setting::firstOrCreate(['key' => self::GO_LIVE_KEY], [
            'type' => 'date',
            'group' => 'fee_distribution',
            'label' => 'Manual payments go-live date',
            'description' => 'From this day manual (branch-collected) payments are shared out for real. Earlier quarters are estimates for comparison. Empty: not live yet.',
            'value' => '',
            'autoload' => true,
        ]);
        Cache::forget('setting.'.self::GO_LIVE_KEY);
    }
};
