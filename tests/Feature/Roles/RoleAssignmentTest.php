<?php

/**
 * Coverage for UserController::updateRoles() / searchUsersForRoles():
 *
 * PART 1 — the getAssignableRoles() enforcement fix (the primary target of
 * this file): closes the gap where a branch-level actor (branch_secretary,
 * branch_db_administrator) — or in principle any actor — could assign a
 * role they hold no authorize_* permission for, to a target not otherwise
 * protected by the peer-demotion/national-target guards.
 *
 * PART 2 — the pre-existing peer-demotion and self-modification guards,
 * previously untested.
 *
 * Permissions/roles are hand-built here (Permission::findOrCreate +
 * Role::findOrCreate()->syncPermissions()) rather than via
 * RolesTableSeeder/PermissionsTableSeeder, mirroring the convention already
 * used in tests/Feature/Approval/*ApprovalTest.php. This also sidesteps a
 * real risk: PermissionsTableSeeder issues a raw TRUNCATE (an implicit-
 * commit DDL statement on MySQL), which would end RefreshDatabase's
 * per-test transaction early and leak fixtures into later tests.
 *
 * The roles.edit / roles.update / search-for-roles route group requires the
 * 'password.confirm' middleware in addition to 'auth' + 'can:manage_roles_and_permissions' —
 * every request in this file seeds a fresh auth.password_confirmed_at
 * session value to satisfy it (see actingWithConfirmedPassword()).
 */

use App\Models\Branch;
use App\Models\User;
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

function actingWithConfirmedPassword(User $user)
{
    return test()->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
}

function updateRolesRequest(User $actor, array $payload)
{
    return actingWithConfirmedPassword($actor)->post(route('users.roles.update'), $payload);
}

