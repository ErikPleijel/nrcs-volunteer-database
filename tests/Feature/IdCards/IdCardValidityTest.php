<?php

/**
 * Feature tests for fee-based ID card validity on the bulk-print page:
 * - a paid user's validity defaults to their fee's validity_years × 12
 *   (1-year fee → 12, 3-year fee → 36), shown as "ID paid N Year(s)"
 * - a user with no current personal payment (e.g. a volunteer) defaults to 12
 * - a blank per-card validity submitted to printBulkCards() /
 *   recordBulkIdCardPrints() falls back to that same rule, not the old
 *   payment-expiry / null fallback
 * - the "Showing X to Y of Z results" count is rendered once, above the grid
 *
 * The rule itself lives in User::defaultIdCardValidityMonths().
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\IdCardPrint;
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
    $this->withoutVite();

    foreach (['manage-admin-panel', 'view_idcards', 'print_idcards'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['manage-admin-panel', 'view_idcards', 'print_idcards']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);
});

/** A printable member with a current personal payment on a fee of the given duration. */
function makePaidMemberForValidity(Branch $branch, Division $division, int $validityYears): User
{
    $user = User::factory()->withNationalId()->create([
        'branch_id' => $branch->id,
        'division_id' => $division->id,
        'picture' => 'pic.jpg',
        'signature' => 'sig.jpg',
    ]);

    $fee = MembershipFeeFactory::new()->create(['validity_years' => $validityYears, 'is_volunteer_fee' => false]);

    MembershipPaymentFactory::new()->approved()->create([
        'user_id' => $user->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subMonth(),
        'expiry_date' => now()->subMonth()->addYears($validityYears),
        'id_card_included' => true,
    ]);

    return $user->fresh();
}

/** A printable volunteer: Red Cross unit, no payment. */
function makeUnpaidVolunteerForValidity(Branch $branch, Division $division): User
{
    $unit = RedCrossUnit::create(['name' => 'Holy Mary RC Unit', 'division_id' => $division->id]);

    return User::factory()->withNationalId()->create([
        'branch_id' => $branch->id,
        'division_id' => $division->id,
        'red_cross_unit_id' => $unit->id,
        'picture' => 'pic.jpg',
        'signature' => 'sig.jpg',
    ])->fresh();
}

/** The value="" of a user's per-card validity input on the rendered page. */
function validityInputValue(string $html, User $user): ?string
{
    preg_match('/id="validity-'.$user->id.'"[^>]*value="([^"]*)"/s', $html, $m);

    return $m[1] ?? null;
}

/*
|--------------------------------------------------------------------------
| Defaults on the prepare-bulk-print page
|--------------------------------------------------------------------------
*/

test('a 1-year fee payer defaults to 12 months and shows "ID paid 1 Year"', function () {
    $member = makePaidMemberForValidity($this->branch, $this->division, 1);

    $html = $this->actingAs($this->admin)
        ->get(route('id-cards.prepare-bulk-print'))
        ->assertOk()
        ->getContent();

    expect(validityInputValue($html, $member))->toBe('12')
        ->and($member->defaultIdCardValidityMonths())->toBe(12)
        ->and($html)->toContain('<strong>1 Year</strong>')
        ->and($html)->not->toContain('<strong>1 Year</strong>s');
});

test('a 3-year fee payer defaults to 36 months and shows "ID paid 3 Years"', function () {
    $member = makePaidMemberForValidity($this->branch, $this->division, 3);

    $html = $this->actingAs($this->admin)
        ->get(route('id-cards.prepare-bulk-print'))
        ->assertOk()
        ->getContent();

    expect(validityInputValue($html, $member))->toBe('36')
        ->and($member->defaultIdCardValidityMonths())->toBe(36)
        ->and($html)->toContain('<strong>3 Year</strong>s');
});

test('an unpaid volunteer defaults to 12 months', function () {
    $volunteer = makeUnpaidVolunteerForValidity($this->branch, $this->division);

    $html = $this->actingAs($this->admin)
        ->get(route('id-cards.prepare-bulk-print'))
        ->assertOk()
        ->getContent();

    expect(validityInputValue($html, $volunteer))->toBe('12')
        ->and($volunteer->defaultIdCardValidityMonths())->toBe(12);
});

test('a non-printable paid member still shows membership and ID status, but no validity input', function () {
    $member = makePaidMemberForValidity($this->branch, $this->division, 3);
    $member->update(['picture' => null]); // missing photo → not printable

    $html = $this->actingAs($this->admin)
        ->get(route('id-cards.prepare-bulk-print'))
        ->assertOk()
        ->getContent();

    $text = preg_replace('/\s+/', ' ', strip_tags($html));

    expect($html)
        ->not->toContain('id="user-'.$member->id.'"')          // no checkbox
        ->not->toContain('id="validity-'.$member->id.'"')      // no validity input
        ->not->toContain('id="expiry-display-'.$member->id.'"') // no "New ID expiry"
        ->toContain('<strong>3 Year</strong>s')
        ->and($text)
        ->toContain('Memb. valid to: '.$member->membershipPayments()->first()->expiry_date->format('M Y'))
        ->toContain('Current ID valid to: —');
});

