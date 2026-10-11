<?php

/**
 * Income Distribution report, Tab 1 (online / Paystack), branch level:
 * quarter selector, headline, one row per branch (plus "Deleted branch #id"
 * and "No branch (to HQ)"), totals that must match the headline, the
 * percentages used, and the view_income_distribution permission.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\User;
use App\Services\FeeDistributionSettings;
use App\Services\IncomeDistributionCalculator;
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

    foreach (['view_reports', 'view_income_distribution'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['view_reports', 'view_income_distribution']);
    Role::findOrCreate('branch_db_administrator', 'web')->syncPermissions(['view_reports']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $this->kano = Branch::create(['name' => 'Kano', 'code' => 'KAN']);
    $this->abia = Branch::create(['name' => 'Abia', 'code' => 'ABI']);
    $this->kanoDivision = Division::create(['name' => 'Kano Municipal', 'branch_id' => $this->kano->id]);
    $this->abiaDivision = Division::create(['name' => 'Aba North', 'branch_id' => $this->abia->id]);
});

/**
 * A fulfilled Paystack payment with its split worked out by the real
 * calculator (default percentages unless $percentages is given).
 */
function incomePayment(int $netKobo, ?int $branchId, ?int $divisionId, string $paidAt, array $overrides = [], ?array $percentages = null): MembershipPayment
{
    $fee = intdiv($netKobo, 50);

    return MembershipPayment::factory()->approved()->create(array_merge([
        'payment_channel' => 'paystack',
        'gateway_reference' => 'PSK-'.fake()->uuid(),
        'branch_id' => $branchId,
        'division_id' => $divisionId,
        'paid_at' => $paidAt,
        'payment_date' => substr($paidAt, 0, 10),
        'amount_paid_kobo' => $netKobo + $fee,
        'paystack_fee_kobo' => $fee,
        'net_kobo' => $netKobo,
    ], IncomeDistributionCalculator::calculate($netKobo, $branchId, $divisionId, $percentages ?? FeeDistributionSettings::DEFAULTS), $overrides));
}

function viewIncomeReport(array $query = [])
{
    return test()->actingAs(test()->admin)->get(route('reports.income-distribution.index', $query));
}

test('a national DB admin can open the report; view_reports alone is not enough', function () {
    viewIncomeReport()->assertOk()->assertSee('Online payments (Paystack)');

    $branchAdmin = User::factory()->create();
    $branchAdmin->assignRole('branch_db_administrator');

    $this->actingAs($branchAdmin)->get(route('reports.income-distribution.index'))->assertForbidden();
});

test('guests are sent to the login page', function () {
    $this->get(route('reports.income-distribution.index'))->assertRedirect(route('login'));
});

test('the seeder gives view_income_distribution to the national DB administrator only', function () {
    $this->seed([RolesTableSeeder::class, PermissionsTableSeeder::class]);

    $holders = Role::whereHas('permissions', fn ($q) => $q->where('name', 'view_income_distribution'))->pluck('name')->all();

    expect($holders)->toBe(['national_db_administrator']);
});

test('the dashboard links to the report only for users with the permission', function () {
    $this->actingAs($this->admin)->get(route('reports.dashboard'))
        ->assertOk()
        ->assertSee(route('reports.income-distribution.index'), false);

    $branchAdmin = User::factory()->create();
    $branchAdmin->assignRole('branch_db_administrator');

    $this->actingAs($branchAdmin)->get(route('reports.dashboard'))
        ->assertOk()
        ->assertDontSee(route('reports.income-distribution.index'), false);
});

