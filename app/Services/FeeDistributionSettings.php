<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\Log;

/**
 * The percentages that split online (Paystack) membership income between
 * HQ, branch and division. Stored as three whole-number settings in the
 * 'fee_distribution' group and edited on their own Settings page
 * (FeeDistributionSettingsController), never on the generic settings form,
 * because each rule must add up to exactly 100.
 *
 * One set of percentages, 'standard' (HQ / branch / division). A payment
 * with a branch but no division (rule 'branch_level') uses the same
 * percentages, its division's share going to the branch; one with no
 * branch ('hq_only') is 100% HQ. Until 2026-10-11 'branch_level' had
 * percentages of its own (removed by migration 2026_10_12_120000).
 */
class FeeDistributionSettings
{
    public const GROUP = 'fee_distribution';

    /** rule => party => default whole percentage. */
    public const DEFAULTS = [
        'standard' => ['hq' => 25, 'branch' => 25, 'division' => 50],
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
     * The day manual (staff-entered) payments start being shared out for
     * real: MANUAL_GO_LIVE_DATE in .env (Y-m-d), as the start of that day
     * in Lagos time. Null while it is not set — then every manual payment
     * counts as before go-live and is shown as an estimate for comparison.
     */
    public static function manualGoLiveDate(): ?CarbonImmutable
    {
        $value = self::manualGoLiveValue();
        if ($value === '') {
            return null;
        }

        // Anything other than a real Y-m-d date counts as unset.
        $date = self::parseGoLiveDate($value);
        if ($date === null) {
            Log::warning('MANUAL_GO_LIVE_DATE is not a valid YYYY-MM-DD date; treated as not set.', ['value' => $value]);
        }

        return $date;
    }

    /**
     * Whether MANUAL_GO_LIVE_DATE is set but not a real YYYY-MM-DD date —
     * a mistake in .env that the report points out to national admins.
     */
    public static function manualGoLiveDateIsInvalid(): bool
    {
        $value = self::manualGoLiveValue();

        return $value !== '' && self::parseGoLiveDate($value) === null;
    }

    private static function manualGoLiveValue(): string
    {
        return trim((string) config('income.manual_go_live_date'));
    }

    private static function parseGoLiveDate(string $value): ?CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Africa/Lagos');
        } catch (InvalidFormatException) {
            return null;
        }

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
