<?php

/**
 * Group 1 of RCU annual fee payments: the for_red_cross_units fee flag, the
 * membership-fees admin UI, the seeded "RCU annual fee", and keeping RCU
 * fees out of every personal fee list.
 */

use App\Models\MembershipFee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    foreach (['manage-admin-panel', 'add_payments'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['manage-admin-panel', 'add_payments']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');
});

function feeAdmin($test)
{
    return $test->actingAs($test->admin)->withSession(['auth.password_confirmed_at' => time()]);
}

function newFeePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Fee',
        'amount' => 1234,
        'id_card_fee' => 0,
        'validity_years' => 1,
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| Seed data migration
|--------------------------------------------------------------------------
*/

test('the data migration seeds exactly one RCU annual fee', function () {
    $fee = MembershipFee::where('name', 'RCU annual fee')->sole();

    expect((float) $fee->amount)->toBe(20000.0)
        ->and((float) $fee->id_card_fee)->toBe(0.0)
        ->and($fee->validity_years)->toBe(1)
        ->and($fee->for_red_cross_units)->toBeTrue()
        ->and($fee->for_organizations)->toBeFalse()
        ->and($fee->is_volunteer_fee)->toBeFalse()
        ->and($fee->is_active)->toBeTrue();
});

test('the data migration is idempotent', function () {
    $migration = require base_path('database/migrations/2026_09_25_090001_seed_rcu_annual_fee.php');
    $migration->up();
    $migration->up();

    expect(MembershipFee::where('name', 'RCU annual fee')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Admin create / edit
|--------------------------------------------------------------------------
*/

test('creating a fee with the Red Cross Unit type persists for_red_cross_units only', function () {
    feeAdmin($this)->post(route('membership-fees.store'), newFeePayload(['fee_type' => 'red_cross_unit']))
        ->assertRedirect(route('membership-fees.index'))
        ->assertSessionHasNoErrors();

    $fee = MembershipFee::where('name', 'Test Fee')->sole();
    expect($fee->for_red_cross_units)->toBeTrue()
        ->and($fee->for_organizations)->toBeFalse();
});

test('creating a fee with the Organization type persists for_organizations only', function () {
    feeAdmin($this)->post(route('membership-fees.store'), newFeePayload(['fee_type' => 'organisation']))
        ->assertSessionHasNoErrors();

    $fee = MembershipFee::where('name', 'Test Fee')->sole();
    expect($fee->for_organizations)->toBeTrue()
        ->and($fee->for_red_cross_units)->toBeFalse();
});

test('a fee cannot be for both organisations and Red Cross Units', function () {
    feeAdmin($this)->post(route('membership-fees.store'), newFeePayload([
        'for_organizations' => '1',
        'for_red_cross_units' => '1',
    ]))->assertSessionHasErrors('for_red_cross_units');

    expect(MembershipFee::where('name', 'Test Fee')->exists())->toBeFalse();
});

test('the create form persists is_volunteer_fee', function () {
    feeAdmin($this)->post(route('membership-fees.store'), newFeePayload([
        'fee_type' => 'individual',
        'is_volunteer_fee' => '1',
    ]))->assertSessionHasNoErrors();

    expect(MembershipFee::where('name', 'Test Fee')->sole()->is_volunteer_fee)->toBeTrue();
});

test('superseding an RCU fee carries for_red_cross_units to the new version', function () {
    $fee = MembershipFee::factory()->forRedCrossUnits()->create(['name' => 'Test Fee', 'amount' => 1000]);

    feeAdmin($this)->put(route('membership-fees.update', $fee), [
        'name' => $fee->name,
        'validity_years' => $fee->validity_years,
        'for_organizations' => '0',
        'for_red_cross_units' => '1',
        'amount' => 1500,
        'id_card_fee' => 0,
        'is_active' => '1',
    ])->assertSessionHasNoErrors();

    $newVersion = MembershipFee::where('name', 'Test Fee')->where('is_active', true)->sole();
    expect($newVersion->id)->not->toBe($fee->id)
        ->and((float) $newVersion->amount)->toBe(1500.0)
        ->and($newVersion->for_red_cross_units)->toBeTrue()
        ->and($newVersion->for_organizations)->toBeFalse();
});

test('the fee index shows the Red Cross Unit badge', function () {
    feeAdmin($this)->get(route('membership-fees.index'))
        ->assertOk()
        // In order: the fee row's name, then its type badge (the help text
        // above the table also mentions "Red Cross Units").
        ->assertSeeInOrder(['RCU annual fee', 'fa-people-group', 'Red Cross Unit'], false);
});

/*
|--------------------------------------------------------------------------
| Personal fee lists exclude RCU fees
|--------------------------------------------------------------------------
*/

test('forPersons and getActiveOneYearMemberships exclude RCU fees', function () {
    MembershipFee::factory()->create(['name' => 'Personal Fee']);

    expect(MembershipFee::forPersons()->pluck('name'))
        ->toContain('Personal Fee')
        ->not->toContain('RCU annual fee')
        ->and(MembershipFee::getActiveOneYearMemberships()->pluck('name'))
        ->toContain('Personal Fee')
        ->not->toContain('RCU annual fee')
        ->and(MembershipFee::forRedCrossUnits()->pluck('name')->all())
        ->toBe(['RCU annual fee']);
});

test('the public membership journey page does not list the RCU fee', function () {
    MembershipFee::factory()->create(['name' => 'Personal Fee', 'is_volunteer_fee' => false]);

    $this->get('/membership-journey')
        ->assertOk()
        ->assertSee('Personal Fee')
        ->assertDontSee('RCU annual fee');
});

test('the personal payment create form excludes RCU and organisation fees', function () {
    MembershipFee::factory()->create(['name' => 'Personal Fee']);
    MembershipFee::factory()->create(['name' => 'Corporate Fee', 'for_organizations' => true]);

    $response = $this->actingAs($this->admin)->get(route('membership-payments.create'))->assertOk();

    expect($response->viewData('membershipFees')->pluck('name'))
        ->toContain('Personal Fee')
        ->not->toContain('RCU annual fee')
        ->not->toContain('Corporate Fee');
});

test('the Paystack personal fee list excludes RCU fees', function () {
    MembershipFee::factory()->create(['name' => 'Personal Fee', 'is_volunteer_fee' => false]);

    $response = $this->actingAs(User::factory()->create())->get(route('make-payment.show'))->assertOk();

    expect($response->viewData('personalMembershipFees')->pluck('name'))
        ->toContain('Personal Fee')
        ->not->toContain('RCU annual fee')
        ->and($response->viewData('organisationMembershipFees')->pluck('name'))
        ->not->toContain('RCU annual fee');
});
