<?php

/**
 * Archive Tool (/dormant-users): branch / division / unit filters per
 * access level (2026-10-17), area limits that URL values cannot widen,
 * and bulkArchive() archiving only users the list itself would show.
 *
 * Fixture: Branch A (Division A1: units U1, U2; Division A2: unit U3)
 * and Branch B (Division B1: unit U4), one dormant user in each unit.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipPayment;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Carbon::setTestNow('2026-10-17 10:00:00');

    foreach (['manage-admin-panel', 'use_archive_tool'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    foreach (['national_db_administrator', 'branch_db_administrator'] as $role) {
        Role::findOrCreate($role, 'web')->syncPermissions(['manage-admin-panel', 'use_archive_tool']);
    }
    // No division role has the Archive Tool today; granted here only to
    // cover the division-level code path.
    Role::findOrCreate('division_db_assistant_operations', 'web')
        ->syncPermissions(['manage-admin-panel', 'use_archive_tool']);
    Role::findOrCreate('branch_db_assistant', 'web')->syncPermissions(['manage-admin-panel']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branchA = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->branchB = Branch::create(['name' => 'Bravo Branch', 'code' => 'BRA']);
    $this->divA1 = Division::create(['name' => 'Alpha One', 'branch_id' => $this->branchA->id]);
    $this->divA2 = Division::create(['name' => 'Alpha Two', 'branch_id' => $this->branchA->id]);
    $this->divB1 = Division::create(['name' => 'Bravo One', 'branch_id' => $this->branchB->id]);
    $this->u1 = RedCrossUnit::create(['name' => 'Unit One', 'division_id' => $this->divA1->id]);
    $this->u2 = RedCrossUnit::create(['name' => 'Unit Two', 'division_id' => $this->divA1->id]);
    $this->u3 = RedCrossUnit::create(['name' => 'Unit Three', 'division_id' => $this->divA2->id]);
    $this->u4 = RedCrossUnit::create(['name' => 'Unit Four', 'division_id' => $this->divB1->id]);

    $this->inU1 = dormantIn($this->u1);
    $this->inU2 = dormantIn($this->u2);
    $this->inU3 = dormantIn($this->u3);
    $this->inU4 = dormantIn($this->u4);
});

afterEach(function () {
    Carbon::setTestNow();
});

/** A user the Archive Tool lists by default: dormant, last active 5 years ago. */
function dormantIn(RedCrossUnit $unit, array $overrides = []): User
{
    $division = Division::find($unit->division_id);

    return User::factory()->create([
        'branch_id' => $division->branch_id,
        'division_id' => $division->id,
        'red_cross_unit_id' => $unit->id,
        'lifecycle_status' => 'dormant',
        'last_activity_at' => now()->subYears(5),
        ...$overrides,
    ]);
}

function archiveAdmin(string $role, ?Branch $branch = null, ?Division $division = null): User
{
    $admin = User::factory()->create([
        'branch_id' => $branch?->id ?? $division?->branch_id,
        'division_id' => $division?->id,
    ]);
    $admin->assignRole($role);

    return $admin;
}

/** @return list<int> ids of the users listed, sorted */
function listedIds(TestResponse $response): array
{
    $response->assertOk();

    return collect($response->viewData('users')->items())->pluck('id')->sort()->values()->all();
}

function idsOf(User ...$users): array
{
    return collect($users)->pluck('id')->sort()->values()->all();
}

/*
|--------------------------------------------------------------------------
| National level: any branch, then division, then unit
|--------------------------------------------------------------------------
*/

test('national: lists every branch without filters', function () {
    $this->actingAs(archiveAdmin('national_db_administrator'));

    expect(listedIds($this->get(route('dormant-users.index'))))
        ->toBe(idsOf($this->inU1, $this->inU2, $this->inU3, $this->inU4));
});

test('national: filters by branch, division and unit', function () {
    $this->actingAs(archiveAdmin('national_db_administrator'));

    expect(listedIds($this->get(route('dormant-users.index', ['branch_id' => $this->branchA->id]))))
        ->toBe(idsOf($this->inU1, $this->inU2, $this->inU3))
        ->and(listedIds($this->get(route('dormant-users.index', ['branch_id' => $this->branchA->id, 'division_id' => $this->divA1->id]))))
        ->toBe(idsOf($this->inU1, $this->inU2))
        ->and(listedIds($this->get(route('dormant-users.index', [
            'branch_id' => $this->branchA->id, 'division_id' => $this->divA1->id, 'red_cross_unit_id' => $this->u1->id,
        ]))))->toBe(idsOf($this->inU1));
});

