<?php

/**
 * users.red_cross_id_number holds the paper membership form number. Staff set
 * it on users/create and users/edit; it is stored exactly as entered (only
 * Laravel's trimming), so legacy values survive an edit untouched. Online
 * registration and the person's own profile edit cannot set it.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();

    foreach (['manage-admin-panel', 'add_user', 'edit_user', 'view_user'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions([
        'manage-admin-panel', 'add_user', 'edit_user', 'view_user',
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $this->branch = Branch::create(['name' => 'Kano Branch', 'code' => 'KAN']);
    $this->division = Division::create(['name' => 'Kano Division', 'branch_id' => $this->branch->id]);
});

function formNumberStorePayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Form',
        'last_name' => 'Number',
        'gender' => 'male',
        'birth_year' => 1990,
        'telephone1' => '08012345678',
        'branch_id' => test()->branch->id,
        'division_id' => test()->division->id,
        'contribution_type' => 'member',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'admin_consent_confirmed' => '1',
        'admin_consent_form' => '1',
    ], $overrides);
}

function formNumberUpdatePayload(User $user, array $overrides = []): array
{
    return array_merge([
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'email' => $user->email,
        'gender' => 'male',
        'birth_year' => 1990,
        'branch_id' => $user->branch_id,
        'division_id' => $user->division_id,
        'contribution_type' => 'member',
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| users/create
|--------------------------------------------------------------------------
*/

test('staff can save a new user with a membership form number, stored as entered', function () {
    $this->actingAs($this->admin)
        ->post(route('users.store'), formNumberStorePayload(['red_cross_id_number' => '  kan 0456 ']))
        ->assertSessionHasNoErrors();

    expect(User::where('first_name', 'Form')->firstOrFail()->red_cross_id_number)->toBe('kan 0456');
});

test('an empty membership form number is saved as null on create', function () {
    $this->actingAs($this->admin)
        ->post(route('users.store'), formNumberStorePayload(['red_cross_id_number' => '']))
        ->assertSessionHasNoErrors();

    expect(User::where('first_name', 'Form')->firstOrFail()->red_cross_id_number)->toBeNull();
});

test('a membership form number over 50 characters is rejected on create', function () {
    $this->actingAs($this->admin)
        ->post(route('users.store'), formNumberStorePayload(['red_cross_id_number' => str_repeat('A', 51)]))
        ->assertSessionHasErrors('red_cross_id_number');

    expect(User::where('first_name', 'Form')->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| users/edit
|--------------------------------------------------------------------------
*/

test('staff can set, change and clear the membership form number', function () {
    $user = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);

    foreach (['KAN0456' => 'KAN0456', '0789' => '0789', '' => null] as $submitted => $stored) {
        $this->actingAs($this->admin)
            ->put(route('users.update', $user), formNumberUpdatePayload($user, ['red_cross_id_number' => (string) $submitted]))
            ->assertSessionHasNoErrors();

        expect($user->refresh()->red_cross_id_number)->toBe($stored);
    }
});

test('a legacy value submitted as-is is kept unchanged', function () {
    $user = User::factory()->create([
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);
    $user->forceFill(['red_cross_id_number' => 'FCT/1234/05'])->save();

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), formNumberUpdatePayload($user, ['red_cross_id_number' => 'FCT/1234/05']))
        ->assertSessionHasNoErrors();

    expect($user->refresh()->red_cross_id_number)->toBe('FCT/1234/05');
});

test('a membership form number over 50 characters is rejected on update', function () {
    $user = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), formNumberUpdatePayload($user, ['red_cross_id_number' => str_repeat('A', 51)]))
        ->assertSessionHasErrors('red_cross_id_number');

    expect($user->refresh()->red_cross_id_number)->toBeNull();
});

test('the edit form is pre-filled with the current value', function () {
    $user = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);
    $user->forceFill(['red_cross_id_number' => 'KAN0456'])->save();

    $this->actingAs($this->admin)
        ->get(route('users.edit', $user))
        ->assertOk()
        ->assertSee('Membership form number')
        ->assertSee('value="KAN0456"', false);
});

/*
|--------------------------------------------------------------------------
| Self-service paths cannot set it
|--------------------------------------------------------------------------
*/

test('online registration ignores a posted membership form number', function () {
    $this->post(route('register'), [
        'form_rendered_at' => now()->timestamp - 60,
        'first_name' => 'Rita',
        'last_name' => 'Registrant',
        'email' => 'rita@example.org',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
        'telephone1' => '08031234567',
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
        'birth_year' => 1990,
        'gender' => 'female',
        'contribution_type' => 'volunteering',
        'coc_commitment_1' => '1',
        'coc_commitment_2' => '1',
        'coc_commitment_3' => '1',
        'coc_commitment_4' => '1',
        'red_cross_id_number' => 'KAN0456',
    ])->assertRedirect(route('registration.success'));

    expect(User::where('email', 'rita@example.org')->firstOrFail()->red_cross_id_number)->toBeNull();
});

test('the person\'s own profile update ignores a posted membership form number', function () {
    $user = User::factory()->create([
        'telephone1' => '08031234567',
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);
    $user->forceFill(['red_cross_id_number' => 'KAN0456'])->save();

    $this->actingAs($user)
        ->put(route('profile.update'), [
            'first_name' => 'Renamed',
            'last_name' => $user->last_name,
            'email' => $user->email,
            'gender' => 'male',
            'birth_year' => 1990,
            'telephone1' => '08031234567',
            'branch_id' => $this->branch->id,
            'division_id' => $this->division->id,
            'contribution_type' => 'member',
            'red_cross_id_number' => 'HACKED',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->first_name)->toBe('Renamed')
        ->and($user->red_cross_id_number)->toBe('KAN0456');
});

/*
|--------------------------------------------------------------------------
| Display
|--------------------------------------------------------------------------
*/

test('users/show displays the membership form number', function () {
    $user = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);
    $user->forceFill(['red_cross_id_number' => 'KAN0456'])->save();

    $this->actingAs($this->admin)
        ->get(route('users.show', $user))
        ->assertOk()
        ->assertSeeInOrder(['Membership form number', 'KAN0456']);
});

test('profile/show displays the person\'s own membership form number', function () {
    $user = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);
    $user->forceFill(['red_cross_id_number' => 'KAN0456'])->save();

    $this->actingAs($user)
        ->get(route('profile.show'))
        ->assertOk()
        ->assertSeeInOrder(['Membership form number:', 'KAN0456']);
});
