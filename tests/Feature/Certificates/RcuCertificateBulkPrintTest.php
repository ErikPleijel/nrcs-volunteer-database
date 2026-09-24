<?php

/**
 * RCU certificate Group 2: the "Certificates for Red Cross Units" bulk page
 * (paid units only, scoped to the admin's branch/division), its per-fee-
 * period Printed badge, the two print endpoints (server-side eligibility
 * re-check), and Mark as printed.
 */

use App\Models\Branch;
use App\Models\CertificatePrint;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
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

    $permissions = ['manage-admin-panel', 'print_certificates', 'view_certificates'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    foreach (['national_db_administrator', 'branch_db_administrator', 'division_db_assistant_operations'] as $role) {
        Role::findOrCreate($role, 'web')->syncPermissions($permissions);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branchA = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->branchB = Branch::create(['name' => 'Beta Branch', 'code' => 'BET']);
    $this->divisionA1 = Division::create(['name' => 'Alpha One', 'branch_id' => $this->branchA->id]);
    $this->divisionA2 = Division::create(['name' => 'Alpha Two', 'branch_id' => $this->branchA->id]);
    $this->divisionB1 = Division::create(['name' => 'Beta One', 'branch_id' => $this->branchB->id]);

    $this->national = User::factory()->create();
    $this->national->assignRole('national_db_administrator');

    $this->branchAdmin = User::factory()->create(['branch_id' => $this->branchA->id]);
    $this->branchAdmin->assignRole('branch_db_administrator');

    $this->divisionAdmin = User::factory()->create(['branch_id' => $this->branchA->id, 'division_id' => $this->divisionA1->id]);
    $this->divisionAdmin->assignRole('division_db_assistant_operations');

    $this->rcuFee = MembershipFee::factory()->forRedCrossUnits()->create(['name' => 'Unit Yearly', 'amount' => 20000]);
});

function bulkUnit(string $name, Division $division, bool $paid = true, array $paymentOverrides = []): RedCrossUnit
{
    $unit = RedCrossUnit::create(['name' => $name, 'division_id' => $division->id, 'is_active' => true]);

    if ($paid) {
        MembershipPayment::factory()->approved()->create(array_merge([
            'user_id' => User::factory()->create()->id,
            'red_cross_unit_id' => $unit->id,
            'membership_fee_id' => test()->rcuFee->id,
            'payment_date' => now()->subMonth()->toDateString(),
            'expiry_date' => now()->addMonths(11)->toDateString(),
        ], $paymentOverrides));
    }

    return $unit;
}

function listedUnitNames($response): array
{
    return $response->viewData('records')->pluck('name')->sort()->values()->all();
}

/*
|--------------------------------------------------------------------------
| Index: eligibility and scoping
|--------------------------------------------------------------------------
*/

test('the page lists only currently paid, active units, with fee and expiry on the cards', function () {
    $paid = bulkUnit('Unit Paid', $this->divisionA1);
    bulkUnit('Unit Unpaid', $this->divisionA1, paid: false);
    bulkUnit('Unit Lapsed', $this->divisionA1, paymentOverrides: ['payment_date' => '2024-01-01', 'expiry_date' => '2025-01-01']);
    $pending = bulkUnit('Unit Pending', $this->divisionA1, paid: false);
    MembershipPayment::factory()->create(['user_id' => $this->national->id, 'red_cross_unit_id' => $pending->id, 'membership_fee_id' => $this->rcuFee->id]);
    bulkUnit('Unit Archived', $this->divisionA1)->update(['is_active' => false]);

    $response = $this->actingAs($this->national)
        ->get(route('red-cross-units.certificates.index'))
        ->assertOk()
        ->assertSeeInOrder([
            'Unit Paid', 'Alpha One', 'Alpha Branch', $paid->fresh()->rcu_reference,
            'Paid', 'Unit Yearly', $paid->activeMembership->expiry_date->format('M d, Y'),
        ]);

    expect(listedUnitNames($response))->toBe(['Unit Paid']);
});