test('national: the dropdowns follow the chosen branch and division', function () {
    $this->actingAs(archiveAdmin('national_db_administrator'));

    $response = $this->get(route('dormant-users.index', ['branch_id' => $this->branchA->id, 'division_id' => $this->divA1->id]));

    expect($response->viewData('branches')->pluck('id')->all())->toEqualCanonicalizing([$this->branchA->id, $this->branchB->id])
        ->and($response->viewData('divisions')->pluck('id')->all())->toEqualCanonicalizing([$this->divA1->id, $this->divA2->id])
        ->and($response->viewData('redCrossUnits')->pluck('id')->all())->toEqualCanonicalizing([$this->u1->id, $this->u2->id]);
    $response->assertSee('data-location-cascade', false);
});

/*
|--------------------------------------------------------------------------
| Branch level: branch fixed, division and unit within it
|--------------------------------------------------------------------------
*/

test('branch: sees only their own branch', function () {
    $this->actingAs(archiveAdmin('branch_db_administrator', branch: $this->branchA));

    expect(listedIds($this->get(route('dormant-users.index'))))
        ->toBe(idsOf($this->inU1, $this->inU2, $this->inU3));
});

test('branch: filters by division and unit within the branch', function () {
    $this->actingAs(archiveAdmin('branch_db_administrator', branch: $this->branchA));

    expect(listedIds($this->get(route('dormant-users.index', ['division_id' => $this->divA2->id]))))
        ->toBe(idsOf($this->inU3))
        ->and(listedIds($this->get(route('dormant-users.index', ['division_id' => $this->divA1->id, 'red_cross_unit_id' => $this->u2->id]))))
        ->toBe(idsOf($this->inU2));
});

test('branch: another branch, division or unit in the URL shows nothing outside the branch', function () {
    $this->actingAs(archiveAdmin('branch_db_administrator', branch: $this->branchA));

    // branch_id is ignored for branch staff: still their own branch.
    expect(listedIds($this->get(route('dormant-users.index', ['branch_id' => $this->branchB->id]))))
        ->toBe(idsOf($this->inU1, $this->inU2, $this->inU3))
        ->and(listedIds($this->get(route('dormant-users.index', ['division_id' => $this->divB1->id]))))->toBe([])
        ->and(listedIds($this->get(route('dormant-users.index', ['red_cross_unit_id' => $this->u4->id]))))->toBe([]);
});

