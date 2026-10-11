<?php

/**
 * Organisation payments now carry the organisation's division, but are
 * hidden from division-level users (Decisions.md 2026-10-11): the payments
 * list and its count, the payment page (403), the financial report, its
 * drill-downs and CSV, and the dashboard revenue card. Branch and national
 * users still see them.
 */

use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\User;
use Database\Factories\MembershipFeeFactory;
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

    $permissions = ['manage-admin-panel', 'view_payments', 'remove_payments', 'view_reports', 'view_user'];
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

    $payer = User::factory()->create(['first_name' => 'Personal', 'last_name' => 'Payer', 'branch_id' => 848, 'division_id' => 3069]);
    $organisation = Organisation::create(['name' => 'Acme Sponsors', 'branch_id' => 848, 'division_id' => 3069]);
    $date = now()->toDateString();

    $this->personal = MembershipPayment::factory()->approved()->create([
        'user_id' => $payer->id, 'branch_id' => 848, 'division_id' => 3069, 'payment_date' => $date,
        'membership_fee_id' => MembershipFeeFactory::new()->create(['amount' => 10000])->id,
    ]);
    $this->organisational = MembershipPayment::factory()->approved()->create([
        'user_id' => $payer->id, 'organisation_id' => $organisation->id, 'branch_id' => 848, 'division_id' => 3069, 'payment_date' => $date,
        'membership_fee_id' => MembershipFeeFactory::new()->create(['amount' => 20000, 'for_organizations' => true])->id,
    ]);
});

test('the payments list leaves organisation payments out for division users only', function () {
    $this->actingAs($this->divisionUser)->get(route('membership-payments.index'))
        ->assertOk()
        ->assertSee(route('membership-payments.show', $this->personal), false)
        ->assertDontSee(route('membership-payments.show', $this->organisational), false)
        ->assertDontSee('<option value="organisation"', false);

    // Asking for organisation payments gives none.
    $this->actingAs($this->divisionUser)->get(route('membership-payments.index', ['organisation_scope' => 'organisation']))
        ->assertOk()
        ->assertDontSee(route('membership-payments.show', $this->organisational), false);

    $this->actingAs($this->branchUser)->get(route('membership-payments.index'))
        ->assertOk()
        ->assertSee(route('membership-payments.show', $this->organisational), false);
});

test('an organisation payment\'s page and delete are 403 for division users', function () {
    $this->actingAs($this->divisionUser)->get(route('membership-payments.show', $this->organisational))->assertForbidden();
    $this->actingAs($this->divisionUser)->delete(route('membership-payments.destroy', $this->organisational))->assertForbidden();
    $this->actingAs($this->divisionUser)->get(route('membership-payments.show', $this->personal))->assertOk();

    $this->actingAs($this->branchUser)->get(route('membership-payments.show', $this->organisational))->assertOk();

    expect($this->organisational->fresh()->is_deleted)->toBeFalse();
});

test('financial drill-downs leave organisation payments out for division users', function () {
    $quarter = now()->year.'-Q'.now()->quarter;
    $params = ['branch_id' => 3069, 'level' => 'division', 'quarter' => $quarter];

    $this->actingAs($this->divisionUser)->get(route('reports.financial.breakdown', $params + ['category' => 'organisation']))
        ->assertForbidden();

    $this->actingAs($this->divisionUser)->get(route('reports.financial.breakdown', $params))
        ->assertOk()
        ->assertViewHas('totalCount', 1);

    $this->actingAs($this->branchUser)->get(route('reports.financial.breakdown', $params))
        ->assertOk()
        ->assertViewHas('totalCount', 2);

    $feeParams = ['fee_id' => $this->organisational->membership_fee_id, 'quarter' => $quarter, 'scope' => 848];
    $this->actingAs($this->divisionUser)->get(route('reports.financial.breakdown-by-fee', $feeParams + ['category' => 'organisation']))
        ->assertForbidden();
    $this->actingAs($this->divisionUser)->get(route('reports.financial.breakdown-by-fee', $feeParams))
        ->assertOk()
        ->assertDontSee('Acme Sponsors');
});

test('the financial report and its CSV show no organisation amounts to division users, with a note', function () {
    $params = ['tab' => 'payments', 'scope' => 848, 'year' => now()->year];

    $division = $this->actingAs($this->divisionUser)->get(route('reports.financial.index', $params))
        ->assertOk()
        ->assertSee('Organisation payments are not shown at division level.');
    $row = collect($division->viewData('paymentsData'))->firstWhere('id', 3069);
    $quarter = 'q'.now()->quarter;
    expect($row["{$quarter}_org"])->toBe(0.0)
        ->and($row['year_total'])->toBe(10000.0);

    $branch = $this->actingAs($this->branchUser)->get(route('reports.financial.index', $params))
        ->assertOk()
        ->assertDontSee('Organisation payments are not shown at division level.');
    expect(collect($branch->viewData('paymentsData'))->firstWhere('id', 3069)['year_total'])->toBe(30000.0);

    $csv = $this->actingAs($this->divisionUser)->get(route('reports.financial.index', $params + ['export' => 'csv']))->streamedContent();
    expect($csv)->not->toContain('20000')->and($csv)->not->toContain('30000');

    $breakdown = $this->actingAs($this->divisionUser)
        ->get(route('reports.financial.index', ['tab' => 'breakdown', 'scope' => 848, 'year' => now()->year]))
        ->assertOk();
    expect($breakdown->viewData('organisationFeeBreakdown'))->toHaveCount(0)
        ->and($breakdown->viewData('feeBreakdownGrandTotal'))->toBe(10000.0);
});

test('the dashboard revenue card leaves organisation payments out for division users', function () {
    $params = ['branch_id' => 848, 'extended' => 1];

    $division = $this->actingAs($this->divisionUser)->get(route('reports.dashboard', $params))
        ->assertOk()
        ->assertDontSee('₦20,000');
    expect($division->viewData('dashboardData')['revenueLast12Months'])->toBe(10000.0);

    // Same cached build, full figures for a branch user.
    $branch = $this->actingAs($this->branchUser)->get(route('reports.dashboard', $params))
        ->assertOk()
        ->assertSee('₦20,000');
    expect($branch->viewData('dashboardData')['revenueLast12Months'])->toBe(30000.0);
});