test('scoping: national sees all, branch admin sees own branch, division admin sees own division', function () {
    bulkUnit('Unit A1', $this->divisionA1);
    bulkUnit('Unit A2', $this->divisionA2);
    bulkUnit('Unit B1', $this->divisionB1);

    expect(listedUnitNames($this->actingAs($this->national)->get(route('red-cross-units.certificates.index'))))
        ->toBe(['Unit A1', 'Unit A2', 'Unit B1'])
        ->and(listedUnitNames($this->actingAs($this->branchAdmin)->get(route('red-cross-units.certificates.index'))))
        ->toBe(['Unit A1', 'Unit A2'])
        ->and(listedUnitNames($this->actingAs($this->divisionAdmin)->get(route('red-cross-units.certificates.index'))))
        ->toBe(['Unit A1']);

    // A branch admin can't widen their scope through the filters.
    expect(listedUnitNames($this->actingAs($this->branchAdmin)->get(route('red-cross-units.certificates.index', ['branch_id' => $this->branchB->id]))))
        ->toBe(['Unit A1', 'Unit A2'])
        ->and(listedUnitNames($this->actingAs($this->divisionAdmin)->get(route('red-cross-units.certificates.index', ['division_id' => $this->divisionA2->id]))))
        ->toBe(['Unit A1']);
});

test('branch, division and search filters narrow the list for a national admin', function () {
    $a1 = bulkUnit('Unit A1', $this->divisionA1);
    bulkUnit('Unit A2', $this->divisionA2);
    bulkUnit('Unit B1', $this->divisionB1);

    $get = fn (array $params) => listedUnitNames($this->actingAs($this->national)->get(route('red-cross-units.certificates.index', $params)));

    expect($get(['branch_id' => $this->branchA->id]))->toBe(['Unit A1', 'Unit A2'])
        ->and($get(['branch_id' => $this->branchA->id, 'division_id' => $this->divisionA2->id]))->toBe(['Unit A2'])
        ->and($get(['search' => 'B1']))->toBe(['Unit B1'])
        ->and($get(['search' => (string) $a1->id]))->toContain('Unit A1');
});

