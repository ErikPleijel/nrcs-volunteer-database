<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * A payment with a branch but no division (rule branch_level) now uses the
 * standard percentages, its division's share going to the branch, and
 * organisation payments follow the standard rule (Decisions.md 2026-10-11).
 * Removes the branch_level setting rows added by
 * 2026_10_11_120000_add_fee_distribution_settings. Payments already stored
 * keep the shares they were given.
 */
return new class extends Migration
{
    private const ROWS = [
        'fee_distribution.branch_level.hq' => ['label' => 'HQ share (payment with a branch but no division)', 'value' => 25],
        'fee_distribution.branch_level.branch' => ['label' => 'Branch share (payment with a branch but no division)', 'value' => 75],
    ];

    public function up(): void
    {
        foreach (array_keys(self::ROWS) as $key) {
            Setting::where('key', $key)->delete();
            Cache::forget("setting.{$key}");
        }
    }

    public function down(): void
    {
        foreach (self::ROWS as $key => $row) {
            Setting::firstOrCreate(['key' => $key], [
                'type' => 'int',
                'group' => 'fee_distribution',
                'label' => $row['label'],
                'description' => 'Whole percentage of the net online membership income (after the Paystack charge).',
                'value' => $row['value'],
                'autoload' => true,
            ]);
            Cache::forget("setting.{$key}");
        }
    }
};
