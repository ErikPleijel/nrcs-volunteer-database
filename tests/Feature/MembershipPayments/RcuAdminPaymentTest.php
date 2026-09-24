<?php

/**
 * Group 5 of RCU annual fee payments: fee status on red-cross-units/index
 * and /show, staff registration of an RCU payment (red-cross-units/show →
 * Add Payment → membership-payments.store), and RCU payments on
 * membership-payments/index (label + Type filter).
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
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

    $permissions = ['manage-admin-panel', 'view_red_cross_unit', 'add_payments', 'view_payments'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);

    $this->leader = User::factory()->create(['first_name' => 'Lena', 'last_name' => 'Leader']);
    $this->assistant = User::factory()->create(['first_name' => 'Ade', 'last_name' => 'Assistant']);
    $this->unit = RedCrossUnit::create([
        'name' => 'Unit Admin',
        'division_id' => $this->division->id,
        'team_leader_user_id' => $this->leader->id,
        'assistant_team_leader_user_id' => $this->assistant->id,
        'is_active' => true,
    ]);

    $this->rcuFee = MembershipFee::factory()->forRedCrossUnits()->create(['name' => 'Unit Yearly', 'amount' => 20000]);
});

function rcuAdminUnit(string $name, array $overrides = []): RedCrossUnit
{
    return RedCrossUnit::create(array_merge([
        'name' => $name,
        'division_id' => test()->division->id,
        'is_active' => true,
    ], $overrides));
}

function rcuAdminPayment(RedCrossUnit $unit, array $overrides = []): MembershipPayment
{
    return MembershipPayment::factory()->approved()->create(array_merge([
        'user_id' => test()->leader->id,
        'red_cross_unit_id' => $unit->id,
        'membership_fee_id' => test()->rcuFee->id,
        'branch_id' => test()->branch->id,
        'division_id' => test()->division->id,
        'payment_date' => now()->subMonth()->toDateString(),
        'expiry_date' => now()->addMonths(11)->toDateString(),
    ], $overrides));
}

/*
|--------------------------------------------------------------------------
| red-cross-units/index
|--------------------------------------------------------------------------
*/

test('the index shows paid, expired and unpaid fee badges', function () {
    $paid = rcuAdminUnit('Unit P1');
    $expired = rcuAdminUnit('Unit P2');
    rcuAdminUnit('Unit P3');
    rcuAdminPayment($paid);
    rcuAdminPayment($expired, ['payment_date' => now()->subYears(2)->toDateString(), 'expiry_date' => now()->subYear()->toDateString()]);

    $this->actingAs($this->admin)
        ->get(route('red-cross-units.index', ['search' => 'Unit P']))
        ->assertOk()
        ->assertSeeInOrder(['Unit P1', 'Paid', 'Unit Yearly', 'Unit P2', 'Annual fee', 'Expired', 'Unit P3', '—']);
});

test('the fee badges do not add per-row queries', function () {
    $countQueries = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('red-cross-units.index'))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    rcuAdminPayment($this->unit);
    $countQueries(); // warm-up: first request also loads permission caches etc.
    $withOne = $countQueries();

    foreach (range(1, 5) as $i) {
        rcuAdminPayment(rcuAdminUnit("Unit Extra {$i}"));
    }
    $withSix = $countQueries();

    expect($withSix)->toBe($withOne);
});

test('the index fee filter isolates paid and unpaid units', function () {
    rcuAdminPayment($this->unit);
    rcuAdminUnit('Unit Never Paid');

    $paid = $this->actingAs($this->admin)->get(route('red-cross-units.index', ['fee' => 'paid']))->viewData('redCrossUnits');
    $unpaid = $this->actingAs($this->admin)->get(route('red-cross-units.index', ['fee' => 'unpaid']))->viewData('redCrossUnits');

    expect($paid->pluck('name')->all())->toBe(['Unit Admin'])
        ->and($unpaid->pluck('name')->all())->toBe(['Unit Never Paid']);
});

/*
|--------------------------------------------------------------------------
| red-cross-units/show
|--------------------------------------------------------------------------
*/

