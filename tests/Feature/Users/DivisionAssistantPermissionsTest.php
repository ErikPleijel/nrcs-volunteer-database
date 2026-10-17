<?php

/**
 * users/{user}/images (UserImageController, UserPolicy::uploadImages) and staff
 * registration (UserController::create/store), for division DB assistants with
 * and without their tick boxes ("Can upload photos and signatures" =
 * upload_user_images, "Can register new users" = add_user, both given directly),
 * plus the two closed gaps: uploading images outside your own area, and
 * registering someone in another branch/division, through the URL / a posted form.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function daStorePayload(Division $division, array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Newly',
        'last_name' => 'Registered',
        'gender' => 'female',
        'birth_year' => 1995,
        'telephone1' => '08012345678',
        'branch_id' => $division->branch_id,
        'division_id' => $division->id,
        'contribution_type' => 'member',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'admin_consent_confirmed' => '1',
        'admin_consent_form' => '1',
    ], $overrides);
}

function daAssistant(Division $division, array $tickBoxes = [], string $role = 'division_db_assistant_finance'): User
{
    $user = User::factory()->inDivision($division)->create();
    $user->assignRole($role);
    if ($tickBoxes !== []) {
        $user->givePermissionTo($tickBoxes);
    }

    return $user->fresh();
}

function daRegistered(): ?User
{
    return User::where('first_name', 'Newly')->where('last_name', 'Registered')->first();
}

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');

    foreach (['manage-admin-panel', 'view_user', 'edit_user', 'add_user', 'upload_user_images'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['manage-admin-panel', 'view_user', 'edit_user', 'add_user']);
    Role::findOrCreate('branch_secretary', 'web')->syncPermissions(['manage-admin-panel', 'view_user', 'edit_user', 'add_user']);
    Role::findOrCreate('branch_db_assistant', 'web')->syncPermissions(['manage-admin-panel', 'view_user', 'edit_user', 'add_user']);
    Role::findOrCreate('division_db_assistant_finance', 'web')->syncPermissions(['manage-admin-panel', 'view_user']);
    Role::findOrCreate('division_db_assistant_operations', 'web')->syncPermissions(['manage-admin-panel', 'view_user']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branchA = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->branchB = Branch::create(['name' => 'Beta Branch', 'code' => 'BET']);
    $this->divisionA = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branchA->id]);
    $this->divisionA2 = Division::create(['name' => 'Alpha Two Division', 'branch_id' => $this->branchA->id]);
    $this->divisionB = Division::create(['name' => 'Beta Division', 'branch_id' => $this->branchB->id]);

    $this->memberA = User::factory()->inDivision($this->divisionA)->create(['picture' => null, 'signature' => null]);
});

/*
|--------------------------------------------------------------------------
| Photo & signature page
|--------------------------------------------------------------------------
*/

test('a division assistant with the upload tick box can upload photo and signature in their own division', function (string $role) {
    $assistant = daAssistant($this->divisionA, ['upload_user_images'], $role);

    $this->actingAs($assistant)->get(route('users.images.edit', $this->memberA))
        ->assertOk()
        ->assertSee('Profile Photo Management')
        ->assertSee('Signature Management');

    $this->actingAs($assistant)
        ->post(route('users.images.photo', $this->memberA), ['picture' => UploadedFile::fake()->image('p.jpg', 300, 300)])
        ->assertSessionHas('success');
    $this->actingAs($assistant)
        ->post(route('users.images.signature', $this->memberA), ['signature_file' => UploadedFile::fake()->image('s.png', 300, 100)])
        ->assertSessionHas('success');

    $member = $this->memberA->fresh();
    expect($member->picture)->not->toBeNull()
        ->and($member->image_upload_date)->not->toBeNull()
        ->and($member->signature)->not->toBeNull();
})->with(['division_db_assistant_finance', 'division_db_assistant_operations']);

test('a division assistant with the upload tick box still cannot edit other personal data', function () {
    $assistant = daAssistant($this->divisionA, ['upload_user_images']);

    $this->actingAs($assistant)->get(route('users.edit', $this->memberA))->assertForbidden();
    $this->actingAs($assistant)->put(route('users.update', $this->memberA), ['first_name' => 'Changed'])->assertForbidden();

    expect($this->memberA->fresh()->first_name)->not->toBe('Changed');
});

test('a division assistant without the upload tick box cannot reach the image page or upload by URL', function () {
    $assistant = daAssistant($this->divisionA);

    $this->actingAs($assistant)->get(route('users.images.edit', $this->memberA))->assertForbidden();
    $this->actingAs($assistant)
        ->post(route('users.images.photo', $this->memberA), ['picture' => UploadedFile::fake()->image('p.jpg', 300, 300)])
        ->assertForbidden();

    expect($this->memberA->fresh()->picture)->toBeNull();
});

test('a division assistant with the tick box cannot upload for someone outside their division', function (string $where) {
    $assistant = daAssistant($this->divisionA, ['upload_user_images']);
    $target = User::factory()->inDivision($where === 'same branch' ? $this->divisionA2 : $this->divisionB)->create(['picture' => null]);

    $this->actingAs($assistant)->get(route('users.images.edit', $target))->assertForbidden();
    $this->actingAs($assistant)
        ->post(route('users.images.photo', $target), ['picture' => UploadedFile::fake()->image('p.jpg', 300, 300)])
        ->assertForbidden();
    $this->actingAs($assistant)
        ->post(route('users.images.signature', $target), ['signature_file' => UploadedFile::fake()->image('s.png', 300, 100)])
        ->assertForbidden();

    expect($target->fresh()->picture)->toBeNull();
})->with(['same branch', 'other branch']);