test('one row per branch, sorted by name, then deleted branches, then no branch; totals match the headline', function () {
    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10 09:00:00');
    incomePayment(500_001, $this->kano->id, $this->kanoDivision->id, '2026-08-01 12:00:00');
    incomePayment(400_000, $this->abia->id, $this->abiaDivision->id, '2026-09-15 08:00:00');

    $organisation = Organisation::create(['name' => 'Sponsor Org', 'branch_id' => $this->abia->id]);
    incomePayment(2_000_000, $this->abia->id, null, '2026-08-20 10:00:00', ['organisation_id' => $organisation->id]);

    // A branch and division that no longer exist (membership_payments has no FKs).
    incomePayment(300_000, 999_998, 999_999, '2026-07-20 10:00:00');
    incomePayment(100_000, null, null, '2026-09-01 10:00:00');

    $response = viewIncomeReport(['quarter' => '2026-Q3'])->assertOk();

    $rows = $response->viewData('rows');
    expect($rows->pluck('label')->all())->toBe(['Abia', 'Kano', 'Deleted branch #999998', 'No branch (to HQ)'])
        ->and($rows->pluck('count')->all())->toBe([2, 2, 1, 1]);

    $abia = $rows[0];
    expect($abia['net'])->toBe(2_400_000)
        ->and($abia['organisation_net'])->toBe(2_000_000)
        ->and($abia['branch'])->toBe(100_000 + 1_500_000)
        ->and($abia['division'])->toBe(200_000)
        ->and($abia['hq'])->toBe(100_000 + 500_000);

    $kano = $rows[1];
    expect($kano['net'])->toBe(1_500_001)
        ->and($kano['hq'] + $kano['branch'] + $kano['division'])->toBe(1_500_001);

    expect($rows[3]['hq'])->toBe(100_000)
        ->and($rows[3]['branch'] + $rows[3]['division'])->toBe(0);

    $headline = $response->viewData('headline');
    $totals = $response->viewData('totals');
    expect($headline['net'])->toBe(4_300_001)
        ->and($totals['net'])->toBe($headline['net'])
        ->and($totals['paid'])->toBe($headline['paid'])
        ->and($totals['fees'])->toBe($headline['fees'])
        ->and($totals['count'])->toBe(6)
        ->and($totals['hq'] + $totals['branch'] + $totals['division'])->toBe($totals['net'])
        ->and($response->viewData('integrityProblems'))->toBe([]);

    $response->assertSee('₦43,000.01')
        ->assertSee('Deleted branch #999998')
        ->assertSee('No branch (to HQ)')
        ->assertSeeInOrder(['Abia', 'Kano', 'Deleted branch #999998', 'No branch (to HQ)', 'TOTAL'])
        ->assertDontSee('do not add up')
        ->assertSee('Based on Paystack payment time. A payment near the end of a quarter may reach the bank account in the next quarter.');
});

test('only approved, not deleted Paystack payments paid in the quarter count', function () {
    $counted = incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-09-30 23:59:59');

    incomePayment(111_100, $this->kano->id, $this->kanoDivision->id, '2026-08-01 10:00:00', ['is_deleted' => true]);
    incomePayment(222_200, $this->kano->id, $this->kanoDivision->id, '2026-08-01 10:00:00', ['approval_status' => 'pending']);
    incomePayment(333_300, $this->kano->id, $this->kanoDivision->id, '2026-08-01 10:00:00', ['approval_status' => 'rejected']);
    incomePayment(444_400, $this->kano->id, $this->kanoDivision->id, '2026-08-01 10:00:00', ['payment_channel' => 'manual', 'gateway_reference' => null]);
    incomePayment(555_500, $this->kano->id, $this->kanoDivision->id, '2026-06-30 23:59:59');
    incomePayment(666_600, $this->kano->id, $this->kanoDivision->id, '2026-10-01 00:00:00');

    $headline = viewIncomeReport(['quarter' => '2026-Q3'])->viewData('headline');

    expect($headline['count'])->toBe(1)
        ->and($headline['net'])->toBe($counted->net_kobo);

    expect(viewIncomeReport(['quarter' => '2026-Q2'])->viewData('headline')['net'])->toBe(555_500)
        ->and(viewIncomeReport(['quarter' => '2026-Q4'])->viewData('headline')['net'])->toBe(666_600);
});

