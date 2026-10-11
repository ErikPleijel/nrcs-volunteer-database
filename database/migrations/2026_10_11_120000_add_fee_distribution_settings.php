<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * The percentages that split online (Paystack) membership income between
 * HQ, branch and division, as five whole-number settings in the
 * 'fee_distribution' group, edited on their own Settings page (each rule
 * must add up to 100). firstOrCreate, so an existing row (an admin's edit)
 * is never overwritten.
 */
return new class extends Migration
{
    private const ROWS = [
        'fee_distribution.standard.hq' => ['label' => 'HQ share (payment with a division)', 'value' => 25],
        'fee_distribution.standard.branch' => ['label' => 'Branch share (payment with a division)', 'value' => 25],
        'fee_distribution.standard.division' => ['label' => 'Division share (payment with a division)', 'value' => 50],
        'fee_distribution.branch_level.hq' => ['label' => 'HQ share (payment with a branch but no division)', 'value' => 25],
        'fee_distribution.branch_level.branch' => ['label' => 'Branch share (payment with a branch but no division)', 'value' => 75],
    ];

    public function up(): void
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

            // Setting::get() caches a missing row's default forever.
            Cache::forget("setting.{$key}");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::ROWS) as $key) {
            Setting::where('key', $key)->delete();
            Cache::forget("setting.{$key}");
        }
    }
};
