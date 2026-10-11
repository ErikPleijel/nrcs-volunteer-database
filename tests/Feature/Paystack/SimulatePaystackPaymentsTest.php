<?php

/**
 * payments:simulate — local-only command that writes realistic simulated
 * Paystack membership payments (SIM- references) for the Income
 * Distribution report, and --purge, which removes exactly those rows.
 */

use App\Console\Commands\SimulatePaystackPayments;
use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\PaymentTransaction;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
        $branch = Branch::create(['name' => "{$name} Branch", 'code' => strtoupper(substr($name, 0, 3))]);
        foreach ([1, 2] as $n) {
            $division = Division::create(['name' => "{$name} Division {$n}", 'branch_id' => $branch->id]);
            User::factory()->count(3)->create(['branch_id' => $branch->id, 'division_id' => $division->id, 'lifecycle_status' => 'active']);
        }
    }

    $this->branch = Branch::first();
    $this->division = Division::first();

    // Edge cases: branch but no division, no branch at all, unit with no division.
    User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => null, 'lifecycle_status' => 'active']);
    User::factory()->create(['branch_id' => null, 'division_id' => null, 'lifecycle_status' => 'active']);

    $leader = User::where('division_id', $this->division->id)->first();
    RedCrossUnit::create(['name' => 'Alpha Unit', 'division_id' => $this->division->id, 'team_leader_user_id' => $leader->id, 'is_active' => true]);
    RedCrossUnit::create(['name' => 'Lost Unit', 'division_id' => null, 'is_active' => true]);
    Organisation::create(['name' => 'Sponsor Org', 'branch_id' => $this->branch->id]);

    MembershipFee::factory()->create(['amount' => 5000, 'id_card_fee' => 2000, 'is_active' => true]);
    MembershipFee::factory()->create(['amount' => 1500, 'id_card_fee' => 500, 'is_active' => true]);
    MembershipFee::factory()->create(['amount' => 20000, 'for_organizations' => true, 'is_active' => true]);
    MembershipFee::factory()->forRedCrossUnits()->create(['amount' => 20000, 'is_active' => true]);
});

test('the simulated Paystack charge is 1.5% + ₦100, the ₦100 waived under ₦2,500, capped at ₦2,000', function (int $amountKobo, int $expectedFeeKobo) {
    expect(SimulatePaystackPayments::simulatedPaystackFee($amountKobo))->toBe($expectedFeeKobo);
})->with([
    '₦50' => [5_000, 75],
    '₦2,000' => [200_000, 3_000],
    '₦2,500' => [250_000, 13_750],
    '₦7,000' => [700_000, 20_500],
    '₦75,000' => [7_500_000, 122_500],
    '₦200,000 (capped)' => [20_000_000, 200_000],
]);

test('it refuses to run, or purge, outside local and testing', function (string $environment) {
    // A simulated row that a purge would delete if it were allowed to run.
    $this->artisan('payments:simulate', ['--count' => 3])->assertSuccessful();

    app()->detectEnvironment(fn () => $environment);

    $this->artisan('payments:simulate', ['--count' => 10])
        ->expectsOutput("payments:simulate only runs when APP_ENV is local or testing (this is \"{$environment}\"). Nothing was changed.")
        ->assertFailed();

    $this->artisan('payments:simulate', ['--purge' => true])
        ->expectsOutput("payments:simulate only runs when APP_ENV is local or testing (this is \"{$environment}\"). Nothing was changed.")
        ->assertFailed();

    expect(DB::table('membership_payments')->count())->toBe(3)
        ->and(DB::table('payment_transactions')->count())->toBe(3);
})->with(['production', 'staging', 'development']);

test('it runs in the local environment', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->artisan('payments:simulate', ['--count' => 5])->assertSuccessful();

    expect(DB::table('membership_payments')->count())->toBe(5);
});

