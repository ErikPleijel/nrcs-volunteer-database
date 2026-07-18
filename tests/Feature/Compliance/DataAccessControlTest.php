<?php

/**
 * Coverage for two NDPA-relevant access-control surfaces, previously
 * investigated but never explicitly tested:
 *
 * PART 1 — encrypted national_id_number display is gated by the same
 * UserPolicy::view() access-level check used everywhere else (self /
 * national / same-branch / same-division), and the column really is
 * encrypted at rest (not merely cast-but-plaintext).
 *
 * PART 2 — PhotoController::show() (routes.photos.show) is protected by
 * authentication + UserPolicy::view() alone, no signed URL or other
 * obscurity — enumerating a sequential user ID must not bypass it.
 *
 * Permissions/roles are hand-built (Permission::findOrCreate +
 * Role::findOrCreate()->syncPermissions()) rather than via
 * RolesTableSeeder/PermissionsTableSeeder, mirroring the convention
 * already used in tests/Feature/Roles/RoleAssignmentTest.php — that
 * seeder issues a raw TRUNCATE (an implicit-commit DDL statement on
 * MySQL), which would end RefreshDatabase's per-test transaction early
 * and leak fixtures into later tests.
 */

use App\Models\Branch;
use App\Models\User;
use Database\Factories\MembershipFeeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // The admin layout loads assets via @vite; no build manifest exists in CI/test.
    $this->withoutVite();

    foreach (['manage-admin-panel', 'view_user', 'print_idcards'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions([
        'manage-admin-panel', 'view_user', 'print_idcards',
    ]);

    // Deliberately WITHOUT print_idcards, matching PermissionsTableSeeder's
    // real grant (only national_db_administrator has it directly).
    Role::findOrCreate('branch_secretary', 'web')->syncPermissions([
        'manage-admin-panel', 'view_user',
    ]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branchA = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->branchB = Branch::create(['name' => 'Beta Branch', 'code' => 'BET']);
});

/*
|--------------------------------------------------------------------------
| PART 1 — Encrypted national ID display authorization
|--------------------------------------------------------------------------
*/

test('a user viewing their own profile sees their own national_id_number decrypted', function () {
    $self = User::factory()->withNationalId()->create();

    $response = $this->actingAs($self)->get(route('profile.show'));

    $response->assertOk();
    $response->assertSee($self->national_id_number);
});

test('a national-level admin can view another user\'s national_id_number on the admin user show page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('national_db_administrator');

    $target = User::factory()->withNationalId()->create();

    $response = $this->actingAs($admin)->get(route('users.show', $target));

    $response->assertOk();
    $response->assertSee($target->national_id_number);
});

test('a branch-level admin can view national_id_number for a user in their own branch', function () {
    $admin = User::factory()->inBranch($this->branchA)->create();
    $admin->assignRole('branch_secretary');

    $target = User::factory()->inBranch($this->branchA)->withNationalId()->create();

    $response = $this->actingAs($admin)->get(route('users.show', $target));

    $response->assertOk();
    $response->assertSee($target->national_id_number);
});

test('a branch-level admin is forbidden from viewing a user outside their branch', function () {
    $admin = User::factory()->inBranch($this->branchA)->create();
    $admin->assignRole('branch_secretary');

    $target = User::factory()->inBranch($this->branchB)->withNationalId()->create();

    $response = $this->actingAs($admin)->get(route('users.show', $target));

    $response->assertForbidden();
});

test('the id-card print view is only reachable with print_idcards permission', function () {
    $target = User::factory()->withNationalId()->create();

    $authorized = User::factory()->create();
    $authorized->givePermissionTo(['manage-admin-panel', 'print_idcards']);

    $unauthorized = User::factory()->create();
    $unauthorized->givePermissionTo('manage-admin-panel');

    $this->actingAs($authorized)
        ->get(route('id-card.print', $target))
        ->assertOk();

    $this->actingAs($unauthorized)
        ->get(route('id-card.print', $target))
        ->assertForbidden();
});

test('national_id_number is encrypted at rest but decrypts correctly through Eloquent', function () {
    $user = User::factory()->withNationalId()->create();
    $plaintext = $user->national_id_number;

    $rawColumnValue = DB::table('users')->where('id', $user->id)->value('national_id_number');

    expect($rawColumnValue)->not->toBe($plaintext);
    expect($rawColumnValue)->not->toContain($plaintext);

    $viaEloquent = User::find($user->id);
    expect($viaEloquent->national_id_number)->toBe($plaintext);
});

/*
|--------------------------------------------------------------------------
| PART 2 — Photo access control (PhotoController)
|--------------------------------------------------------------------------
*/

test('a user can view their own photo', function () {
    $self = User::factory()->create();

    $response = $this->actingAs($self)->get(route('photos.show', [$self, 'profile']));

    $response->assertOk();
});

test('a national-level admin can view any user\'s photo', function () {
    $admin = User::factory()->create();
    $admin->assignRole('national_db_administrator');

    $target = User::factory()->inBranch($this->branchB)->create();

    $response = $this->actingAs($admin)->get(route('photos.show', [$target, 'profile']));

    $response->assertOk();
});

test('a branch-level admin can view a photo for a user in their own branch', function () {
    $admin = User::factory()->inBranch($this->branchA)->create();
    $admin->assignRole('branch_secretary');

    $target = User::factory()->inBranch($this->branchA)->create();

    $response = $this->actingAs($admin)->get(route('photos.show', [$target, 'profile']));

    $response->assertOk();
});

test('a branch-level admin is forbidden from viewing a photo for a user outside their branch', function () {
    $admin = User::factory()->inBranch($this->branchA)->create();
    $admin->assignRole('branch_secretary');

    $target = User::factory()->inBranch($this->branchB)->create();

    $response = $this->actingAs($admin)->get(route('photos.show', [$target, 'profile']));

    $response->assertForbidden();
});

test('an unrelated authenticated user cannot view another user\'s photo by guessing the URL', function () {
    // No role at all: getAccessLevel() === 'none', so UserPolicy::view()
    // falls to its default branch (self-only) — confirms enumerating a
    // sequential user ID doesn't bypass authorization for a plain user.
    $bystander = User::factory()->create();
    $target = User::factory()->create();

    $response = $this->actingAs($bystander)->get(route('photos.show', [$target, 'profile']));

    $response->assertForbidden();
});

test('an unauthenticated request to the photo route is redirected, not served', function () {
    $target = User::factory()->create();

    $response = $this->get(route('photos.show', [$target, 'profile']));

    $response->assertRedirect();
});
