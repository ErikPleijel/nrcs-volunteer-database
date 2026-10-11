<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\CarbonImmutable;

/**
 * The percentages that split online (Paystack) membership income between
 * HQ, branch and division. Stored as five whole-number settings in the
 * 'fee_distribution' group and edited on their own Settings page
 * (FeeDistributionSettingsController), never on the generic settings form,
 * because each rule must add up to exactly 100.
 *
 * Rules: 'standard' (payment has a division: HQ / branch / division) and
 * 'branch_level' (branch but no division: HQ / branch). The third rule,
 * 'hq_only' (no branch), is always 100% HQ and has no settings.
 */
class FeeDistributionSettings
{
    public const GROUP = 'fee_distribution';

    /**
     * The day manual (staff-entered) payments start being shared out for
     * real (Y-m-d, empty until set). Payments counted before it are shown
     * as estimates for comparison only.
     */
    public const MANUAL_GO_LIVE_KEY = self::GROUP.'.manual_go_live_date';

    /** rule => party => default whole percentage. */
    public const DEFAULTS = [
        'standard' => ['hq' => 25, 'branch' => 25, 'division' => 50],
        'branch_level' => ['hq' => 25, 'branch' => 75],
    ];

    public static function key(string $rule, string $party): string
    {
        return self::GROUP.".{$rule}.{$party}";
    }

    /**
     * Every setting key in the group.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        $keys = [];
        foreach (self::DEFAULTS as $rule => $parties) {
            foreach (array_keys($parties) as $party) {
                $keys[] = self::key($rule, $party);
            }
        }

        return $keys;
    }

    /**
     * The current whole percentages, shaped like DEFAULTS.
     *
     * @return array<string, array<string, int>>
     */
    public static function percentages(): array
    {
        $percentages = [];
        foreach (self::DEFAULTS as $rule => $parties) {
            foreach ($parties as $party => $default) {
                $percentages[$rule][$party] = Setting::getInt(self::key($rule, $party), $default);
            }
        }

        return $percentages;
    }

    /**
     * The manual payments go-live date (start of that day, app timezone),
     * or null while it is not set — then every manual payment counts as
     * before go-live.
     */
    public static function manualGoLiveDate(): ?CarbonImmutable
    {
        $value = (string) Setting::get(self::MANUAL_GO_LIVE_KEY, '');

        // The settings page only stores Y-m-d; anything else counts as unset.
        return $value === '' ? null : (CarbonImmutable::createFromFormat('!Y-m-d', $value) ?: null);
    }
}