test('the quarter list runs from the first Paystack payment to now; the default is the latest FINISHED quarter with income', function () {
    incomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2025-02-10 10:00:00');
    incomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-08-10 10:00:00');
    // Income in the current quarter (2026 Q4) does not make it the default.
    incomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-10-05 10:00:00');

    $response = viewIncomeReport();

    expect($response->viewData('quarterOptions'))->toBe([
        '2026-Q4' => '2026 Q4 (in progress)',
        '2026-Q3' => '2026 Q3',
        '2026-Q2' => '2026 Q2',
        '2026-Q1' => '2026 Q1',
        '2025-Q4' => '2025 Q4',
        '2025-Q3' => '2025 Q3',
        '2025-Q2' => '2025 Q2',
        '2025-Q1' => '2025 Q1',
    ])->and($response->viewData('selectedQuarter'))->toBe('2026-Q3');

    // Unknown or malformed quarters fall back to the default.
    expect(viewIncomeReport(['quarter' => '2019-Q1'])->viewData('selectedQuarter'))->toBe('2026-Q3')
        ->and(viewIncomeReport(['quarter' => 'garbage'])->viewData('selectedQuarter'))->toBe('2026-Q3');
});

test('with no Paystack income at all, the current quarter is shown empty, with the in-progress note', function () {
    viewIncomeReport()
        ->assertOk()
        ->assertViewHas('selectedQuarter', '2026-Q4')
        ->assertSee('No online payments in 2026 Q4.')
        ->assertSee('This quarter is not finished yet. Figures will change. Do not transfer money based on these numbers.');
});

test('with income only in the current quarter, that quarter is the default', function () {
    incomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-10-05 10:00:00');

    viewIncomeReport()->assertViewHas('selectedQuarter', '2026-Q4');
});

test('the in-progress note shows for the current quarter only', function () {
    incomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-08-10 10:00:00');
    incomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-10-05 10:00:00');
    $note = 'This quarter is not finished yet. Figures will change. Do not transfer money based on these numbers.';

    viewIncomeReport(['quarter' => '2026-Q4'])
        ->assertViewHas('quarterInProgress', true)
        ->assertSee('2026 Q4 (in progress)')
        ->assertSee($note);

    viewIncomeReport(['quarter' => '2026-Q3'])
        ->assertViewHas('quarterInProgress', false)
        ->assertDontSee($note);

    $this->actingAs($this->admin)
        ->get(route('reports.income-distribution.divisions', ['quarter' => '2026-Q4']))
        ->assertSee($note);
});

test('the organisation column is called From Organisation', function () {
    incomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-08-10 10:00:00');

    viewIncomeReport(['quarter' => '2026-Q3'])
        ->assertSee('From Organisation')
        ->assertDontSee('of which organisations');
});

test('the percentages used are shown, with a note when they changed during the quarter', function () {
    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10 09:00:00');

    viewIncomeReport(['quarter' => '2026-Q3'])
        ->assertSee('HQ 25% / Branch 25% / Division 50%')
        ->assertDontSee('Percentages changed during this quarter.');

    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-08-10 09:00:00', [], [
        'standard' => ['hq' => 30, 'branch' => 20, 'division' => 50],
        'branch_level' => ['hq' => 25, 'branch' => 75],
    ]);

    viewIncomeReport(['quarter' => '2026-Q3'])
        ->assertSee('HQ 25% / Branch 25% / Division 50%')
        ->assertSee('HQ 30% / Branch 20% / Division 50%')
        ->assertSee('Percentages changed during this quarter.');
});

test('a red warning appears if the stored shares do not add up to the net', function () {
    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10 09:00:00', ['hq_share_kobo' => 1]);

    $response = viewIncomeReport(['quarter' => '2026-Q3']);

    expect($response->viewData('integrityProblems'))->toContain('Kano: the shares do not add up to the net received.');
    $response->assertSee('The figures on this page do not add up.');
});

