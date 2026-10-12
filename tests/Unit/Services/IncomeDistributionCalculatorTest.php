<?php

/**
 * Unit tests for IncomeDistributionCalculator::calculate() — the pure split
 * of a Paystack payment's net income between HQ, branch and division. No
 * database: percentages are passed in.
 */

use App\Services\FeeDistributionSettings;
use App\Services\IncomeDistributionCalculator;

const DEFAULT_SPLIT = FeeDistributionSettings::DEFAULTS;

test('a payment with a branch and division uses the standard rule', function () {
    expect(IncomeDistributionCalculator::calculate(1_000_000, 1, 2, DEFAULT_SPLIT))->toBe([
        'distribution_rule' => 'standard',
        'hq_pct_bp' => 2500,
        'branch_pct_bp' => 2500,
        'division_pct_bp' => 5000,
        'hq_share_kobo' => 250_000,
        'branch_share_kobo' => 250_000,
        'division_share_kobo' => 500_000,
    ]);
});

test('a payment with a branch but no division uses the branch_level rule: the division\'s share goes to the branch', function () {
    expect(IncomeDistributionCalculator::calculate(1_000_000, 1, null, DEFAULT_SPLIT))->toBe([
        'distribution_rule' => 'branch_level',
        'hq_pct_bp' => 2500,
        'branch_pct_bp' => 7500,
        'division_pct_bp' => 0,
        'hq_share_kobo' => 250_000,
        'branch_share_kobo' => 750_000,
        'division_share_kobo' => 0,
    ]);
});

test('branch_level follows the standard percentages, not percentages of its own', function () {
    $split = IncomeDistributionCalculator::calculate(1_000_000, 1, null, [
        'standard' => ['hq' => 20, 'branch' => 30, 'division' => 50],
    ]);

    expect($split)->toBe([
        'distribution_rule' => 'branch_level',
        'hq_pct_bp' => 2000,
        'branch_pct_bp' => 8000, // 30% + the division's 50%
        'division_pct_bp' => 0,
        'hq_share_kobo' => 200_000,
        'branch_share_kobo' => 800_000,
        'division_share_kobo' => 0,
    ]);
});

// calculate() gets the branch already resolved through the division
// (resolveBranchId(), covered in IncomeDistributionWebhookTest), so here no
// branch always means HQ only.
test('calculate() sends a payment with no branch entirely to HQ', function (?int $divisionId) {
    expect(IncomeDistributionCalculator::calculate(1_000_000, null, $divisionId, DEFAULT_SPLIT))->toBe([
        'distribution_rule' => 'hq_only',
        'hq_pct_bp' => 10000,
        'branch_pct_bp' => 0,
        'division_pct_bp' => 0,
        'hq_share_kobo' => 1_000_000,
        'branch_share_kobo' => 0,
        'division_share_kobo' => 0,
    ]);
})->with([
    'no division' => [null],
    'a division' => [2],
]);

test('branch and division are rounded down and HQ gets the leftover kobo', function () {
    // 10,001 kobo: branch 2,500.25 -> 2,500; division 5,000.5 -> 5,000; HQ 2,501.
    $split = IncomeDistributionCalculator::calculate(10_001, 1, 2, DEFAULT_SPLIT);

    expect($split['branch_share_kobo'])->toBe(2_500)
        ->and($split['division_share_kobo'])->toBe(5_000)
        ->and($split['hq_share_kobo'])->toBe(2_501);

    // 3 kobo, branch level: branch 2.25 -> 2; HQ 1.
    $split = IncomeDistributionCalculator::calculate(3, 1, null, DEFAULT_SPLIT);

    expect($split['branch_share_kobo'])->toBe(2)
        ->and($split['hq_share_kobo'])->toBe(1);
});

test('the three shares always add up exactly to the net amount', function () {
    $splits = [
        DEFAULT_SPLIT,
        ['standard' => ['hq' => 33, 'branch' => 33, 'division' => 34]],
        ['standard' => ['hq' => 0, 'branch' => 0, 'division' => 100]],
        ['standard' => ['hq' => 7, 'branch' => 29, 'division' => 64]],
    ];

    mt_srand(20261011);
    foreach ($splits as $percentages) {
        foreach ([[1, 2], [1, null], [null, null]] as [$branchId, $divisionId]) {
            for ($i = 0; $i < 200; $i++) {
                $net = mt_rand(0, 10_000_000);
                $split = IncomeDistributionCalculator::calculate($net, $branchId, $divisionId, $percentages);

                expect($split['hq_share_kobo'] + $split['branch_share_kobo'] + $split['division_share_kobo'])->toBe($net)
                    ->and($split['hq_pct_bp'] + $split['branch_pct_bp'] + $split['division_pct_bp'])->toBe(10000)
                    ->and(min($split['hq_share_kobo'], $split['branch_share_kobo'], $split['division_share_kobo']))->toBeGreaterThanOrEqual(0);
            }
        }
    }
});

test('a zero net amount gives zero shares', function () {
    $split = IncomeDistributionCalculator::calculate(0, 1, 2, DEFAULT_SPLIT);

    expect([$split['hq_share_kobo'], $split['branch_share_kobo'], $split['division_share_kobo']])->toBe([0, 0, 0]);
});

test('a negative net amount is refused', function () {
    IncomeDistributionCalculator::calculate(-1, 1, 2, DEFAULT_SPLIT);
})->throws(InvalidArgumentException::class);

test('percentages that do not add up to 100 are refused', function () {
    IncomeDistributionCalculator::calculate(1_000, 1, 2, [
        'standard' => ['hq' => 25, 'branch' => 25, 'division' => 60],
    ]);
})->throws(InvalidArgumentException::class);

