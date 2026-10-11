<?php

/**
 * The Paystack webhook stores the income distribution on the membership
 * payment it creates: amount paid, Paystack charge, net, paid_at (Africa/
 * Lagos) and the HQ / branch / division split from the Fee distribution
 * settings (IncomeDistributionCalculator). Http::fake() stands in for
 * Paystack's verify endpoint.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Services\IncomeDistributionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config(['paystack.enabled' => true, 'paystack.public_key' => 'pk_test_public', 'paystack.secret_key' => 'sk_test_secret']);

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha One', 'branch_id' => $this->branch->id]);
    $this->fee = MembershipFee::factory()->create(['amount' => 5000]);
});

/**
 * Create an initiated transaction for $user, fake Paystack's verify reply
 * with $verifyData merged in, post a signed charge.success webhook and
 * return the membership payment it created.
 */
function fulfilWithVerifyData(User $user, int $amountKobo, array $verifyData, array $transactionOverrides = []): MembershipPayment
{
    $reference = 'PSK-'.fake()->uuid();

    PaymentTransaction::create([
        'user_id' => $user->id,
        'payable_type' => 'membership_payment',
        'reference' => $reference,
        'amount' => $amountKobo,
        'status' => 'initiated',
        'meta' => ['payment_type' => 'membership', 'membership_fee_id' => test()->fee->id] + ($transactionOverrides['meta'] ?? []),
    ] + array_diff_key($transactionOverrides, ['meta' => true]));

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'success', 'amount' => $amountKobo, 'reference' => $reference] + $verifyData,
        ], 200),
    ]);

    $payload = ['event' => 'charge.success', 'data' => ['reference' => $reference]];
    $rawBody = json_encode($payload);

    test()->postJson('/webhooks/paystack', $payload, [
        'x-paystack-signature' => hash_hmac('sha512', $rawBody, config('paystack.secret_key')),
    ])->assertOk()->assertJson(['status' => 'processed']);

    return MembershipPayment::where('gateway_reference', $reference)->sole();
}

function setFeeDistribution(string $key, int $value): void
{
    Setting::where('key', "fee_distribution.{$key}")->update(['value' => (string) $value]);
    Cache::forget("setting.fee_distribution.{$key}");
}

test('a personal payment stores the amount, charge, net, paid_at and the standard split', function () {
    $user = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);

    // ₦5,000 paid, ₦175 Paystack charge -> ₦4,825 net.
    $payment = fulfilWithVerifyData($user, 500_000, ['fees' => 17_500, 'paid_at' => '2026-08-14T10:15:00.000Z']);

    expect($payment->branch_id)->toBe($this->branch->id)
        ->and($payment->division_id)->toBe($this->division->id)
        ->and($payment->paid_at->format('Y-m-d H:i:s'))->toBe('2026-08-14 11:15:00')
        ->and($payment->amount_paid_kobo)->toBe(500_000)
        ->and($payment->paystack_fee_kobo)->toBe(17_500)
        ->and($payment->net_kobo)->toBe(482_500)
        ->and($payment->distribution_rule)->toBe('standard')
        ->and($payment->hq_pct_bp)->toBe(2500)
        ->and($payment->branch_pct_bp)->toBe(2500)
        ->and($payment->division_pct_bp)->toBe(5000)
        ->and($payment->hq_share_kobo)->toBe(120_625)
        ->and($payment->branch_share_kobo)->toBe(120_625)
        ->and($payment->division_share_kobo)->toBe(241_250);
});

test('paid_at is converted from UTC to Lagos time, which can move it into the next quarter', function () {
    $user = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);

    $payment = fulfilWithVerifyData($user, 500_000, ['fees' => 17_500, 'paid_at' => '2026-09-30T23:30:00.000Z']);

    expect($payment->paid_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 00:30:00');
});

