<?php

/**
 * Member-list pagination on unit pages.
 *
 * All five member-list pages (/my-unit, /my-unit/report, /my-unit/tables,
 * red-cross-units/show, task-forces/show) page their members through the
 * same PaginatesMembers helper (24 per page, 'members_page' parameter) and
 * the same <x-members-empty-state> component, so the red-cross-units/show
 * tests below cover that shared logic. The /my-unit/report and
 * /my-unit/tables tests cover their active-members-only scoping.
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

beforeEach(function () {
    $this->withoutVite();

    $permissions = ['manage-admin-panel', 'view_red_cross_unit'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $division = Division::create(['name' => 'Alpha One', 'branch_id' => $branch->id]);

    $this->national = User::factory()->create(['first_name' => 'Nora', 'last_name' => 'National']);
    $this->national->assignRole('national_db_administrator');

    $this->unit = RedCrossUnit::create(['name' => 'Big Unit', 'division_id' => $division->id, 'is_active' => true]);

    // 30 active members, named so name order is Member 01 .. Member 30.
    foreach (range(1, 30) as $i) {
        User::factory()->create([
            'first_name' => 'Member',
            'last_name' => sprintf('%02d', $i),
            'branch_id' => $branch->id,
            'division_id' => $division->id,
            'red_cross_unit_id' => $this->unit->id,
        ]);
    }
});

function unitShow(array $query = [])
{
    return test()->actingAs(test()->national)
        ->get(route('red-cross-units.show', ['red_cross_unit' => test()->unit, ...$query]))
        ->assertOk();
}

test('page 1 shows the first 24 members and members_page=2 the remaining 6', function () {
    $page1 = unitShow();
    expect($page1->viewData('members'))->toHaveCount(24)
        ->and($page1->viewData('members')->total())->toBe(30);
    $page1->assertSee('Member 01')->assertSee('Member 24')->assertDontSee('Member 25');

    $page2 = unitShow(['members_page' => 2]);
    expect($page2->viewData('members'))->toHaveCount(6);
    $page2->assertSee('Member 25')->assertSee('Member 30')->assertDontSee('Member 24');
});

test('the member paginator does not clobber the activities paginator', function () {
    $response = unitShow(['members_page' => 2, 'page' => 3]);

    expect($response->viewData('members')->currentPage())->toBe(2)
        ->and($response->viewData('recentActivities')->currentPage())->toBe(3);
});

test('a page past the end links back to page 1, keeping other query params', function () {
    $response = unitShow(['members_page' => 9, 'page' => 3]);

    expect($response->viewData('members'))->toHaveCount(0);
    $response->assertSee('No members on this page.')
        ->assertSee('Go back to the first page')
        ->assertDontSee('No members in this unit (excluding leaders).')
        ->assertSee(e($response->viewData('members')->url(1)), false);

    expect($response->viewData('members')->url(1))
        ->toContain('members_page=1')
        ->toContain('page=3');
});

test('/my-unit/report and /my-unit/tables list active members only', function () {
    User::factory()->create([
        'first_name' => 'Archie', 'last_name' => 'Archived',
        'red_cross_unit_id' => $this->unit->id, 'lifecycle_status' => 'archived',
    ]);
    $viewer = User::where('red_cross_unit_id', $this->unit->id)->where('last_name', '01')->first();

    $report = $this->actingAs($viewer)->get(route('red-cross-units.my-unit-report'))->assertOk();
    expect($report->viewData('summary')['total'])->toBe(30)
        ->and($report->viewData('users')->total())->toBe(30);
    $report->assertDontSee('ARCHIVED');

    $tables = $this->actingAs($viewer)->get(route('red-cross-units.my-unit-tables'))->assertOk();
    expect($tables->viewData('members')->total())->toBe(30);
});
