<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Splits the net income of an online (Paystack) membership payment between
 * HQ, branch and division. Run once when the payment is fulfilled; the
 * result is stored on the membership_payments row and never recalculated,
 * so a later change to the percentages never changes an earlier payment.
 *
 * Rules, decided by the branch/division stored on the payment:
 *   standard     - branch and division: HQ / branch / division
 *   branch_level - branch, no division (e.g. organisation payments): HQ / branch
 *   hq_only      - no branch: 100% HQ
 *
 * All money in kobo, percentages in basis points (25% = 2500). Branch and
 * division shares are rounded down and HQ gets the remainder, so the three
 * shares always add up to exactly the net amount.
 */
class IncomeDistributionCalculator
{
    public const STANDARD = 'standard';

    public const BRANCH_LEVEL = 'branch_level';

    public const HQ_ONLY = 'hq_only';

    /**
     * Every income-distribution column for a fulfilled Paystack payment,
     * keyed by membership_payments column name, using the current
     * percentages from Settings.
     *
     * @return array<string, mixed>
     */
    public static function paymentColumns(
        int $amountPaidKobo,
        int $paystackFeeKobo,
        CarbonInterface $paidAt,
        ?int $branchId,
        ?int $divisionId,
    ): array {
        $netKobo = $amountPaidKobo - $paystackFeeKobo;

        return [
            'paid_at' => $paidAt,
            'amount_paid_kobo' => $amountPaidKobo,
            'paystack_fee_kobo' => $paystackFeeKobo,
            'net_kobo' => $netKobo,
        ] + self::calculate($netKobo, $branchId, $divisionId, self::currentPercentages());
    }

    /**
     * The pure split: rule, percentages used and the three shares.
     *
     * @param  array<string, array<string, int>>  $percentages  whole percentages, shaped like FeeDistributionSettings::DEFAULTS
     * @return array{distribution_rule: string, hq_pct_bp: int, branch_pct_bp: int, division_pct_bp: int, hq_share_kobo: int, branch_share_kobo: int, division_share_kobo: int}
     *
     * @throws InvalidArgumentException on a negative amount or percentages that do not add up to 100
     */
    public static function calculate(int $netKobo, ?int $branchId, ?int $divisionId, array $percentages): array
    {
        if ($netKobo < 0) {
            throw new InvalidArgumentException("Net amount cannot be negative ({$netKobo} kobo).");
        }

        $rule = match (true) {
            $branchId === null => self::HQ_ONLY,
            $divisionId === null => self::BRANCH_LEVEL,
            default => self::STANDARD,
        };

        $bp = match ($rule) {
            self::STANDARD => [
                'hq' => self::toBasisPoints($percentages['standard']['hq']),
                'branch' => self::toBasisPoints($percentages['standard']['branch']),
                'division' => self::toBasisPoints($percentages['standard']['division']),
            ],
            self::BRANCH_LEVEL => [
                'hq' => self::toBasisPoints($percentages['branch_level']['hq']),
                'branch' => self::toBasisPoints($percentages['branch_level']['branch']),
                'division' => 0,
            ],
            self::HQ_ONLY => ['hq' => 10000, 'branch' => 0, 'division' => 0],
        };

        if (array_sum($bp) !== 10000) {
            throw new InvalidArgumentException("The {$rule} percentages do not add up to 100.");
        }

        $branchShare = intdiv($netKobo * $bp['branch'], 10000);
        $divisionShare = intdiv($netKobo * $bp['division'], 10000);

        return [
            'distribution_rule' => $rule,
            'hq_pct_bp' => $bp['hq'],
            'branch_pct_bp' => $bp['branch'],
            'division_pct_bp' => $bp['division'],
            'hq_share_kobo' => $netKobo - $branchShare - $divisionShare,
            'branch_share_kobo' => $branchShare,
            'division_share_kobo' => $divisionShare,
        ];
    }

    /**
     * The percentages from Settings, or the defaults if a stored rule is
     * out of range or does not add up to 100 (the Settings page prevents
     * that, but a paid payment must never fail to be fulfilled over it).
     *
     * @return array<string, array<string, int>>
     */
    private static function currentPercentages(): array
    {
        $percentages = FeeDistributionSettings::percentages();

        foreach ($percentages as $rule => $parties) {
            $inRange = collect($parties)->every(fn (int $value) => $value >= 0 && $value <= 100);

            if (! $inRange || array_sum($parties) !== 100) {
                Log::warning('Fee distribution settings invalid; using the defaults', [
                    'rule' => $rule,
                    'percentages' => $parties,
                ]);

                return FeeDistributionSettings::DEFAULTS;
            }
        }

        return $percentages;
    }

    private static function toBasisPoints(int $wholePercent): int
    {
        if ($wholePercent < 0 || $wholePercent > 100) {
            throw new InvalidArgumentException("Percentage out of range: {$wholePercent}.");
        }

        return $wholePercent * 100;
    }
}
