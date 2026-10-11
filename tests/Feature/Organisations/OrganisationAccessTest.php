<?php

/**
 * Who reaches organisations: every route is gated on its organisation
 * permission, and every page, donation and certificate is limited to the
 * user's scope — national staff all, branch staff their own branch,
 * division staff none (403, Decisions.md 2026-10-11).
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

const ORG_PERMISSIONS = [
    'manage-admin-panel', 'add_organisation', 'edit_organisation', 'view_organisation',
    'archive_organisation', 'restore_organisation', 'add_donations', 'print_certificates', 'view_certificates',
];

beforeEach(function () {
    $this->withoutVite();

    DB::table('branches')->insert([
        ['id' => 848, 'name' => 'FCT', 'code' => 'FCT', 'is_active' => true],
        ['id' => 858, 'name' => 'Lagos', 'code' => 'LAG', 'is_active' => true],
    ]);
    DB::table('divisions')->insert([
        ['id' => 3069, 'name' => 'Abuja Municipal', 'branch_id' => 848, 'is_active' => true],
        ['id' => 3582, 'name' => 'Oshodi-Isolo', 'branch_id' => 858, 'is_active' => true],
    ]);

    foreach (ORG_PERMISSIONS as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    // Division staff get every permission here on purpose: the controller
    // must refuse them even so.
    foreach (['national_db_administrator', 'branch_db_administrator', 'division_db_assistant_finance'] as $role) {
        Role::findOrCreate($role, 'web')->syncPermissions(ORG_PERMISSIONS);
    }
    Role::findOrCreate('observer_national_level', 'web')->syncPermissions(['manage-admin-panel']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $make = function (string $role, array $attributes) {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    };

    $this->national = $make('national_db_administrator', ['branch_id' => 848, 'division_id' => 3069]);
    $this->branch = $make('branch_db_administrator', ['branch_id' => 848, 'division_id' => 3069]);
    $this->division = $make('division_db_assistant_finance', ['branch_id' => 848, 'division_id' => 3069]);
    $this->observer = $make('observer_national_level', ['branch_id' => 848]);

    $this->own = Organisation::create(['name' => 'Own Org', 'branch_id' => 848, 'division_id' => 3069]);
    $this->other = Organisation::create(['name' => 'Other Org', 'branch_id' => 858, 'division_id' => 3582]);
    $this->contact = User::factory()->create(['branch_id' => 858]);
});

/** Every organisation request a user can make, for one organisation. */
function organisationRequests(Organisation $organisation, User $contact): array
{
    return [
        ['get', route('organisations.show', $organisation), []],
        ['get', route('organisations.edit', $organisation), []],
        ['put', route('organisations.update', $organisation), ['name' => 'Changed', 'branch_id' => $organisation->branch_id, 'division_id' => $organisation->division_id]],
        ['post', route('organisations.archive', $organisation), []],
        ['post', route('organisations.restore', $organisation->id), []],
        ['post', route('organisations.link-user', $organisation), ['user_id' => $contact->id]],
        ['delete', route('organisations.unlink-user', [$organisation, $contact]), []],
        ['put', route('organisations.set-primary-contact', [$organisation, $contact]), []],
        ['get', route('organisations.donations.create', $organisation), []],
    ];
}

test('division staff get 403 on every organisation page, even with the permissions', function () {
    $this->actingAs($this->division)->get(route('organisations.index'))->assertForbidden();
    $this->actingAs($this->division)->get(route('organisations.create'))->assertForbidden();
    $this->actingAs($this->division)
        ->post(route('organisations.store'), ['name' => 'X', 'branch_id' => 848, 'division_id' => 3069])
        ->assertForbidden();
    $this->actingAs($this->division)->get(route('organisations.certificates.index'))->assertForbidden();

    foreach (organisationRequests($this->own, $this->contact) as [$method, $url, $data]) {
        $this->actingAs($this->division)->{$method}($url, $data)->assertForbidden();
    }

    expect(Organisation::where('name', 'X')->exists())->toBeFalse()
        ->and($this->own->fresh()->name)->toBe('Own Org');
});

test('branch staff get 403 on another branch\'s organisation', function () {
    foreach (organisationRequests($this->other, $this->contact) as [$method, $url, $data]) {
        $this->actingAs($this->branch)->{$method}($url, $data)->assertForbidden();
    }

    expect($this->other->fresh()->name)->toBe('Other Org')
        ->and($this->other->fresh()->trashed())->toBeFalse()
        ->and($this->other->users()->count())->toBe(0);
});

test('branch staff can open and manage their own branch\'s organisation', function () {
    $this->actingAs($this->branch)->get(route('organisations.show', $this->own))->assertOk();
    $this->actingAs($this->branch)->get(route('organisations.edit', $this->own))->assertOk();
    $this->actingAs($this->branch)->get(route('organisations.donations.create', $this->own))->assertOk();
    $this->actingAs($this->branch)
        ->post(route('organisations.link-user', $this->own), ['user_id' => $this->contact->id])
        ->assertRedirect();

    expect($this->own->users()->count())->toBe(1);
});

