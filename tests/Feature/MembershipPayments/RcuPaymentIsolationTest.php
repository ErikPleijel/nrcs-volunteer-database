<?php

/**
 * Group 2 of RCU annual fee payments: a membership payment attributed to a
 * Red Cross Unit (red_cross_unit_id set) belongs to the unit, not to the
 * team leader who paid it — mirrors OrganisationalPaymentIsolationTest.
 *
 * Covers: the personal()/organisational()/rcuAttributed()/attributed()
 * scopes, the ID card and certificate queries, the membership badge, the
 * campaign/user membership filter, the overlap check, approval lifecycle
 * effects, the approval review page, and the financial report's RCU
 * category.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\RedCrossUnit;
use App\Models\User;
use App\Services\UserFilterService;
use App\View\Components\UserMembershipStatusBadge;
use Database\Factories\MembershipFeeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $permissions = ['manage-admin-panel', 'view_user', 'add_payments', 'edit_payments', 'view_payments', 'view_reports'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);
    $this->unit = RedCrossUnit::create(['name' => 'Unit Alpha', 'division_id' => $this->division->id]);

    // A team leader who is NOT a member of any unit — so the only thing that
    // could make them look like a member is the RCU payment itself.
    $this->leader = User::factory()->create([
        'can_contribute_member' => true,
        'can_contribute_volunteering' => false,
    ]);
    $this->unit->update(['team_leader_user_id' => $this->leader->id]);

    $this->rcuFee = MembershipFeeFactory::new()->forRedCrossUnits()->create(['amount' => 20000]);
});

/** A valid, approved RCU annual fee payment made by $this->leader. */
function makeRcuPayment(array $overrides = []): MembershipPayment
{
    return MembershipPayment::factory()->approved()->create(array_merge([
        'user_id' => test()->leader->id,
        'red_cross_unit_id' => test()->unit->id,
        'membership_fee_id' => test()->rcuFee->id,
        'branch_id' => test()->branch->id,
        'division_id' => test()->division->id,
        'payment_date' => now()->subDay()->toDateString(),
        'expiry_date' => now()->addYear()->toDateString(),
    ], $overrides));
}

/*
|--------------------------------------------------------------------------
| Scopes
|--------------------------------------------------------------------------
*/

test('personal() excludes both organisation- and RCU-attributed payments; attributed() is its complement', function () {
    $personalFee = MembershipFeeFactory::new()->create();
    $organisation = Organisation::create(['name' => 'Test Org']);

    $personal = MembershipPayment::factory()->approved()->create(['user_id' => $this->leader->id, 'membership_fee_id' => $personalFee->id]);
    $org = MembershipPayment::factory()->approved()->create(['user_id' => $this->leader->id, 'organisation_id' => $organisation->id, 'membership_fee_id' => $personalFee->id]);
    $rcu = makeRcuPayment();

    expect(MembershipPayment::personal()->pluck('id')->all())->toBe([$personal->id])
        ->and(MembershipPayment::organisational()->pluck('id')->all())->toBe([$org->id])
        ->and(MembershipPayment::rcuAttributed()->pluck('id')->all())->toBe([$rcu->id])
        ->and(MembershipPayment::attributed()->orderBy('id')->pluck('id')->all())->toBe([$org->id, $rcu->id])
        ->and($rcu->isAttributed())->toBeTrue()
        ->and($personal->isAttributed())->toBeFalse();
});