test('the show page has the Annual Fee card with Add Payment for an active unit with a leader', function () {
    rcuAdminPayment($this->unit);

    $this->actingAs($this->admin)
        ->get(route('red-cross-units.show', $this->unit))
        ->assertOk()
        ->assertSee('ANNUAL FEE')
        ->assertSee('Paid')
        ->assertSee('Unit Yearly')
        ->assertSee(route('red-cross-units.payments.create', $this->unit), false);
});

test('the show page hides Add Payment and asks for a team leader when the unit has none', function () {
    $unit = rcuAdminUnit('Unit Leaderless');

    $this->actingAs($this->admin)
        ->get(route('red-cross-units.show', $unit))
        ->assertOk()
        ->assertSee('Not Paid')
        ->assertSee('Assign a team leader before registering a payment for this unit.')
        ->assertDontSee(route('red-cross-units.payments.create', $unit), false);
});

test('the show page hides Add Payment for an archived unit', function () {
    $this->unit->update(['is_active' => false]);

    $this->actingAs($this->admin)
        ->get(route('red-cross-units.show', $this->unit))
        ->assertOk()
        ->assertDontSee(route('red-cross-units.payments.create', $this->unit), false);
});

/*
|--------------------------------------------------------------------------
| Admin registration
|--------------------------------------------------------------------------
*/

test('the create form offers only the unit\'s leaders as payer and only RCU fees', function () {
    $member = User::factory()->create(['first_name' => 'Mo', 'last_name' => 'Member', 'red_cross_unit_id' => $this->unit->id]);
    MembershipFee::factory()->create(['name' => 'Personal Gold']);

    $response = $this->actingAs($this->admin)
        ->get(route('red-cross-units.payments.create', $this->unit))
        ->assertOk()
        ->assertSee('Lena Leader')
        ->assertSee('Ade Assistant')
        ->assertDontSee('Mo Member')
        ->assertSee('Unit Yearly')
        ->assertDontSee('Personal Gold')
        ->assertSee('name="division_id" value="'.$this->division->id.'"', false);

    expect($response->viewData('payers')->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->leader->id, $this->assistant->id])->sort()->values()->all());
});

test('the create form redirects back for a unit with no leader or an archived unit', function () {
    $leaderless = rcuAdminUnit('Unit Leaderless');

    $this->actingAs($this->admin)
        ->get(route('red-cross-units.payments.create', $leaderless))
        ->assertRedirect(route('red-cross-units.show', $leaderless))
        ->assertSessionHas('error', 'Assign a team leader before registering a payment for this unit.');

    $this->unit->update(['is_active' => false]);

    $this->actingAs($this->admin)
        ->get(route('red-cross-units.payments.create', $this->unit))
        ->assertRedirect(route('red-cross-units.show', $this->unit))
        ->assertSessionHas('error');
});

