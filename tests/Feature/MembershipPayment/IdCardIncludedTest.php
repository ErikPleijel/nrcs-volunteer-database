<?php

/**
 * MembershipPaymentController::store() accepts id_card_included for any
 * person's own membership payment (User::canOrderIdCardWithPayment()), but
 * never for an organisation or Red Cross Unit payment — the same rule the
 * online Paystack flow enforces.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\RedCrossUnit;
use App\Models\User;
use Database\Factories\MembershipFeeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    foreach (['manage-admin-panel', 'add_payments', 'view_payments'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions([
        'manage-admin-panel', 'add_payments', 'view_payments',
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actor = User::factory()->create();
    $this->actor->assignRole('national_db_administrator');

    $this->branch = Branch::create(['name' => 'Kano', 'code' => 'KAN']);
    $this->division = Division::create(['name' => 'Kano Division', 'branch_id' => $this->branch->id]);
    $this->unit = RedCrossUnit::create(['name' => 'Kano Unit', 'division_id' => $this->division->id, 'is_active' => true]);
});

test('store saves an ID card for a supporting member', function () {
    $member = User::factory()->create();
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);

    $this->actingAs($this->actor)->post(route('membership-payments.store'), [
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
        'id_card_included' => '1',
    ])->assertSessionHasNoErrors();

    expect(MembershipPayment::withAnyApprovalStatus()->sole()->id_card_included)->toBeTrue();
});

test('store saves an ID card for a volunteer as before', function () {
    $volunteer = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id, 'red_cross_unit_id' => $this->unit->id]);
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => true]);

    $this->actingAs($this->actor)->post(route('membership-payments.store'), [
        'user_id' => $volunteer->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
        'id_card_included' => '1',
    ])->assertSessionHasNoErrors();

    expect(MembershipPayment::withAnyApprovalStatus()->sole()->id_card_included)->toBeTrue();
});

test('store still accepts an unticked ID card', function () {
    $member = User::factory()->create();
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);

    $this->actingAs($this->actor)->post(route('membership-payments.store'), [
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
        'id_card_included' => '0',
    ])->assertSessionHasNoErrors();

    expect(MembershipPayment::withAnyApprovalStatus()->sole()->id_card_included)->toBeFalse();
});

test('store refuses an ID card on an organisation payment', function () {
    $contact = User::factory()->create();
    $organisation = Organisation::create(['name' => 'Sponsor Org', 'branch_id' => $this->branch->id]);
    $fee = MembershipFeeFactory::new()->create(['for_organizations' => true, 'is_volunteer_fee' => false]);

    $this->actingAs($this->actor)->post(route('membership-payments.store'), [
        'user_id' => $contact->id,
        'organisation_id' => $organisation->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
        'id_card_included' => '1',
    ])->assertSessionHasErrors('id_card_included');

    expect(MembershipPayment::withAnyApprovalStatus()->count())->toBe(0);
});

test('store refuses an ID card on a Red Cross Unit payment', function () {
    $leader = User::factory()->create();
    $this->unit->update(['team_leader_user_id' => $leader->id]);
    $fee = MembershipFeeFactory::new()->forRedCrossUnits()->create();

    $this->actingAs($this->actor)->post(route('membership-payments.store'), [
        'user_id' => $leader->id,
        'red_cross_unit_id' => $this->unit->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
        'id_card_included' => '1',
    ])->assertSessionHasErrors('id_card_included');

    expect(MembershipPayment::withAnyApprovalStatus()->count())->toBe(0);
});