test('a division assistant with the tick box cannot upload for a person holding an admin role', function () {
    $assistant = daAssistant($this->divisionA, ['upload_user_images']);
    $colleague = daAssistant($this->divisionA);

    $this->actingAs($assistant)
        ->post(route('users.images.photo', $colleague), ['picture' => UploadedFile::fake()->image('p.jpg', 300, 300)])
        ->assertForbidden();
});

test('gap closed: a branch assistant with edit_user cannot upload images for someone in another branch by URL', function () {
    $assistant = User::factory()->inBranch($this->branchB)->create();
    $assistant->assignRole('branch_db_assistant');

    $this->actingAs($assistant)->get(route('users.images.edit', $this->memberA))->assertForbidden();
    $this->actingAs($assistant)
        ->post(route('users.images.photo', $this->memberA), ['picture' => UploadedFile::fake()->image('p.jpg', 300, 300)])
        ->assertForbidden();
    $this->actingAs($assistant)
        ->post(route('users.images.signature', $this->memberA), ['signature_file' => UploadedFile::fake()->image('s.png', 300, 100)])
        ->assertForbidden();

    $member = $this->memberA->fresh();
    expect($member->picture)->toBeNull()->and($member->signature)->toBeNull();
});

test('admins who can edit the person can still upload, and users/edit links to the image page', function () {
    $secretary = User::factory()->inBranch($this->branchA)->create();
    $secretary->assignRole('branch_secretary');

    $this->actingAs($secretary)->get(route('users.edit', $this->memberA))
        ->assertOk()
        ->assertSee(route('users.images.edit', $this->memberA), false)
        ->assertDontSee('Profile Photo Management');

    $this->actingAs($secretary)
        ->post(route('users.images.photo', $this->memberA), ['picture' => UploadedFile::fake()->image('p.jpg', 300, 300)])
        ->assertSessionHas('success');

    $this->actingAs($secretary)->get(route('users.show', $this->memberA))
        ->assertOk()
        ->assertSee(route('users.images.edit', $this->memberA), false);
});

test('users/show offers the image page to a division assistant with the tick box, but no Edit Person', function () {
    $assistant = daAssistant($this->divisionA, ['upload_user_images']);

    $this->actingAs($assistant)->get(route('users.show', $this->memberA))
        ->assertOk()
        ->assertSee(route('users.images.edit', $this->memberA), false)
        ->assertDontSee(route('users.edit', $this->memberA), false);
});

/*
|--------------------------------------------------------------------------
| Registering new users
|--------------------------------------------------------------------------
*/

test('a division assistant with the register tick box registers people in their own division only', function (string $role) {
    $assistant = daAssistant($this->divisionA, ['add_user'], $role);

    $this->actingAs($assistant)->get(route('users.create'))
        ->assertOk()
        ->assertSee('Alpha Division')
        ->assertDontSee('Alpha Two Division');

    $this->actingAs($assistant)->post(route('users.store'), daStorePayload($this->divisionA))
        ->assertSessionHasNoErrors();

    $user = daRegistered();
    expect($user)->not->toBeNull()
        ->and($user->division_id)->toBe($this->divisionA->id)
        ->and($user->lifecycle_status)->toBe('pending_engagement')
        ->and($user->form_reg_id)->toBe($assistant->id);
})->with(['division_db_assistant_finance', 'division_db_assistant_operations']);

test('a division assistant without the register tick box cannot register anyone', function () {
    $assistant = daAssistant($this->divisionA);

    $this->actingAs($assistant)->get(route('users.create'))->assertForbidden();
    $this->actingAs($assistant)->post(route('users.store'), daStorePayload($this->divisionA))->assertForbidden();

    expect(daRegistered())->toBeNull();
});

test('gap closed: a division assistant cannot register someone in another division through a posted form', function (string $where) {
    $assistant = daAssistant($this->divisionA, ['add_user']);
    $division = $where === 'same branch' ? $this->divisionA2 : $this->divisionB;

    $this->actingAs($assistant)->post(route('users.store'), daStorePayload($division))
        ->assertSessionHasErrors(['division_id' => 'You can only register people in your own division.']);

    expect(daRegistered())->toBeNull();
})->with(['same branch', 'other branch']);

test('gap closed: a branch admin cannot register someone in another branch through a posted form', function () {
    $secretary = User::factory()->inBranch($this->branchA)->create();
    $secretary->assignRole('branch_secretary');

    $this->actingAs($secretary)->post(route('users.store'), daStorePayload($this->divisionB))
        ->assertSessionHasErrors(['branch_id' => 'You can only register people in your own branch.']);

    expect(daRegistered())->toBeNull();
});

test('a Red Cross Unit from another division is refused on registration', function () {
    $assistant = daAssistant($this->divisionA, ['add_user']);
    $otherUnit = RedCrossUnit::create(['name' => 'Other Unit', 'division_id' => $this->divisionA2->id]);

    $this->actingAs($assistant)->post(route('users.store'), daStorePayload($this->divisionA, [
        'contribution_type' => 'volunteering',
        'red_cross_unit_id' => $otherUnit->id,
    ]))->assertSessionHasErrors('red_cross_unit_id');

    expect(daRegistered())->toBeNull();
});

test('registration by a division assistant ignores a posted photo unless they also have the upload tick box', function (array $tickBoxes, bool $kept) {
    $assistant = daAssistant($this->divisionA, $tickBoxes);

    $this->actingAs($assistant)->post(route('users.store'), daStorePayload($this->divisionA, [
        'picture' => UploadedFile::fake()->image('p.jpg', 300, 300),
    ]))->assertSessionHasNoErrors();

    expect(daRegistered()->picture !== null)->toBe($kept);
})->with([
    'register only' => [['add_user'], false],
    'register and upload' => [['add_user', 'upload_user_images'], true],
]);
