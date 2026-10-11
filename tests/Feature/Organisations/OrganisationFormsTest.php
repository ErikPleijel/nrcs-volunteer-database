<?php

/**
 * Organisation create and edit forms: branch and division are required and
 * must match; moving an organisation follows the same rules as moving a
 * person (national anywhere, branch staff within their branch) and is
 * written to the Audit Log.
 */

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    DB::table('branches')->insert([
        ['id' => 848, 'name' => 'FCT', 'code' => 'FCT', 'is_active' => true],
        ['id' => 858, 'name' => 'Lagos', 'code' => 'LAG', 'is_active' => true],
    ]);
    DB::table('divisions')->insert([
        ['id' => 3069, 'name' => 'Abuja Municipal', 'branch_id' => 848, 'is_active' => true],
        ['id' => 3070, 'name' => 'Bwari', 'branch_id' => 848, 'is_active' => true],
        ['id' => 3582, 'name' => 'Oshodi-Isolo', 'branch_id' => 858, 'is_active' => true],
    ]);

    $permissions = ['manage-admin-panel', 'add_organisation', 'edit_organisation', 'view_organisation'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    foreach (['national_db_administrator', 'branch_db_assistant'] as $role) {
        Role::findOrCreate($role, 'web')->syncPermissions($permissions);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->national = User::factory()->create(['branch_id' => 848, 'division_id' => 3069]);
    $this->national->assignRole('national_db_administrator');

    $this->branchUser = User::factory()->create(['branch_id' => 848, 'division_id' => 3069]);
    $this->branchUser->assignRole('branch_db_assistant');

    $this->organisation = Organisation::create(['name' => 'Acme', 'branch_id' => 848, 'division_id' => 3069]);
});

function orgForm(array $overrides = []): array
{
    return array_merge(['name' => 'Acme'], $overrides);
}

test('creating needs a branch and a division', function () {
    $this->actingAs($this->national)
        ->post(route('organisations.store'), orgForm(['name' => 'New org']))
        ->assertSessionHasErrors(['branch_id', 'division_id']);

    expect(Organisation::where('name', 'New org')->exists())->toBeFalse();
});

test('a division from another branch is refused', function () {
    $this->actingAs($this->national)
        ->post(route('organisations.store'), orgForm(['name' => 'New org', 'branch_id' => 848, 'division_id' => 3582]))
        ->assertSessionHasErrors(['division_id' => 'The selected division is not in the selected branch.']);
});

test('national staff create an organisation with branch and division', function () {
    $this->actingAs($this->national)
        ->post(route('organisations.store'), orgForm(['name' => 'New org', 'branch_id' => 858, 'division_id' => 3582]))
        ->assertRedirect(route('organisations.index'));

    $organisation = Organisation::where('name', 'New org')->sole();
    expect($organisation->branch_id)->toBe(858)
        ->and($organisation->division_id)->toBe(3582);
});

test('branch staff always create in their own branch, with a division of it', function () {
    $this->actingAs($this->branchUser)
        ->post(route('organisations.store'), orgForm(['name' => 'Posted elsewhere', 'branch_id' => 858, 'division_id' => 3582]))
        ->assertSessionHasErrors(['division_id']);

    $this->actingAs($this->branchUser)
        ->post(route('organisations.store'), orgForm(['name' => 'Own branch', 'branch_id' => 858, 'division_id' => 3070]))
        ->assertRedirect();

    $organisation = Organisation::where('name', 'Own branch')->sole();
    expect($organisation->branch_id)->toBe(848)
        ->and($organisation->division_id)->toBe(3070);
});

test('the create form lists the divisions of the branch staff\'s own branch only', function () {
    $this->actingAs($this->branchUser)
        ->get(route('organisations.create'))
        ->assertOk()
        ->assertSee('Bwari')
        ->assertDontSee('Oshodi-Isolo');
});