test('a non-printable user with no payment shows dashes and ID NOT PAID', function () {
    $user = User::factory()->withNationalId()->create([
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
        'picture' => 'pic.jpg',
        'signature' => 'sig.jpg',
    ]); // member with no payment → not printable

    $html = $this->actingAs($this->admin)
        ->get(route('id-cards.prepare-bulk-print'))
        ->assertOk()
        ->getContent();

    $text = preg_replace('/\s+/', ' ', strip_tags($html));

    expect($html)->not->toContain('id="validity-'.$user->id.'"')
        ->and($text)
        ->toContain('Memb. valid to: —')
        ->toContain('Current ID valid to: —')
        ->toContain('ID NOT PAID');
});

test('the Bulk Set Validity dropdown is gone', function () {
    makePaidMemberForValidity($this->branch, $this->division, 1);

    $html = $this->actingAs($this->admin)
        ->get(route('id-cards.prepare-bulk-print'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->not->toContain('global-validity-months')
        ->not->toContain('Bulk Set Validity');
});

test('the result count is shown once, above the grid, matching the paginator', function () {
    makePaidMemberForValidity($this->branch, $this->division, 1);
    makeUnpaidVolunteerForValidity($this->branch, $this->division);

    $html = $this->actingAs($this->admin)
        ->get(route('id-cards.prepare-bulk-print'))
        ->assertOk()
        ->getContent();

    $total = User::query()->selectableForEntry()->count();
    $text = preg_replace('/\s+/', ' ', strip_tags($html));

    expect(substr_count($text, 'results'))->toBe(1)
        ->and($text)->toContain("Showing 1 to {$total} of {$total} results")
        ->and(strpos($html, 'Showing'))->toBeLessThan(strpos($html, 'user-card-container'));
});

/*
|--------------------------------------------------------------------------
| Blank per-card validity — server-side fallback
|--------------------------------------------------------------------------
*/

test('bulk print with a blank validity uses the fee-based default, or 12 when unpaid', function () {
    $this->freezeTime();

    $oneYear = makePaidMemberForValidity($this->branch, $this->division, 1);
    $threeYear = makePaidMemberForValidity($this->branch, $this->division, 3);
    $volunteer = makeUnpaidVolunteerForValidity($this->branch, $this->division);

    $html = $this->actingAs($this->admin)
        ->post(route('id-cards.print-bulk'), [
            'user_ids' => json_encode([
                ['id' => $threeYear->id, 'validity' => ''],
                ['id' => $volunteer->id, 'validity' => ''],
            ]),
        ])
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain(now()->addMonths(36)->format('M Y'))
        ->toContain(now()->addMonths(12)->format('M Y'))
        ->not->toContain('N/A');

    // A 1-year payer on its own: the card must show +12 months, not the
    // payment's expiry_date (payment date + 1 year = 11 months from now).
    $html = $this->actingAs($this->admin)
        ->post(route('id-cards.print-bulk'), [
            'user_ids' => json_encode([['id' => $oneYear->id, 'validity' => '']]),
        ])
        ->getContent();

    expect($html)->toContain(now()->addMonths(12)->format('M Y'));
});

test('recording prints with a blank validity saves the fee-based default, or 12 when unpaid', function () {
    $this->freezeTime();

    $oneYear = makePaidMemberForValidity($this->branch, $this->division, 1);
    $threeYear = makePaidMemberForValidity($this->branch, $this->division, 3);
    $volunteer = makeUnpaidVolunteerForValidity($this->branch, $this->division);

    $this->actingAs($this->admin)
        ->post(route('id-cards.record-bulk-prints'), [
            'user_ids' => json_encode([
                ['id' => $oneYear->id, 'validity' => ''],
                ['id' => $threeYear->id, 'validity' => ''],
                ['id' => $volunteer->id, 'validity' => ''],
            ]),
        ])
        ->assertRedirect();

    $expect = [$oneYear->id => 12, $threeYear->id => 36, $volunteer->id => 12];

    foreach ($expect as $userId => $months) {
        $print = IdCardPrint::where('user_id', $userId)->sole();

        expect($print->validity_months)->toBe($months)
            ->and($print->expiry_date->toDateString())->toBe(now()->addMonths($months)->toDateString());
    }
});

test('an explicit per-card validity still overrides the default', function () {
    $this->freezeTime();

    $volunteer = makeUnpaidVolunteerForValidity($this->branch, $this->division);

    $this->actingAs($this->admin)
        ->post(route('id-cards.record-bulk-prints'), [
            'user_ids' => json_encode([['id' => $volunteer->id, 'validity' => '24']]),
        ])
        ->assertRedirect();

    $print = IdCardPrint::where('user_id', $volunteer->id)->sole();

    expect($print->validity_months)->toBe(24)
        ->and($print->expiry_date->toDateString())->toBe(now()->addMonths(24)->toDateString());
});
