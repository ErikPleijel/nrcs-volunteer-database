<?php

/**
 * Regression coverage for the double-counting bug fixed in
 * FinancialOverviewReportController's Payments tab: member_amount and
 * volunteer_amount previously had no whereNull('organisation_id') filter, so
 * an organisational payment was summed once into member_amount/
 * volunteer_amount (by fee type) AND again into org_amount, inflating the
 * row total. The fix scopes member_amount/volunteer_amount to
 * whereNull('organisation_id'); org_amount was already correctly scoped via
 * whereNotNull('organisation_id').
 *
 * Since 2026-08-05 the Payments tab is a full-year view (param 'year') with
 * per-quarter columns q1..q4 × member/volunteer/org/rcu and year_total; the
 * same personal-only scoping applies to member/volunteer, and org and RCU
 * payments each count only in their own column.
 *
 * All tests use the 'national' scope (the controller's default), where rows
 * are branches and each row is scoped by branch_id only. All payments are
 * dated 2024-05-15 (Q2 of 2024), safely in the past regardless of when tests
 * run.
 *
 * MembershipFee does NOT use HasFactory in its class body (see
 * MembershipFeeFactory's own docblock), so MembershipFee::factory() does not
 * resolve — MembershipFeeFactory::new()->create() is used directly instead.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\RedCrossUnit;
use App\Models\User;
use Database\Factories\MembershipFeeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/** Find this branch's row in the controller's paymentsData by label (branch name). */
function paymentsRowFor(array $paymentsData, string $label): ?array
{
    foreach ($paymentsData as $row) {
        if ($row['label'] === $label) {
            return $row;
        }
    }

    return null;
}

beforeEach(function () {
    // The admin layout loads assets via @vite; no build manifest exists in CI/test.
    $this->withoutVite();

    Permission::findOrCreate('view_reports', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->viewer = User::factory()->create();
    $this->viewer->givePermissionTo('view_reports');

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);

    // Fixed date in Q2 2024, safely in the past regardless of when tests run.
    $this->paymentDate = '2024-05-15';
});

function hitFinancialReport()
{
    return test()->actingAs(test()->viewer)->get(route('reports.financial.index', [
        'tab' => 'payments',
        'scope' => 'national',
        'year' => 2024,
    ]));
}

/*
|--------------------------------------------------------------------------
| 1 — organisational payment on a non-volunteer fee
|--------------------------------------------------------------------------
*/

test('an organisational payment on a non-volunteer fee counts only in org_amount, not member_amount', function () {
    $organisation = Organisation::create(['name' => 'Org One']);
    $member = User::factory()->create();

    $fee = MembershipFeeFactory::new()->create([
        'is_volunteer_fee' => false,
        'amount' => 5000,
    ]);

    MembershipPayment::factory()->approved()->create([
        'user_id' => $member->id,
        'organisation_id' => $organisation->id,
        'membership_fee_id' => $fee->id,
        'branch_id' => $this->branch->id,
        'payment_date' => $this->paymentDate,
    ]);

    $response = hitFinancialReport();
    $response->assertOk();

    $row = paymentsRowFor($response->viewData('paymentsData'), $this->branch->name);
    expect($row)->not->toBeNull();

    expect((float) $row['q2_member'])->toBe(0.0)
        ->and((float) $row['q2_volunteer'])->toBe(0.0)
        ->and((float) $row['q2_org'])->toBe(5000.0)
        ->and((float) $row['year_total'])->toBe(5000.0);
});

/*
|--------------------------------------------------------------------------
| 2 — organisational payment on a volunteer fee
|--------------------------------------------------------------------------
*/

test('an organisational payment on a volunteer fee counts only in org_amount, not volunteer_amount', function () {
    $organisation = Organisation::create(['name' => 'Org Two']);
    $member = User::factory()->create();

    $fee = MembershipFeeFactory::new()->create([
        'is_volunteer_fee' => true,
        'amount' => 3000,
    ]);

    MembershipPayment::factory()->approved()->create([
        'user_id' => $member->id,
        'organisation_id' => $organisation->id,
        'membership_fee_id' => $fee->id,
        'branch_id' => $this->branch->id,
        'payment_date' => $this->paymentDate,
    ]);

    $response = hitFinancialReport();
    $response->assertOk();

    $row = paymentsRowFor($response->viewData('paymentsData'), $this->branch->name);
    expect($row)->not->toBeNull();

    expect((float) $row['q2_member'])->toBe(0.0)
        ->and((float) $row['q2_volunteer'])->toBe(0.0)
        ->and((float) $row['q2_org'])->toBe(3000.0)
        ->and((float) $row['year_total'])->toBe(3000.0);
});

/*
|--------------------------------------------------------------------------
| 3 — mixed: one personal + one organisational non-volunteer payment
|--------------------------------------------------------------------------
*/

