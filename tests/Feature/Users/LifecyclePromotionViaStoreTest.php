<?php

/**
 * Feature coverage for the lifecycle-promotion fix added to
 * UserController::store() this session: unlike update(), store() never had
 * any markActive()/lifecycle_status logic at all (confirmed via git history
 * — present in update() since the initial commit, never added to store()).
 * A volunteer created with a Red Cross Unit assigned now gets markActive()
 * called immediately after creation; a member (no RCU, nothing to promote
 * on at creation time) is intentionally left untouched at the schema
 * default of pending_engagement.
 *
 * Acting admin holds national_db_administrator, matching
 * LifecyclePromotionViaUpdateTest's convention for the sibling update()
 * coverage.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** Minimum valid payload for UserController::store(), merged with per-test overrides. */
function minimalStorePayload(Branch $branch, Division $division, array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Test',
        'last_name' => 'User',
        'gender' => 'male',
        'birth_year' => 1990,
        'telephone1' => '08012345678',
        'branch_id' => $branch->id,
        'division_id' => $division->id,
        'contribution_type' => 'volunteering',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'admin_consent_confirmed' => '1',
        'admin_consent_form' => '1',
    ], $overrides);
}

beforeEach(function () {
    // The admin layout loads assets via @vite; no build manifest exists in CI/test.
    $this->withoutVite();

    foreach (['manage-admin-panel', 'add_user'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions([
        'manage-admin-panel', 'add_user',
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);
});

test('registering a volunteer with an active RCU promotes them to active immediately upon creation', function () {
    $unit = RedCrossUnit::create(['name' => 'Unit A', 'division_id' => $this->division->id]);

    $this->actingAs($this->admin)
        ->post(route('users.store'), minimalStorePayload($this->branch, $this->division, [
            'contribution_type' => 'volunteering',
            'red_cross_unit_id' => $unit->id,
        ]))
        ->assertSessionDoesntHaveErrors();

    $user = User::where('first_name', 'Test')->where('last_name', 'User')->firstOrFail();

    expect($user->lifecycle_status)->toBe('active')
        ->and($user->red_cross_unit_id)->toBe($unit->id);
});

test('registering a member (no RCU) leaves them at pending_engagement upon creation', function () {
    $this->actingAs($this->admin)
        ->post(route('users.store'), minimalStorePayload($this->branch, $this->division, [
            'contribution_type' => 'member',
            'red_cross_unit_id' => null,
        ]))
        ->assertSessionDoesntHaveErrors();

    $user = User::where('first_name', 'Test')->where('last_name', 'User')->firstOrFail();

    expect($user->lifecycle_status)->toBe('pending_engagement')
        ->and($user->red_cross_unit_id)->toBeNull();
});
