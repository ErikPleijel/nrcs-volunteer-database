<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * The Data Protection Officer's contact details as four institutional
 * settings (group 'Data protection'), edited by the National DB
 * administrator on the Settings page. Not a role — see Decisions.md
 * 2026-10-08. All values start empty; firstOrCreate, so an existing row
 * (an admin's edit) is never overwritten.
 */
return new class extends Migration
{
    private const ROWS = [
        'dpo.name' => [
            'label' => 'DPO name',
            'description' => "Name of the Data Protection Officer, or the office (e.g. 'Data Protection Officer, NRCS National Headquarters').",
        ],
        'dpo.address' => [
            'label' => 'DPO postal address',
            'description' => null,
        ],
        'dpo.email' => [
            'label' => 'DPO email',
            'description' => 'Preferably an institutional address, e.g. dpo@…, not a personal one.',
        ],
        'dpo.phone' => [
            'label' => 'DPO phone',
            'description' => null,
        ],
    ];

    public function up(): void
    {
        foreach (self::ROWS as $key => $row) {
            Setting::firstOrCreate(['key' => $key], [
                'type' => 'string',
                'group' => 'Data protection',
                'label' => $row['label'],
                'description' => $row['description'],
                'value' => '',
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
