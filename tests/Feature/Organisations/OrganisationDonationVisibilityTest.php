<?php

/**
 * Organisation donations are hidden from division-level users, the same
 * way as organisation payments (Decisions.md 2026-10-11): the donations
 * list, the donation page and delete (403), the person page, the donation
 * reports (summaries, CSV, drill-down, in-kind list) and the dashboard's
 * Donations card. Branch users still see them.
 */

use App\Models\Donation;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Cache::flush();

    DB::table('branches')->insert(['id' => 848, 'name' => 'FCT', 'code' => 'FCT', 'is_active' => true]);
    DB::table('divisions')->insert(['id' => 3069, 'name' => 'Abuja Municipal', 'branch_id' => 848, 'is_active' => true]);

    $permissions = ['manage-admin-panel', 'view_donations', 'remove_donations', 'view_reports', 'view_user'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    foreach (['branch_db_administrator', 'division_db_assistant_finance'] as $role) {
        Role::findOrCreate($role, 'web')->syncPermissions($permissions);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branchUser = User::factory()->create(['branch_id' => 848, 'division_id' => 3069]);
    $this->branchUser->assignRole('branch_db_administrator');
    $this->divisionUser = User::factory()->create(['branch_id' => 848, 'division_id' => 3069]);
    $this->divisionUser->assignRole('division_db_assistant_finance');

    $this->contact = User::factory()->create(['branch_id' => 848, 'division_id' => 3069]);
    $organisation = Organisation::create(['name' => 'Acme Sponsors', 'branch_id' => 848, 'division_id' => 3069]);
    $date = now()->toDateString();

    $this->personal = Donation::factory()->approved()->create([
        'user_id' => $this->contact->id, 'branch_id' => 848, 'division_id' => 3069, 'date_donation' => $date, 'amount' => 7000,
    ]);
    $this->organisational = Donation::factory()->approved()->create([
        'user_id' => $this->contact->id, 'organisation_id' => $organisation->id, 'branch_id' => 848, 'division_id' => 3069,
        'date_donation' => $date, 'amount' => 40000,
    ]);
    $this->orgInKind = Donation::factory()->approved()->create([
        'user_id' => $this->contact->id, 'organisation_id' => $organisation->id, 'branch_id' => 848, 'division_id' => 3069,
        'date_donation' => $date, 'in_kind_donation' => true, 'donation_item' => 'Blankets', 'amount' => null,
    ]);
});

test('the donations list leaves organisation donations out for division users only', function () {
    $this->actingAs($this->divisionUser)->get(route('donations.index'))
        ->assertOk()
        ->assertSee(route('donations.show', $this->personal), false)
        ->assertDontSee(route('donations.show', $this->organisational), false)
        ->assertDontSee('<option value="organisation"', false);

    $this->actingAs($this->branchUser)->get(route('donations.index'))
        ->assertOk()
        ->assertSee(route('donations.show', $this->organisational), false);
});

test('an organisation donation\'s page and delete are 403 for division users', function () {
    $this->actingAs($this->divisionUser)->get(route('donations.show', $this->organisational))->assertForbidden();
    $this->actingAs($this->divisionUser)->delete(route('donations.destroy', $this->organisational))->assertForbidden();
    $this->actingAs($this->divisionUser)->get(route('donations.show', $this->personal))->assertOk();

    $this->actingAs($this->branchUser)->get(route('donations.show', $this->organisational))->assertOk();

    expect($this->organisational->fresh()->is_deleted)->toBeFalse();
});

test('the person page leaves the contact\'s organisation donations out for division users', function () {
    $this->actingAs($this->divisionUser)->get(route('users.show', $this->contact))
        ->assertOk()
        ->assertDontSee('40,000');

    $this->actingAs($this->branchUser)->get(route('users.show', $this->contact))
        ->assertOk()
        ->assertSee('40,000');
});

test('donation report summaries and their CSV leave organisation donations out for division users', function () {
    $year = now()->year;

    $division = $this->actingAs($this->divisionUser)->get(route('reports.donations.branch', ['branch' => 848, 'year' => $year]))
        ->assertOk()
        ->assertSee('Organisation donations are not shown at division level.');
    $row = $division->viewData('divisionQuarterlySummaries')->firstWhere('division_id', 3069);
    expect((float) $row->total_cash)->toBe(7000.0)
        ->and((int) $row->total_in_kind)->toBe(0);

    $national = $this->actingAs($this->divisionUser)->get(route('reports.donations.national', ['year' => $year]))->assertOk();
    expect((float) $national->viewData('branchQuarterlySummaries')->firstWhere('branch_id', 848)->total_cash)->toBe(7000.0);

    $csv = $this->actingAs($this->divisionUser)->get(route('reports.donations.national', ['year' => $year, 'export' => 'csv']))->streamedContent();
    expect($csv)->not->toContain('47000')->and($csv)->not->toContain('40000');

    $branch = $this->actingAs($this->branchUser)->get(route('reports.donations.branch', ['branch' => 848, 'year' => $year]))
        ->assertOk()
        ->assertDontSee('Organisation donations are not shown at division level.');
    expect((float) $branch->viewData('divisionQuarterlySummaries')->firstWhere('division_id', 3069)->total_cash)->toBe(47000.0);
});

test('the donation drill-down and in-kind list leave organisation donations out for division users', function () {
    $params = ['branch_id' => 3069, 'level' => 'division', 'year' => now()->year, 'quarter' => now()->quarter];

    $division = $this->actingAs($this->divisionUser)->get(route('reports.donations.breakdown', $params))->assertOk();
    expect($division->viewData('donations')->pluck('id')->all())->toBe([$this->personal->id]);

    $branch = $this->actingAs($this->branchUser)->get(route('reports.donations.breakdown', $params))->assertOk();
    expect($branch->viewData('donations'))->toHaveCount(2);

    $this->actingAs($this->divisionUser)->get(route('reports.donations.in-kind', ['branch' => 848]))
        ->assertOk()
        ->assertViewHas('totalCount', 0);
    $this->actingAs($this->branchUser)->get(route('reports.donations.in-kind', ['branch' => 848]))
        ->assertOk()
        ->assertViewHas('totalCount', 1);
});

test('the dashboard Donations card leaves organisation donations out for division users', function () {
    $params = ['branch_id' => 848, 'extended' => 1];

    $division = $this->actingAs($this->divisionUser)->get(route('reports.dashboard', $params))
        ->assertOk()
        ->assertDontSee('₦40,000');
    expect($division->viewData('dashboardData')['cashLast12'])->toBe(7000.0)
        ->and($division->viewData('dashboardData')['donationsCashOrganisation'])->toBeNull();

    // Same cached build, full figures for a branch user.
    $branch = $this->actingAs($this->branchUser)->get(route('reports.dashboard', $params))
        ->assertOk()
        ->assertSee('₦40,000');
    expect($branch->viewData('dashboardData')['cashLast12'])->toBe(47000.0);
});
