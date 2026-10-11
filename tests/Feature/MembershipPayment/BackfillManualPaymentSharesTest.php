<?php

/**
 * payments:backfill-manual-shares — Income Distribution Tab 2, step 4:
 * stores amount, shares and accounting_date on approved, not deleted manual
 * payments without a split (the legacy imports). Safe to repeat; never
 * touches Paystack, pending, rejected, deleted or already-split payments;
 * lists (never guesses) rows whose amount cannot be worked out.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $this->kano = Branch::create(['name' => 'Kano', 'code' => 'KAN']);
    $this->kanoDivision = Division::create(['name' => 'Kano Municipal', 'branch_id' => $this->kano->id]);
    $this->fee = MembershipFee::factory()->create(['amount' => 500, 'id_card_fee' => 500]);
});

/**
 * A payment as the legacy import leaves it: approved, no approver,
 * decided_at one hour before the payment date (the known import shift).
 */
function legacyPayment(array $overrides = []): MembershipPayment
{
    return MembershipPayment::factory()->approved()->create(array_merge([
        'membership_fee_id' => test()->fee->id,
        'branch_id' => test()->kano->id,
        'division_id' => test()->kanoDivision->id,
        'payment_date' => '2016-07-14',
        'decided_at' => '2016-07-13 23:00:00',
        'decided_by_user_id' => null,
        'id_card_included' => false,
        'payment_channel' => 'manual',
    ], $overrides));
}

function backfilled(MembershipPayment $payment): MembershipPayment
{
    return MembershipPayment::withAnyApprovalStatus()->find($payment->id);
}

test('a legacy payment gets amount, shares and its payment date as accounting date', function () {
    $withCard = legacyPayment(['id_card_included' => true]);
    $withoutCard = legacyPayment();

    $this->artisan('payments:backfill-manual-shares')
        ->expectsOutputToContain('Filled 2 approved manual payments.')
        ->assertSuccessful();

    $withCard = backfilled($withCard);
    expect($withCard->amount_paid_kobo)->toBe(100_000)
        ->and($withCard->paystack_fee_kobo)->toBe(0)
        ->and($withCard->net_kobo)->toBe(100_000)
        ->and($withCard->distribution_rule)->toBe('standard')
        ->and([$withCard->hq_share_kobo, $withCard->branch_share_kobo, $withCard->division_share_kobo])->toBe([25_000, 25_000, 50_000])
        ->and([$withCard->hq_pct_bp, $withCard->branch_pct_bp, $withCard->division_pct_bp])->toBe([2500, 2500, 5000])
        // Not 2016-07-13: the shifted decided_at is ignored for legacy rows.
        ->and($withCard->accounting_date->toDateString())->toBe('2016-07-14');

    expect(backfilled($withoutCard)->amount_paid_kobo)->toBe(50_000);
});

test('an organisation payment is filled with the branch level rule, even with a division stored', function () {
    $organisation = \App\Models\Organisation::create(['name' => 'Sponsor Org', 'branch_id' => $this->kano->id]);
    $payment = legacyPayment(['organisation_id' => $organisation->id]);

    $this->artisan('payments:backfill-manual-shares')->assertSuccessful();

    $payment = backfilled($payment);
    expect($payment->division_id)->toBe($this->kanoDivision->id)
        ->and($payment->distribution_rule)->toBe('branch_level')
        ->and($payment->division_share_kobo)->toBe(0)
        ->and([$payment->hq_share_kobo, $payment->branch_share_kobo])->toBe([12_500, 37_500]);
});

test('a payment approved in this system uses its approval date', function () {
    $approver = User::factory()->create();
    $payment = legacyPayment(['decided_by_user_id' => $approver->id, 'payment_date' => '2026-06-28', 'decided_at' => '2026-07-02 10:00:00']);

    $this->artisan('payments:backfill-manual-shares')->assertSuccessful();

    expect(backfilled($payment)->accounting_date->toDateString())->toBe('2026-07-02');
});

