<?php

/**
 * Income Distribution report, Tab 2 (manual payments, collected by the
 * branches), branch level: figures by accounting_date, one row per branch,
 * the go-live notes (before / transition / invalid .env value), and who
 * may see it (national: every branch; branch roles: own branch only).
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\User;
use App\Services\FeeDistributionSettings;
use App\Services\IncomeDistributionCalculator;
use App\Services\Reports\IncomeDistributionStatsService;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(\Carbon\Carbon::parse('2026-10-11 10:00:00', 'Africa/Lagos'));
    config(['income.manual_go_live_date' => null]);

    foreach (['view_reports', 'view_income_distribution', 'view_income_distribution_branch'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['view_reports', 'view_income_distribution']);
    Role::findOrCreate('branch_db_administrator', 'web')->syncPermissions(['view_reports', 'view_income_distribution_branch']);
    Role::findOrCreate('division_db_administrator', 'web')->syncPermissions(['view_reports']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $this->kano = Branch::create(['name' => 'Kano', 'code' => 'KAN']);
    $this->abia = Branch::create(['name' => 'Abia', 'code' => 'ABI']);
    $this->kanoDivision = Division::create(['name' => 'Kano Municipal', 'branch_id' => $this->kano->id]);
    $this->abiaDivision = Division::create(['name' => 'Aba North', 'branch_id' => $this->abia->id]);

    $this->branchAdmin = User::factory()->create(['branch_id' => $this->kano->id, 'division_id' => $this->kanoDivision->id]);
    $this->branchAdmin->assignRole('branch_db_administrator');
});

/**
 * An approved manual payment with its split worked out by the real
 * calculator (default percentages), counted on $accountingDate.
 */
function manualIncomePayment(int $amountKobo, ?int $branchId, ?int $divisionId, string $accountingDate, array $overrides = [], ?array $percentages = null): MembershipPayment
{
    return MembershipPayment::factory()->approved()->create(array_merge([
        'payment_channel' => 'manual',
        'branch_id' => $branchId,
        'division_id' => $divisionId,
        'payment_date' => $accountingDate,
        'accounting_date' => $accountingDate,
        'amount_paid_kobo' => $amountKobo,
        'paystack_fee_kobo' => 0,
        'net_kobo' => $amountKobo,
    ], IncomeDistributionCalculator::calculate($amountKobo, $branchId, $divisionId, $percentages ?? FeeDistributionSettings::DEFAULTS), $overrides));
}

function viewManualTab(array $query = [], ?User $user = null)
{
    return test()->actingAs($user ?? test()->admin)
        ->get(route('reports.income-distribution.index', ['tab' => 'manual'] + $query));
}

test('one row per branch by accounting date; only approved, not deleted manual payments; totals match the headline', function () {
    manualIncomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10');
    manualIncomePayment(500_001, $this->kano->id, $this->kanoDivision->id, '2026-09-30');
    manualIncomePayment(400_000, $this->abia->id, $this->abiaDivision->id, '2026-08-15');
    $organisation = Organisation::create(['name' => 'Sponsor Org', 'branch_id' => $this->abia->id]);
    manualIncomePayment(2_000_000, $this->abia->id, null, '2026-08-20', ['organisation_id' => $organisation->id]);

    // Not counted: other quarters, Paystack, pending, deleted.
    manualIncomePayment(9_000_000, $this->kano->id, $this->kanoDivision->id, '2026-06-30');
    manualIncomePayment(9_000_000, $this->kano->id, $this->kanoDivision->id, '2026-10-01');
    manualIncomePayment(9_000_000, $this->kano->id, $this->kanoDivision->id, '2026-08-01', ['payment_channel' => 'paystack', 'gateway_reference' => 'PSK-1', 'paid_at' => '2026-08-01 10:00:00']);
    manualIncomePayment(9_000_000, $this->kano->id, $this->kanoDivision->id, '2026-08-01', ['approval_status' => 'pending']);
    manualIncomePayment(9_000_000, $this->kano->id, $this->kanoDivision->id, '2026-08-01', ['is_deleted' => true]);

    $response = viewManualTab(['quarter' => '2026-Q3'])->assertOk();

    $response->assertViewHas('activeTab', 'manual');
    $headline = $response->viewData('headline');
    expect($headline['count'])->toBe(4)
        ->and($headline['net'])->toBe(3_900_001)
        ->and($headline['hq'] + $headline['branch'] + $headline['division'])->toBe(3_900_001);

    $rows = $response->viewData('rows');
    expect($rows->pluck('label')->all())->toBe(['Abia', 'Kano'])
        ->and($rows[0]['net'])->toBe(2_400_000)
        ->and($rows[0]['organisation_net'])->toBe(2_000_000)
        ->and($rows[1]['net'])->toBe(1_500_001)
        ->and($rows[1]['count'])->toBe(2);

    expect($response->viewData('integrityProblems'))->toBe([]);
    $response->assertSee('₦39,000.01')->assertSee('Collected by branches')
        ->assertSee('A payment counts in the quarter it was approved.');
});

