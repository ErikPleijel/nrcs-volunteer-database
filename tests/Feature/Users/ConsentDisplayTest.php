<?php

/**
 * users/show, SYSTEM & REGISTRATION card: the Code of Conduct & consent
 * acceptance date (or "Not yet accepted"), and the staff member who
 * attested consent when it was not the person themselves.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    foreach (['manage-admin-panel', 'view_user'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['manage-admin-panel', 'view_user']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create(['first_name' => 'Nora', 'last_name' => 'National']);
    $this->admin->assignRole('national_db_administrator');
});

test('users/show shows the acceptance date when it is set', function () {
    // Self-registered: consent recorded by the person themselves.
    $target = User::factory()->create(['code_of_conduct_accepted_at' => '2026-03-15 10:00:00']);
    $target->update(['consent_obtained_by_id' => $target->id]);

    $this->actingAs($this->admin)->get(route('users.show', $target))
        ->assertOk()
        ->assertSee('Code of Conduct &amp; consent accepted: Mar 15, 2026', false)
        ->assertDontSee('Not yet accepted')
        ->assertDontSee('Consent obtained by:');
});

test('users/show shows "Not yet accepted" when there is no acceptance', function () {
    $target = User::factory()->notConsented()->create();

    $this->actingAs($this->admin)->get(route('users.show', $target))
        ->assertOk()
        ->assertSee('Not yet accepted')
        ->assertDontSee('consent accepted:');
});

test('users/show names the staff member who attested consent', function () {
    $staff = User::factory()->create(['first_name' => 'Sam', 'last_name' => 'Staffer']);
    $target = User::factory()->notConsented()->create([
        'consent_obtained_at' => now(),
        'consent_obtained_by_id' => $staff->id,
    ]);

    $this->actingAs($this->admin)->get(route('users.show', $target))
        ->assertOk()
        ->assertSee("Consent obtained by: Sam Staffer DB-{$staff->id}");
});
