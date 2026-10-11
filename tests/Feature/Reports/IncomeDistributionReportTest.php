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

test('the quarter list runs from the first Paystack payment to now; the default is the latest quarter with income', function () {
    incomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2025-02-10 10:00:00');
    incomePayment(100_000, $this->kano->id, $this->kanoDivision->id, '2026-08-10 10:00:00');

    $response = viewIncomeReport();

    expect($response->viewData('quarterOptions'))->toBe([
        '2026-Q4' => '2026 Q4',
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

test('with no Paystack income at all, the current quarter is shown empty', function () {
    viewIncomeReport()
        ->assertOk()
        ->assertViewHas('selectedQuarter', '2026-Q4')
        ->assertSee('No online payments in 2026 Q4.');
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

test('the manual payments tab is a placeholder', function () {
    viewIncomeReport(['tab' => 'manual'])
        ->assertOk()
        ->assertSee('Manual payments (branches)')
        ->assertSee('Coming later.');
});