test('legacy rows with an empty channel count; the default quarter is the latest finished quarter with manual income', function () {
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2025-11-05', ['payment_channel' => null]);
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-10-02');

    $response = viewManualTab()->assertOk();

    expect($response->viewData('selectedQuarter'))->toBe('2025-Q4')
        ->and($response->viewData('headline')['count'])->toBe(1)
        ->and(array_key_last($response->viewData('quarterOptions')))->toBe('2025-Q4')
        ->and(array_key_first($response->viewData('quarterOptions')))->toBe('2026-Q4');
});

test('the tabs link to each other and keep the quarter', function () {
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-08-01');

    viewManualTab(['quarter' => '2026-Q3'])
        ->assertSee(route('reports.income-distribution.index', ['tab' => 'online', 'quarter' => '2026-Q3']))
        ->assertSee('Manual payments (branches)');
});

test('with no go-live date every quarter shows the before-go-live note', function () {
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-08-01');

    viewManualTab(['quarter' => '2026-Q3'])
        ->assertViewHas('goLivePhase', 'before')
        ->assertSee(IncomeDistributionStatsService::BEFORE_GO_LIVE_NOTE)
        ->assertDontSee('data-testid="transition-note"', false)
        ->assertDontSee('data-testid="invalid-go-live-note"', false);
});

test('a quarter ending on the go-live date is before go-live; one starting on it is live with no note', function () {
    config(['income.manual_go_live_date' => '2026-10-01']);
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-08-01');
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-10-05');

    viewManualTab(['quarter' => '2026-Q3'])
        ->assertViewHas('goLivePhase', 'before')
        ->assertSee(IncomeDistributionStatsService::BEFORE_GO_LIVE_NOTE);

    viewManualTab(['quarter' => '2026-Q4'])
        ->assertViewHas('goLivePhase', 'live')
        ->assertDontSee(IncomeDistributionStatsService::BEFORE_GO_LIVE_NOTE)
        ->assertDontSee('Transition quarter');
});

test('the transition quarter shows its note and the parts before and from go-live', function () {
    config(['income.manual_go_live_date' => '2026-08-15']);
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10');
    manualIncomePayment(200_000, $this->abia->id, $this->abiaDivision->id, '2026-08-14');
    manualIncomePayment(400_000, $this->kano->id, $this->kanoDivision->id, '2026-08-15');

    $response = viewManualTab(['quarter' => '2026-Q3'])->assertOk();

    expect($response->viewData('goLivePhase'))->toBe('transition')
        ->and($response->viewData('transitionParts')['before']['net'])->toBe(300_000)
        ->and($response->viewData('transitionParts')['before']['count'])->toBe(2)
        ->and($response->viewData('transitionParts')['from']['net'])->toBe(400_000)
        ->and($response->viewData('transitionParts')['from']['count'])->toBe(1)
        ->and($response->viewData('integrityProblems'))->toBe([]);

    $response
        ->assertSee('Transition quarter: payments before 15 August 2026 are estimates based on fee prices; payments from 15 August 2026 are recorded amounts.')
        ->assertSee('Before 15 August 2026 (estimates):')
        ->assertSee('From 15 August 2026 (recorded):')
        ->assertDontSee(IncomeDistributionStatsService::BEFORE_GO_LIVE_NOTE);
});

test('an invalid MANUAL_GO_LIVE_DATE shows a yellow notice to national admins only', function () {
    config(['income.manual_go_live_date' => '2026-02-30']);
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-08-01');

    viewManualTab(['quarter' => '2026-Q3'])
        ->assertSee('MANUAL_GO_LIVE_DATE in .env is not a valid date (YYYY-MM-DD). All payments are treated as before go-live.')
        ->assertSee(IncomeDistributionStatsService::BEFORE_GO_LIVE_NOTE);

    viewManualTab(['quarter' => '2026-Q3'], $this->branchAdmin)
        ->assertOk()
        ->assertDontSee('MANUAL_GO_LIVE_DATE')
        ->assertSee(IncomeDistributionStatsService::BEFORE_GO_LIVE_NOTE);

    config(['income.manual_go_live_date' => '']);
    viewManualTab(['quarter' => '2026-Q3'])->assertDontSee('MANUAL_GO_LIVE_DATE');
});

test('approved manual payments without shares are flagged to national admins only', function () {
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-08-01');
    MembershipPayment::factory()->approved()->create(['payment_channel' => 'manual', 'branch_id' => $this->kano->id]);

    viewManualTab(['quarter' => '2026-Q3'])
        ->assertSee('1 approved manual payment has no income shares yet');

    viewManualTab(['quarter' => '2026-Q3'], $this->branchAdmin)
        ->assertDontSee('no income shares yet');
});