test('Paystack payments do not show on the manual payments tab (IncomeDistributionManualTabTest)', function () {
    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10 09:00:00');

    $response = viewIncomeReport(['tab' => 'manual', 'quarter' => '2026-Q3'])->assertOk();

    expect($response->viewData('activeTab'))->toBe('manual')
        ->and($response->viewData('headline')['count'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Division level (reports.income-distribution.divisions)
|--------------------------------------------------------------------------
*/

function viewDivisions(array $query = [])
{
    return test()->actingAs(test()->admin)->get(route('reports.income-distribution.divisions', $query));
}

test('the branch level links to the division level, for all branches and per branch', function () {
    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10 09:00:00');
    incomePayment(300_000, 999_998, 999_999, '2026-07-20 10:00:00');
    incomePayment(100_000, null, null, '2026-09-01 10:00:00');

    $response = viewIncomeReport(['quarter' => '2026-Q3']);

    $response->assertSee('View division level')
        ->assertSee(e(route('reports.income-distribution.divisions', ['quarter' => '2026-Q3'])), false)
        ->assertSee(e(route('reports.income-distribution.divisions', ['quarter' => '2026-Q3', 'branch' => $this->kano->id])), false)
        ->assertSee(e(route('reports.income-distribution.divisions', ['quarter' => '2026-Q3', 'branch' => 999_998])), false);

    // "No branch (to HQ)" has no divisions, so no link: only Kano and the deleted branch.
    expect(substr_count($response->getContent(), 'divisions?quarter=2026-Q3&amp;branch='))->toBe(2);
});

test('every division with a share is listed, and the division shares add up to the branch level total', function () {
    $secondKanoDivision = Division::create(['name' => 'Fagge', 'branch_id' => $this->kano->id]);

    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10 09:00:00');
    incomePayment(500_001, $this->kano->id, $this->kanoDivision->id, '2026-08-01 12:00:00');
    incomePayment(200_000, $this->kano->id, $secondKanoDivision->id, '2026-08-02 12:00:00');
    incomePayment(400_000, $this->abia->id, $this->abiaDivision->id, '2026-09-15 08:00:00');
    incomePayment(300_000, 999_998, 999_999, '2026-07-20 10:00:00');

    // No division share: organisation (branch level) and no-branch payments.
    $organisation = Organisation::create(['name' => 'Sponsor Org', 'branch_id' => $this->abia->id]);
    incomePayment(2_000_000, $this->abia->id, null, '2026-08-20 10:00:00', ['organisation_id' => $organisation->id]);
    incomePayment(100_000, null, null, '2026-09-01 10:00:00');

    // Outside the quarter.
    incomePayment(700_000, $this->kano->id, $this->kanoDivision->id, '2026-06-30 23:59:59');

    $response = viewDivisions(['quarter' => '2026-Q3'])->assertOk();
    $rows = $response->viewData('rows');

    expect($rows->map(fn ($r) => $r['branch'].' / '.$r['division'])->all())->toBe([
        'Abia / Aba North',
        'Deleted branch #999998 / Deleted division #999999',
        'Kano / Fagge',
        'Kano / Kano Municipal',
    ]);

    $kanoMunicipal = $rows[3];
    expect($kanoMunicipal['count'])->toBe(2)
        ->and($kanoMunicipal['net'])->toBe(1_500_001)
        ->and($kanoMunicipal['division_share'])->toBe(500_000 + 250_000);

    $branchLevelDivisionTotal = viewIncomeReport(['quarter' => '2026-Q3'])->viewData('totals')['division'];
    expect($response->viewData('totals')['division'])->toBe($branchLevelDivisionTotal)
        ->and($response->viewData('integrityProblems'))->toBe([]);

    $response->assertSee('Deleted division #999999')
        ->assertSee('of 4 divisions')
        ->assertDontSee('do not add up');
});

test('filtered by branch, the division shares equal that branch row on the branch level', function () {
    $secondKanoDivision = Division::create(['name' => 'Fagge', 'branch_id' => $this->kano->id]);
    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10 09:00:00');
    incomePayment(200_003, $this->kano->id, $secondKanoDivision->id, '2026-08-02 12:00:00');
    incomePayment(400_000, $this->abia->id, $this->abiaDivision->id, '2026-09-15 08:00:00');
    incomePayment(300_000, 999_998, 999_999, '2026-07-20 10:00:00');

    $branchRows = viewIncomeReport(['quarter' => '2026-Q3'])->viewData('rows')->keyBy('label');

    $kano = viewDivisions(['quarter' => '2026-Q3', 'branch' => $this->kano->id]);
    expect($kano->viewData('rows')->pluck('division')->all())->toBe(['Fagge', 'Kano Municipal'])
        ->and($kano->viewData('totals')['division'])->toBe($branchRows['Kano']['division'])
        ->and($kano->viewData('integrityProblems'))->toBe([]);
    $kano->assertSee('Show all branches')->assertViewHas('branchFilterLabel', 'Kano');

    // A branch that no longer exists can still be opened.
    $deleted = viewDivisions(['quarter' => '2026-Q3', 'branch' => 999_998]);
    expect($deleted->viewData('rows')->pluck('division')->all())->toBe(['Deleted division #999999'])
        ->and($deleted->viewData('totals')['division'])->toBe($branchRows['Deleted branch #999998']['division'])
        ->and($deleted->viewData('branchFilterLabel'))->toBe('Deleted branch #999998');
});

test('an invalid branch filter shows all branches', function (string $branch) {
    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10 09:00:00');
    incomePayment(400_000, $this->abia->id, $this->abiaDivision->id, '2026-09-15 08:00:00');

    $response = viewDivisions(['quarter' => '2026-Q3', 'branch' => $branch]);

    expect($response->viewData('branchFilter'))->toBeNull()
        ->and($response->viewData('rows'))->toHaveCount(2);
})->with(['text' => ['abc'], 'zero' => ['0'], 'negative' => ['-3']]);

test('the division level warns in red if a division share has no division', function () {
    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10 09:00:00');
    // Corrupt: a standard-rule share with no division to pay it to.
    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-11 09:00:00', ['division_id' => null]);

    viewDivisions(['quarter' => '2026-Q3'])
        ->assertSee('The figures on this page do not add up.')
        ->assertSee('do not match the division share total on the branch level');
});

test('the division level needs one of the two permissions and shows an empty state', function () {
    $branchAdmin = User::factory()->create();
    $branchAdmin->assignRole('branch_db_administrator');

    $this->actingAs($branchAdmin)->get(route('reports.income-distribution.divisions'))->assertForbidden();

    viewDivisions(['quarter' => '2026-Q4'])->assertOk()->assertSee('No division received a share in 2026 Q4.');
});

/*
|--------------------------------------------------------------------------
| CSV export (?export=csv, both levels)
|--------------------------------------------------------------------------
*/

/**
 * The downloaded CSV as rows of cells, after checking the BOM and the
 * "sep=," line every report export starts with.
 */
function incomeCsv($response): array
{
    $content = $response->streamedContent();

    expect(substr($content, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and(substr($content, 3, 7))->toBe("sep=,\r\n");

    $lines = preg_split('/\r?\n/', trim(substr($content, 10)));

    return array_map(fn (string $line) => str_getcsv($line), $lines);
}

/** The first CSV row whose first cell is $label. */
function csvRow(array $rows, string $label): ?array
{
    foreach ($rows as $row) {
        if (($row[0] ?? null) === $label) {
            return $row;
        }
    }

    return null;
}

test('the branch level exports a CSV with the quarter, percentages, headline, rows and TOTAL', function () {
    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10 09:00:00');
    incomePayment(500_001, $this->kano->id, $this->kanoDivision->id, '2026-08-01 12:00:00');
    $organisation = Organisation::create(['name' => 'Sponsor Org', 'branch_id' => $this->abia->id]);
    incomePayment(2_000_000, $this->abia->id, null, '2026-08-20 10:00:00', ['organisation_id' => $organisation->id]);
    incomePayment(300_000, 999_998, 999_999, '2026-07-20 10:00:00');

    $response = viewIncomeReport(['quarter' => '2026-Q3', 'export' => 'csv'])->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('income-distribution-2026-Q3-branches.csv');

    $rows = incomeCsv($response);
    $page = viewIncomeReport(['quarter' => '2026-Q3']);
    $headline = $page->viewData('headline');
    $totals = $page->viewData('totals');

    expect(csvRow($rows, 'Quarter'))->toBe(['Quarter', '2026 Q3'])
        ->and(csvRow($rows, 'WARNING'))->toBeNull()
        ->and(csvRow($rows, 'Net received (distributed)'))->toBe(['Net received (distributed)', '38000.01'])
        ->and(csvRow($rows, 'Total paid by members')[1])->toBe(\App\Support\Naira::decimal($headline['paid']))
        ->and(csvRow($rows, 'Payments'))->toBe(['Payments', '4']);

    $percentages = array_values(array_filter($rows, fn ($r) => $r[0] === 'Percentages used'));
    expect(array_column($percentages, 2))->toBe(['HQ 25% / Branch 75%', 'HQ 25% / Branch 25% / Division 50%']);

    expect(csvRow($rows, 'Branch'))->toBe([
        'Branch', 'Payments', 'Paid by members', 'Paystack charges', 'Net received',
        'HQ share', 'Branch share', 'Division share', 'From Organisation',
    ]);
    expect(csvRow($rows, 'Abia'))->toBe(['Abia', '1', '20400.00', '400.00', '20000.00', '5000.00', '15000.00', '0.00', '20000.00'])
        ->and(csvRow($rows, 'Deleted branch #999998'))->not->toBeNull();

    $total = csvRow($rows, 'TOTAL');
    expect($total[1])->toBe((string) $headline['count'])
        ->and($total[4])->toBe(\App\Support\Naira::decimal($headline['net']))
        ->and($total[5])->toBe(\App\Support\Naira::decimal($totals['hq']))
        ->and($total[7])->toBe(\App\Support\Naira::decimal($totals['division']));
});

test('a CSV of the current quarter carries the in-progress warning', function () {
    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-10-05 09:00:00');

    $branches = viewIncomeReport(['quarter' => '2026-Q4', 'export' => 'csv']);
    $divisions = $this->actingAs($this->admin)->get(route('reports.income-distribution.divisions', ['quarter' => '2026-Q4', 'export' => 'csv']));

    foreach ([$branches, $divisions] as $response) {
        $rows = incomeCsv($response);
        expect(csvRow($rows, 'Quarter'))->toBe(['Quarter', '2026 Q4 (in progress)'])
            ->and(csvRow($rows, 'WARNING'))->toBe(['WARNING', 'This quarter is not finished yet. Figures will change. Do not transfer money based on these numbers.']);
    }

    expect($branches->headers->get('Content-Disposition'))->toContain('income-distribution-2026-Q4-branches.csv');
});

test('the division level exports every division, and the TOTAL matches the branch level', function () {
    $secondKanoDivision = Division::create(['name' => 'Fagge', 'branch_id' => $this->kano->id]);
    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10 09:00:00');
    incomePayment(200_003, $this->kano->id, $secondKanoDivision->id, '2026-08-02 12:00:00');
    incomePayment(400_000, $this->abia->id, $this->abiaDivision->id, '2026-09-15 08:00:00');

    $response = viewDivisions(['quarter' => '2026-Q3', 'export' => 'csv'])->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('income-distribution-2026-Q3-divisions.csv');

    $rows = incomeCsv($response);
    expect(csvRow($rows, 'Branch'))->toBe(['Branch', 'All branches'])
        ->and(csvRow($rows, 'Divisions'))->toBe(['Divisions', '3'])
        ->and(csvRow($rows, 'Division'))->toBe(['Division', 'Branch', 'Payments', 'Net received', 'Division share'])
        ->and(csvRow($rows, 'Kano Municipal'))->toBe(['Kano Municipal', 'Kano', '1', '10000.00', '5000.00'])
        ->and(csvRow($rows, 'Fagge'))->toBe(['Fagge', 'Kano', '1', '2000.03', '1000.01']);

    $branchLevelDivisionTotal = viewIncomeReport(['quarter' => '2026-Q3'])->viewData('totals')['division'];
    expect(csvRow($rows, 'TOTAL'))->toBe(['TOTAL', '', '3', '16000.03', \App\Support\Naira::decimal($branchLevelDivisionTotal)]);
});

test('the division export respects the branch filter, including a deleted branch', function () {
    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10 09:00:00');
    incomePayment(400_000, $this->abia->id, $this->abiaDivision->id, '2026-09-15 08:00:00');
    incomePayment(300_000, 999_998, 999_999, '2026-07-20 10:00:00');

    $kano = viewDivisions(['quarter' => '2026-Q3', 'branch' => $this->kano->id, 'export' => 'csv']);
    expect($kano->headers->get('Content-Disposition'))->toContain('income-distribution-2026-Q3-divisions-kano.csv');

    $rows = incomeCsv($kano);
    expect(csvRow($rows, 'Branch'))->toBe(['Branch', 'Kano'])
        ->and(csvRow($rows, 'Kano Municipal'))->not->toBeNull()
        ->and(csvRow($rows, 'Aba North'))->toBeNull()
        ->and(csvRow($rows, 'TOTAL')[4])->toBe('5000.00');

    $deleted = viewDivisions(['quarter' => '2026-Q3', 'branch' => 999_998, 'export' => 'csv']);
    expect($deleted->headers->get('Content-Disposition'))->toContain('income-distribution-2026-Q3-divisions-deleted-branch-999998.csv')
        ->and(csvRow(incomeCsv($deleted), 'Deleted division #999999'))->not->toBeNull();
});

test('names that a spreadsheet would run as a formula are neutralised', function () {
    $division = Division::create(['name' => '=HYPERLINK("http://x","y")', 'branch_id' => $this->kano->id]);
    incomePayment(1_000_000, $this->kano->id, $division->id, '2026-07-10 09:00:00');

    $rows = incomeCsv(viewDivisions(['quarter' => '2026-Q3', 'export' => 'csv']));

    expect(csvRow($rows, '\'=HYPERLINK("http://x","y")'))->not->toBeNull()
        ->and(csvRow($rows, '=HYPERLINK("http://x","y")'))->toBeNull();
});

test('both exports need one of the two permissions', function () {
    $branchAdmin = User::factory()->create();
    $branchAdmin->assignRole('branch_db_administrator');

    $this->actingAs($branchAdmin)->get(route('reports.income-distribution.index', ['export' => 'csv']))->assertForbidden();
    $this->actingAs($branchAdmin)->get(route('reports.income-distribution.divisions', ['export' => 'csv']))->assertForbidden();
});

test('both pages offer the export with the current quarter and branch', function () {
    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10 09:00:00');

    viewIncomeReport(['quarter' => '2026-Q3'])
        ->assertSee(e(route('reports.income-distribution.index', ['tab' => 'online', 'quarter' => '2026-Q3', 'export' => 'csv'])), false);

    viewDivisions(['quarter' => '2026-Q3', 'branch' => $this->kano->id])
        ->assertSee(e(route('reports.income-distribution.divisions', ['quarter' => '2026-Q3', 'branch' => $this->kano->id, 'export' => 'csv'])), false);
});

test('the division table carries every row for client-side search and sort', function () {
    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10 09:00:00');
    incomePayment(400_000, $this->abia->id, $this->abiaDivision->id, '2026-09-15 08:00:00');

    viewDivisions(['quarter' => '2026-Q3'])
        ->assertSee('x-data="incomeDivisionTable(', false)
        ->assertSee('Kano Municipal')
        ->assertSee('Aba North')
        ->assertSee('Search division or branch')
        ->assertSee('TOTAL');
});

/*
|--------------------------------------------------------------------------
| Branch users (view_income_distribution_branch): own branch only
|--------------------------------------------------------------------------
*/

function kanoBranchUser(): User
{
    Permission::findOrCreate('view_income_distribution_branch', 'web');
    Role::findOrCreate('branch_secretary', 'web')->syncPermissions(['view_income_distribution_branch']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create(['branch_id' => test()->kano->id, 'division_id' => test()->kanoDivision->id]);
    $user->assignRole('branch_secretary');

    return $user;
}

test('a branch user sees Tab 1 for their own branch only', function () {
    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10 09:00:00');
    incomePayment(400_000, $this->abia->id, $this->abiaDivision->id, '2026-08-10 09:00:00');
    incomePayment(100_000, null, null, '2026-08-11 09:00:00');
    $user = kanoBranchUser();

    $response = $this->actingAs($user)
        ->get(route('reports.income-distribution.index', ['quarter' => '2026-Q3']))
        ->assertOk();

    expect($response->viewData('activeTab'))->toBe('online')
        ->and($response->viewData('rows')->pluck('label')->all())->toBe(['Kano'])
        ->and($response->viewData('headline')['net'])->toBe(1_000_000)
        ->and($response->viewData('integrityProblems'))->toBe([]);

    $response->assertSee('Online payments (Paystack) — Kano')
        ->assertSee('Manual payments (branches)')
        ->assertDontSee('Abia')
        ->assertDontSee('No branch (to HQ)')
        ->assertSee(route('reports.income-distribution.divisions', ['quarter' => '2026-Q3', 'branch' => $this->kano->id]));
});

test('a branch user sees their own branch\'s divisions on Tab 1; another branch is refused', function () {
    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10 09:00:00');
    incomePayment(400_000, $this->abia->id, $this->abiaDivision->id, '2026-08-10 09:00:00');
    $user = kanoBranchUser();

    $response = $this->actingAs($user)
        ->get(route('reports.income-distribution.divisions', ['quarter' => '2026-Q3']))
        ->assertOk();

    expect($response->viewData('branchFilter'))->toBe($this->kano->id)
        ->and($response->viewData('rows')->pluck('division')->all())->toBe(['Kano Municipal'])
        ->and($response->viewData('integrityProblems'))->toBe([]);
    $response->assertDontSee('Aba North')->assertDontSee('Show all branches');

    $this->actingAs($user)
        ->get(route('reports.income-distribution.divisions', ['quarter' => '2026-Q3', 'branch' => $this->abia->id]))
        ->assertForbidden();
    $this->actingAs($user)
        ->get(route('reports.income-distribution.divisions', ['quarter' => '2026-Q3', 'branch' => $this->abia->id, 'export' => 'csv']))
        ->assertForbidden();
});

test('a branch user\'s Tab 1 exports hold their own branch only', function () {
    incomePayment(1_000_000, $this->kano->id, $this->kanoDivision->id, '2026-07-10 09:00:00');
    incomePayment(400_000, $this->abia->id, $this->abiaDivision->id, '2026-08-10 09:00:00');
    $user = kanoBranchUser();

    $branches = $this->actingAs($user)
        ->get(route('reports.income-distribution.index', ['quarter' => '2026-Q3', 'export' => 'csv']))
        ->assertOk()
        ->assertDownload('income-distribution-2026-Q3-branches-kano.csv');
    $rows = incomeCsv($branches);
    expect(csvRow($rows, 'Branch')[1])->toBe('Kano')
        ->and(csvRow($rows, 'Abia'))->toBeNull()
        ->and(csvRow($rows, 'TOTAL')[4])->toBe('10000.00');

    $divisions = $this->actingAs($user)
        ->get(route('reports.income-distribution.divisions', ['quarter' => '2026-Q3', 'export' => 'csv']))
        ->assertOk();
    $rows = incomeCsv($divisions);
    expect(csvRow($rows, 'Aba North'))->toBeNull()
        ->and(csvRow($rows, 'Kano Municipal'))->not->toBeNull();
});

test('Tab 1 quarters also start at 2016 Q1; earlier Paystack payments get the same note for national admins', function () {
    incomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2015-12-31 10:00:00');
    incomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2016-01-01 10:00:00');

    $response = viewIncomeReport();

    expect(array_key_last($response->viewData('quarterOptions')))->toBe('2016-Q1')
        ->and($response->viewData('paymentsBeforeFirstQuarter'))->toBe(1);
    $response->assertSee('1 older payment (before 2016, from paper records) is not included.');

    $this->actingAs(kanoBranchUser())->get(route('reports.income-distribution.index'))
        ->assertOk()
        ->assertDontSee('before 2016');
});