test('branch: the dropdowns offer only their own branch and its divisions and units', function () {
    $this->actingAs(archiveAdmin('branch_db_administrator', branch: $this->branchA));

    $own = $this->get(route('dormant-users.index', ['division_id' => $this->divA1->id]));
    expect($own->viewData('branches')->pluck('id')->all())->toBe([$this->branchA->id])
        ->and($own->viewData('divisions')->pluck('id')->all())->toEqualCanonicalizing([$this->divA1->id, $this->divA2->id])
        ->and($own->viewData('redCrossUnits')->pluck('id')->all())->toEqualCanonicalizing([$this->u1->id, $this->u2->id]);

    $other = $this->get(route('dormant-users.index', ['branch_id' => $this->branchB->id, 'division_id' => $this->divB1->id]));
    expect($other->viewData('branches')->pluck('id')->all())->toBe([$this->branchA->id])
        ->and($other->viewData('redCrossUnits'))->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| Division level: branch and division fixed, unit within it
|--------------------------------------------------------------------------
*/

test('division: sees only their own division and can filter by unit', function () {
    $this->actingAs(archiveAdmin('division_db_assistant_operations', division: $this->divA1));

    expect(listedIds($this->get(route('dormant-users.index'))))->toBe(idsOf($this->inU1, $this->inU2))
        ->and(listedIds($this->get(route('dormant-users.index', ['red_cross_unit_id' => $this->u2->id]))))->toBe(idsOf($this->inU2));
});

test('division: another branch, division or unit in the URL shows nothing outside the division', function () {
    $this->actingAs(archiveAdmin('division_db_assistant_operations', division: $this->divA1));

    expect(listedIds($this->get(route('dormant-users.index', ['branch_id' => $this->branchB->id, 'division_id' => $this->divB1->id]))))
        ->toBe(idsOf($this->inU1, $this->inU2))
        ->and(listedIds($this->get(route('dormant-users.index', ['division_id' => $this->divA2->id]))))
        ->toBe(idsOf($this->inU1, $this->inU2))
        ->and(listedIds($this->get(route('dormant-users.index', ['red_cross_unit_id' => $this->u3->id]))))->toBe([])
        ->and(listedIds($this->get(route('dormant-users.index', ['red_cross_unit_id' => $this->u4->id]))))->toBe([]);
});

test('division: the unit dropdown offers only their own division\'s units', function () {
    $this->actingAs(archiveAdmin('division_db_assistant_operations', division: $this->divA1));

    $response = $this->get(route('dormant-users.index', ['division_id' => $this->divB1->id]));

    expect($response->viewData('branches')->pluck('id')->all())->toBe([$this->branchA->id])
        ->and($response->viewData('divisions')->pluck('id')->all())->toBe([$this->divA1->id])
        ->and($response->viewData('redCrossUnits')->pluck('id')->all())->toEqualCanonicalizing([$this->u1->id, $this->u2->id]);
});

/*
|--------------------------------------------------------------------------
| Access and defaults
|--------------------------------------------------------------------------
*/

test('the Archive Tool needs use_archive_tool', function () {
    $this->actingAs(archiveAdmin('branch_db_assistant', branch: $this->branchA))
        ->get(route('dormant-users.index'))
        ->assertForbidden();
});

test('the inactivity threshold defaults to 4 years, also for an unknown value', function () {
    $this->actingAs(archiveAdmin('national_db_administrator'));
    $threeYears = dormantIn($this->u1, ['last_activity_at' => now()->subYears(3)]);

    expect($this->get(route('dormant-users.index'))->viewData('years'))->toBe(4)
        ->and($this->get(route('dormant-users.index', ['years' => 99]))->viewData('years'))->toBe(4)
        ->and(listedIds($this->get(route('dormant-users.index', ['years' => 99]))))->not->toContain($threeYears->id)
        ->and(listedIds($this->get(route('dormant-users.index', ['years' => 2]))))->toContain($threeYears->id);
});

/*
|--------------------------------------------------------------------------
| bulkArchive(): only users the list would show
|--------------------------------------------------------------------------
*/

const ARCHIVE_SKIP_NOTE = 'because they do not meet the Archive Tool conditions (for example recent activity or a current membership payment).';

test('an active user with a current payment is refused', function () {
    $this->actingAs(archiveAdmin('national_db_administrator'));
    $active = dormantIn($this->u1, ['lifecycle_status' => 'active', 'last_activity_at' => now()->subMonth()]);
    MembershipPayment::factory()->approved()->create(['user_id' => $active->id, 'payment_date' => now()->subMonth()->toDateString()]);

    $this->post(route('dormant-users.archive'), ['user_ids' => [$active->id]])
        ->assertSessionHas('error', 'No users were archived. You may not have permission to archive the selected users. '
            .'1 user was skipped '.ARCHIVE_SKIP_NOTE);

    expect($active->fresh()->lifecycle_status)->toBe('active')
        ->and($active->fresh()->archived_at)->toBeNull();
});

test('a dormant user with a current payment or recent activity is skipped, the rest archived', function () {
    $this->actingAs(archiveAdmin('national_db_administrator'));
    $paid = dormantIn($this->u1);
    MembershipPayment::factory()->approved()->create(['user_id' => $paid->id, 'payment_date' => now()->subMonth()->toDateString()]);
    $recent = dormantIn($this->u1, ['last_activity_at' => now()->subYear()]);

    $this->post(route('dormant-users.archive'), ['user_ids' => [$this->inU1->id, $paid->id, $recent->id]])
        ->assertSessionHas('success', '1 user has been archived. This can be reversed individually from their profile. '
            .'2 users were skipped '.ARCHIVE_SKIP_NOTE);

    expect($this->inU1->fresh()->lifecycle_status)->toBe('archived')
        ->and($paid->fresh()->lifecycle_status)->toBe('dormant')
        ->and($recent->fresh()->lifecycle_status)->toBe('dormant');
});

test('a current payment awaiting approval protects the user from the list and the archive', function () {
    $this->actingAs(archiveAdmin('national_db_administrator'));
    $pendingPayer = dormantIn($this->u1);
    $payment = MembershipPayment::factory()->create(['user_id' => $pendingPayer->id, 'payment_date' => now()->subMonth()->toDateString()]);
    expect($payment->approval_status)->toBe(MembershipPayment::PENDING);

    expect(listedIds($this->get(route('dormant-users.index'))))->not->toContain($pendingPayer->id);

    $this->post(route('dormant-users.archive'), ['user_ids' => [$pendingPayer->id]])
        ->assertSessionHas('error', 'No users were archived. You may not have permission to archive the selected users. '
            .'1 user was skipped '.ARCHIVE_SKIP_NOTE);
    expect($pendingPayer->fresh()->lifecycle_status)->toBe('dormant');
});

test('a rejected payment does not protect the user', function () {
    $this->actingAs(archiveAdmin('national_db_administrator'));
    $rejected = dormantIn($this->u1);
    MembershipPayment::factory()->create([
        'user_id' => $rejected->id,
        'payment_date' => now()->subMonth()->toDateString(),
        'approval_status' => MembershipPayment::REJECTED,
    ]);

    expect(listedIds($this->get(route('dormant-users.index'))))->toContain($rejected->id);

    $this->post(route('dormant-users.archive'), ['user_ids' => [$rejected->id]])
        ->assertSessionHas('success');
    expect($rejected->fresh()->lifecycle_status)->toBe('archived');
});

test('the posted list type decides which lifecycle qualifies', function () {
    $this->actingAs(archiveAdmin('national_db_administrator'));
    $pending = dormantIn($this->u1, ['lifecycle_status' => 'pending_engagement', 'last_activity_at' => null, 'created_at' => now()->subYears(5)]);

    // From the Dormant list a pending user does not qualify...
    $this->post(route('dormant-users.archive'), ['type' => 'dormant', 'user_ids' => [$pending->id]])
        ->assertSessionHas('error');
    expect($pending->fresh()->lifecycle_status)->toBe('pending_engagement');

    // ...from the Pending engagement list they do.
    $this->post(route('dormant-users.archive'), ['type' => 'pending', 'years' => 4, 'user_ids' => [$pending->id]])
        ->assertRedirect(route('dormant-users.index', ['type' => 'pending', 'years' => 4]))
        ->assertSessionHas('success');
    expect($pending->fresh()->lifecycle_status)->toBe('archived');
});

test('a branch admin cannot archive users of another branch by posting their ids', function () {
    $this->actingAs(archiveAdmin('branch_db_administrator', branch: $this->branchA));

    $this->post(route('dormant-users.archive'), ['user_ids' => [$this->inU1->id, $this->inU4->id]])
        ->assertSessionHas('success', '1 user has been archived. This can be reversed individually from their profile. '
            .'1 user was skipped '.ARCHIVE_SKIP_NOTE);

    expect($this->inU1->fresh()->lifecycle_status)->toBe('archived')
        ->and($this->inU4->fresh()->lifecycle_status)->toBe('dormant');
});

test('a division admin cannot archive users of another division', function () {
    $this->actingAs(archiveAdmin('division_db_assistant_operations', division: $this->divA1));

    $this->post(route('dormant-users.archive'), ['user_ids' => [$this->inU3->id]])
        ->assertSessionHas('error');

    expect($this->inU3->fresh()->lifecycle_status)->toBe('dormant');
});

test('the list\'s location filters are applied to the archive too, and kept on the redirect', function () {
    $this->actingAs(archiveAdmin('national_db_administrator'));
    $filters = ['type' => 'dormant', 'years' => 4, 'branch_id' => $this->branchA->id, 'division_id' => $this->divA1->id];

    $this->post(route('dormant-users.archive'), [...$filters, 'user_ids' => [$this->inU1->id, $this->inU3->id]])
        ->assertRedirect(route('dormant-users.index', $filters))
        ->assertSessionHas('success');

    expect($this->inU1->fresh()->lifecycle_status)->toBe('archived')
        ->and($this->inU3->fresh()->lifecycle_status)->toBe('dormant');
});

test('the archive form posts the list\'s filters back', function () {
    $this->actingAs(archiveAdmin('national_db_administrator'));

    $this->get(route('dormant-users.index', ['years' => 3, 'branch_id' => $this->branchA->id, 'division_id' => $this->divA1->id]))
        ->assertSee('<input type="hidden" name="years" value="3">', false)
        ->assertSee('<input type="hidden" name="type" value="dormant">', false)
        ->assertSee('<input type="hidden" name="branch_id" value="'.$this->branchA->id.'">', false)
        ->assertSee('<input type="hidden" name="division_id" value="'.$this->divA1->id.'">', false);
});