test('with --edge-cases it creates approved Paystack payments that look like the webhook made them', function () {
    // Fixed seed: the personal/organisation/unit mix is random.
    mt_srand(20261011);

    $this->artisan('payments:simulate', ['--count' => 60, '--from' => '2025-01-01', '--to' => '2026-09-30', '--edge-cases' => true])
        ->assertSuccessful();

    $payments = MembershipPayment::withAnyApprovalStatus()->get();
    expect($payments)->toHaveCount(60);

    foreach ($payments as $payment) {
        $transaction = PaymentTransaction::where('membership_payment_id', $payment->id)->sole();

        expect($payment->gateway_reference)->toStartWith('SIM-')
            ->and($transaction->reference)->toBe($payment->gateway_reference)
            ->and($transaction->status)->toBe('success')
            ->and($transaction->meta['simulated'])->toBeTrue()
            ->and($transaction->raw_payload['data']['amount'])->toBe($payment->amount_paid_kobo)
            ->and($transaction->raw_payload['data']['fees'])->toBe($payment->paystack_fee_kobo)
            ->and($transaction->amount)->toBe($payment->amount_paid_kobo)
            ->and($payment->payment_channel)->toBe('paystack')
            ->and($payment->approval_status)->toBe(MembershipPayment::APPROVED)
            ->and($payment->decided_at)->not->toBeNull()
            ->and($payment->decided_by_user_id)->toBeNull()
            ->and($payment->paystack_fee_kobo)->toBe(SimulatePaystackPayments::simulatedPaystackFee($payment->amount_paid_kobo))
            ->and($payment->net_kobo)->toBe($payment->amount_paid_kobo - $payment->paystack_fee_kobo)
            ->and($payment->hq_share_kobo + $payment->branch_share_kobo + $payment->division_share_kobo)->toBe($payment->net_kobo)
            ->and($payment->paid_at->between('2025-01-01 00:00:00', '2026-09-30 23:59:59'))->toBeTrue()
            ->and($payment->payment_date->toDateString())->toBe($payment->paid_at->toDateString());

        // Paystack's paid_at is UTC; the stored paid_at is Lagos time.
        expect(\Carbon\Carbon::parse($transaction->raw_payload['data']['paid_at'])->setTimezone('Africa/Lagos')->format('Y-m-d H:i:s'))
            ->toBe($payment->paid_at->format('Y-m-d H:i:s'));
    }

    // With --edge-cases: all three rules, every branch, and organisation and unit payments appear.
    expect($payments->pluck('distribution_rule')->unique()->sort()->values()->all())->toBe(['branch_level', 'hq_only', 'standard'])
        ->and($payments->pluck('branch_id')->filter()->unique()->count())->toBe(3)
        ->and($payments->whereNotNull('red_cross_unit_id')->count())->toBeGreaterThan(0)
        ->and($payments->whereNotNull('organisation_id')->count())->toBeGreaterThan(0)
        ->and($payments->where('id_card_included', true)->count())->toBeGreaterThan(0);
});

test('by default every payment has a branch and division, organisation payments a branch only', function () {
    mt_srand(20261011);

    $this->artisan('payments:simulate', ['--count' => 60])->assertSuccessful();

    $payments = MembershipPayment::withAnyApprovalStatus()->get();
    $organisationPayments = $payments->whereNotNull('organisation_id');
    $otherPayments = $payments->whereNull('organisation_id');

    expect($payments)->toHaveCount(60)
        ->and($organisationPayments->count())->toBeGreaterThan(0)
        ->and($payments->whereNull('branch_id')->count())->toBe(0)
        ->and($organisationPayments->pluck('division_id')->unique()->values()->all())->toBe([null])
        ->and($organisationPayments->pluck('distribution_rule')->unique()->values()->all())->toBe(['branch_level'])
        ->and($otherPayments->whereNull('division_id')->count())->toBe(0)
        ->and($otherPayments->pluck('distribution_rule')->unique()->values()->all())->toBe(['standard']);
});

test('it does not touch the payers\' lifecycle status', function () {
    $before = User::pluck('lifecycle_status', 'id')->all();

    $this->artisan('payments:simulate', ['--count' => 20])->assertSuccessful();

    expect(User::pluck('lifecycle_status', 'id')->all())->toBe($before);
});

test('--purge removes only the simulated rows', function () {
    $this->artisan('payments:simulate', ['--count' => 15])->assertSuccessful();

    $user = User::whereNotNull('division_id')->first();
    $realPaystack = MembershipPayment::factory()->approved()->create([
        'user_id' => $user->id,
        'payment_channel' => 'paystack',
        'gateway_reference' => 'PSK-real-payment',
    ]);
    $manual = MembershipPayment::factory()->approved()->create(['user_id' => $user->id]);
    $realTransaction = PaymentTransaction::create([
        'user_id' => $user->id,
        'payable_type' => 'membership_payment',
        'reference' => 'PSK-real-payment',
        'amount' => 500_000,
        'status' => 'success',
        'membership_payment_id' => $realPaystack->id,
    ]);

    $this->artisan('payments:simulate', ['--purge' => true])
        ->expectsOutput('Deleted 15 simulated membership payments and 15 simulated payment transactions.')
        ->assertSuccessful();

    expect(MembershipPayment::withAnyApprovalStatus()->pluck('id')->sort()->values()->all())->toBe(collect([$realPaystack->id, $manual->id])->sort()->values()->all())
        ->and(PaymentTransaction::pluck('id')->all())->toBe([$realTransaction->id]);
});

test('invalid options are refused', function (array $options) {
    $this->artisan('payments:simulate', $options)->assertFailed();

    expect(DB::table('membership_payments')->count())->toBe(0);
})->with([
    'zero count' => [['--count' => 0]],
    'from after to' => [['--from' => '2026-01-01', '--to' => '2025-01-01']],
    'bad date' => [['--from' => 'not-a-date']],
]);