test('it skips pending, rejected, deleted, Paystack and already-split payments, and says so', function () {
    $pending = legacyPayment(['approval_status' => 'pending', 'decided_at' => null]);
    $rejected = legacyPayment(['approval_status' => 'rejected']);
    $deleted = legacyPayment(['is_deleted' => true]);
    $paystack = legacyPayment(['payment_channel' => 'paystack', 'gateway_reference' => 'PSK-x']);
    $split = legacyPayment(['distribution_rule' => 'standard', 'net_kobo' => 123, 'hq_share_kobo' => 23, 'branch_share_kobo' => 50, 'division_share_kobo' => 50, 'accounting_date' => '2025-01-01']);
    $todo = legacyPayment();

    $this->artisan('payments:backfill-manual-shares')
        ->expectsOutputToContain('Filled 1 approved manual payment.')
        ->expectsOutputToContain('already have shares')
        ->expectsOutputToContain('pending approval (shares come with approval)')
        ->expectsOutputToContain('rejected')
        ->expectsOutputToContain('deleted')
        ->assertSuccessful();

    foreach ([$pending, $rejected, $deleted, $paystack] as $untouched) {
        expect(backfilled($untouched)->distribution_rule)->toBeNull()
            ->and(backfilled($untouched)->accounting_date)->toBeNull();
    }

    expect(backfilled($split)->net_kobo)->toBe(123)
        ->and(backfilled($split)->accounting_date->toDateString())->toBe('2025-01-01')
        ->and(backfilled($todo)->distribution_rule)->toBe('standard');
});

test('running it again fills nothing and changes nothing', function () {
    $payment = legacyPayment();
    $this->artisan('payments:backfill-manual-shares')->assertSuccessful();
    $after = DB::table('membership_payments')->where('id', $payment->id)->first();

    $this->artisan('payments:backfill-manual-shares')
        ->expectsOutputToContain('Nothing to fill')
        ->assertSuccessful();

    expect(DB::table('membership_payments')->where('id', $payment->id)->first())->toEqual($after);
});

test('it uses one snapshot of the current percentages', function () {
    foreach (['hq' => 40, 'branch' => 20, 'division' => 40] as $party => $value) {
        Setting::where('key', "fee_distribution.standard.{$party}")->update(['value' => (string) $value]);
    }
    Cache::flush();
    $payment = legacyPayment();

    $this->artisan('payments:backfill-manual-shares')
        ->expectsOutputToContain('Percentages used: payments with a division HQ 40% / Branch 20% / Division 40%')
        ->assertSuccessful();

    expect([backfilled($payment)->hq_pct_bp, backfilled($payment)->branch_pct_bp, backfilled($payment)->division_pct_bp])->toBe([4000, 2000, 4000]);
});

test('a payment whose fee is missing is listed, not guessed, and the command fails', function () {
    $good = legacyPayment();
    $orphan = legacyPayment();
    // Fees in use cannot be deleted through the app; simulate a damaged import.
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    DB::table('membership_payments')->where('id', $orphan->id)->update(['membership_fee_id' => 999_999]);
    DB::statement('SET FOREIGN_KEY_CHECKS=1');

    // One table row lists the id and the reason ("Payment id" column is 10 wide).
    $row = sprintf('| %-10s | fee #999999 does not exist', $orphan->id);

    $this->artisan('payments:backfill-manual-shares')
        ->expectsOutputToContain('1 payment could not be filled because the amount could not be worked out')
        ->expectsOutputToContain($row)
        ->assertFailed();

    expect(backfilled($orphan)->distribution_rule)->toBeNull()
        ->and(backfilled($good)->distribution_rule)->toBe('standard');
});

test('--dry-run reports what it would fill and writes nothing', function () {
    $payment = legacyPayment();

    $this->artisan('payments:backfill-manual-shares', ['--dry-run' => true])
        ->expectsOutputToContain('DRY RUN')
        ->expectsOutputToContain('Would fill 1 approved manual payment.')
        ->assertSuccessful();

    expect(backfilled($payment)->distribution_rule)->toBeNull();
});

test('it works across several batches', function () {
    $payments = collect(range(1, 7))->map(fn () => legacyPayment());

    $this->artisan('payments:backfill-manual-shares', ['--chunk' => 3])
        ->expectsOutputToContain('Filled 7 approved manual payments.')
        ->assertSuccessful();

    expect($payments->map(fn ($p) => backfilled($p)->distribution_rule)->unique()->all())->toBe(['standard']);
});