test('personal() stays unambiguous when joined to users, which also has red_cross_unit_id', function () {
    makeRcuPayment();

    $count = MembershipPayment::join('users', 'users.id', '=', 'membership_payments.user_id')
        ->personal()
        ->count();

    expect($count)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Personal membership surfaces
|--------------------------------------------------------------------------
*/

test('an RCU payment does not give the paying leader the Member badge', function () {
    makeRcuPayment();

    $badge = new UserMembershipStatusBadge($this->leader->fresh());

    expect($badge->type)->toBe('membership_interested')
        ->and($badge->line1)->not->toBe('Member');
});

test('an RCU payment is not the leader\'s current membership on their ID card', function () {
    makeRcuPayment(['id_card_included' => true]);

    // Same eager-load constraint IdCardController::printCard() uses.
    $leader = $this->leader->fresh()->load(['currentMembershipPayment' => fn ($q) => $q->personal()]);

    expect($leader->currentMembershipPayment)->toBeNull();
});

test('an RCU payment does not appear in the membership certificate query', function () {
    makeRcuPayment();

    // Same base query CertificateController uses for membership certificates.
    expect(MembershipPayment::query()->valid()->personal()->whereHas('user')->count())->toBe(0);
});

test('the members filter (users list and campaign audiences) does not count an RCU payment', function () {
    makeRcuPayment();

    $members = app(UserFilterService::class)
        ->apply(User::query(), ['membership_filter' => 'members'], 'national', null)
        ->pluck('id');
    $nonMembers = app(UserFilterService::class)
        ->apply(User::query(), ['membership_filter' => 'non_members'], 'national', null)
        ->pluck('id');

    expect($members)->not->toContain($this->leader->id)
        ->and($nonMembers)->toContain($this->leader->id);
});

test('an existing RCU payment does not trigger the overlap check on a new personal payment', function () {
    makeRcuPayment();
    $personalFee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);

    $response = $this->actingAs($this->admin)->post(route('membership-payments.store'), [
        'user_id' => $this->leader->id,
        'membership_fee_id' => $personalFee->id,
        'payment_date' => now()->toDateString(),
    ]);

    $response->assertSessionHasNoErrors()
        ->assertSessionMissing('overlap_confirmation_needed')
        ->assertSessionHas('success');
    expect(MembershipPayment::withAnyApprovalStatus()->personal()->where('user_id', $this->leader->id)->count())->toBe(1);
});

test('the overlap check still catches a genuinely overlapping personal payment', function () {
    $personalFee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);
    MembershipPayment::factory()->approved()->create([
        'user_id' => $this->leader->id,
        'membership_fee_id' => $personalFee->id,
        'payment_date' => now()->subDay()->toDateString(),
        'expiry_date' => now()->addYear()->toDateString(),
    ]);

    $this->actingAs($this->admin)->post(route('membership-payments.store'), [
        'user_id' => $this->leader->id,
        'membership_fee_id' => $personalFee->id,
        'payment_date' => now()->toDateString(),
    ])->assertSessionHas('overlap_confirmation_needed', true);
});

/*
|--------------------------------------------------------------------------
| Approval lifecycle — same effects as an organisational payment
|--------------------------------------------------------------------------
*/

test('approving an RCU payment does not promote a pending_engagement leader to active', function () {
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $this->leader->update(['lifecycle_status' => 'pending_engagement']);

    $payment = MembershipPayment::factory()->create([
        'user_id' => $this->leader->id,
        'red_cross_unit_id' => $this->unit->id,
        'membership_fee_id' => $this->rcuFee->id,
        'submitted_by_user_id' => $submitter->id,
    ]);

    $payment->approve($approver);

    expect(MembershipPayment::withAnyApprovalStatus()->find($payment->id)->approval_status)->toBe(MembershipPayment::APPROVED)
        ->and($this->leader->refresh()->lifecycle_status)->toBe('pending_engagement')
        ->and($payment->promotesFromPendingEngagement())->toBeFalse()
        ->and($payment->contributionMismatchNote())->toBeNull();
});

/*
|--------------------------------------------------------------------------
| RedCrossUnit / User helpers
|--------------------------------------------------------------------------
*/