test('a branch user sees both tabs, with only their own branch on Tab 2', function () {
    manualIncomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-08-01');
    manualIncomePayment(400_000, $this->abia->id, $this->abiaDivision->id, '2026-08-01');
    incomePaymentOnline($this->abia->id);

    $response = viewManualTab(['quarter' => '2026-Q3'], $this->branchAdmin)->assertOk();

    expect($response->viewData('rows')->pluck('label')->all())->toBe(['Kano'])
        ->and($response->viewData('headline')['net'])->toBe(1_000_000)
        ->and($response->viewData('integrityProblems'))->toBe([]);

    $response->assertSee('Manual payments — Kano')
        ->assertSee('Collected by the branch')
        ->assertDontSee('Abia')
        ->assertSee('id="income-distribution-tabs"', false)
        ->assertSee('Online payments (Paystack)')
        ->assertSee(route('reports.income-distribution.divisions', ['channel' => 'manual', 'quarter' => '2026-Q3', 'branch' => $this->kano->id]));
});

/*
|--------------------------------------------------------------------------
| Quarters start at 2016 Q1 (the old system started in 2016)
|--------------------------------------------------------------------------
*/

test('the quarter list starts at 2016 Q1; earlier payments are in no quarter and national admins see a note', function () {
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2010-08-24');
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2015-12-31');
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2014-02-24', ['is_deleted' => true]);
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2016-02-01');

    $response = viewManualTab(['quarter' => '2015-Q4'])->assertOk();

    expect(array_key_last($response->viewData('quarterOptions')))->toBe('2016-Q1')
        ->and($response->viewData('selectedQuarter'))->toBe('2016-Q1')
        ->and($response->viewData('paymentsBeforeFirstQuarter'))->toBe(2);
    $response->assertSee('2 older payments (before 2016, from paper records) are not included.')
        ->assertDontSee('data-testid="before-first-quarter-note" role="alert"', false);

    viewManualTab([], $this->branchAdmin)->assertOk()->assertDontSee('before 2016');

    // Same notice on the division level.
    $this->actingAs($this->admin)
        ->get(route('reports.income-distribution.divisions', ['channel' => 'manual']))
        ->assertSee('2 older payments (before 2016, from paper records) are not included.');
});

test('with no payments before 2016 there is no note; a single one reads in the singular', function () {
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2016-01-01');
    viewManualTab()->assertDontSee('before 2016');

    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2015-06-03');
    viewManualTab()->assertSee('1 older payment (before 2016, from paper records) is not included.');
});

/*
|--------------------------------------------------------------------------
| Division level (?channel=manual)
|--------------------------------------------------------------------------
*/

function viewManualDivisions(array $query = [], ?User $user = null)
{
    return test()->actingAs($user ?? test()->admin)
        ->get(route('reports.income-distribution.divisions', ['channel' => 'manual'] + $query));
}

test('the manual division level lists manual division shares only, and they match Tab 2', function () {
    manualIncomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10');
    manualIncomePayment(500_001, $this->kano->id, $this->kanoDivision->id, '2026-09-30');
    manualIncomePayment(400_000, $this->abia->id, $this->abiaDivision->id, '2026-08-15');
    manualIncomePayment(2_000_000, $this->abia->id, null, '2026-08-20');
    incomePaymentOnline($this->abia->id);

    $response = viewManualDivisions(['quarter' => '2026-Q3'])->assertOk();

    $rows = $response->viewData('rows');
    expect($rows->pluck('division')->all())->toBe(['Aba North', 'Kano Municipal'])
        ->and($rows[1]['count'])->toBe(2)
        ->and($rows[1]['net'])->toBe(1_500_001)
        ->and($response->viewData('integrityProblems'))->toBe([]);

    $tab2 = viewManualTab(['quarter' => '2026-Q3']);
    expect($response->viewData('totals')['division'])->toBe($tab2->viewData('headline')['division']);

    $response->assertSee('2026 Q3 &mdash; Manual payments &mdash; All branches', false)
        ->assertSee('Collected')
        ->assertDontSee('data-testid="export-csv"', false)
        ->assertSee(route('reports.income-distribution.index', ['tab' => 'manual', 'quarter' => '2026-Q3']));

    $kanoOnly = viewManualDivisions(['quarter' => '2026-Q3', 'branch' => $this->kano->id]);
    expect($kanoOnly->viewData('rows')->pluck('division')->all())->toBe(['Kano Municipal']);
    $kanoOnly->assertSee(route('reports.income-distribution.divisions', ['channel' => 'manual', 'quarter' => '2026-Q3']));
});

