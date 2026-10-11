<?php

/**
 * Income Distribution Tab 2, step 3: when a manual payment is approved —
 * one at a time or in bulk — it gets its amount (fee + ID card fee from its
 * own fee row), its HQ / branch / division shares (current Fee distribution
 * percentages) and accounting_date (the approval date). Stored once; never
 * for Paystack payments, never recalculated.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Cache::flush();

    $permissions = ['manage-admin-panel', 'approve_payments', 'view_payments', 'add_payments'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->approver = User::factory()->create();
    $this->approver->assignRole('national_db_administrator');

    $this->kano = Branch::create(['name' => 'Kano', 'code' => 'KAN']);
    $this->kanoDivision = Division::create(['name' => 'Kano Municipal', 'branch_id' => $this->kano->id]);

    // ₦5,000 + ₦2,000 ID card.
    $this->fee = MembershipFee::factory()->create(['amount' => 5000, 'id_card_fee' => 2000, 'is_active' => true]);
});

/** A pending manual payment entered by someone other than the approver. */
function pendingManualPayment(array $overrides = []): MembershipPayment
{
    $member = User::factory()->create(['branch_id' => test()->kano->id, 'division_id' => test()->kanoDivision->id]);

    return MembershipPayment::factory()->create(array_merge([
        'user_id' => $member->id,
        'submitted_by_user_id' => User::factory()->create()->id,
        'membership_fee_id' => test()->fee->id,
        'branch_id' => test()->kano->id,
        'division_id' => test()->kanoDivision->id,
        'id_card_included' => false,
        'payment_channel' => 'manual',
    ], $overrides));
}

function approveOne(MembershipPayment $payment)
{
    return test()->actingAs(test()->approver)->post(route('membership-payments.approve', $payment->id));
}

function freshPayment(MembershipPayment $payment): MembershipPayment
{
    return MembershipPayment::withAnyApprovalStatus()->find($payment->id);
}

test('approving a manual payment stores the amount, shares and accounting date', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-08-14 10:00:00', 'Africa/Lagos'));
    $payment = pendingManualPayment(['id_card_included' => true]);

    expect($payment->approval_status)->toBe(MembershipPayment::PENDING)
        ->and($payment->distribution_rule)->toBeNull();

    approveOne($payment)->assertSessionHas('success');

    $payment = freshPayment($payment);
    expect($payment->approval_status)->toBe(MembershipPayment::APPROVED)
        ->and($payment->amount_paid_kobo)->toBe(700_000)
        ->and($payment->paystack_fee_kobo)->toBe(0)
        ->and($payment->net_kobo)->toBe(700_000)
        ->and($payment->distribution_rule)->toBe('standard')
        ->and([$payment->hq_pct_bp, $payment->branch_pct_bp, $payment->division_pct_bp])->toBe([2500, 2500, 5000])
        ->and([$payment->hq_share_kobo, $payment->branch_share_kobo, $payment->division_share_kobo])->toBe([175_000, 175_000, 350_000])
        ->and($payment->accounting_date->toDateString())->toBe('2026-08-14')
        ->and($payment->paid_at)->toBeNull();
});

test('without the ID card only the fee counts', function () {
    $payment = pendingManualPayment(['id_card_included' => false]);

    approveOne($payment);

    expect(freshPayment($payment)->amount_paid_kobo)->toBe(500_000);
});

test('the accounting date is the approval date in Lagos time, even late at night on a quarter\'s last day', function () {
    $payment = pendingManualPayment(['payment_date' => '2026-06-20']);
    // 23:30 in Lagos is 22:30 UTC — still 30 September.
    $this->travelTo(\Carbon\Carbon::parse('2026-09-30 23:30:00', 'Africa/Lagos'));

    approveOne($payment);

    expect(freshPayment($payment)->accounting_date->toDateString())->toBe('2026-09-30');
});

test('a late entry counts in the quarter it is approved, not the quarter it was paid', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-10-03 09:00:00', 'Africa/Lagos'));
    $payment = pendingManualPayment(['payment_date' => '2026-08-01']);

    approveOne($payment);

    expect(freshPayment($payment)->accounting_date->toDateString())->toBe('2026-10-03');
});

test('bulk approval stores the shares on every approved payment', function () {
    $first = pendingManualPayment();
    $second = pendingManualPayment(['id_card_included' => true]);

    $this->actingAs($this->approver)
        ->post(route('membership-payments.bulk-approve'), ['ids' => [$first->id, $second->id]])
        ->assertSessionHas('bulk_result', fn ($result) => $result['approved'] === 2);

    expect(freshPayment($first)->net_kobo)->toBe(500_000)
        ->and(freshPayment($second)->net_kobo)->toBe(700_000)
        ->and(freshPayment($first)->distribution_rule)->toBe('standard')
        ->and(freshPayment($second)->accounting_date)->not->toBeNull();
});