test('a mixed personal + organisational scenario keeps each amount separate and does not inflate the total', function () {
    $organisation = Organisation::create(['name' => 'Org Three']);
    $personalMember = User::factory()->create();
    $orgMember = User::factory()->create();

    // Distinct fees/amounts for personal vs organisational so a
    // double-counting regression (org amount leaking into member_amount)
    // would be unambiguous in the assertions below.
    $personalFee = MembershipFeeFactory::new()->create([
        'is_volunteer_fee' => false,
        'amount' => 1000,
    ]);
    $orgFee = MembershipFeeFactory::new()->create([
        'is_volunteer_fee' => false,
        'amount' => 5000,
    ]);

    MembershipPayment::factory()->approved()->create([
        'user_id' => $personalMember->id,
        'organisation_id' => null,
        'membership_fee_id' => $personalFee->id,
        'branch_id' => $this->branch->id,
        'payment_date' => $this->paymentDate,
    ]);

    MembershipPayment::factory()->approved()->create([
        'user_id' => $orgMember->id,
        'organisation_id' => $organisation->id,
        'membership_fee_id' => $orgFee->id,
        'branch_id' => $this->branch->id,
        'payment_date' => $this->paymentDate,
    ]);

    $response = hitFinancialReport();
    $response->assertOk();

    $row = paymentsRowFor($response->viewData('paymentsData'), $this->branch->name);
    expect($row)->not->toBeNull();

    expect((float) $row['q2_member'])->toBe(1000.0)
        ->and((float) $row['q2_volunteer'])->toBe(0.0)
        ->and((float) $row['q2_org'])->toBe(5000.0)
        ->and((float) $row['year_total'])->toBe(6000.0);
});

/*
|--------------------------------------------------------------------------
| 4 — pure personal scenario (regression guard: unaffected by the fix)
|--------------------------------------------------------------------------
*/

test('a pure personal scenario with no organisational payments is unaffected by the fix', function () {
    $member1 = User::factory()->create();
    $member2 = User::factory()->create();

    $memberFee = MembershipFeeFactory::new()->create([
        'is_volunteer_fee' => false,
        'amount' => 1500,
    ]);
    $volunteerFee = MembershipFeeFactory::new()->create([
        'is_volunteer_fee' => true,
        'amount' => 750,
    ]);

    MembershipPayment::factory()->approved()->create([
        'user_id' => $member1->id,
        'organisation_id' => null,
        'membership_fee_id' => $memberFee->id,
        'branch_id' => $this->branch->id,
        'payment_date' => $this->paymentDate,
    ]);

    MembershipPayment::factory()->approved()->create([
        'user_id' => $member2->id,
        'organisation_id' => null,
        'membership_fee_id' => $volunteerFee->id,
        'branch_id' => $this->branch->id,
        'payment_date' => $this->paymentDate,
    ]);

    $response = hitFinancialReport();
    $response->assertOk();

    $row = paymentsRowFor($response->viewData('paymentsData'), $this->branch->name);
    expect($row)->not->toBeNull();

    expect((float) $row['q2_member'])->toBe(1500.0)
        ->and((float) $row['q2_volunteer'])->toBe(750.0)
        ->and((float) $row['q2_org'])->toBe(0.0)
        ->and((float) $row['year_total'])->toBe(2250.0);
});

/*
|--------------------------------------------------------------------------
| 5 — Red Cross unit payment alongside personal and organisational ones
|--------------------------------------------------------------------------
*/

test('a Red Cross unit payment counts only in q2_rcu, next to personal and organisational payments', function () {
    $division = Division::create(['name' => 'Alpha One', 'branch_id' => $this->branch->id]);
    $unit = RedCrossUnit::create(['name' => 'Unit A', 'division_id' => $division->id, 'is_active' => true]);
    $organisation = Organisation::create(['name' => 'Org Five']);

    // Distinct amounts so any leak between columns is unambiguous.
    $memberFee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false, 'amount' => 1000]);
    $volunteerFee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => true, 'amount' => 300]);
    $orgFee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false, 'amount' => 5000]);
    $rcuFee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => true, 'amount' => 20000]);

    $pay = fn (array $attributes) => MembershipPayment::factory()->approved()->create([
        'user_id' => User::factory()->create()->id,
        'organisation_id' => null,
        'red_cross_unit_id' => null,
        'branch_id' => $this->branch->id,
        'payment_date' => $this->paymentDate,
        ...$attributes,
    ]);

    $pay(['membership_fee_id' => $memberFee->id]);
    $pay(['membership_fee_id' => $volunteerFee->id]);
    $pay(['membership_fee_id' => $orgFee->id, 'organisation_id' => $organisation->id]);
    // An RCU annual fee on a volunteer-flavoured fee must not also count as volunteer_amount.
    $pay(['membership_fee_id' => $rcuFee->id, 'red_cross_unit_id' => $unit->id]);

    $response = hitFinancialReport();
    $response->assertOk();

    $row = paymentsRowFor($response->viewData('paymentsData'), $this->branch->name);
    expect($row)->not->toBeNull();

    expect((float) $row['q2_member'])->toBe(1000.0)
        ->and((float) $row['q2_volunteer'])->toBe(300.0)
        ->and((float) $row['q2_org'])->toBe(5000.0)
        ->and((float) $row['q2_rcu'])->toBe(20000.0)
        ->and((float) $row['q1_rcu'])->toBe(0.0)
        ->and((float) $row['year_total'])->toBe(26300.0);
});