test('national staff move an organisation to another branch and division, and it is logged', function () {
    $this->actingAs($this->national)
        ->put(route('organisations.update', $this->organisation), orgForm(['branch_id' => 858, 'division_id' => 3582]))
        ->assertRedirect(route('organisations.show', $this->organisation));

    $organisation = $this->organisation->fresh();
    expect($organisation->branch_id)->toBe(858)
        ->and($organisation->division_id)->toBe(3582);

    $log = DB::table('logs')->where('action', 'organisation_branch_division_changed')->sole();
    expect(json_decode($log->old_values, true))->toBe(['branch_id' => 848, 'division_id' => 3069])
        ->and(json_decode($log->new_values, true))->toBe(['branch_id' => 858, 'division_id' => 3582])
        ->and((int) $log->branch_id)->toBe(858)
        ->and((int) $log->division_id)->toBe(3582)
        ->and($log->description)->toContain('Branch: FCT → Lagos')
        ->and($log->description)->toContain('Division: Abuja Municipal → Oshodi-Isolo');
});

test('branch staff can move an organisation between divisions of their branch', function () {
    $this->actingAs($this->branchUser)
        ->put(route('organisations.update', $this->organisation), orgForm(['branch_id' => 848, 'division_id' => 3070]))
        ->assertRedirect();

    expect($this->organisation->fresh()->division_id)->toBe(3070)
        ->and(DB::table('logs')->where('action', 'organisation_branch_division_changed')->value('description'))
        ->toContain('Division: Abuja Municipal → Bwari');
});

test('branch staff cannot move an organisation to another branch', function () {
    $this->actingAs($this->branchUser)
        ->put(route('organisations.update', $this->organisation), orgForm(['branch_id' => 858, 'division_id' => 3582]))
        ->assertSessionHasErrors(['branch_id' => 'Only national staff can move an organisation to a different branch.']);

    expect($this->organisation->fresh()->branch_id)->toBe(848)
        ->and(DB::table('logs')->where('action', 'organisation_branch_division_changed')->count())->toBe(0);
});

test('an edit that keeps branch and division writes no move entry', function () {
    $this->actingAs($this->national)
        ->put(route('organisations.update', $this->organisation), orgForm(['name' => 'Acme Ltd', 'branch_id' => 848, 'division_id' => 3069]))
        ->assertRedirect();

    expect($this->organisation->fresh()->name)->toBe('Acme Ltd')
        ->and(DB::table('logs')->where('action', 'organisation_branch_division_changed')->count())->toBe(0);
});

test('an organisation without a division must get one when it is edited', function () {
    $organisation = Organisation::create(['name' => 'No division yet', 'branch_id' => 848]);

    $this->actingAs($this->branchUser)
        ->put(route('organisations.update', $organisation), orgForm(['name' => 'No division yet', 'branch_id' => 848]))
        ->assertSessionHasErrors(['division_id']);

    $this->actingAs($this->branchUser)
        ->put(route('organisations.update', $organisation), orgForm(['name' => 'No division yet', 'branch_id' => 848, 'division_id' => 3070]))
        ->assertRedirect();

    expect($organisation->fresh()->division_id)->toBe(3070)
        ->and(DB::table('logs')->where('action', 'organisation_branch_division_changed')->value('description'))
        ->toContain('Division: N/A → Bwari');
});

test('the edit form shows the move note, and branch staff see their branch locked', function () {
    $this->actingAs($this->branchUser)
        ->get(route('organisations.edit', $this->organisation))
        ->assertOk()
        ->assertSee('only affects payments and donations recorded from now on')
        ->assertSee('Bwari')
        ->assertDontSee('Oshodi-Isolo')
        ->assertDontSee('Select Branch');

    $this->actingAs($this->national)
        ->get(route('organisations.edit', $this->organisation))
        ->assertOk()
        ->assertSee('Select Branch')
        ->assertSee('Lagos');
});

test('show and index display the division, and index filters by it', function () {
    Organisation::create(['name' => 'Bwari Org', 'branch_id' => 848, 'division_id' => 3070]);

    $this->actingAs($this->national)
        ->get(route('organisations.show', $this->organisation))
        ->assertOk()
        ->assertSee('Abuja Municipal');

    $this->actingAs($this->national)
        ->get(route('organisations.index', ['branch_id' => 848, 'division_id' => 3070]))
        ->assertOk()
        ->assertSee('Bwari Org')
        ->assertDontSee('>Acme<', false);

    // A division outside the chosen branch is ignored, not applied.
    $this->actingAs($this->national)
        ->get(route('organisations.index', ['branch_id' => 858, 'division_id' => 3070]))
        ->assertOk()
        ->assertDontSee('Bwari Org');
});