test('RedCrossUnit payment helpers and isLedBy() mirror Organisation', function () {
    $assistant = User::factory()->create();
    $outsider = User::factory()->create();
    $this->unit->update(['assistant_team_leader_user_id' => $assistant->id]);
    $unit = $this->unit->fresh();

    expect($unit->isPaid())->toBeFalse()
        ->and($unit->isLedBy($this->leader))->toBeTrue()
        ->and($unit->isLedBy($assistant))->toBeTrue()
        ->and($unit->isLedBy($outsider))->toBeFalse();

    $payment = makeRcuPayment();

    expect($unit->isPaid())->toBeTrue()
        ->and($unit->membership_expiry_date->toDateString())->toBe($payment->expiry_date->toDateString())
        ->and($unit->activeMembership->id)->toBe($payment->id)
        ->and($unit->latestMembership->id)->toBe($payment->id)
        ->and($this->leader->fresh()->leadRedCrossUnits()->pluck('id')->all())->toBe([$unit->id])
        ->and($assistant->fresh()->leadRedCrossUnits()->pluck('id')->all())->toBe([$unit->id])
        ->and($outsider->fresh()->leadRedCrossUnits())->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| Approval review page
|--------------------------------------------------------------------------
*/

test('the approval review page shows which Red Cross Unit a pending RCU payment is for', function () {
    $payment = MembershipPayment::factory()->create([
        'user_id' => $this->leader->id,
        'red_cross_unit_id' => $this->unit->id,
        'membership_fee_id' => $this->rcuFee->id,
        'submitted_by_user_id' => $this->admin->id,
        'branch_id' => $this->branch->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('membership-payments.review', $payment->id))
        ->assertOk()
        ->assertSeeInOrder(['Red Cross Unit', 'Unit Alpha', 'Alpha Branch', 'Alpha Division']);
});

/*
|--------------------------------------------------------------------------
| Financial overview report
|--------------------------------------------------------------------------
*/

test('the financial report puts RCU payments in their own category on both tabs', function () {
    $paymentDate = '2024-05-15'; // Q2
    makeRcuPayment(['payment_date' => $paymentDate, 'expiry_date' => '2025-05-15']);

    $personalFee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false, 'amount' => 3000]);
    MembershipPayment::factory()->approved()->create([
        'user_id' => User::factory()->create()->id,
        'membership_fee_id' => $personalFee->id,
        'branch_id' => $this->branch->id,
        'payment_date' => $paymentDate,
        'expiry_date' => '2025-05-15',
    ]);

    $payments = $this->actingAs($this->admin)
        ->get(route('reports.financial.index', ['tab' => 'payments', 'scope' => 'national', 'year' => 2024]))
        ->assertOk()
        ->viewData('paymentsData');
    $row = collect($payments)->firstWhere('label', 'Alpha Branch');

    expect((float) $row['q2_rcu'])->toBe(20000.0)
        ->and((float) $row['q2_member'])->toBe(3000.0)
        ->and((float) $row['q2_volunteer'])->toBe(0.0)
        ->and((float) $row['q2_org'])->toBe(0.0)
        ->and((float) $row['year_total'])->toBe(23000.0);

    $breakdown = $this->actingAs($this->admin)
        ->get(route('reports.financial.index', ['tab' => 'breakdown', 'scope' => 'national', 'year' => 2024]))
        ->assertOk();

    expect($breakdown->viewData('rcuFeeBreakdown'))->toHaveCount(1)
        ->and((float) $breakdown->viewData('rcuFeeBreakdown')->first()['q2'])->toBe(20000.0)
        ->and($breakdown->viewData('memberFeeBreakdown')->pluck('fee_id')->all())->toBe([$personalFee->id])
        ->and($breakdown->viewData('organisationFeeBreakdown'))->toBeEmpty()
        ->and((float) $breakdown->viewData('feeBreakdownGrandTotal'))->toBe(23000.0);

    $drillDown = $this->actingAs($this->admin)
        ->get(route('reports.financial.breakdown', ['branch_id' => $this->branch->id, 'quarter' => '2024-Q2', 'category' => 'rcu']))
        ->assertOk()
        ->assertSee('Unit Alpha');

    expect($drillDown->viewData('totalCount'))->toBe(1)
        ->and((float) $drillDown->viewData('total'))->toBe(20000.0);
});