test('an organisation payment uses the branch_level rule', function () {
    $user = User::factory()->create();
    $organisation = Organisation::create(['name' => 'Sponsor Org', 'branch_id' => $this->branch->id]);

    $payment = fulfilWithVerifyData($user, 2_000_000, ['fees' => 30_000, 'paid_at' => '2026-08-14T10:15:00.000Z'], [
        'organisation_id' => $organisation->id,
        'meta' => ['organisation_id' => $organisation->id],
    ]);

    expect($payment->division_id)->toBeNull()
        ->and($payment->distribution_rule)->toBe('branch_level')
        ->and($payment->net_kobo)->toBe(1_970_000)
        ->and($payment->hq_share_kobo)->toBe(492_500)
        ->and($payment->branch_share_kobo)->toBe(1_477_500)
        ->and($payment->division_share_kobo)->toBe(0)
        ->and($payment->division_pct_bp)->toBe(0);
});

test('a payer with no branch sends everything to HQ', function () {
    $user = User::factory()->create(['branch_id' => null, 'division_id' => null]);

    $payment = fulfilWithVerifyData($user, 500_000, ['fees' => 17_500, 'paid_at' => '2026-08-14T10:15:00.000Z']);

    expect($payment->distribution_rule)->toBe('hq_only')
        ->and($payment->hq_pct_bp)->toBe(10000)
        ->and($payment->hq_share_kobo)->toBe(482_500)
        ->and($payment->branch_share_kobo)->toBe(0)
        ->and($payment->division_share_kobo)->toBe(0);
});

test('a payer with a division but no branch gets the division\'s branch and the standard rule', function () {
    $user = User::factory()->create(['branch_id' => null, 'division_id' => $this->division->id]);

    $payment = fulfilWithVerifyData($user, 500_000, ['fees' => 17_500, 'paid_at' => '2026-08-14T10:15:00.000Z']);

    expect($payment->branch_id)->toBe($this->branch->id)
        ->and($payment->division_id)->toBe($this->division->id)
        ->and($payment->distribution_rule)->toBe('standard')
        ->and([$payment->hq_share_kobo, $payment->branch_share_kobo, $payment->division_share_kobo])->toBe([120_625, 120_625, 241_250]);
});

test('a payer whose division has no branch goes to HQ', function () {
    $orphanDivision = Division::create(['name' => 'Orphan Division', 'branch_id' => null]);
    $user = User::factory()->create(['branch_id' => null, 'division_id' => $orphanDivision->id]);

    $payment = fulfilWithVerifyData($user, 500_000, ['fees' => 17_500, 'paid_at' => '2026-08-14T10:15:00.000Z']);

    expect($payment->branch_id)->toBeNull()
        ->and($payment->division_id)->toBe($orphanDivision->id)
        ->and($payment->distribution_rule)->toBe('hq_only')
        ->and($payment->hq_share_kobo)->toBe(482_500);
});

test('resolveBranchId keeps a stored branch and only looks up a missing one', function () {
    $otherBranch = Branch::create(['name' => 'Beta Branch', 'code' => 'BET']);

    expect(IncomeDistributionCalculator::resolveBranchId($otherBranch->id, $this->division->id))->toBe($otherBranch->id)
        ->and(IncomeDistributionCalculator::resolveBranchId(null, $this->division->id))->toBe($this->branch->id)
        ->and(IncomeDistributionCalculator::resolveBranchId(null, null))->toBeNull()
        ->and(IncomeDistributionCalculator::resolveBranchId(null, 999_999))->toBeNull();
});

test('the leftover kobo from rounding goes to HQ', function () {
    $user = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);

    // Net 10,001 kobo: branch 2,500, division 5,000, HQ 2,501.
    $payment = fulfilWithVerifyData($user, 10_101, ['fees' => 100, 'paid_at' => '2026-08-14T10:15:00.000Z']);

    expect($payment->net_kobo)->toBe(10_001)
        ->and($payment->branch_share_kobo)->toBe(2_500)
        ->and($payment->division_share_kobo)->toBe(5_000)
        ->and($payment->hq_share_kobo)->toBe(2_501);
});