beforeEach(function () {
    // The admin layout loads assets via @vite; no build manifest exists in CI/test.
    $this->withoutVite();

    foreach ([
        'manage-admin-panel',
        'manage_roles_and_permissions',
        'authorize_branch_secretary',
        'authorize_branch_db_administrator',
        'authorize_national_db_assistant',
        'authorize_observer_national_level',
        'authorize_branch_db_assistant',
        'authorize_division_db_assistant_finance',
        'authorize_division_db_assistant_operations',
        'authorize_national_db_administrator',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    // Roles that assign other roles (per PermissionsTableSeeder's authorize_* grants).
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions([
        'manage-admin-panel', 'manage_roles_and_permissions',
        'authorize_branch_secretary', 'authorize_branch_db_administrator',
        'authorize_national_db_assistant', 'authorize_observer_national_level',
    ]);

    Role::findOrCreate('branch_secretary', 'web')->syncPermissions([
        'manage-admin-panel', 'manage_roles_and_permissions',
        'authorize_branch_db_assistant', 'authorize_division_db_assistant_finance',
        'authorize_division_db_assistant_operations',
    ]);

    Role::findOrCreate('branch_db_administrator', 'web')->syncPermissions([
        'manage-admin-panel', 'manage_roles_and_permissions',
        'authorize_branch_db_assistant', 'authorize_division_db_assistant_finance',
        'authorize_division_db_assistant_operations',
    ]);

    Role::findOrCreate('super-admin', 'web')->syncPermissions([
        'manage-admin-panel', 'manage_roles_and_permissions',
        'authorize_national_db_administrator',
    ]);

    // Roles that only need to exist as assignment targets — no
    // manage_roles_and_permissions of their own.
    Role::findOrCreate('branch_db_assistant', 'web');
    Role::findOrCreate('division_db_assistant_finance', 'web');
    Role::findOrCreate('division_db_assistant_operations', 'web');
    Role::findOrCreate('national_db_assistant', 'web');
    Role::findOrCreate('observer_national_level', 'web');

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branchA = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->branchB = Branch::create(['name' => 'Beta Branch', 'code' => 'BET']);
});

/*
|--------------------------------------------------------------------------
| PART 1 — getAssignableRoles() enforcement (the fix)
|--------------------------------------------------------------------------
*/

test('a branch_secretary cannot escalate an unprotected target to national_db_administrator', function () {
    $actor = User::factory()->inBranch($this->branchA)->create();
    $actor->assignRole('branch_secretary');

    $target = User::factory()->inBranch($this->branchA)->create();

    $response = updateRolesRequest($actor, [
        'user_id' => $target->id,
        'role' => 'national_db_administrator',
    ]);

    $response->assertForbidden();
    expect($target->fresh()->hasRole('national_db_administrator'))->toBeFalse();
});

test('a branch_secretary cannot escalate an unprotected target to super-admin', function () {
    $actor = User::factory()->inBranch($this->branchA)->create();
    $actor->assignRole('branch_secretary');

    $target = User::factory()->inBranch($this->branchA)->create();

    $response = updateRolesRequest($actor, [
        'user_id' => $target->id,
        'role' => 'super-admin',
    ]);

    $response->assertForbidden();
    expect($target->fresh()->hasRole('super-admin'))->toBeFalse();
});

test('a branch_db_administrator cannot escalate an unprotected target to national_db_administrator', function () {
    $actor = User::factory()->inBranch($this->branchA)->create();
    $actor->assignRole('branch_db_administrator');

    $target = User::factory()->inBranch($this->branchA)->create();

    $response = updateRolesRequest($actor, [
        'user_id' => $target->id,
        'role' => 'national_db_administrator',
    ]);

    $response->assertForbidden();
    expect($target->fresh()->hasRole('national_db_administrator'))->toBeFalse();
});

test('a branch_secretary can still assign roles within their own getAssignableRoles() list', function () {
    $actor = User::factory()->inBranch($this->branchA)->create();
    $actor->assignRole('branch_secretary');

    $assistantTarget = User::factory()->inBranch($this->branchA)->create();
    $response = updateRolesRequest($actor, [
        'user_id' => $assistantTarget->id,
        'role' => 'branch_db_assistant',
    ]);
    $response->assertRedirect(route('users.roles.edit', ['user_id' => $assistantTarget->id]));
    $response->assertSessionHas('success');
    expect($assistantTarget->fresh()->hasRole('branch_db_assistant'))->toBeTrue();

    $financeTarget = User::factory()->inBranch($this->branchA)->create();
    $response = updateRolesRequest($actor, [
        'user_id' => $financeTarget->id,
        'role' => 'division_db_assistant_finance',
    ]);
    $response->assertRedirect(route('users.roles.edit', ['user_id' => $financeTarget->id]));
    expect($financeTarget->fresh()->hasRole('division_db_assistant_finance'))->toBeTrue();
});

test('a national_db_administrator can assign roles within their own getAssignableRoles() list', function () {
    $actor = User::factory()->create();
    $actor->assignRole('national_db_administrator');

    $target = User::factory()->create();

    $response = updateRolesRequest($actor, [
        'user_id' => $target->id,
        'role' => 'branch_secretary',
    ]);

    $response->assertRedirect(route('users.roles.edit', ['user_id' => $target->id]));
    expect($target->fresh()->hasRole('branch_secretary'))->toBeTrue();
});

test('a national_db_administrator cannot assign super-admin — the fix is not scoped only to branch-level actors', function () {
    $actor = User::factory()->create();
    $actor->assignRole('national_db_administrator');

    $target = User::factory()->create();

    $response = updateRolesRequest($actor, [
        'user_id' => $target->id,
        'role' => 'super-admin',
    ]);

    $response->assertForbidden();
    expect($target->fresh()->hasRole('super-admin'))->toBeFalse();
});

test('clearing a role entirely is not blocked by the getAssignableRoles() check', function () {
    $actor = User::factory()->inBranch($this->branchA)->create();
    $actor->assignRole('branch_secretary');

    $target = User::factory()->inBranch($this->branchA)->create();
    $target->assignRole('branch_db_assistant');

    $response = updateRolesRequest($actor, [
        'user_id' => $target->id,
        'role' => null,
    ]);

    $response->assertRedirect(route('users.roles.edit', ['user_id' => $target->id]));
    expect($target->fresh()->getRoleNames())->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| PART 2 — pre-existing peer-demotion and self-modification guards
|--------------------------------------------------------------------------
*/

test('a branch_secretary cannot modify another branch_secretary\'s role, even in a different branch', function () {
    $actor = User::factory()->inBranch($this->branchA)->create();
    $actor->assignRole('branch_secretary');

    $target = User::factory()->inBranch($this->branchB)->create();
    $target->assignRole('branch_secretary');

    // A role the actor is otherwise permitted to assign — isolates that the
    // block is driven by the target's current role, not the incoming one.
    $response = updateRolesRequest($actor, [
        'user_id' => $target->id,
        'role' => 'branch_db_assistant',
    ]);

    $response->assertForbidden();
    expect($target->fresh()->hasRole('branch_secretary'))->toBeTrue();
});

test('a branch_secretary cannot modify a national-role-holder\'s role', function () {
    $actor = User::factory()->inBranch($this->branchA)->create();
    $actor->assignRole('branch_secretary');

    $target = User::factory()->create();
    $target->assignRole('national_db_assistant');

    $response = updateRolesRequest($actor, [
        'user_id' => $target->id,
        'role' => 'branch_db_assistant',
    ]);

    $response->assertForbidden();
    expect($target->fresh()->hasRole('national_db_assistant'))->toBeTrue();
});

test('a user attempting to modify their own role is redirected with a validation error, not a 403', function () {
    $actor = User::factory()->inBranch($this->branchA)->create();
    $actor->assignRole('branch_secretary');

    $response = updateRolesRequest($actor, [
        'user_id' => $actor->id,
        'role' => 'branch_db_assistant',
    ]);

    $response->assertRedirect(route('users.roles.edit'));
    $response->assertSessionHasErrors('user_id');
    expect($actor->fresh()->hasRole('branch_secretary'))->toBeTrue();
});

test('searchUsersForRoles excludes self, national-role holders, and branch_secretary/branch_db_administrator peers for a branch-level searcher', function () {
    $actor = User::factory()->inBranch($this->branchA)->create(['last_name' => 'ZzSearchTarget']);
    $actor->assignRole('branch_secretary');

    $nationalUser = User::factory()->inBranch($this->branchA)->create(['last_name' => 'ZzSearchTarget']);
    $nationalUser->assignRole('national_db_assistant');

    $peerSecretary = User::factory()->inBranch($this->branchA)->create(['last_name' => 'ZzSearchTarget']);
    $peerSecretary->assignRole('branch_secretary');

    $peerAdmin = User::factory()->inBranch($this->branchA)->create(['last_name' => 'ZzSearchTarget']);
    $peerAdmin->assignRole('branch_db_administrator');

    $assistant = User::factory()->inBranch($this->branchA)->create(['last_name' => 'ZzSearchTarget']);
    $assistant->assignRole('branch_db_assistant');

    $response = actingWithConfirmedPassword($actor)
        ->get(route('users.search-for-roles', ['search' => 'ZzSearchTarget']));

    $response->assertOk();
    $ids = collect($response->json())->pluck('id')->all();

    expect($ids)->not->toContain($actor->id)
        ->and($ids)->not->toContain($nationalUser->id)
        ->and($ids)->not->toContain($peerSecretary->id)
        ->and($ids)->not->toContain($peerAdmin->id)
        ->and($ids)->toContain($assistant->id);
});
