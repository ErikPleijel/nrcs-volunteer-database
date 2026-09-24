<?php

/**
 * RCU certificate Group 3: the single-print button and PRINTED CERTIFICATES
 * section on red-cross-units/show, the leader's list on profile/red-cross-unit,
 * RCU rows in the certificate prints report (rendering, filters, scoping,
 * no N+1), and RCU attribution in the admin activity report.
 */

use App\Models\Branch;
use App\Models\CertificatePrint;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $permissions = ['manage-admin-panel', 'view_red_cross_unit', 'print_certificates', 'view_certificates', 'view_reports'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    foreach (['national_db_administrator', 'branch_db_administrator', 'division_db_assistant_operations'] as $role) {
        Role::findOrCreate($role, 'web')->syncPermissions($permissions);
    }
    Role::findOrCreate('national_db_assistant', 'web')->syncPermissions(['manage-admin-panel', 'view_red_cross_unit']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branchA = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->branchB = Branch::create(['name' => 'Beta Branch', 'code' => 'BET']);
    $this->divisionA1 = Division::create(['name' => 'Alpha One', 'branch_id' => $this->branchA->id]);
    $this->divisionA2 = Division::create(['name' => 'Alpha Two', 'branch_id' => $this->branchA->id]);
    $this->divisionB1 = Division::create(['name' => 'Beta One', 'branch_id' => $this->branchB->id]);

    $this->national = User::factory()->create(['first_name' => 'Nora', 'last_name' => 'National']);
    $this->national->assignRole('national_db_administrator');

    $this->branchAdmin = User::factory()->create(['branch_id' => $this->branchA->id]);
    $this->branchAdmin->assignRole('branch_db_administrator');

    $this->divisionAdmin = User::factory()->create(['branch_id' => $this->branchA->id, 'division_id' => $this->divisionA1->id]);
    $this->divisionAdmin->assignRole('division_db_assistant_operations');

    $this->leader = User::factory()->create();
    $this->unit = RedCrossUnit::create([
        'name' => 'Unit Display', 'division_id' => $this->divisionA1->id,
        'team_leader_user_id' => $this->leader->id, 'is_active' => true,
    ]);

    $this->rcuFee = MembershipFee::factory()->forRedCrossUnits()->create(['name' => 'Unit Yearly', 'amount' => 20000]);
});

function displayPay(RedCrossUnit $unit, array $overrides = []): MembershipPayment
{
    return MembershipPayment::factory()->approved()->create(array_merge([
        'user_id' => test()->leader->id,
        'red_cross_unit_id' => $unit->id,
        'membership_fee_id' => test()->rcuFee->id,
        'payment_date' => now()->subMonth()->toDateString(),
        'expiry_date' => now()->addMonths(11)->toDateString(),
    ], $overrides));
}

function displayPrint(RedCrossUnit $unit, array $overrides = []): CertificatePrint
{
    return CertificatePrint::create(array_merge([
        'red_cross_unit_id' => $unit->id,
        'printed_by_user_id' => test()->national->id,
        'certificate_type' => 'rcu_membership',
        'printed_at' => now()->subDay(),
    ], $overrides));
}

function singlePrintLink(RedCrossUnit $unit): string
{
    return e(route('red-cross-units.certificates.index', ['search' => $unit->id]));
}

/*
|--------------------------------------------------------------------------
| red-cross-units/show
|--------------------------------------------------------------------------
*/

test('the single-print button links to the pre-filtered RCU page only for a paid, active unit', function () {
    $show = fn () => $this->actingAs($this->national)->get(route('red-cross-units.show', $this->unit));

    // Unpaid: disabled.
    $show()->assertOk()->assertSee('Print membership certificate')->assertDontSee(singlePrintLink($this->unit), false);

    // Paid: enabled, and the link opens a list containing this unit.
    displayPay($this->unit);
    $show()->assertSee(singlePrintLink($this->unit), false);
    expect($this->actingAs($this->national)->get(route('red-cross-units.certificates.index', ['search' => $this->unit->id]))
        ->viewData('records')->pluck('id')->all())->toContain($this->unit->id);

    // Paid but archived: disabled again (the bulk page lists active units only).
    $this->unit->update(['is_active' => false]);
    $show()->assertDontSee(singlePrintLink($this->unit), false);
});