test('the split uses the percentages from Settings, and changing them leaves earlier payments alone', function () {
    $user = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);

    $first = fulfilWithVerifyData($user, 1_000_000, ['fees' => 0, 'paid_at' => '2026-08-14T10:15:00.000Z']);

    setFeeDistribution('standard.hq', 40);
    setFeeDistribution('standard.branch', 20);
    setFeeDistribution('standard.division', 40);

    $second = fulfilWithVerifyData($user, 1_000_000, ['fees' => 0, 'paid_at' => '2026-08-15T10:15:00.000Z']);

    expect([$second->hq_pct_bp, $second->branch_pct_bp, $second->division_pct_bp])->toBe([4000, 2000, 4000])
        ->and([$second->hq_share_kobo, $second->branch_share_kobo, $second->division_share_kobo])->toBe([400_000, 200_000, 400_000]);

    $first->refresh();
    expect([$first->hq_pct_bp, $first->branch_pct_bp, $first->division_pct_bp])->toBe([2500, 2500, 5000])
        ->and([$first->hq_share_kobo, $first->branch_share_kobo, $first->division_share_kobo])->toBe([250_000, 250_000, 500_000]);
});

test('settings that do not add up to 100 fall back to the defaults, with a warning, so the payment is still fulfilled', function () {
    Log::spy();
    setFeeDistribution('standard.division', 90);
    $user = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);

    $payment = fulfilWithVerifyData($user, 1_000_000, ['fees' => 0, 'paid_at' => '2026-08-14T10:15:00.000Z']);

    expect([$payment->hq_pct_bp, $payment->branch_pct_bp, $payment->division_pct_bp])->toBe([2500, 2500, 5000]);
    Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'Fee distribution settings invalid'))->once();
});

test('a missing Paystack charge is recorded as 0 with a warning', function () {
    Log::spy();
    $user = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);

    $payment = fulfilWithVerifyData($user, 500_000, ['paid_at' => '2026-08-14T10:15:00.000Z']);

    expect($payment->paystack_fee_kobo)->toBe(0)
        ->and($payment->net_kobo)->toBe(500_000)
        ->and($payment->hq_share_kobo + $payment->branch_share_kobo + $payment->division_share_kobo)->toBe(500_000);
    Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'data.fees'))->once();
});

test('a missing paid_at falls back to the current time with a warning', function () {
    Log::spy();
    $this->travelTo(\Carbon\Carbon::parse('2026-08-20 09:00:00', 'Africa/Lagos'));
    $user = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);

    $payment = fulfilWithVerifyData($user, 500_000, ['fees' => 17_500]);

    expect($payment->paid_at->format('Y-m-d H:i:s'))->toBe('2026-08-20 09:00:00');
    Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'data.paid_at'))->once();
});

test('a donation gets no income distribution figures', function () {
    $user = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);
    $reference = 'PSK-donation-split';
    PaymentTransaction::create([
        'user_id' => $user->id,
        'payable_type' => 'donation',
        'reference' => $reference,
        'amount' => 100_000,
        'status' => 'initiated',
        'meta' => ['payment_type' => 'donation'],
    ]);
    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'success', 'amount' => 100_000, 'reference' => $reference, 'fees' => 1_500],
        ], 200),
    ]);
    $payload = ['event' => 'charge.success', 'data' => ['reference' => $reference]];

    $this->postJson('/webhooks/paystack', $payload, [
        'x-paystack-signature' => hash_hmac('sha512', json_encode($payload), config('paystack.secret_key')),
    ])->assertOk()->assertJson(['status' => 'processed']);

    expect(MembershipPayment::withAnyApprovalStatus()->exists())->toBeFalse();
});

test('manual payments leave the income distribution columns empty', function () {
    $payment = MembershipPayment::factory()->approved()->create();

    expect($payment->fresh()->only(['paid_at', 'net_kobo', 'hq_share_kobo', 'distribution_rule']))
        ->toBe(['paid_at' => null, 'net_kobo' => null, 'hq_share_kobo' => null, 'distribution_rule' => null]);
});