test('a user without print_certificates cannot open the page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('red-cross-units.certificates.index'))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Per-period Printed badge
|--------------------------------------------------------------------------
*/

test('Printed is per fee period: a print from the previous period no longer counts after renewal', function () {
    $unit = bulkUnit('Unit Renewed', $this->divisionA1, paid: false);

    // Previous period, printed during it.
    MembershipPayment::factory()->approved()->create([
        'user_id' => $this->national->id, 'red_cross_unit_id' => $unit->id, 'membership_fee_id' => $this->rcuFee->id,
        'payment_date' => now()->subYear()->subMonth()->toDateString(), 'expiry_date' => now()->subMonth()->toDateString(),
    ]);
    CertificatePrint::create([
        'red_cross_unit_id' => $unit->id, 'printed_by_user_id' => $this->national->id,
        'certificate_type' => 'rcu_membership', 'printed_at' => now()->subMonths(6),
    ]);

    // Current period (renewed a week ago).
    MembershipPayment::factory()->approved()->create([
        'user_id' => $this->national->id, 'red_cross_unit_id' => $unit->id, 'membership_fee_id' => $this->rcuFee->id,
        'payment_date' => now()->subWeek()->toDateString(), 'expiry_date' => now()->addYear()->subWeek()->toDateString(),
    ]);

    $keys = fn () => $this->actingAs($this->national)->get(route('red-cross-units.certificates.index'))->viewData('printedKeys');

    expect($keys()->has($unit->id))->toBeFalse();

    // Printed within the current period → Printed.
    CertificatePrint::create([
        'red_cross_unit_id' => $unit->id, 'printed_by_user_id' => $this->national->id,
        'certificate_type' => 'rcu_membership', 'printed_at' => now(),
    ]);

    expect($keys()->has($unit->id))->toBeTrue();
});

test('a print on the first day of the current period counts as Printed', function () {
    $unit = bulkUnit('Unit Same Day', $this->divisionA1, paymentOverrides: ['payment_date' => now()->subDays(3)->toDateString()]);
    CertificatePrint::create([
        'red_cross_unit_id' => $unit->id, 'printed_by_user_id' => $this->national->id,
        'certificate_type' => 'rcu_membership', 'printed_at' => now()->subDays(3)->startOfDay()->addHours(9),
    ]);

    $this->actingAs($this->national)
        ->get(route('red-cross-units.certificates.index'))
        ->assertSeeInOrder(['Unit Same Day', 'Printed']);
});

/*
|--------------------------------------------------------------------------
| Print endpoints
|--------------------------------------------------------------------------
*/

test('both print endpoints render the selected paid units with their QR codes', function (string $routeName) {
    $unit = bulkUnit('Unit Print', $this->divisionA1);

    $this->actingAs($this->national)
        ->post(route($routeName), ['certificate_type' => 'rcu_membership', 'training_ids' => [$unit->id]])
        ->assertOk()
        ->assertSee('Unit Print')
        ->assertSee('is a registered Red Cross Unit of the')
        ->assertSee('data:image/svg+xml;base64', false);
})->with(['red-cross-units.certificates.print.plain', 'red-cross-units.certificates.print.branded']);

test('ineligible units in a selection are dropped server-side', function (string $routeName) {
    $ok = bulkUnit('Unit Eligible', $this->divisionA1);
    $unpaid = bulkUnit('Unit Unpaid', $this->divisionA1, paid: false);
    $otherBranch = bulkUnit('Unit Other Branch', $this->divisionB1);

    $this->actingAs($this->branchAdmin)
        ->post(route($routeName), ['certificate_type' => 'rcu_membership', 'training_ids' => [$ok->id, $unpaid->id, $otherBranch->id]])
        ->assertOk()
        ->assertSee('Unit Eligible')
        ->assertDontSee('Unit Unpaid')
        ->assertDontSee('Unit Other Branch');
})->with(['red-cross-units.certificates.print.plain', 'red-cross-units.certificates.print.branded']);

test('a selection with nothing eligible redirects back with a message', function (string $routeName) {
    $unpaid = bulkUnit('Unit Unpaid', $this->divisionA1, paid: false);
    $otherBranch = bulkUnit('Unit Other Branch', $this->divisionB1);

    $this->actingAs($this->branchAdmin)
        ->from(route('red-cross-units.certificates.index'))
        ->post(route($routeName), ['certificate_type' => 'rcu_membership', 'training_ids' => [$unpaid->id, $otherBranch->id]])
        ->assertRedirect(route('red-cross-units.certificates.index'))
        ->assertSessionHas('error', 'None of the selected Red Cross Units are currently eligible for this certificate.');

    $this->actingAs($this->branchAdmin)
        ->withSession(['error' => 'None of the selected Red Cross Units are currently eligible for this certificate.'])
        ->get(route('red-cross-units.certificates.index'))
        ->assertSee('None of the selected Red Cross Units are currently eligible for this certificate.');
})->with(['red-cross-units.certificates.print.plain', 'red-cross-units.certificates.print.branded']);

/*
|--------------------------------------------------------------------------
| Mark as printed
|--------------------------------------------------------------------------
*/

test('Mark as printed records the eligible units and flips their badge', function () {
    $unit = bulkUnit('Unit Mark', $this->divisionA1);
    $otherBranch = bulkUnit('Unit Other Branch', $this->divisionB1);

    $this->actingAs($this->branchAdmin)
        ->get(route('red-cross-units.certificates.index'))
        ->assertSeeInOrder(['Unit Mark', 'Not printed']);

    $this->actingAs($this->branchAdmin)
        ->postJson(route('certificates.mark-as-printed'), [
            'certificate_type' => 'rcu_membership',
            'training_ids' => [$unit->id, $otherBranch->id],
        ])
        ->assertOk()
        ->assertJson(['message' => 'Successfully marked 1 record as printed.']);

    $print = CertificatePrint::sole();
    expect($print->red_cross_unit_id)->toBe($unit->id)
        ->and($print->printed_by_user_id)->toBe($this->branchAdmin->id)
        ->and($print->user_id)->toBeNull()
        ->and($print->organisation_id)->toBeNull();

    $this->actingAs($this->branchAdmin)
        ->get(route('red-cross-units.certificates.index'))
        ->assertSeeInOrder(['Unit Mark', 'Printed'])
        ->assertDontSee('Not printed');
});

/*
|--------------------------------------------------------------------------
| Entry points
|--------------------------------------------------------------------------
*/

test('certificates index links to both the organisation and the RCU bulk pages', function () {
    $this->actingAs($this->national)
        ->get(route('certificates.index'))
        ->assertOk()
        ->assertSeeInOrder([
            route('organisations.certificates.index'), 'Certificates for organisations',
            route('red-cross-units.certificates.index'), 'Certificates for Red Cross Units',
        ]);
});
