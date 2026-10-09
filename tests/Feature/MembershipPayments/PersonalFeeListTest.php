<?php

/**
 * One fee list for an individual's own membership payment, on the online page
 * (/make-payment) and the staff form (/membership-payments/create) alike:
 * every active personal fee, split into "Member fees" and "Volunteer fees",
 * whatever the person's unit or preference. Explainers guide the choice
 * instead of hiding or rejecting fees (Decisions.md 2026-10-09).
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const GENERAL_EXPLAINER_SELF = 'If you are a volunteer, choose a volunteer fee — or a member fee if you wish to give more.';
const GENERAL_EXPLAINER_STAFF = 'If this person is a volunteer, choose a volunteer fee — or a member fee if they wish to give more.';

beforeEach(function () {
    $this->withoutVite();
    config(['paystack.enabled' => true, 'paystack.public_key' => 'pk_test_public', 'paystack.secret_key' => 'sk_test_secret']);

    foreach (['manage-admin-panel', 'view_user', 'add_payments', 'view_payments'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions([
        'manage-admin-panel', 'view_user', 'add_payments', 'view_payments',
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $branch = Branch::create(['name' => 'Fee List Branch', 'code' => 'FLB']);
    $division = Division::create(['name' => 'Fee List Division', 'branch_id' => $branch->id]);
    $this->unit = RedCrossUnit::create(['name' => 'Fee List Unit', 'division_id' => $division->id, 'is_active' => true]);

    $this->gold1 = MembershipFee::factory()->create(['name' => 'Gold', 'amount' => 10000, 'validity_years' => 1, 'is_volunteer_fee' => false]);
    $this->gold3 = MembershipFee::factory()->create(['name' => 'Gold', 'amount' => 30000, 'validity_years' => 3, 'is_volunteer_fee' => false]);
    $this->bronze1 = MembershipFee::factory()->create(['name' => 'Bronze', 'amount' => 1500, 'validity_years' => 1, 'is_volunteer_fee' => false]);
    $this->detachment1 = MembershipFee::factory()->create(['name' => 'Detachment', 'amount' => 200, 'validity_years' => 1, 'is_volunteer_fee' => true]);
    $this->detachment3 = MembershipFee::factory()->create(['name' => 'Detachment', 'amount' => 600, 'validity_years' => 3, 'is_volunteer_fee' => true]);
    $this->associate1 = MembershipFee::factory()->create(['name' => 'Associate', 'amount' => 2000, 'validity_years' => 1, 'is_volunteer_fee' => true]);

    // Never offered to an individual.
    MembershipFee::factory()->create(['name' => 'Retired', 'is_active' => false]);
    MembershipFee::factory()->create(['name' => 'Corporate Gold', 'for_organizations' => true]);
    $this->rcuFee = MembershipFee::factory()->forRedCrossUnits()->create(['name' => 'RCU annual fee']);
});

function feeListPerson(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'lifecycle_status' => 'active',
        'can_contribute_volunteering' => false,
        'can_contribute_member' => true,
        'red_cross_unit_id' => null,
    ], $attributes));
}

/** A person who left their unit and holds no current member fee (User::isUnassignedGhost()). */
function feeListFormerUnitMember(): User
{
    $user = feeListPerson();
    $user->forceFill(['assigned_rcu_date' => now()->subYear()->toDateString()])->save();

    return $user->fresh();
}

/** Fee ids per <optgroup> label, in page order, from the given select. */
function feeGroupsOnPage(string $html, string $selectId): array
{
    preg_match('/<select[^>]*id="'.$selectId.'".*?<\/select>/s', $html, $select);
    preg_match_all('/<optgroup label="([^"]+)"[^>]*>(.*?)<\/optgroup>/s', $select[0] ?? '', $groups, PREG_SET_ORDER);

    $result = [];
    foreach ($groups as [, $label, $body]) {
        preg_match_all('/<option value="(\d+)"/', $body, $ids);
        $result[$label] = array_map('intval', $ids[1]);
    }

    return $result;
}

/** Whether the given explainer box is rendered visible (no "hidden" class). */
function feeAdviceVisible(string $html, string $advice): bool
{
    preg_match('/data-fee-advice="'.$advice.'"\s+class="([^"]*)"/', $html, $match);

    expect($match)->not->toBeEmpty();

    return ! in_array('hidden', preg_split('/\s+/', trim($match[1])), true);
}