test('the single-print button is hidden without print_certificates', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('national_db_assistant');
    displayPay($this->unit);

    $this->actingAs($viewer)
        ->get(route('red-cross-units.show', $this->unit))
        ->assertOk()
        ->assertDontSee('Print membership certificate');
});

test('the show page lists the unit\'s full print history', function () {
    $this->actingAs($this->national)
        ->get(route('red-cross-units.show', $this->unit))
        ->assertSee('PRINTED CERTIFICATES')
        ->assertSee('No printed certificates found');

    displayPrint($this->unit, ['printed_at' => now()->subYears(2)]); // an earlier fee period
    displayPrint($this->unit, ['printed_at' => now()->subDay()]);

    $response = $this->actingAs($this->national)->get(route('red-cross-units.show', $this->unit))
        ->assertSeeInOrder(['PRINTED CERTIFICATES', 'RCU – Membership', 'Nora National'])
        ->assertDontSee('No printed certificates found');

    expect($response->viewData('certificatePrints'))->toHaveCount(2);
});

test('the leader sees the unit\'s printed certificates on profile/red-cross-unit', function () {
    displayPrint($this->unit);

    $this->actingAs($this->leader)
        ->get(route('profile.red-cross-unit', $this->unit))
        ->assertOk()
        ->assertSeeInOrder(['PRINTED CERTIFICATES', 'RCU – Membership', 'Nora National']);
});

/*
|--------------------------------------------------------------------------
| Certificate prints report
|--------------------------------------------------------------------------
*/

test('the prints report renders RCU rows with unit, label, branch and division', function () {
    displayPrint($this->unit);

    $this->actingAs($this->national)
        ->get(route('certificates.prints-report'))
        ->assertOk()
        ->assertSeeInOrder(['Unit Display', 'Red Cross Unit', 'Alpha Branch', 'Alpha One', 'Unit Display', 'RCU – Membership']);
});

test('the type, branch, division and unit filters include RCU rows', function () {
    $rcuPrint = displayPrint($this->unit);
    $org = Organisation::create(['name' => 'Org Row', 'branch_id' => $this->branchA->id]);
    $orgPrint = CertificatePrint::create(['organisation_id' => $org->id, 'printed_by_user_id' => $this->national->id, 'certificate_type' => 'organisation_membership', 'printed_at' => now()]);
    $otherUnit = RedCrossUnit::create(['name' => 'Unit Beta', 'division_id' => $this->divisionB1->id]);
    $otherPrint = displayPrint($otherUnit);

    $ids = fn (array $params) => $this->actingAs($this->national)
        ->get(route('certificates.prints-report', $params))
        ->viewData('certificatePrints')->pluck('id')->sort()->values()->all();

    expect($ids(['certificate_type' => 'rcu_membership']))->toBe([$rcuPrint->id, $otherPrint->id])
        ->and($ids(['branch_id' => $this->branchA->id]))->toBe([$rcuPrint->id, $orgPrint->id])
        ->and($ids(['division_id' => $this->divisionA1->id]))->toBe([$rcuPrint->id])
        ->and($ids(['red_cross_unit_id' => $otherUnit->id]))->toBe([$otherPrint->id]);
});

test('branch and division admins only see in-scope RCU rows', function () {
    $a1 = displayPrint($this->unit);
    $a2 = displayPrint(RedCrossUnit::create(['name' => 'Unit A2', 'division_id' => $this->divisionA2->id]));
    $b1 = displayPrint(RedCrossUnit::create(['name' => 'Unit B1', 'division_id' => $this->divisionB1->id]));

    $ids = fn (User $user) => $this->actingAs($user)
        ->get(route('certificates.prints-report'))
        ->viewData('certificatePrints')->pluck('id')->sort()->values()->all();

    expect($ids($this->national))->toBe([$a1->id, $a2->id, $b1->id])
        ->and($ids($this->branchAdmin))->toBe([$a1->id, $a2->id])
        ->and($ids($this->divisionAdmin))->toBe([$a1->id]);
});

