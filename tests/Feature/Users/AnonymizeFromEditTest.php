<?php

/**
 * Manual anonymization on users/edit (2026-10-08): a red box under "Archive
 * user", shown only to anonymize_user holders (national_db_administrator)
 * for archived, not yet anonymized accounts without a role. It needs both
 * confirmation boxes and the admin's password; nothing else in the form is
 * saved. An anonymized account can no longer be edited or restored.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const ANONYMIZE_BOX = 'Anonymize this account (permanent)';

beforeEach(function () {
    $this->withoutVite();

    foreach (['manage-admin-panel', 'view_user', 'edit_user', 'anonymize_user'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')
        ->syncPermissions(['manage-admin-panel', 'view_user', 'edit_user', 'anonymize_user']);
    // A national role that can edit users but not anonymize them.
    Role::findOrCreate('national_db_assistant', 'web')
        ->syncPermissions(['manage-admin-panel', 'view_user', 'edit_user']);
    Role::findOrCreate('branch_db_assistant', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');
    $this->assistant = User::factory()->create();
    $this->assistant->assignRole('national_db_assistant');

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);
});

function archivedPerson(array $overrides = []): User
{
    $user = User::factory()->create([
        'first_name' => 'Amina', 'last_name' => 'Bello', 'gender' => 'female', 'birth_year' => 1984,
        'branch_id' => test()->branch->id, 'division_id' => test()->division->id,
        'lifecycle_status' => 'active', ...$overrides,
    ]);
    $user->markArchived(test()->admin)->save();

    return $user->fresh();
}

/** The whole edit form as the browser would send it, plus the anonymize fields. */
function anonymizePayload(array $overrides = []): array
{
    return [
        'first_name' => 'Changed',
        'last_name' => 'Name',
        'gender' => 'male',
        'birth_year' => 1999,
        'branch_id' => test()->branch->id,
        'division_id' => test()->division->id,
        'contribution_type' => 'member',
        'is_inactive' => '0', // would restore the account if the normal save ran
        'action' => 'anonymize',
        'anonymize_confirm' => '1',
        'anonymize_dpo_consulted' => '1',
        'anonymize_password' => 'password',
        ...$overrides,
    ];
}

function anonymizedAt(User $user): ?string
{
    return DB::table('users')->where('id', $user->id)->value('anonymized_at');
}

/*
|--------------------------------------------------------------------------
| Who sees the box
|--------------------------------------------------------------------------
*/

test('a National DB admin sees the box for an archived account, with the DPO details', function () {
    $user = archivedPerson();

    $this->actingAs($this->admin)->get(route('users.edit', $user))
        ->assertOk()
        ->assertSee(ANONYMIZE_BOX)
        ->assertSee('This cannot be undone. Before you anonymize an account, you must consult')
        ->assertSee('No Data Protection Officer is registered.') // <x-dpo-contact /> with no DPO set
        ->assertSee('I have consulted the Data Protection Officer about this');
});

test('the box is not shown for active accounts, role holders, or users without anonymize_user', function () {
    $active = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);
    $this->actingAs($this->admin)->get(route('users.edit', $active))->assertOk()->assertDontSee(ANONYMIZE_BOX);

    $staff = archivedPerson();
    $staff->assignRole('branch_db_assistant');
    $this->actingAs($this->admin)->get(route('users.edit', $staff))->assertOk()->assertDontSee(ANONYMIZE_BOX);

    $plain = archivedPerson();
    $this->actingAs($this->assistant)->get(route('users.edit', $plain))->assertOk()->assertDontSee(ANONYMIZE_BOX);
});

/*
|--------------------------------------------------------------------------
| Submitting
|--------------------------------------------------------------------------
*/

test('it fails without both boxes or with a wrong password, and nothing is saved', function () {
    $user = archivedPerson();

    foreach ([
        ['anonymize_dpo_consulted' => null],
        ['anonymize_confirm' => null],
        ['anonymize_password' => 'wrong-password'],
        ['anonymize_password' => null],
    ] as $overrides) {
        $this->actingAs($this->admin)
            ->from(route('users.edit', $user))
            ->put(route('users.update', $user), anonymizePayload($overrides))
            ->assertRedirect(route('users.edit', $user))
            ->assertSessionHasErrors();
    }

    $fresh = $user->fresh();
    expect(anonymizedAt($user))->toBeNull()
        ->and($fresh->first_name)->toBe('Amina')
        ->and($fresh->lifecycle_status)->toBe('archived');
});

test('the password is never flashed back into the form', function () {
    $user = archivedPerson();

    $this->actingAs($this->admin)
        ->from(route('users.edit', $user))
        ->put(route('users.update', $user), anonymizePayload(['anonymize_confirm' => null, 'anonymize_password' => 'password']));

    expect(session()->getOldInput('anonymize_password'))->toBeNull();
});

test('with both boxes and the password it anonymizes, saves nothing else, and goes to users/show', function () {
    $user = archivedPerson();

    $this->actingAs($this->admin)
        ->from(route('users.edit', $user))
        ->put(route('users.update', $user), anonymizePayload())
        ->assertRedirect(route('users.show', $user))
        ->assertSessionHas('success', "DB-{$user->id} has been anonymized.");

    $row = DB::table('users')->where('id', $user->id)->first();
    expect($row->anonymized_at)->not->toBeNull()
        ->and($row->anonymized_by_id)->toBe($this->admin->id)
        ->and($row->first_name)->toBe('Anonymized') // not 'Changed'
        ->and($row->gender)->toBe('female')         // not 'male'
        ->and($row->birth_year)->toBe(1980)         // not 1999
        ->and($row->lifecycle_status)->toBe('archived'); // is_inactive=0 did not restore it

    expect(DB::table('logs')->where('action', 'user_anonymized')->where('subject_id', $user->id)->value('description'))
        ->toBe("DB-{$user->id} anonymized (manual)");
});

test('a user without anonymize_user cannot anonymize by posting the form', function () {
    $user = archivedPerson();

    $this->actingAs($this->assistant)
        ->put(route('users.update', $user), anonymizePayload())
        ->assertForbidden();

    expect(anonymizedAt($user))->toBeNull();
});

test('a role holder cannot be anonymized even by posting the form', function () {
    $user = archivedPerson();
    $user->assignRole('branch_db_assistant');

    $this->actingAs($this->admin)
        ->from(route('users.edit', $user))
        ->put(route('users.update', $user), anonymizePayload())
        ->assertRedirect(route('users.edit', $user))
        ->assertSessionHasErrors('anonymize');

    expect(anonymizedAt($user))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| After anonymization
|--------------------------------------------------------------------------
*/

test('an anonymized account cannot be edited or restored', function () {
    $user = archivedPerson();
    $this->actingAs($this->admin)->put(route('users.update', $user), anonymizePayload());

    $this->actingAs($this->admin)->get(route('users.edit', $user))
        ->assertOk()
        ->assertSee('This account was anonymized on')
        ->assertDontSee('Update Person')
        ->assertDontSee(ANONYMIZE_BOX);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), [...anonymizePayload(), 'action' => null, 'first_name' => 'Restored'])
        ->assertRedirect(route('users.show', $user));

    $row = DB::table('users')->where('id', $user->id)->first();
    expect($row->first_name)->toBe('Anonymized')
        ->and($row->lifecycle_status)->toBe('archived');

    $this->actingAs($this->admin)->get(route('users.show', $user))
        ->assertOk()
        ->assertSee('This account was anonymized on');
});
