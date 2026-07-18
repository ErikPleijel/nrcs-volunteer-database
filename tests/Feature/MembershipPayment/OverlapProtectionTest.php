<?php

/**
 * Regression coverage for the membership-payment overlap-protection feature
 * added to MembershipPaymentController::store()/update() and the
 * ->personal() fix on getCurrentMembership().
 *
 * The acting user throughout is a national_db_administrator: store() has no
 * per-target authorize() call (route middleware `can:add_payments` is
 * enough), but update()/show()/edit() call
 * $this->authorize('view', $membershipPayment->user), which (per
 * UserPolicy::view()) requires either 'national' access or being the target
 * themselves. A national role sidesteps needing to match branch/division
 * scope for every test's target member.
 *
 * membership-payments.update is exercised directly via a real HTTP PUT to
 * the route (no Blade form submits to it yet — resources/views/membership-
 * payments/edit.blade.php is currently an empty file — but the route and
 * controller method are fully wired and reachable).
 */

use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\User;
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

    foreach (['manage-admin-panel', 'add_payments', 'edit_payments', 'view_payments'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions([
        'manage-admin-panel', 'add_payments', 'edit_payments', 'view_payments',
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actor = User::factory()->create();
    $this->actor->assignRole('national_db_administrator');
});

/*
|--------------------------------------------------------------------------
| 1-2 — store(): genuine overlap, unconfirmed then confirmed
|--------------------------------------------------------------------------
*/

test('a genuine overlap on store() is blocked without confirm_overlap', function () {
    $member = User::factory()->create();
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false, 'validity_years' => 3]);

    MembershipPayment::factory()->approved()->create([
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
        'expiry_date' => now()->addYears(3)->toDateString(),
    ]);

    $response = $this->actingAs($this->actor)->post(route('membership-payments.store'), [
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('warning');
    $response->assertSessionHas('overlap_confirmation_needed', true);
    expect(MembershipPayment::withAnyApprovalStatus()->where('user_id', $member->id)->count())->toBe(1);
});

test('the same overlap on store() succeeds once confirm_overlap=1 is included', function () {
    $member = User::factory()->create();
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false, 'validity_years' => 3]);

    MembershipPayment::factory()->approved()->create([
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
        'expiry_date' => now()->addYears(3)->toDateString(),
    ]);

    $response = $this->actingAs($this->actor)->post(route('membership-payments.store'), [
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
        'confirm_overlap' => '1',
    ]);

    $response->assertRedirect(route('membership-payments.create'));
    $response->assertSessionHas('success');
    expect(MembershipPayment::withAnyApprovalStatus()->where('user_id', $member->id)->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| 3 — store(): early renewal (day after expiry) is never blocked
|--------------------------------------------------------------------------
*/

test('an early renewal starting the day after the existing payment expires is not blocked', function () {
    $member = User::factory()->create();
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false, 'validity_years' => 3]);

    $existing = MembershipPayment::factory()->approved()->create([
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subYears(3)->toDateString(),
        'expiry_date' => now()->toDateString(),
    ]);

    $newPaymentDate = $existing->expiry_date->copy()->addDay()->toDateString();

    $response = $this->actingAs($this->actor)->post(route('membership-payments.store'), [
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => $newPaymentDate,
    ]);

    $response->assertRedirect(route('membership-payments.create'));
    $response->assertSessionHas('success');
    $response->assertSessionMissing('overlap_confirmation_needed');
    expect(MembershipPayment::withAnyApprovalStatus()->where('user_id', $member->id)->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| 4 — store(): organisational submissions are exempt
|--------------------------------------------------------------------------
*/

test('an organisational submission is exempt from the overlap check even when it would overlap a personal payment', function () {
    $member = User::factory()->create();
    $organisation = Organisation::create(['name' => 'Test Org']);
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false, 'validity_years' => 3]);

    MembershipPayment::factory()->approved()->create([
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
        'expiry_date' => now()->addYears(3)->toDateString(),
    ]);

    $response = $this->actingAs($this->actor)->post(route('membership-payments.store'), [
        'user_id' => $member->id,
        'organisation_id' => $organisation->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
    ]);

    $response->assertRedirect(route('organisations.show', $organisation->id));
    $response->assertSessionHas('success');
    $response->assertSessionMissing('overlap_confirmation_needed');
});

/*
|--------------------------------------------------------------------------
| 5 — getCurrentMembership() only considers personal payments
|--------------------------------------------------------------------------
*/

test('getCurrentMembership only considers personal payments, ignoring a more recent organisational one', function () {
    $member = User::factory()->create();
    $organisation = Organisation::create(['name' => 'Test Org']);
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);

    $personal = MembershipPayment::factory()->approved()->create([
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subYears(2)->toDateString(),
        'expiry_date' => now()->subYear()->toDateString(),
    ]);

    // Most recent by payment_date, but organisational — must be ignored.
    MembershipPayment::factory()->approved()->create([
        'user_id' => $member->id,
        'organisation_id' => $organisation->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
        'expiry_date' => now()->addYears(3)->toDateString(),
    ]);

    $response = $this->actingAs($this->actor)->getJson(route('membership-payments.current-membership', $member));

    $response->assertOk();
    $response->assertJson([
        'membership_fee_id' => $fee->id,
        'payment_date' => $personal->payment_date->toDateString(),
    ]);
});

/*
|--------------------------------------------------------------------------
| 6 — update(): overlap check, confirmation override, self-exclusion
|--------------------------------------------------------------------------
*/

test('update() blocks a genuine overlap with a different payment, and confirm_overlap=1 allows it through', function () {
    $member = User::factory()->create();
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);

    MembershipPayment::factory()->approved()->create([
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => '2020-01-01',
        'expiry_date' => '2021-01-01',
    ]);

    $paymentB = MembershipPayment::factory()->approved()->create([
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => '2022-01-01',
        'expiry_date' => '2023-01-01',
    ]);

    // Edit payment B into payment A's range.
    $response = $this->actingAs($this->actor)->put(route('membership-payments.update', $paymentB), [
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => '2020-06-01',
        'expiry_date' => '2021-06-01',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('warning');
    $response->assertSessionHas('overlap_confirmation_needed', true);
    expect($paymentB->fresh()->payment_date->toDateString())->toBe('2022-01-01');

    // Same edit, now confirmed.
    $response = $this->actingAs($this->actor)->put(route('membership-payments.update', $paymentB), [
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => '2020-06-01',
        'expiry_date' => '2021-06-01',
        'confirm_overlap' => '1',
    ]);

    $response->assertRedirect(route('membership-payments.show', $paymentB));
    $response->assertSessionHas('success');
    expect($paymentB->fresh()->payment_date->toDateString())->toBe('2020-06-01');
});

test('update() excludes the record being edited from its own overlap check', function () {
    $member = User::factory()->create();
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);

    $payment = MembershipPayment::factory()->approved()->create([
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => '2024-01-01',
        'expiry_date' => '2025-01-01',
    ]);

    // A one-day shift still overlaps the record's OWN original range, which
    // would false-positive if the record weren't excluded from its own check.
    $response = $this->actingAs($this->actor)->put(route('membership-payments.update', $payment), [
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => '2024-01-02',
        'expiry_date' => '2025-01-02',
    ]);

    $response->assertRedirect(route('membership-payments.show', $payment));
    $response->assertSessionHas('success');
    $response->assertSessionMissing('overlap_confirmation_needed');
    expect($payment->fresh()->payment_date->toDateString())->toBe('2024-01-02');
});

/*
|--------------------------------------------------------------------------
| 7 — regression lock: deleting a superseded payment doesn't change
|     currentMembershipPayment()
|--------------------------------------------------------------------------
*/

test('deleting an earlier, already-superseded payment never changes currentMembershipPayment()', function () {
    $member = User::factory()->create();
    $feeA = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false, 'validity_years' => 5]);
    $feeB = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false, 'validity_years' => 8]);

    $paymentA = MembershipPayment::factory()->approved()->create([
        'user_id' => $member->id,
        'membership_fee_id' => $feeA->id,
        'payment_date' => '2024-01-01',
        'expiry_date' => '2029-01-01',
    ]);

    // Overlaps A — created via the real store() flow with confirm_overlap=1.
    $response = $this->actingAs($this->actor)->post(route('membership-payments.store'), [
        'user_id' => $member->id,
        'membership_fee_id' => $feeB->id,
        'payment_date' => '2024-01-01',
        'confirm_overlap' => '1',
    ]);
    $response->assertSessionHas('success');

    // store() creates payments pending (DB default) — approve it (as a
    // different user; a submitter can't approve their own submission) so
    // it's visible to currentMembershipPayment(), which — like every
    // unqualified MembershipPayment query — is restricted to approved
    // records by the ApprovedScope global scope.
    $paymentB = MembershipPayment::withAnyApprovalStatus()
        ->where('user_id', $member->id)
        ->where('expiry_date', '2032-01-01')
        ->firstOrFail();
    $paymentB->approve(User::factory()->create());

    expect($member->fresh()->currentMembershipPayment?->id)->toBe($paymentB->id);

    $paymentA->update(['is_deleted' => true]);

    expect($member->fresh()->currentMembershipPayment?->id)->toBe($paymentB->id);
});