test('soft-deleted RCU prints are hidden from the report, like organisation prints', function () {
    $kept = displayPrint($this->unit);
    displayPrint($this->unit)->delete();

    $org = Organisation::create(['name' => 'Org Row', 'branch_id' => $this->branchA->id]);
    CertificatePrint::create(['organisation_id' => $org->id, 'printed_by_user_id' => $this->national->id, 'certificate_type' => 'organisation_membership', 'printed_at' => now()])->delete();

    expect($this->actingAs($this->national)->get(route('certificates.prints-report'))
        ->viewData('certificatePrints')->pluck('id')->all())->toBe([$kept->id]);
});

test('RCU rows in the prints report do not add per-row queries', function () {
    $org = Organisation::create(['name' => 'Org Row', 'branch_id' => $this->branchA->id]);
    CertificatePrint::create(['organisation_id' => $org->id, 'printed_by_user_id' => $this->national->id, 'certificate_type' => 'organisation_membership', 'printed_at' => now()]);
    displayPrint($this->unit);

    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->national)->get(route('certificates.prints-report'))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $count(); // warm-up
    $withOne = $count();

    foreach (range(1, 5) as $i) {
        displayPrint(RedCrossUnit::create(['name' => "Unit Extra {$i}", 'division_id' => $this->divisionA2->id]));
    }

    expect($count())->toBe($withOne);
});

/*
|--------------------------------------------------------------------------
| Admin activity report
|--------------------------------------------------------------------------
*/

test('the admin activity report attributes RCU prints via the unit\'s division, at every drill level', function () {
    $a2Unit = RedCrossUnit::create(['name' => 'Unit A2', 'division_id' => $this->divisionA2->id]);
    $b1Unit = RedCrossUnit::create(['name' => 'Unit B1', 'division_id' => $this->divisionB1->id]);
    displayPrint($this->unit);
    displayPrint($this->unit);
    displayPrint($a2Unit);
    displayPrint($b1Unit);

    $get = fn (array $params = []) => $this->actingAs($this->national)
        ->get(route('reports.admin-activities.index', array_merge(['tab' => 'certificates', 'certificate_type' => 'rcu_membership'], $params)))
        ->assertOk();

    $national = $get();
    $byBranch = $national->viewData('drillRows')->pluck('total', 'name');
    expect($byBranch['Alpha Branch'])->toBe(3)
        ->and($byBranch['Beta Branch'])->toBe(1)
        ->and($national->viewData('drillRowField'))->toBe('branch_id')
        ->and(array_sum($national->viewData('certificateTrend')['values']))->toBe(4);
    $national->assertSee('RCU – Membership');

    $branch = $get(['branch_id' => $this->branchA->id]);
    expect($branch->viewData('drillLevel'))->toBe('division')
        ->and($branch->viewData('drillRows')->pluck('total', 'name')->all())->toBe(['Alpha One' => 2, 'Alpha Two' => 1])
        ->and(array_sum($branch->viewData('certificateTrend')['values']))->toBe(3);

    $division = $get(['branch_id' => $this->branchA->id, 'division_id' => $this->divisionA1->id]);
    expect($division->viewData('drillLevel'))->toBe('unit')
        ->and($division->viewData('drillRows')->pluck('total', 'name')->all())->toBe(['Unit Display' => 2]);
});

test('regression: organisation certificate types are still clamped to branch level', function () {
    $response = $this->actingAs($this->national)
        ->get(route('reports.admin-activities.index', [
            'tab' => 'certificates', 'certificate_type' => 'organisation_membership',
            'branch_id' => $this->branchA->id, 'division_id' => $this->divisionA1->id,
        ]))
        ->assertOk();

    expect($response->viewData('drillLevel'))->toBe('branch')
        ->and($response->viewData('isOrganisationScoped'))->toBeTrue();
});