/*
|--------------------------------------------------------------------------
| The shared list and its groups
|--------------------------------------------------------------------------
*/

test('offeredToPersons lists active personal fees by amount, 1- and 3-year versions together', function () {
    expect(MembershipFee::offeredToPersons()->pluck('id')->all())->toBe([
        $this->detachment1->id, $this->detachment3->id,
        $this->bronze1->id,
        $this->associate1->id,
        $this->gold1->id, $this->gold3->id,
    ]);
});

test('personalFeeGroups splits into member and volunteer fees and orders the groups', function () {
    $fees = MembershipFee::offeredToPersons();

    $memberFirst = MembershipFee::personalFeeGroups($fees);
    expect(array_column($memberFirst, 'label'))->toBe(['Member fees', 'Volunteer fees'])
        ->and($memberFirst[0]['fees']->pluck('id')->all())->toBe([$this->bronze1->id, $this->gold1->id, $this->gold3->id])
        ->and($memberFirst[1]['fees']->pluck('id')->all())->toBe([$this->detachment1->id, $this->detachment3->id, $this->associate1->id]);

    expect(array_column(MembershipFee::personalFeeGroups($fees, true), 'label'))->toBe(['Volunteer fees', 'Member fees']);
});

test('both pages offer the same grouped fees', function (bool $inUnit) {
    $person = feeListPerson(['red_cross_unit_id' => $inUnit ? $this->unit->id : null]);

    $online = $this->actingAs($person)->get(route('make-payment.show', ['payment_type' => 'membership']))->assertOk();
    $staff = $this->actingAs($this->admin)->get(route('membership-payments.create', $person))->assertOk();

    $onlineGroups = feeGroupsOnPage($online->getContent(), 'personal_membership_fee_id');
    $staffGroups = feeGroupsOnPage($staff->getContent(), 'membership_fee_id');

    $expectedOrder = $inUnit ? ['Volunteer fees', 'Member fees'] : ['Member fees', 'Volunteer fees'];

    expect($onlineGroups)->toBe($staffGroups)
        ->and(array_keys($onlineGroups))->toBe($expectedOrder)
        ->and($onlineGroups['Member fees'])->toBe([$this->bronze1->id, $this->gold1->id, $this->gold3->id])
        ->and($onlineGroups['Volunteer fees'])->toBe([$this->detachment1->id, $this->detachment3->id, $this->associate1->id]);
})->with([
    'unit member' => [true],
    'not in a unit' => [false],
]);

test('the staff form without a selected person shows both groups, member fees first', function () {
    $html = $this->actingAs($this->admin)->get(route('membership-payments.create'))->assertOk()->getContent();

    expect(array_keys(feeGroupsOnPage($html, 'membership_fee_id')))->toBe(['Member fees', 'Volunteer fees']);
});

test('fee options keep the data attributes the summary and advice read', function () {
    $this->actingAs(feeListPerson())->get(route('make-payment.show', ['payment_type' => 'membership']))
        ->assertSee('data-amount="200.00"', false)
        ->assertSee('data-id-card-fee=', false)
        ->assertSee('data-volunteer-fee="1"', false)
        ->assertSee('data-volunteer-fee="0"', false);
});

/*
|--------------------------------------------------------------------------
| Server checks: every personal fee is accepted for every individual
|--------------------------------------------------------------------------
*/

