<?php

/**
 * Feature tests for the two lifecycle-promotion paths inside
 * UserController::update(): RCU-assignment promotion and archive/reactivate
 * promotion — both touched this session (including two narrow guard fixes:
 * markActive() is now pending_engagement-specific, and reactivation's
 * hasQualifyingPayment now excludes organisational payments via
 * ->personal()) but with zero prior test coverage.
 *
 * Acting admin throughout holds national_db_administrator, which bypasses
 * UserPolicy::update()'s scope/role checks entirely — sidesteps needing to
 * match branch/division scope for every test's target user.
 */

use App\Models\Activity;
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

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/** Minimum valid payload for UserController::update(), merged with per-test overrides. */
function minimalUpdatePayload(Branch $branch, Division $division, array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Test',
        'last_name' => 'User',
        'gender' => 'male',
        'birth_year' => 1990,
        'branch_id' => $branch->id,
        'division_id' => $division->id,
        'contribution_type' => 'volunteering',
    ], $overrides);
}

beforeEach(function () {
    // The admin layout loads assets via @vite; no build manifest exists in CI/test.
    $this->withoutVite();

    foreach (['manage-admin-panel', 'edit_user'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions([
        'manage-admin-panel', 'edit_user',
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);
});

/*
|--------------------------------------------------------------------------
| RCU-assignment promotion
|--------------------------------------------------------------------------
*/

test('assigning an active RCU to a pending_engagement user promotes them to active and updates last_activity_at', function () {
    $user = User::factory()->create([
        'lifecycle_status' => 'pending_engagement',
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);
    $unit = RedCrossUnit::create(['name' => 'Unit A']);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), minimalUpdatePayload($this->branch, $this->division, [
            'red_cross_unit_id' => $unit->id,
        ]))
        ->assertRedirect(route('users.show', $user));

    $fresh = $user->fresh();
    expect($fresh->lifecycle_status)->toBe('active')
        ->and($fresh->red_cross_unit_id)->toBe($unit->id)
        ->and($fresh->last_activity_at)->not->toBeNull();
});

test('assigning an RCU equal to the user\'s current red_cross_unit_id does not trigger promotion', function () {
    $unit = RedCrossUnit::create(['name' => 'Unit A']);
    $user = User::factory()->create([
        'lifecycle_status' => 'pending_engagement',
        'red_cross_unit_id' => $unit->id,
        'assigned_rcu_date' => now()->subMonth(),
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), minimalUpdatePayload($this->branch, $this->division, [
            'red_cross_unit_id' => $unit->id,
        ]))
        ->assertRedirect(route('users.show', $user));

    expect($user->fresh()->lifecycle_status)->toBe('pending_engagement');
});

test('unassigning a user\'s RCU does not change lifecycle_status regardless of prior status', function () {
    $unit = RedCrossUnit::create(['name' => 'Unit A']);
    $user = User::factory()->create([
        'lifecycle_status' => 'dormant',
        'red_cross_unit_id' => $unit->id,
        'assigned_rcu_date' => now()->subYear(),
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);

    // Must send red_cross_unit_id explicitly as null — omitting the key
    // entirely means it's absent from $validator->validated(), so
    // $user->update($validated) would leave the column untouched rather
    // than clearing it, even though the controller's own $newUnitId
    // computation treats "absent" and "null" the same via `?? null`.
    $this->actingAs($this->admin)
        ->put(route('users.update', $user), minimalUpdatePayload($this->branch, $this->division, [
            'red_cross_unit_id' => null,
        ]))
        ->assertRedirect(route('users.show', $user));

    $fresh = $user->fresh();
    expect($fresh->red_cross_unit_id)->toBeNull()
        ->and($fresh->lifecycle_status)->toBe('dormant');
});

test('reassigning an already-active user to a different active RCU leaves them active', function () {
    $unitA = RedCrossUnit::create(['name' => 'Unit A']);
    $unitB = RedCrossUnit::create(['name' => 'Unit B']);
    $user = User::factory()->create([
        'lifecycle_status' => 'active',
        'red_cross_unit_id' => $unitA->id,
        'assigned_rcu_date' => now()->subYear(),
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), minimalUpdatePayload($this->branch, $this->division, [
            'red_cross_unit_id' => $unitB->id,
        ]))
        ->assertRedirect(route('users.show', $user));

    $fresh = $user->fresh();
    expect($fresh->lifecycle_status)->toBe('active')
        ->and($fresh->red_cross_unit_id)->toBe($unitB->id);
});

test('reassigning a dormant user to a different active RCU does not promote them to active', function () {
    $unitA = RedCrossUnit::create(['name' => 'Unit A']);
    $unitB = RedCrossUnit::create(['name' => 'Unit B']);
    $user = User::factory()->create([
        'lifecycle_status' => 'dormant',
        'red_cross_unit_id' => $unitA->id,
        'assigned_rcu_date' => now()->subYear(),
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), minimalUpdatePayload($this->branch, $this->division, [
            'red_cross_unit_id' => $unitB->id,
        ]))
        ->assertRedirect(route('users.show', $user));

    $fresh = $user->fresh();
    expect($fresh->lifecycle_status)->toBe('dormant')
        ->and($fresh->red_cross_unit_id)->toBe($unitB->id);
});

test('assigning a user to a different, inactive RCU fails validation and leaves lifecycle_status and red_cross_unit_id unchanged', function () {
    $unitA = RedCrossUnit::create(['name' => 'Unit A']);
    $inactiveUnit = RedCrossUnit::create(['name' => 'Inactive Unit', 'is_active' => false]);
    $user = User::factory()->create([
        'lifecycle_status' => 'pending_engagement',
        'red_cross_unit_id' => $unitA->id,
        'assigned_rcu_date' => now()->subMonth(),
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), minimalUpdatePayload($this->branch, $this->division, [
            'red_cross_unit_id' => $inactiveUnit->id,
        ]))
        ->assertSessionHasErrors('red_cross_unit_id');

    $fresh = $user->fresh();
    expect($fresh->lifecycle_status)->toBe('pending_engagement')
        ->and($fresh->red_cross_unit_id)->toBe($unitA->id);
});

test('saving with the user\'s existing RCU is allowed even if that unit has since gone inactive', function () {
    $unit = RedCrossUnit::create(['name' => 'Unit A']);
    $user = User::factory()->create([
        'lifecycle_status' => 'active',
        'red_cross_unit_id' => $unit->id,
        'assigned_rcu_date' => now()->subYear(),
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);
    $unit->update(['is_active' => false]);

    $response = $this->actingAs($this->admin)
        ->put(route('users.update', $user), minimalUpdatePayload($this->branch, $this->division, [
            'red_cross_unit_id' => $unit->id,
        ]));

    $response->assertRedirect(route('users.show', $user))
        ->assertSessionDoesntHaveErrors();

    $fresh = $user->fresh();
    expect($fresh->red_cross_unit_id)->toBe($unit->id)
        ->and($fresh->lifecycle_status)->toBe('active');
});

test('an archived user assigned an RCU without also reactivating is not promoted and stays archived', function () {
    $unit = RedCrossUnit::create(['name' => 'Unit A']);
    $user = User::factory()->create([
        'lifecycle_status' => 'archived',
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), minimalUpdatePayload($this->branch, $this->division, [
            'red_cross_unit_id' => $unit->id,
            'is_inactive' => '1', // still checked — stays archived
        ]))
        ->assertRedirect(route('users.show', $user));

    $fresh = $user->fresh();
    expect($fresh->lifecycle_status)->toBe('archived')
        ->and($fresh->red_cross_unit_id)->toBe($unit->id);
});

/*
|--------------------------------------------------------------------------
| Archive / reactivate promotion
|--------------------------------------------------------------------------
*/

test('archiving an active user sets them to archived unconditionally', function () {
    $user = User::factory()->create([
        'lifecycle_status' => 'active',
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), minimalUpdatePayload($this->branch, $this->division, [
            'is_inactive' => '1',
        ]))
        ->assertRedirect(route('users.show', $user));

    expect($user->fresh()->lifecycle_status)->toBe('archived');
});

test('a user cannot archive their own account', function () {
    $response = $this->actingAs($this->admin)
        ->put(route('users.update', $this->admin), minimalUpdatePayload($this->branch, $this->division, [
            'is_inactive' => '1',
        ]));

    $response->assertSessionHasErrors('is_inactive');
    expect($this->admin->fresh()->lifecycle_status)->toBe('active');
});

test('reactivating an archived user with no RCU and no qualifying personal payment returns them to pending_engagement', function () {
    $user = User::factory()->create([
        'lifecycle_status' => 'archived',
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), minimalUpdatePayload($this->branch, $this->division, [
            'is_inactive' => '0',
        ]))
        ->assertRedirect(route('users.show', $user));

    expect($user->fresh()->lifecycle_status)->toBe('pending_engagement');
});

test('reactivating an archived user by assigning a brand-new RCU with no activity history lands them dormant, not active', function () {
    $unit = RedCrossUnit::create(['name' => 'Unit A']);
    $user = User::factory()->create([
        'lifecycle_status' => 'archived',
        'red_cross_unit_id' => null,
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), minimalUpdatePayload($this->branch, $this->division, [
            'is_inactive' => '0',
            'red_cross_unit_id' => $unit->id,
        ]))
        ->assertRedirect(route('users.show', $user));

    // Set active by the reactivation branch, then immediately demoted by
    // recalculateLifecycle() (no activity/training/payment/donation history
    // at all). Before markActive()'s guard was narrowed to
    // pending_engagement-only, the RCU-assignment block below would have
    // re-promoted this dormant user back to active, silently overriding the
    // recompute. That override no longer happens — dormant is the correct,
    // intentional outcome here, confirmed after narrowing the guard.
    $fresh = $user->fresh();
    expect($fresh->lifecycle_status)->toBe('dormant')
        ->and($fresh->red_cross_unit_id)->toBe($unit->id);
});

test('reactivating an archived user who already has an RCU and no activity history is demoted back to dormant', function () {
    $unit = RedCrossUnit::create(['name' => 'Unit A']);
    $user = User::factory()->create([
        'lifecycle_status' => 'archived',
        'red_cross_unit_id' => $unit->id,
        'assigned_rcu_date' => now()->subYear(),
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), minimalUpdatePayload($this->branch, $this->division, [
            'is_inactive' => '0',
            'red_cross_unit_id' => $unit->id, // unchanged in this request
        ]))
        ->assertRedirect(route('users.show', $user));

    expect($user->fresh()->lifecycle_status)->toBe('dormant');
});

test('reactivating an archived user who already has an RCU and recent qualifying activity stays active', function () {
    $unit = RedCrossUnit::create(['name' => 'Unit A']);
    $user = User::factory()->create([
        'lifecycle_status' => 'archived',
        'red_cross_unit_id' => $unit->id,
        'assigned_rcu_date' => now()->subYear(),
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);
    Activity::factory()->approved()->create([
        'user_id' => $user->id,
        'branch_id' => $this->branch->id,
        'date' => now()->subMonth()->toDateString(),
    ]);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), minimalUpdatePayload($this->branch, $this->division, [
            'is_inactive' => '0',
            'red_cross_unit_id' => $unit->id, // unchanged in this request
        ]))
        ->assertRedirect(route('users.show', $user));

    expect($user->fresh()->lifecycle_status)->toBe('active');
});

test('reactivating an archived user via a qualifying, non-expired personal membership payment lands them active', function () {
    $user = User::factory()->create([
        'lifecycle_status' => 'archived',
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);
    MembershipPayment::factory()->approved()->create([
        'user_id' => $user->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subMonth()->toDateString(),
        'expiry_date' => now()->addMonths(11)->toDateString(),
    ]);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), minimalUpdatePayload($this->branch, $this->division, [
            'is_inactive' => '0',
        ]))
        ->assertRedirect(route('users.show', $user));

    expect($user->fresh()->lifecycle_status)->toBe('active');
});

test('reactivating via a qualifying-type payment that is expired initially sets active then demotes back to dormant', function () {
    $user = User::factory()->create([
        'lifecycle_status' => 'archived',
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);
    MembershipPayment::factory()->approved()->create([
        'user_id' => $user->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subMonths(18)->toDateString(),
        'expiry_date' => now()->subMonths(6)->toDateString(),
    ]);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), minimalUpdatePayload($this->branch, $this->division, [
            'is_inactive' => '0',
        ]))
        ->assertRedirect(route('users.show', $user));

    expect($user->fresh()->lifecycle_status)->toBe('dormant');
});

test('reactivating an archived user whose only qualifying payment is organisational does not count as a basis', function () {
    $user = User::factory()->create([
        'lifecycle_status' => 'archived',
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);
    $organisation = Organisation::create(['name' => 'Test Org']);
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);
    MembershipPayment::factory()->approved()->create([
        'user_id' => $user->id,
        'organisation_id' => $organisation->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subMonth()->toDateString(),
        'expiry_date' => now()->addMonths(11)->toDateString(),
    ]);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), minimalUpdatePayload($this->branch, $this->division, [
            'is_inactive' => '0',
        ]))
        ->assertRedirect(route('users.show', $user));

    expect($user->fresh()->lifecycle_status)->toBe('pending_engagement');
});