test('the list shows branch staff only their own branch, national staff everything', function () {
    $this->actingAs($this->branch)->get(route('organisations.index'))
        ->assertOk()->assertSee('Own Org')->assertDontSee('Other Org');

    // Even when another branch is asked for.
    $this->actingAs($this->branch)->get(route('organisations.index', ['branch_id' => 858]))
        ->assertOk()->assertDontSee('Other Org');

    $this->actingAs($this->national)->get(route('organisations.index'))
        ->assertOk()->assertSee('Own Org')->assertSee('Other Org');
});

test('archived organisations are scoped too', function () {
    $this->other->delete();

    $this->actingAs($this->branch)->get(route('organisations.index', ['status' => 'archived']))
        ->assertOk()->assertDontSee('Other Org');
    $this->actingAs($this->branch)->post(route('organisations.restore', $this->other->id))->assertForbidden();

    expect($this->other->fresh()->trashed())->toBeTrue();
});

test('each route needs its own organisation permission', function () {
    Role::findOrCreate('branch_db_administrator', 'web')->syncPermissions(['manage-admin-panel']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->branch)->get(route('organisations.index'))->assertForbidden();
    $this->actingAs($this->branch)->get(route('organisations.create'))->assertForbidden();
    foreach (organisationRequests($this->own, $this->contact) as [$method, $url, $data]) {
        $this->actingAs($this->branch)->{$method}($url, $data)->assertForbidden();
    }
});

test('a national observer sees organisations read-only, without action buttons', function () {
    Role::findOrCreate('observer_national_level', 'web')->givePermissionTo('view_organisation');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->observer)->get(route('organisations.index'))
        ->assertOk()
        ->assertSee('Own Org')->assertSee('Other Org')
        ->assertDontSee(route('organisations.create'), false);

    $this->actingAs($this->observer)->get(route('organisations.show', $this->own))
        ->assertOk()
        ->assertDontSee(route('organisations.edit', $this->own), false)
        ->assertDontSee(route('organisations.archive', $this->own), false)
        ->assertDontSee(route('organisations.link-user', $this->own), false)
        ->assertDontSee(route('organisations.donations.create', $this->own), false);

    $this->actingAs($this->observer)->get(route('organisations.create'))->assertForbidden();
    foreach (array_slice(organisationRequests($this->own, $this->contact), 1) as [$method, $url, $data]) {
        $this->actingAs($this->observer)->{$method}($url, $data)->assertForbidden();
    }

    $this->other->delete();
    $this->actingAs($this->observer)->get(route('organisations.index', ['status' => 'archived']))
        ->assertOk()
        ->assertSee('Other Org')
        ->assertDontSee(route('organisations.restore', $this->other->id), false);
});

test('super-admin has no organisation access', function () {
    Role::findOrCreate('super-admin', 'web')->syncPermissions(['manage-admin-panel']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $this->actingAs($superAdmin)->get(route('organisations.index'))->assertForbidden();
    $this->actingAs($superAdmin)->get(route('organisations.show', $this->own))->assertForbidden();
});

test('the migration gives observers view_organisation and nothing else', function () {
    Role::findOrCreate('observer_national_level', 'web')->syncPermissions(['manage-admin-panel']);

    (require database_path('migrations/2026_10_12_110000_give_observers_view_organisation.php'))->up();

    $observer = Role::findByName('observer_national_level', 'web');
    expect($observer->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['manage-admin-panel', 'view_organisation']);
});

test('the seeder gives observers view_organisation only, and super-admin none', function () {
    $this->seed([\Database\Seeders\RolesTableSeeder::class, \Database\Seeders\PermissionsTableSeeder::class]);

    $observer = Role::findByName('observer_national_level', 'web')->permissions->pluck('name');
    $superAdmin = Role::findByName('super-admin', 'web')->permissions->pluck('name');

    expect($observer->filter(fn ($p) => str_contains($p, 'organisation'))->values()->all())->toBe(['view_organisation'])
        ->and($superAdmin->filter(fn ($p) => str_contains($p, 'organisation'))->all())->toBe([]);
});

test('a donation for another branch\'s organisation cannot be posted by hand', function () {
    $this->actingAs($this->branch)->post(route('donations.store'), [
        'user_id' => $this->contact->id,
        'organisation_id' => $this->other->id,
        'amount' => 5000,
        'date_donation' => now()->toDateString(),
        'in_kind_donation' => false,
    ])->assertForbidden();

    expect(DB::table('donations')->count())->toBe(0);
});

test('certificates: another branch\'s organisations are neither printed nor recorded as printed', function () {
    $this->actingAs($this->branch)
        ->post(route('organisations.certificates.print.branded'), [
            'training_ids' => [$this->other->id],
            'certificate_type' => 'organisation_donation',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'None of the selected organisations are currently eligible for this certificate.');

    $this->actingAs($this->branch)
        ->postJson(route('certificates.mark-as-printed'), [
            'training_ids' => [$this->own->id, $this->other->id],
            'certificate_type' => 'organisation_donation',
        ])
        ->assertSuccessful();

    expect(DB::table('certificates_print')->pluck('organisation_id')->map(fn ($id) => (int) $id)->all())
        ->toBe([$this->own->id]);
});
