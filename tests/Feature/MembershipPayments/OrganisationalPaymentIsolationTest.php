<?php

/**
 * Regression coverage for every fix made this session around the
 * "organisational payment conflated with personal membership" bug class:
 *
 *  1-3. UserMembershipStatusBadge's currentMembershipPayment/
 *       latestMembershipPayment branches, scoped to ->personal().
 *  4.   UserController::show()'s $allPayments/$currentMembership, scoped
 *       to ->personal().
 *  5-6. MembershipPayment::promotesFromPendingEngagement(), returning
 *       false for organisation-linked payments only.
 *  7-8. OrganisationController::linkUser()'s pending_engagement -> active
 *       promotion guard, mirroring RC-unit assignment.
 *  9-10. The fee/RCU validation closure in
 *        MembershipPaymentController::store(), exempting organisational
 *        payments while leaving the personal-payment check intact.
 */

use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\RedCrossUnit;
use App\Models\User;
use App\View\Components\UserMembershipStatusBadge;
use Database\Factories\MembershipFeeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // The admin layout loads assets via @vite; no build manifest exists in CI/test.
    $this->withoutVite();

    foreach (['manage-admin-panel', 'view_user', 'add_payments', 'edit_payments', 'view_payments'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions([
        'manage-admin-panel', 'view_user', 'add_payments', 'edit_payments', 'view_payments',
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');
});

/*
|--------------------------------------------------------------------------
| 1-2 — UserMembershipStatusBadge: org-only payment is not Member/Expired
|--------------------------------------------------------------------------
*/

test('a user with only an organisational payment does not show the Member badge', function () {
    $user = User::factory()->create([
        'can_contribute_member' => true,
        'can_contribute_volunteering' => false,
    ]);
    $organisation = Organisation::create(['name' => 'Test Org']);
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);

    MembershipPayment::factory()->approved()->create([
        'user_id' => $user->id,
        'organisation_id' => $organisation->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subDay()->toDateString(),
        'expiry_date' => now()->addYear()->toDateString(),
    ]);

    $badge = new UserMembershipStatusBadge($user->fresh());

    expect($badge->type)->not->toBe('active')
        ->and($badge->line1)->not->toBe('Member');
});

test('a user with only an organisational payment does not show Expired either, and falls through to membership_interested', function () {
    $user = User::factory()->create([
        'can_contribute_member' => true,
        'can_contribute_volunteering' => false,
    ]);
    $organisation = Organisation::create(['name' => 'Test Org']);
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);

    // Valid (future expiry), approved, organisational — no personal payment at all.
    MembershipPayment::factory()->approved()->create([
        'user_id' => $user->id,
        'organisation_id' => $organisation->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subDay()->toDateString(),
        'expiry_date' => now()->addYear()->toDateString(),
    ]);

    $badge = new UserMembershipStatusBadge($user->fresh());

    expect($badge->type)->not->toBe('expired')
        ->and($badge->type)->toBe('membership_interested')
        ->and($badge->line1)->toBe('Membership')
        ->and($badge->line2)->toBe('Interested');
});

/*
|--------------------------------------------------------------------------
| 3 — Regression guard: a genuinely expired PERSONAL payment still shows Expired
|--------------------------------------------------------------------------
*/

test('a user with a genuinely expired personal payment still shows the Expired badge', function () {
    $user = User::factory()->create([
        'can_contribute_member' => true,
        'can_contribute_volunteering' => false,
    ]);
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);

    MembershipPayment::factory()->approved()->create([
        'user_id' => $user->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subYears(2)->toDateString(),
        'expiry_date' => now()->subYear()->toDateString(),
    ]);

    $badge = new UserMembershipStatusBadge($user->fresh());

    expect($badge->type)->toBe('expired')
        ->and($badge->line1)->toBe('Membership')
        ->and($badge->line2)->toBe('Expired');
});

/*
|--------------------------------------------------------------------------
| 4 — users/show: personal-only payment history and current-membership summary
|--------------------------------------------------------------------------
*/