test('Tab 2 branch names link to the manual division level', function () {
    manualIncomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-08-01');
    manualIncomePayment(100_000, null, null, '2026-08-01');

    viewManualTab(['quarter' => '2026-Q3'])
        ->assertSee(route('reports.income-distribution.divisions', ['channel' => 'manual', 'quarter' => '2026-Q3', 'branch' => $this->kano->id]))
        ->assertSee('No branch (to HQ)');
});

test('the manual division level shows the same go-live notes as Tab 2', function () {
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-08-01');

    viewManualDivisions(['quarter' => '2026-Q3'])->assertSee(IncomeDistributionStatsService::BEFORE_GO_LIVE_NOTE);

    config(['income.manual_go_live_date' => '2026-08-15']);
    viewManualDivisions(['quarter' => '2026-Q3'])
        ->assertSee('Transition quarter: payments before 15 August 2026 are estimates based on fee prices; payments from 15 August 2026 are recorded amounts.')
        ->assertDontSee(IncomeDistributionStatsService::BEFORE_GO_LIVE_NOTE);

    config(['income.manual_go_live_date' => 'soon']);
    viewManualDivisions(['quarter' => '2026-Q3'])->assertSee(IncomeDistributionStatsService::INVALID_GO_LIVE_NOTE);
    viewManualDivisions(['quarter' => '2026-Q3'], $this->branchAdmin)->assertOk()->assertDontSee('MANUAL_GO_LIVE_DATE');

    // Online division level: no manual notes.
    $this->actingAs($this->admin)->get(route('reports.income-distribution.divisions'))
        ->assertDontSee(IncomeDistributionStatsService::BEFORE_GO_LIVE_NOTE)
        ->assertDontSee('MANUAL_GO_LIVE_DATE');
});

test('a branch user sees only their own branch\'s manual divisions; another branch is refused', function () {
    manualIncomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-08-01');
    manualIncomePayment(400_000, $this->abia->id, $this->abiaDivision->id, '2026-08-01');

    // No ?branch= : forced to their own.
    $response = viewManualDivisions(['quarter' => '2026-Q3'], $this->branchAdmin)->assertOk();
    expect($response->viewData('branchFilter'))->toBe($this->kano->id)
        ->and($response->viewData('rows')->pluck('division')->all())->toBe(['Kano Municipal']);
    $response->assertDontSee('Aba North')->assertDontSee('Show all branches');

    viewManualDivisions(['quarter' => '2026-Q3', 'branch' => $this->kano->id], $this->branchAdmin)->assertOk();
    viewManualDivisions(['quarter' => '2026-Q3', 'branch' => $this->abia->id], $this->branchAdmin)->assertForbidden();
});

test('users without either permission, or a branch user with no branch, are refused', function () {
    $divisionAdmin = User::factory()->create(['branch_id' => $this->kano->id, 'division_id' => $this->kanoDivision->id]);
    $divisionAdmin->assignRole('division_db_administrator');
    viewManualTab([], $divisionAdmin)->assertForbidden();

    $noBranch = User::factory()->create(['branch_id' => null, 'division_id' => null]);
    $noBranch->assignRole('branch_db_administrator');
    viewManualTab([], $noBranch)->assertForbidden();

    auth()->logout();
    $this->get(route('reports.income-distribution.index', ['tab' => 'manual']))->assertRedirect(route('login'));
});

test('the dashboard links branch users to the report', function () {
    $this->actingAs($this->branchAdmin)->get(route('reports.dashboard'))
        ->assertOk()
        ->assertSee(route('reports.income-distribution.index'), false);
});

test('the seeder gives view_income_distribution_branch to the branch secretary and branch DB administrator only', function () {
    $this->seed([RolesTableSeeder::class, PermissionsTableSeeder::class]);

    $holders = Role::whereHas('permissions', fn ($q) => $q->where('name', 'view_income_distribution_branch'))
        ->orderBy('name')->pluck('name')->all();

    expect($holders)->toBe(['branch_db_administrator', 'branch_secretary']);
});

/** A Paystack payment for a branch in 2026 Q3 (must never show on Tab 2). */
function incomePaymentOnline(int $branchId): MembershipPayment
{
    return MembershipPayment::factory()->approved()->create([
        'payment_channel' => 'paystack',
        'gateway_reference' => 'PSK-'.fake()->uuid(),
        'branch_id' => $branchId,
        'paid_at' => '2026-08-01 10:00:00',
        'payment_date' => '2026-08-01',
        'amount_paid_kobo' => 7_777_700,
        'paystack_fee_kobo' => 0,
        'net_kobo' => 7_777_700,
    ] + IncomeDistributionCalculator::calculate(7_777_700, $branchId, null, FeeDistributionSettings::DEFAULTS));
}
