<?php

namespace App\Services;

use App\Models\Division;
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
 *   hq_only      - no branch, not even through the division: 100% HQ
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
     * Every income-distribution column for a manual (staff-entered)
     * payment: the amount from its own fee row (fee + ID card fee when
     * included — fee rows are versioned, so this is the price that applied
     * to the payment), no Paystack charge, and the split with the current
     * percentages. accounting_date is the caller's: the approval date, or
     * the payment date for legacy imports.
     *
     * @return array<string, mixed>
     */
    public static function manualPaymentColumns(
        string|float|int $feeAmountNaira,
        string|float|int $idCardFeeNaira,
        bool $idCardIncluded,
        ?int $branchId,
        ?int $divisionId,
    ): array {
        $amountKobo = self::nairaToKobo($feeAmountNaira) + ($idCardIncluded ? self::nairaToKobo($idCardFeeNaira) : 0);
        $branchId = self::resolveBranchId($branchId, $divisionId);

        return [
            'branch_id' => $branchId,
            'amount_paid_kobo' => $amountKobo,
            'paystack_fee_kobo' => 0,
            'net_kobo' => $amountKobo,
        ] + self::calculate($amountKobo, $branchId, $divisionId, self::currentPercentages());
    }

    /**
     * A naira amount as stored on membership_fees (decimal(10,2), read as
     * "5000.00") in kobo.
     */
    public static function nairaToKobo(string|float|int $naira): int
    {
        return (int) round(((float) $naira) * 100);
    }

    /**
     * The branch to store on a payment that has a division but no branch:
     * the division's own branch, so the standard rule applies. Null when the
     * division has no branch (divisions.branch_id is a foreign key that is
     * set to null when its branch is deleted) — the payment then goes to HQ.
     */
    public static function resolveBranchId(?int $branchId, ?int $divisionId): ?int
    {
        if ($branchId !== null || $divisionId === null) {
            return $branchId;
        }

        $resolved = Division::whereKey($divisionId)->value('branch_id');

        return $resolved === null ? null : (int) $resolved;
    }

    /**
     * The pure split: rule, percentages used and the three shares. Callers
     * resolve a missing branch first (resolveBranchId()); here, no branch
     * always means hq_only.
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