test('users/show lists zero payment-history rows and a null current membership for an org-only-payment user', function () {
    $target = User::factory()->create([
        'can_contribute_member' => true,
        'can_contribute_volunteering' => false,
    ]);
    $organisation = Organisation::create(['name' => 'Test Org']);
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);

    MembershipPayment::factory()->approved()->create([
        'user_id' => $target->id,
        'organisation_id' => $organisation->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subDay()->toDateString(),
        'expiry_date' => now()->addYear()->toDateString(),
    ]);

    $response = $this->actingAs($this->admin)->get(route('users.show', $target));

    $response->assertOk();
    expect($response->viewData('membershipPayments'))->toHaveCount(0);
    expect($response->viewData('currentMembership'))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| 5-6 — promotesFromPendingEngagement(): organisational vs. personal approval
|--------------------------------------------------------------------------
*/

test('approving a pending_engagement contact person\'s organisational payment does NOT promote them to active', function () {
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $contact = User::factory()->create(['lifecycle_status' => 'pending_engagement']);
    $organisation = Organisation::create(['name' => 'Test Org']);

    $payment = MembershipPayment::factory()->create([
        'user_id' => $contact->id,
        'organisation_id' => $organisation->id,
        'submitted_by_user_id' => $submitter->id,
    ]);

    $payment->approve($approver);

    expect(MembershipPayment::withAnyApprovalStatus()->find($payment->id)->approval_status)->toBe(MembershipPayment::APPROVED)
        ->and($contact->refresh()->lifecycle_status)->toBe('pending_engagement');
});

test('approving a pending_engagement user\'s personal membership payment still promotes them to active', function () {
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $member = User::factory()->create(['lifecycle_status' => 'pending_engagement']);

    $payment = MembershipPayment::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
    ]);

    $payment->approve($approver);

    expect(MembershipPayment::withAnyApprovalStatus()->find($payment->id)->approval_status)->toBe(MembershipPayment::APPROVED)
        ->and($member->refresh()->lifecycle_status)->toBe('active');
});

/*
|--------------------------------------------------------------------------
| 7-8 — OrganisationController::linkUser() pending_engagement promotion guard
|--------------------------------------------------------------------------
*/

test('linking a pending_engagement user to an organisation promotes them to active', function () {
    $organisation = Organisation::create(['name' => 'Test Org']);
    $user = User::factory()->create(['lifecycle_status' => 'pending_engagement']);

    $response = $this->actingAs($this->admin)
        ->post(route('organisations.link-user', $organisation), ['user_id' => $user->id]);

    $response->assertSessionHas('success');
    expect($user->refresh()->lifecycle_status)->toBe('active');
    expect($organisation->users()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('linking an already-active or dormant user to an organisation does not change their lifecycle_status', function () {
    $activeOrg = Organisation::create(['name' => 'Org Active']);
    $activeUser = User::factory()->create(['lifecycle_status' => 'active']);

    $this->actingAs($this->admin)
        ->post(route('organisations.link-user', $activeOrg), ['user_id' => $activeUser->id])
        ->assertSessionHas('success');

    expect($activeUser->refresh()->lifecycle_status)->toBe('active');

    $dormantOrg = Organisation::create(['name' => 'Org Dormant']);
    $dormantUser = User::factory()->create(['lifecycle_status' => 'dormant']);

    $this->actingAs($this->admin)
        ->post(route('organisations.link-user', $dormantOrg), ['user_id' => $dormantUser->id])
        ->assertSessionHas('success');

    expect($dormantUser->refresh()->lifecycle_status)->toBe('dormant');
});

/*
|--------------------------------------------------------------------------
| 9-10 — store()'s fee/RCU validation: personal still blocked, org exempted
|--------------------------------------------------------------------------
*/

test('a fee/RCU mismatch on a personal payment is still rejected by validation', function () {
    $unit = RedCrossUnit::create(['name' => 'Unit A']);
    $member = User::factory()->create(['red_cross_unit_id' => $unit->id]);
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);

    $response = $this->actingAs($this->admin)->post(route('membership-payments.store'), [
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
    ]);

    $response->assertSessionHasErrors('membership_fee_id');
    expect(MembershipPayment::withAnyApprovalStatus()->where('user_id', $member->id)->count())->toBe(0);
});

test('the same fee/RCU mismatch on an organisational payment is NOT rejected', function () {
    $unit = RedCrossUnit::create(['name' => 'Unit B']);
    $contact = User::factory()->create(['red_cross_unit_id' => $unit->id]);
    $organisation = Organisation::create(['name' => 'Test Org']);
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);

    $response = $this->actingAs($this->admin)->post(route('membership-payments.store'), [
        'user_id' => $contact->id,
        'organisation_id' => $organisation->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
    ]);

    $response->assertSessionDoesntHaveErrors('membership_fee_id');
    $response->assertSessionHas('success');
    expect(MembershipPayment::withAnyApprovalStatus()->where('user_id', $contact->id)->where('organisation_id', $organisation->id)->count())->toBe(1);
});