test('staff store accepts a member or volunteer fee whatever the person\'s unit', function (string $feeProperty, bool $inUnit) {
    $person = feeListPerson(['red_cross_unit_id' => $inUnit ? $this->unit->id : null]);

    $this->actingAs($this->admin)->post(route('membership-payments.store'), [
        'user_id' => $person->id,
        'membership_fee_id' => test()->{$feeProperty}->id,
        'payment_date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect(MembershipPayment::withAnyApprovalStatus()->where('user_id', $person->id)->sole()->membership_fee_id)
        ->toBe(test()->{$feeProperty}->id);
})->with([
    'volunteer fee, not in a unit' => ['detachment1', false],
    'member fee, unit member' => ['gold1', true],
    'volunteer fee, unit member' => ['detachment1', true],
    'member fee, not in a unit' => ['gold1', false],
]);

test('staff store still refuses an RCU fee as a personal payment', function () {
    $person = feeListPerson(['red_cross_unit_id' => $this->unit->id]);

    $this->actingAs($this->admin)->post(route('membership-payments.store'), [
        'user_id' => $person->id,
        'membership_fee_id' => $this->rcuFee->id,
        'payment_date' => now()->toDateString(),
    ])->assertSessionHasErrors(['membership_fee_id' => 'A Red Cross Unit fee can only be registered for a Red Cross Unit.']);
});

test('the online page still lists only organisation fees for an organisation payment', function () {
    $person = feeListPerson();
    $organisation = Organisation::create(['name' => 'Fee List Org']);
    $person->organisations()->attach($organisation->id);

    $this->actingAs($person)->get(route('make-payment.show', ['payment_type' => 'membership']))
        ->assertViewHas('organisationMembershipFees', fn ($fees) => $fees->pluck('name')->all() === ['Corporate Gold'])
        ->assertViewHas('personalMembershipFees', fn ($fees) => ! $fees->contains('name', 'Corporate Gold') && ! $fees->contains('name', 'RCU annual fee'));
});

/*
|--------------------------------------------------------------------------
| Explainers
|--------------------------------------------------------------------------
*/

test('both pages show the general explainer', function () {
    $person = feeListPerson();

    $this->actingAs($person)->get(route('make-payment.show', ['payment_type' => 'membership']))
        ->assertSee('Member fees are for supporting members who support the Red Cross financially.')
        ->assertSee(GENERAL_EXPLAINER_SELF);

    $this->actingAs($this->admin)->get(route('membership-payments.create', $person))
        ->assertSee('Member fees are for supporting members who support the Red Cross financially.')
        ->assertSee(GENERAL_EXPLAINER_STAFF);
});

test('the volunteer-fee advice is rendered hidden until a volunteer fee is picked', function () {
    $html = $this->actingAs(feeListPerson())->get(route('make-payment.show', ['payment_type' => 'membership']))
        ->assertSee('Volunteer fees are for volunteers in a Red Cross Unit, and you are not in a unit at the moment.')
        ->getContent();

    expect(feeAdviceVisible($html, 'volunteer-fee-no-unit'))->toBeFalse();
});

test('the left-unit advice shows for someone who left their unit, on both pages', function () {
    $former = feeListFormerUnitMember();

    $online = $this->actingAs($former)->get(route('make-payment.show', ['payment_type' => 'membership']))
        ->assertSee('You are no longer in a Red Cross Unit.')
        ->getContent();
    expect(feeAdviceVisible($online, 'left-unit'))->toBeTrue();

    $staff = $this->actingAs($this->admin)->get(route('membership-payments.create', $former))
        ->assertSee('This person has left their Red Cross Unit.')
        ->getContent();
    expect(feeAdviceVisible($staff, 'left-unit'))->toBeTrue();
});

test('the left-unit advice stays hidden for others', function (string $case) {
    $person = match ($case) {
        'never in a unit' => feeListPerson(),
        'unit member' => feeListPerson(['red_cross_unit_id' => $this->unit->id]),
        'left unit, holds a member fee' => tap(feeListFormerUnitMember(), fn (User $u) => MembershipPayment::factory()->approved()->create([
            'user_id' => $u->id,
            'membership_fee_id' => $this->gold1->id,
            'payment_date' => now()->subMonth()->toDateString(),
            'expiry_date' => now()->addMonths(11)->toDateString(),
        ])),
    };

    $html = $this->actingAs($this->admin)->get(route('membership-payments.create', $person))->getContent();

    expect(feeAdviceVisible($html, 'left-unit'))->toBeFalse();
})->with(['never in a unit', 'unit member', 'left unit, holds a member fee']);

test('the staff search returns the unit facts the explainers use', function () {
    $former = feeListFormerUnitMember();
    $former->forceFill(['first_name' => 'Zebedee', 'last_name' => 'Former'])->save();
    feeListPerson(['first_name' => 'Zebedee', 'last_name' => 'Unit', 'red_cross_unit_id' => $this->unit->id]);

    $results = collect($this->actingAs($this->admin)
        ->getJson(route('membership-payments.search-users', ['query' => 'Zebedee']))
        ->assertOk()
        ->json())->keyBy('last_name');

    expect($results['Former'])->toMatchArray(['in_active_unit' => false, 'left_unit' => true])
        ->and($results['Unit'])->toMatchArray(['in_active_unit' => true, 'left_unit' => false, 'rcu_name' => 'Fee List Unit']);
});