test('registering an RCU payment creates a pending payment scoped to the unit', function () {
    $otherBranch = Branch::create(['name' => 'Other Branch', 'code' => 'OTH']);

    $this->actingAs($this->admin)
        ->post(route('membership-payments.store'), [
            'red_cross_unit_id' => $this->unit->id,
            'user_id' => $this->assistant->id,
            'membership_fee_id' => $this->rcuFee->id,
            'payment_date' => now()->toDateString(),
            'reference' => 'BANK-123',
            // Tampered hidden field — the unit's own branch wins.
            'branch_id' => $otherBranch->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('red-cross-units.show', $this->unit))
        ->assertSessionHas('success');

    $payment = MembershipPayment::withAnyApprovalStatus()->sole();
    expect($payment->red_cross_unit_id)->toBe($this->unit->id)
        ->and($payment->organisation_id)->toBeNull()
        ->and($payment->user_id)->toBe($this->assistant->id)
        ->and($payment->approval_status)->toBe(MembershipPayment::PENDING)
        ->and($payment->branch_id)->toBe($this->branch->id)
        ->and($payment->division_id)->toBe($this->division->id)
        ->and($payment->submitted_by_user_id)->toBe($this->admin->id);

    // Pending shows on the unit page, but the unit isn't paid until approved.
    $this->actingAs($this->admin)
        ->get(route('red-cross-units.show', $this->unit))
        ->assertSee($payment->payment_reference)
        ->assertSee('Not Paid');
});

test('store rejects an RCU payment whose payer does not lead the unit', function () {
    $member = User::factory()->create(['red_cross_unit_id' => $this->unit->id]);

    $this->actingAs($this->admin)
        ->post(route('membership-payments.store'), [
            'red_cross_unit_id' => $this->unit->id,
            'user_id' => $member->id,
            'membership_fee_id' => $this->rcuFee->id,
            'payment_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors(['red_cross_unit_id' => 'The payer must be the team leader or assistant team leader of this Red Cross Unit.']);

    expect(MembershipPayment::withAnyApprovalStatus()->count())->toBe(0);
});

test('store rejects an RCU payment for an archived unit', function () {
    $this->unit->update(['is_active' => false]);

    $this->actingAs($this->admin)
        ->post(route('membership-payments.store'), [
            'red_cross_unit_id' => $this->unit->id,
            'user_id' => $this->leader->id,
            'membership_fee_id' => $this->rcuFee->id,
            'payment_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('red_cross_unit_id');

    expect(MembershipPayment::withAnyApprovalStatus()->count())->toBe(0);
});

test('store keeps RCU fees and RCU payments together', function () {
    $personalFee = MembershipFee::factory()->create(['is_volunteer_fee' => false]);

    // A non-RCU fee on an RCU payment...
    $this->actingAs($this->admin)
        ->post(route('membership-payments.store'), [
            'red_cross_unit_id' => $this->unit->id,
            'user_id' => $this->leader->id,
            'membership_fee_id' => $personalFee->id,
            'payment_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('membership_fee_id');

    // ...and the RCU fee on a personal payment.
    $this->actingAs($this->admin)
        ->post(route('membership-payments.store'), [
            'user_id' => $this->leader->id,
            'membership_fee_id' => $this->rcuFee->id,
            'payment_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('membership_fee_id');

    expect(MembershipPayment::withAnyApprovalStatus()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| membership-payments/index
|--------------------------------------------------------------------------
*/

test('the payments index labels RCU payments and the Type filter isolates them', function () {
    $rcuPayment = rcuAdminPayment($this->unit);
    $personalPayment = MembershipPayment::factory()->approved()->create([
        'user_id' => User::factory()->create()->id,
        'membership_fee_id' => MembershipFee::factory()->create()->id,
        'branch_id' => $this->branch->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('membership-payments.index'))
        ->assertOk()
        ->assertSee('Red Cross Unit: Unit Admin')
        ->assertSee('Add RCU Payment')
        ->assertSee(route('red-cross-units.index'), false);

    $rcuOnly = $this->actingAs($this->admin)
        ->get(route('membership-payments.index', ['organisation_scope' => 'rcu']))
        ->viewData('membershipPayments');
    $personOnly = $this->actingAs($this->admin)
        ->get(route('membership-payments.index', ['organisation_scope' => 'person']))
        ->viewData('membershipPayments');

    expect($rcuOnly->pluck('id')->all())->toBe([$rcuPayment->id])
        ->and($personOnly->pluck('id')->all())->toBe([$personalPayment->id]);
});

test('the existing Red Cross Unit filter still matches payments by members of the unit, not payments for it', function () {
    // The leader is not a member of the unit they lead.
    $rcuPayment = rcuAdminPayment($this->unit);
    $member = User::factory()->create(['red_cross_unit_id' => $this->unit->id]);
    $memberPayment = MembershipPayment::factory()->approved()->create([
        'user_id' => $member->id,
        'membership_fee_id' => MembershipFee::factory()->create(['is_volunteer_fee' => true])->id,
        'branch_id' => $this->branch->id,
    ]);

    $payments = $this->actingAs($this->admin)
        ->get(route('membership-payments.index', ['red_cross_unit_id' => $this->unit->id]))
        ->viewData('membershipPayments');

    expect($payments->pluck('id')->all())->toBe([$memberPayment->id])
        ->and($payments->pluck('id'))->not->toContain($rcuPayment->id);
});
