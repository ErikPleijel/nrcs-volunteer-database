<?php

/**
 * "Card validity" filter on the bulk ID card page:
 * - options come from the distinct validity_years of fees with an ID card
 *   fee (inactive too), plus 1 year; a new duration appears automatically
 * - the filter uses the same rule as the card's validity box
 *   (User::defaultIdCardValidityMonths()): the current personal payment's
 *   fee, or 1 year with no current payment — so filter and card agree
 * - it runs in SQL (pagination counts stay right) and combines with the
 *   other filters
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\IdCardPrintRequest;
use App\Models\MembershipFee;
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

    $permissions = ['manage-admin-panel', 'view_idcards', 'print_idcards', 'view_user', 'reject_images'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $this->oneYear = MembershipFeeFactory::new()->create(['validity_years' => 1, 'id_card_fee' => 500]);
    $this->threeYear = MembershipFeeFactory::new()->create(['validity_years' => 3, 'id_card_fee' => 500]);
});

function validityMember(?MembershipFee $fee = null, array $payment = [], array $attributes = []): User
{
    $user = User::factory()->withNationalId()->create([
        'branch_id' => test()->branch->id,
        'division_id' => test()->division->id,
        'picture' => 'pic.jpg',
        'signature' => 'sig.jpg',
        ...$attributes,
    ]);

    if ($fee) {
        validityPayment($user, $fee, $payment);
    }

    return $user->fresh();
}

function validityPayment(User $user, MembershipFee $fee, array $attributes = []): void
{
    MembershipPaymentFactory::new()->approved()->create([
        'user_id' => $user->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subMonth(),
        'expiry_date' => now()->addYears($fee->validity_years)->subMonth(),
        'id_card_included' => true,
        ...$attributes,
    ]);
}

/** IDs of the users listed on the page (from their View links), in page order. */
function listedUserIds(string $html, User ...$candidates): array
{
    return collect($candidates)
        ->filter(fn (User $u) => str_contains($html, 'href="'.route('users.show', $u).'"'))
        ->pluck('id')->values()->all();
}

function bulkPage(array $query = []): string
{
    return test()->actingAs(test()->admin)
        ->get(route('id-cards.prepare-bulk-print', $query))
        ->assertOk()
        ->getContent();
}

test('the options come from the fees with an ID card fee, labelled in plain words, with All as default', function () {
    // Inactive fees count (old payments use them); fees with no ID card fee do not.
    MembershipFeeFactory::new()->create(['validity_years' => 5, 'id_card_fee' => 300, 'is_active' => false]);
    MembershipFeeFactory::new()->create(['validity_years' => 4, 'id_card_fee' => 0]);

    $html = bulkPage();

    preg_match('/<select id="card_validity".*?<\/select>/s', $html, $m);
    preg_match_all('/<option value="([^"]*)"[^>]*>\s*([^<]+?)\s*<\/option>/', $m[0] ?? '', $options, PREG_SET_ORDER);

    expect(array_map(fn ($o) => [$o[1], $o[2]], $options))->toBe([
        ['', 'All'],
        ['1', '1 year'],
        ['3', '3 years'],
        ['5', '5 years'],
    ]);
    expect($m[0])->not->toContain('selected'); // All is the default
});

test('a new fee duration shows up as a new option automatically', function () {
    expect(bulkPage())->not->toContain('>2 years<');

    MembershipFeeFactory::new()->create(['validity_years' => 2, 'id_card_fee' => 500]);

    $html = bulkPage();
    expect($html)->toContain('<option value="2" >2 years</option>');

    // ...and filters on it.
    $twoYearFee = MembershipFee::where('validity_years', 2)->first();
    $two = validityMember($twoYearFee);
    $three = validityMember($this->threeYear);
    expect(listedUserIds(bulkPage(['card_validity' => 2]), $two, $three))->toBe([$two->id]);
});

test('1 year and 3 years list exactly the people whose card shows 12 or 36 months', function () {
    $one = validityMember($this->oneYear);
    $three = validityMember($this->threeYear);
    $noPayment = validityMember(); // default 12 months, like the card
    $expired = validityMember($this->threeYear, ['expiry_date' => now()->subDay()]); // no current payment: 12

    $all = [$one, $three, $noPayment, $expired];

    $html = bulkPage(['card_validity' => 1]);
    expect(listedUserIds($html, ...$all))->toEqualCanonicalizing([$one->id, $noPayment->id, $expired->id]);
    foreach ([$one, $noPayment, $expired] as $user) {
        expect($html)->toMatch('/data-user-id="'.$user->id.'"[^>]*?data-validity="12"/s'); // the card's own validity
    }

    $html = bulkPage(['card_validity' => 3]);
    expect(listedUserIds($html, ...$all))->toBe([$three->id])
        ->and($html)->toMatch('/data-user-id="'.$three->id.'"[^>]*?data-validity="36"/s')
        ->and($html)->toMatch('/id="validity-'.$three->id.'"[^>]*value="36"/s');

    expect(listedUserIds(bulkPage(), ...$all))->toHaveCount(4); // All
});