test('an organisation payment uses the branch level rule; a payment without a branch goes to HQ', function () {
    $organisation = Organisation::create(['name' => 'Sponsor Org', 'branch_id' => $this->kano->id]);
    $orgFee = MembershipFee::factory()->create(['for_organizations' => true, 'amount' => 20000, 'id_card_fee' => 0]);
    $orgPayment = pendingManualPayment(['organisation_id' => $organisation->id, 'membership_fee_id' => $orgFee->id, 'division_id' => null]);
    $noBranch = pendingManualPayment(['branch_id' => null, 'division_id' => null]);

    approveOne($orgPayment);
    approveOne($noBranch);

    $orgPayment = freshPayment($orgPayment);
    expect($orgPayment->distribution_rule)->toBe('branch_level')
        ->and([$orgPayment->hq_share_kobo, $orgPayment->branch_share_kobo, $orgPayment->division_share_kobo])->toBe([500_000, 1_500_000, 0]);

    $noBranch = freshPayment($noBranch);
    expect($noBranch->distribution_rule)->toBe('hq_only')
        ->and($noBranch->hq_share_kobo)->toBe(500_000);
});

test('a payment with a division but no branch gets the division\'s branch at approval', function () {
    $payment = pendingManualPayment(['branch_id' => null]);

    approveOne($payment);

    $payment = freshPayment($payment);
    expect($payment->branch_id)->toBe($this->kano->id)
        ->and($payment->distribution_rule)->toBe('standard');
});

test('the percentages are those in force at approval, not at entry, and stay put afterwards', function () {
    $payment = pendingManualPayment();

    foreach (['hq' => 30, 'branch' => 20, 'division' => 50] as $party => $value) {
        Setting::where('key', "fee_distribution.standard.{$party}")->update(['value' => (string) $value]);
        Cache::forget("setting.fee_distribution.standard.{$party}");
    }

    approveOne($payment);
    expect([freshPayment($payment)->hq_pct_bp, freshPayment($payment)->branch_pct_bp])->toBe([3000, 2000]);

    Setting::where('key', 'fee_distribution.standard.hq')->update(['value' => '25']);
    Setting::where('key', 'fee_distribution.standard.branch')->update(['value' => '25']);
    Cache::flush();

    expect([freshPayment($payment)->hq_pct_bp, freshPayment($payment)->hq_share_kobo])->toBe([3000, 150_000]);
});

test('the amount comes from the payment\'s own fee row, even after the fee price changed', function () {
    $payment = pendingManualPayment();

    // A price change deactivates the old fee row and creates a new one.
    $this->fee->update(['is_active' => false]);
    MembershipFee::factory()->create(['name' => $this->fee->name, 'amount' => 9000, 'id_card_fee' => 2000, 'is_active' => true]);

    approveOne($payment);

    expect(freshPayment($payment)->amount_paid_kobo)->toBe(500_000);
});

test('a split that already exists is never recalculated', function () {
    $payment = pendingManualPayment([
        'distribution_rule' => 'standard',
        'amount_paid_kobo' => 123,
        'net_kobo' => 123,
        'hq_share_kobo' => 23,
        'branch_share_kobo' => 50,
        'division_share_kobo' => 50,
        'accounting_date' => '2025-01-01',
    ]);

    approveOne($payment);

    $payment = freshPayment($payment);
    expect([$payment->net_kobo, $payment->hq_share_kobo, $payment->accounting_date->toDateString()])->toBe([123, 23, '2025-01-01']);
});

test('Paystack payments are left to the webhook', function () {
    $payment = pendingManualPayment(['payment_channel' => 'paystack', 'gateway_reference' => 'PSK-test']);

    $payment->markApprovedViaGateway();

    $payment = freshPayment($payment);
    expect($payment->approval_status)->toBe(MembershipPayment::APPROVED)
        ->and($payment->distribution_rule)->toBeNull()
        ->and($payment->accounting_date)->toBeNull();
});

test('rejected and still-pending payments get no shares', function () {
    $rejected = pendingManualPayment();
    $pending = pendingManualPayment();

    $this->actingAs($this->approver)->post(route('membership-payments.reject', $rejected->id), ['reason' => 'Wrong amount']);

    foreach ([$rejected, $pending] as $payment) {
        $payment = freshPayment($payment);
        expect($payment->distribution_rule)->toBeNull()
            ->and($payment->net_kobo)->toBeNull()
            ->and($payment->accounting_date)->toBeNull();
    }
});
