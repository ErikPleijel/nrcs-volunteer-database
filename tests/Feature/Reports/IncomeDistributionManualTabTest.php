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
        ->and($rows[0])->not->toHaveKey('organisation_net')
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
        ->assertSee(route('reports.income-distribution.divisions', ['channel' => 'manual', 'quarter' => '2026-Q3', 'export' => 'csv']))
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

/*
|--------------------------------------------------------------------------
| CSV export (?export=csv, both levels)
|--------------------------------------------------------------------------
*/

/** The downloaded CSV as rows of cells (after the BOM and "sep=," line). */
function manualCsv($response): array
{
    $content = $response->streamedContent();

    expect(substr($content, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and(substr($content, 3, 7))->toBe("sep=,\r\n");

    return array_map(fn (string $line) => str_getcsv($line), preg_split('/\r?\n/', trim(substr($content, 10))));
}

/** The first CSV row whose first cell is $label. */
function manualCsvRow(array $rows, string $label): ?array
{
    foreach ($rows as $row) {
        if (($row[0] ?? null) === $label) {
            return $row;
        }
    }

    return null;
}

test('the Tab 2 branch level exports the quarter, notes, percentages, headline, rows and TOTAL', function () {
    manualIncomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10');
    manualIncomePayment(500_001, $this->kano->id, $this->kanoDivision->id, '2026-09-30');
    $organisation = Organisation::create(['name' => 'Sponsor Org', 'branch_id' => $this->abia->id]);
    manualIncomePayment(2_000_000, $this->abia->id, null, '2026-08-20', ['organisation_id' => $organisation->id]);
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2015-06-03');

    $response = viewManualTab(['quarter' => '2026-Q3', 'export' => 'csv'])
        ->assertOk()
        ->assertDownload('income-distribution-manual-2026-Q3-branches.csv');
    $rows = manualCsv($response);

    expect($rows[0])->toBe(['Income Distribution — Manual payments (branches) — Branch level'])
        ->and(manualCsvRow($rows, 'Quarter'))->toBe(['Quarter', '2026 Q3'])
        ->and(manualCsvRow($rows, 'WARNING'))->toBeNull()
        ->and($rows)->toContain(['Note', IncomeDistributionStatsService::BEFORE_GO_LIVE_NOTE])
        ->and(collect($rows)->flatten()->filter(fn ($cell) => str_contains((string) $cell, 'older payment'))->all())->toBe([])
        ->and(manualCsvRow($rows, 'Amounts')[1])->toBe('Naira; by approval date (payments from the old system by payment date)')
        ->and(manualCsvRow($rows, 'Percentages used'))->not->toBeNull()
        ->and(manualCsvRow($rows, 'Payments'))->toBe(['Payments', '3'])
        ->and(manualCsvRow($rows, 'Collected by branches'))->toBe(['Collected by branches', '35000.01'])
        ->and(manualCsvRow($rows, 'Paystack charges'))->toBeNull()
        ->and(manualCsvRow($rows, 'Branch'))->toBe(['Branch', 'Payments', 'Collected', 'HQ share', 'Branch share', 'Division share'])
        ->and(manualCsvRow($rows, 'Abia'))->toBe(['Abia', '1', '20000.00', '5000.00', '15000.00', '0.00'])
        ->and(manualCsvRow($rows, 'Kano')[2])->toBe('15000.01');

    $total = manualCsvRow($rows, 'TOTAL');
    $headline = viewManualTab(['quarter' => '2026-Q3'])->viewData('headline');
    $kobo = fn (string $naira) => (int) round((float) $naira * 100);
    expect($total[2])->toBe('35000.01')
        ->and($total[3])->toBe(manualCsvRow($rows, 'HQ share')[1])
        ->and($kobo($total[3]) + $kobo($total[4]) + $kobo($total[5]))->toBe($headline['net']);
});

test('the older-payments note stays on the page but is never in a CSV (both tabs, both levels)', function () {
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2015-06-03');
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-08-01');
    MembershipPayment::factory()->approved()->create([
        'payment_channel' => 'paystack',
        'gateway_reference' => 'PSK-OLD',
        'branch_id' => $this->kano->id,
        'paid_at' => '2015-06-03 10:00:00',
        'payment_date' => '2015-06-03',
        'amount_paid_kobo' => 100_000,
        'paystack_fee_kobo' => 0,
        'net_kobo' => 100_000,
    ] + IncomeDistributionCalculator::calculate(100_000, $this->kano->id, null, FeeDistributionSettings::DEFAULTS));

    viewManualTab(['quarter' => '2026-Q3'])->assertSee('1 older payment (before 2016, from paper records) is not included.');
    $this->actingAs($this->admin)->get(route('reports.income-distribution.index'))
        ->assertSee('1 older payment (before 2016, from paper records) is not included.');

    $files = [
        viewManualTab(['quarter' => '2026-Q3', 'export' => 'csv']),
        viewManualDivisions(['quarter' => '2026-Q3', 'export' => 'csv']),
        $this->actingAs($this->admin)->get(route('reports.income-distribution.index', ['export' => 'csv'])),
        $this->actingAs($this->admin)->get(route('reports.income-distribution.divisions', ['export' => 'csv'])),
    ];
    foreach ($files as $file) {
        expect($file->streamedContent())->not->toContain('older payment');
    }
});

test('a transition quarter CSV carries the transition note and both parts', function () {
    config(['income.manual_go_live_date' => '2026-08-15']);
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10');
    manualIncomePayment(400_000, $this->kano->id, $this->kanoDivision->id, '2026-08-15');
    $transitionNote = ['Note', 'Transition quarter: payments before 15 August 2026 are estimates based on fee prices; payments from 15 August 2026 are recorded amounts.'];

    $rows = manualCsv(viewManualTab(['quarter' => '2026-Q3', 'export' => 'csv']));

    expect($rows)->toContain($transitionNote)
        ->and($rows)->not->toContain(['Note', IncomeDistributionStatsService::BEFORE_GO_LIVE_NOTE])
        ->and(manualCsvRow($rows, 'Before 15 August 2026 (estimates)'))->toBe(['Before 15 August 2026 (estimates)', '1000.00', '1 payment'])
        ->and(manualCsvRow($rows, 'From 15 August 2026 (recorded)'))->toBe(['From 15 August 2026 (recorded)', '4000.00', '1 payment']);

    $divisions = manualCsv(viewManualDivisions(['quarter' => '2026-Q3', 'export' => 'csv']));
    expect($divisions)->toContain($transitionNote);

    // A live quarter has neither note.
    config(['income.manual_go_live_date' => '2026-07-01']);
    $live = manualCsv(viewManualTab(['quarter' => '2026-Q3', 'export' => 'csv']));
    expect(manualCsvRow($live, 'Note'))->toBeNull();
});

test('national admins get the invalid go-live and missing-shares warnings in the CSV; branch users do not', function () {
    config(['income.manual_go_live_date' => 'soon']);
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-08-01');
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2015-06-03');
    MembershipPayment::factory()->approved()->create(['payment_channel' => 'manual', 'branch_id' => $this->kano->id]);

    $national = manualCsv(viewManualTab(['quarter' => '2026-Q3', 'export' => 'csv']));
    expect($national)->toContain(['WARNING', IncomeDistributionStatsService::INVALID_GO_LIVE_NOTE])
        ->and($national)->toContain(['WARNING', '1 approved manual payment with no income shares yet, not counted in any quarter.'])
        ->and($national)->toContain(['Note', IncomeDistributionStatsService::BEFORE_GO_LIVE_NOTE]);

    $branch = manualCsv(viewManualTab(['quarter' => '2026-Q3', 'export' => 'csv'], $this->branchAdmin));
    expect(manualCsvRow($branch, 'WARNING'))->toBeNull()
        ->and($branch)->not->toContain(['Note', '1 older payment (before 2016, from paper records) is not included.'])
        ->and($branch)->toContain(['Note', IncomeDistributionStatsService::BEFORE_GO_LIVE_NOTE]);
});

test('a branch user gets only their own branch in the Tab 2 exports; another branch is refused', function () {
    manualIncomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-08-01');
    manualIncomePayment(400_000, $this->abia->id, $this->abiaDivision->id, '2026-08-01');

    $branches = viewManualTab(['quarter' => '2026-Q3', 'export' => 'csv'], $this->branchAdmin)
        ->assertDownload('income-distribution-manual-2026-Q3-branches-kano.csv');
    $rows = manualCsv($branches);
    expect(manualCsvRow($rows, 'Branch')[1])->toBe('Kano')
        ->and(manualCsvRow($rows, 'Collected by the branch'))->toBe(['Collected by the branch', '10000.00'])
        ->and(manualCsvRow($rows, 'Abia'))->toBeNull()
        ->and(manualCsvRow($rows, 'TOTAL')[2])->toBe('10000.00');

    $divisions = viewManualDivisions(['quarter' => '2026-Q3', 'export' => 'csv'], $this->branchAdmin)
        ->assertDownload('income-distribution-manual-2026-Q3-divisions-kano.csv');
    $rows = manualCsv($divisions);
    expect(manualCsvRow($rows, 'Kano Municipal'))->not->toBeNull()
        ->and(manualCsvRow($rows, 'Aba North'))->toBeNull();

    viewManualDivisions(['quarter' => '2026-Q3', 'branch' => $this->abia->id, 'export' => 'csv'], $this->branchAdmin)->assertForbidden();
});

test('the Tab 2 division level exports every division, and the TOTAL matches Tab 2', function () {
    manualIncomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10');
    manualIncomePayment(400_000, $this->abia->id, $this->abiaDivision->id, '2026-08-15');
    incomePaymentOnline($this->abia->id);

    $response = viewManualDivisions(['quarter' => '2026-Q3', 'export' => 'csv'])
        ->assertDownload('income-distribution-manual-2026-Q3-divisions.csv');
    $rows = manualCsv($response);

    expect($rows[0])->toBe(['Income Distribution — Manual payments (branches) — Division level'])
        ->and(manualCsvRow($rows, 'Branch'))->toBe(['Branch', 'All branches'])
        ->and(manualCsvRow($rows, 'Divisions'))->toBe(['Divisions', '2'])
        ->and(manualCsvRow($rows, 'Division'))->toBe(['Division', 'Branch', 'Payments', 'Collected', 'Division share'])
        ->and(manualCsvRow($rows, 'Kano Municipal'))->toBe(['Kano Municipal', 'Kano', '1', '10000.00', '5000.00']);

    $headline = viewManualTab(['quarter' => '2026-Q3'])->viewData('headline');
    expect((int) round((float) manualCsvRow($rows, 'TOTAL')[4] * 100))->toBe($headline['division']);
});

test('Tab 2 offers the branch-level export with the current quarter', function () {
    manualIncomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-08-01');

    viewManualTab(['quarter' => '2026-Q3'])
        ->assertSee(route('reports.income-distribution.index', ['tab' => 'manual', 'quarter' => '2026-Q3', 'export' => 'csv']));
});

test('Tab 2 has no separate organisation column', function () {
    $organisation = Organisation::create(['name' => 'Sponsor Org', 'branch_id' => $this->kano->id, 'division_id' => $this->kanoDivision->id]);
    manualIncomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-08-10', ['organisation_id' => $organisation->id]);

    viewManualTab(['quarter' => '2026-Q3'])
        ->assertOk()
        ->assertDontSee('From Organisation');
});
