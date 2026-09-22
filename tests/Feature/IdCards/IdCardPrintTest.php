<?php

/**
 * Feature tests for the member/volunteer ID card rendering changes:
 * - the header banner text ("MEMBERSHIP IDENTITY CARD" vs "VOLUNTEER IDENTITY CARD")
 * - the "Memb. category" box, which shows the fee name (members) or the Red
 *   Cross unit name (volunteers), with a matching caption swap
 * - the full-name row (first + last name only, no middle name)
 * - the DB code, which must no longer include the Red Cross unit segment
 *
 * Both printCard() (single) and printBulkCards() (bulk) render the same
 * id-cards.print view, so both are covered here to keep them from drifting
 * out of sync with each other.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\RedCrossUnit;
use App\Models\User;
use Database\Factories\MembershipFeeFactory;
use Database\Factories\MembershipPaymentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // The id-card print routes sit inside routes/web.php's outer
    // can:manage-admin-panel group as well as their own can:print_idcards
    // group — both permissions are required.
    foreach (['manage-admin-panel', 'print_idcards'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['manage-admin-panel', 'print_idcards']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);
});

/** A member: valid personal payment, no Red Cross unit. */
function makeMemberForCard(Branch $branch, Division $division, ?MembershipFee $fee = null): User
{
    $user = User::factory()->withNationalId()->create([
        'first_name' => 'Chidinma',
        'middle_name' => 'Obiageli',
        'last_name' => 'Okonkwo',
        'branch_id' => $branch->id,
        'division_id' => $division->id,
        'picture' => 'pic.jpg',
        'signature' => 'sig.jpg',
    ]);

    $fee ??= MembershipFeeFactory::new()->create(['name' => 'Ordinary Member', 'is_volunteer_fee' => false]);

    MembershipPaymentFactory::new()->approved()->create([
        'user_id' => $user->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subMonth(),
        'expiry_date' => now()->addYear(),
    ]);

    return $user->fresh();
}

/** A volunteer: attached to an active Red Cross unit, no payment. */
function makeVolunteerForCard(Branch $branch, Division $division, ?RedCrossUnit $unit = null): User
{
    $unit ??= RedCrossUnit::create(['name' => 'Holy Mary RC Unit', 'division_id' => $division->id]);

    return User::factory()->withNationalId()->create([
        'first_name' => 'Chidinma',
        'middle_name' => 'Obiageli',
        'last_name' => 'Okonkwo',
        'branch_id' => $branch->id,
        'division_id' => $division->id,
        'red_cross_unit_id' => $unit->id,
        'picture' => 'pic.jpg',
        'signature' => 'sig.jpg',
    ])->fresh();
}

/*
|--------------------------------------------------------------------------
| Single print — printCard()
|--------------------------------------------------------------------------
*/

test('single card header and category box for a member', function () {
    $member = makeMemberForCard($this->branch, $this->division);

    $html = $this->actingAs($this->admin)
        ->get(route('id-card.print', $member))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('MEMBERSHIP IDENTITY CARD')
        ->not->toContain('VOLUNTEER IDENTITY CARD')
        ->toContain('Memb. category')
        ->toContain('ORDINARY MEMBER');
});

test('single card header and category box for a volunteer', function () {
    $volunteer = makeVolunteerForCard($this->branch, $this->division);

    $html = $this->actingAs($this->admin)
        ->get(route('id-card.print', $volunteer))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('VOLUNTEER IDENTITY CARD')
        ->not->toContain('MEMBERSHIP IDENTITY CARD')
        ->toContain('Red Cross unit')
        ->not->toContain('Memb. category')
        ->toContain('HOLY MARY RC UNIT');
});

test('single card shows one full-name field, first + last only, no middle name', function () {
    $member = makeMemberForCard($this->branch, $this->division);

    $html = $this->actingAs($this->admin)
        ->get(route('id-card.print', $member))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('FULL NAME')
        ->toContain('CHIDINMA OKONKWO')
        ->not->toContain('OBIAGELI')
        ->not->toContain('>Surname<')
        ->not->toContain('>Firstname<');
});

test('single card DB code omits the Red Cross unit segment', function () {
    $volunteer = makeVolunteerForCard($this->branch, $this->division);

    $html = $this->actingAs($this->admin)
        ->get(route('id-card.print', $volunteer))
        ->assertOk()
        ->getContent();

    // Short format: DB-{id}-{BRANCH}-{DIVISION} — no unit segment, no slashes.
    expect($html)->toContain($volunteer->user_id_reference_short)
        ->and($volunteer->user_id_reference_short)->not->toContain('/')
        ->and($html)->not->toContain($volunteer->user_id_reference);
});

/*
|--------------------------------------------------------------------------
| Bulk print — printBulkCards()
|--------------------------------------------------------------------------
*/

test('bulk print renders member and volunteer cards correctly in the same batch', function () {
    $member = makeMemberForCard($this->branch, $this->division);
    $volunteer = makeVolunteerForCard($this->branch, $this->division);

    $payload = [
        'user_ids' => json_encode([
            ['id' => $member->id, 'validity' => 36],
            ['id' => $volunteer->id, 'validity' => 36],
        ]),
    ];

    $html = $this->actingAs($this->admin)
        ->post(route('id-cards.print-bulk'), $payload)
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('MEMBERSHIP IDENTITY CARD')
        ->toContain('VOLUNTEER IDENTITY CARD')
        ->toContain('Memb. category')
        ->toContain('Red Cross unit')
        ->toContain('ORDINARY MEMBER')
        ->toContain('HOLY MARY RC UNIT')
        ->toContain('CHIDINMA OKONKWO');
});
