<?php

/**
 * MembershipPaymentController::store() only accepts id_card_included for a
 * volunteer's own payment (User::canOrderIdCardWithPayment()) — the same
 * rule the form's JS applies and the online Paystack flow enforces.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipPayment;
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
});

test('store refuses an ID card for a non-volunteer', function () {
    $member = User::factory()->create();
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);

    $this->actingAs($this->actor)->post(route('membership-payments.store'), [
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
        'id_card_included' => '1',
    ])->assertSessionHasErrors('id_card_included');

    expect(MembershipPayment::withAnyApprovalStatus()->count())->toBe(0);
});

test('store saves an ID card for a volunteer as before', function () {
    $branch = Branch::create(['name' => 'Kano', 'code' => 'KAN']);
    $division = Division::create(['name' => 'Kano Division', 'branch_id' => $branch->id]);
    $unit = RedCrossUnit::create(['name' => 'Kano Unit', 'division_id' => $division->id, 'is_active' => true]);
    $volunteer = User::factory()->create(['branch_id' => $branch->id, 'division_id' => $division->id, 'red_cross_unit_id' => $unit->id]);
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => true]);

    $this->actingAs($this->actor)->post(route('membership-payments.store'), [
        'user_id' => $volunteer->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
        'id_card_included' => '1',
    ])->assertSessionHasNoErrors();

    expect(MembershipPayment::withAnyApprovalStatus()->sole()->id_card_included)->toBeTrue();
});

test('store still accepts an unticked ID card for a non-volunteer', function () {
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
