<?php

/**
 * Where User::contributor_type is displayed: the profile badge, and the
 * welcome page's status box — whose output must stay exactly what the old
 * hand-rolled isVolunteer() / currentMembershipPayment logic produced.
 */

use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\RedCrossUnit;
use App\Models\User;
use Database\Factories\MembershipFeeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->unit = RedCrossUnit::create(['name' => 'Unit Welcome']);
});

/** Approved personal payment on a fee named $feeName; 'expired' => true for a lapsed one. */
function displayFee(User $user, string $feeName, array $overrides = []): MembershipPayment
{
    $expired = $overrides['expired'] ?? false;
    $volunteerFee = $overrides['volunteer_fee'] ?? false;
    unset($overrides['expired'], $overrides['volunteer_fee']);

    return MembershipPayment::factory()->approved()->create(array_merge([
        'user_id' => $user->id,
        'membership_fee_id' => MembershipFeeFactory::new()->create(['name' => $feeName, 'is_volunteer_fee' => $volunteerFee])->id,
        'payment_date' => $expired ? now()->subMonths(18)->toDateString() : now()->subMonth()->toDateString(),
        'expiry_date' => $expired ? now()->subMonths(6)->toDateString() : now()->addMonths(11)->toDateString(),
    ], $overrides));
}

/*
|--------------------------------------------------------------------------
| Profile badge
|--------------------------------------------------------------------------
*/

test('the profile badge shows the user\'s own contributor type', function (string $case, ?string $label) {
    $user = match ($case) {
        'volunteer' => User::factory()->create(['red_cross_unit_id' => $this->unit->id]),
        'volunteer_member' => tap(User::factory()->create(['red_cross_unit_id' => $this->unit->id]), fn ($u) => displayFee($u, 'Detachment', ['volunteer_fee' => true])),
        'member' => tap(User::factory()->create(), fn ($u) => displayFee($u, 'Ordinary')),
        'neither' => User::factory()->create(),
    };

    $response = $this->actingAs($user)->get(route('profile.show'))->assertOk();

    preg_match('/data-contributor-badge>\s*(.*?)\s*<\/span>/s', $response->getContent(), $badge);

    expect(isset($badge[1]) ? html_entity_decode($badge[1]) : null)->toBe($label);
})->with([
    ['volunteer', 'Volunteer'],
    ['volunteer_member', 'Volunteer & Member'],
    ['member', 'Member'],
    ['neither', null],
]);

/*
|--------------------------------------------------------------------------
| Welcome status box — parity with the pre-contributor_type logic
|--------------------------------------------------------------------------
|
| Each case pins the headline (and detail line) the old branches rendered:
| pending -> "Registration pending"; RCU -> "You're a volunteer" + unit;
| current personal fee -> "Your membership:" + fee name; expired personal
| fee -> "Your membership has expired"; wants membership -> "Become a member
| or volunteer"; else "Not a member.".
*/

const WELCOME_HEADLINES = [
    'Registration pending',
    "You're a volunteer",
    'Your membership:',
    'Your membership has expired',
    'Become a member or volunteer',
    'Not a member.',
];

test('the welcome status box renders the same branch as before for each case', function (string $case, string $headline, ?string $detail) {
    $user = match ($case) {
        'pending with rcu' => User::factory()->create(['lifecycle_status' => 'pending_engagement', 'red_cross_unit_id' => $this->unit->id]),
        'volunteer' => User::factory()->create(['red_cross_unit_id' => $this->unit->id]),
        'volunteer + current fee' => tap(User::factory()->create(['red_cross_unit_id' => $this->unit->id]), fn ($u) => displayFee($u, 'Detachment', ['volunteer_fee' => true])),
        'volunteer + expired fee' => tap(User::factory()->create(['red_cross_unit_id' => $this->unit->id]), fn ($u) => displayFee($u, 'Detachment', ['expired' => true, 'volunteer_fee' => true])),
        'member' => tap(User::factory()->create(), fn ($u) => displayFee($u, 'Ordinary')),
        'member via volunteer-type fee' => tap(User::factory()->create(), fn ($u) => displayFee($u, 'Service Group', ['volunteer_fee' => true])),
        'lapsed member' => tap(User::factory()->create(['lifecycle_status' => 'dormant']), fn ($u) => displayFee($u, 'Ordinary', ['expired' => true])),
        'organisational payment only' => tap(User::factory()->create(), fn ($u) => displayFee($u, 'Org Fee', ['organisation_id' => Organisation::create(['name' => 'Org'])->id])),
        'wants membership' => User::factory()->create(['can_contribute_member' => true]),
        'neither' => User::factory()->create(),
    };

    $response = $this->actingAs($user)->get(route('welcome'))->assertOk();

    $response->assertSee($headline, false);
    foreach (array_diff(WELCOME_HEADLINES, [$headline]) as $other) {
        $response->assertDontSee($other, false);
    }
    if ($detail !== null) {
        $response->assertSeeInOrder([$headline, $detail], false);
    }
})->with([
    ['pending with rcu', 'Registration pending', null],
    ['volunteer', "You're a volunteer", 'Unit Welcome'],
    ['volunteer + current fee', "You're a volunteer", 'Unit Welcome'],
    ['volunteer + expired fee', "You're a volunteer", 'Unit Welcome'],
    ['member', 'Your membership:', 'Ordinary'],
    ['member via volunteer-type fee', 'Your membership:', 'Service Group'],
    ['lapsed member', 'Your membership has expired', null],
    ['organisational payment only', 'Not a member.', null],
    ['wants membership', 'Become a member or volunteer', null],
    ['neither', 'Not a member.', null],
]);