test('it follows the same current payment as the card: personal, approved, not deleted, latest expiry', function () {
    // 1-year payment expiring later wins over an earlier-expiring 3-year one.
    $laterOneYear = validityMember($this->threeYear, ['expiry_date' => now()->addMonths(6)]);
    validityPayment($laterOneYear, $this->oneYear, ['expiry_date' => now()->addMonths(11)]);

    // A deleted, a pending and an organisation 3-year payment are all ignored.
    $ignored = validityMember($this->oneYear);
    validityPayment($ignored, $this->threeYear, ['is_deleted' => true, 'expiry_date' => now()->addYears(3)]);
    MembershipPaymentFactory::new()->create([ // pending
        'user_id' => $ignored->id, 'membership_fee_id' => $this->threeYear->id,
        'payment_date' => now(), 'expiry_date' => now()->addYears(3), 'id_card_included' => true,
    ]);
    $org = \App\Models\Organisation::forceCreate(['name' => 'Org A', 'branch_id' => $this->branch->id, 'division_id' => $this->division->id]);
    validityPayment($ignored, $this->threeYear, ['organisation_id' => $org->id, 'expiry_date' => now()->addYears(3)]);

    // Equal expiry dates: the higher id wins (same tie-break as the relation).
    $tie = validityMember($this->oneYear, ['expiry_date' => now()->addYear()->toDateString()]);
    validityPayment($tie, $this->threeYear, ['expiry_date' => now()->addYear()->toDateString()]);

    foreach ([$laterOneYear, $ignored] as $user) {
        expect($user->fresh()->defaultIdCardValidityMonths())->toBe(12);
    }
    expect($tie->fresh()->defaultIdCardValidityMonths())->toBe(36);

    expect(listedUserIds(bulkPage(['card_validity' => 1]), $laterOneYear, $ignored, $tie))
        ->toEqualCanonicalizing([$laterOneYear->id, $ignored->id])
        ->and(listedUserIds(bulkPage(['card_validity' => 3]), $laterOneYear, $ignored, $tie))->toBe([$tie->id]);
});

test('it combines with the other filters and keeps the pagination count right', function () {
    $threeReady = validityMember($this->threeYear);
    IdCardPrintRequest::create(['user_id' => $threeReady->id, 'requested_at' => now(), 'status' => 'pending']);
    $threeRejected = validityMember($this->threeYear);
    $threeRejected->forceFill(['picture_rejected_at' => now()])->save();
    $oneReady = validityMember($this->oneYear);
    IdCardPrintRequest::create(['user_id' => $oneReady->id, 'requested_at' => now(), 'status' => 'pending']);
    $all = [$threeReady, $threeRejected, $oneReady];

    expect(listedUserIds(bulkPage(['card_validity' => 3, 'ready_to_print' => 1]), ...$all))->toBe([$threeReady->id])
        ->and(listedUserIds(bulkPage(['card_validity' => 3, 'has_rejection' => 1]), ...$all))->toBe([$threeRejected->id])
        ->and(listedUserIds(bulkPage(['card_validity' => 3, 'printable_only' => 1]), ...$all))->toBe([$threeReady->id])
        ->and(listedUserIds(bulkPage(['card_validity' => 3, 'search' => $threeRejected->first_name]), ...$all))->toContain($threeRejected->id);

    // 30 more 3-year members: the paginator total is computed in SQL, over 24 per page.
    foreach (range(1, 30) as $i) {
        validityMember($this->threeYear);
    }
    $html = bulkPage(['card_validity' => 3]);
    expect($html)->toMatch('/of\s*<span class="font-medium">32<\/span>\s*results/');
});

test('an unknown card_validity value is ignored (All)', function () {
    $one = validityMember($this->oneYear);
    $three = validityMember($this->threeYear);

    expect(listedUserIds(bulkPage(['card_validity' => 7]), $one, $three))->toHaveCount(2)
        ->and(listedUserIds(bulkPage(['card_validity' => 'abc']), $one, $three))->toHaveCount(2);
});
