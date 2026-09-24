<?php

/**
 * Group 4 of RCU annual fee payments: profile/show's "YOUR RED CROSS UNIT"
 * card (driven by units the user LEADS, not the unit they belong to) and the
 * leader-only profile.red-cross-unit page with the annual fee status,
 * history and the CTA into the RCU-locked Paystack form.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $branch->id]);

    $this->leader = User::factory()->create();
    $this->assistant = User::factory()->create();
    $this->unit = RedCrossUnit::create([
        'name' => 'Unit Profile',
        'division_id' => $this->division->id,
        'team_leader_user_id' => $this->leader->id,
        'assistant_team_leader_user_id' => $this->assistant->id,
        'is_active' => true,
    ]);

    $this->fee = MembershipFee::factory()->forRedCrossUnits()->create(['name' => 'Unit Annual', 'amount' => 20000]);
});

function rcuProfilePayment(array $overrides = []): MembershipPayment
{
    return MembershipPayment::factory()->approved()->create(array_merge([
        'user_id' => test()->leader->id,
        'red_cross_unit_id' => test()->unit->id,
        'membership_fee_id' => test()->fee->id,
        'payment_date' => now()->subMonth()->toDateString(),
        'expiry_date' => now()->addMonths(11)->toDateString(),
    ], $overrides));
}

/*
|--------------------------------------------------------------------------
| profile/show card
|--------------------------------------------------------------------------
*/

test('the team leader sees the YOUR RED CROSS UNIT card linking to the unit page', function () {
    $this->actingAs($this->leader)
        ->get(route('profile.show'))
        ->assertOk()
        ->assertSee('YOUR RED CROSS UNIT')
        ->assertSeeInOrder(['Unit Profile', 'Team Leader'])
        ->assertSee(route('profile.red-cross-unit', $this->unit), false);
});

test('the assistant team leader sees the card too', function () {
    $this->actingAs($this->assistant)
        ->get(route('profile.show'))
        ->assertOk()
        ->assertSee('YOUR RED CROSS UNIT')
        ->assertSee('Assistant Team Leader')
        ->assertSee(route('profile.red-cross-unit', $this->unit), false);
});

test('a leader who is not a member of the unit they lead still sees the card', function () {
    expect($this->leader->red_cross_unit_id)->toBeNull();

    $this->actingAs($this->leader)
        ->get(route('profile.show'))
        ->assertSee(route('profile.red-cross-unit', $this->unit), false);
});

test('a plain member of the unit does not see the card', function () {
    $member = User::factory()->create(['red_cross_unit_id' => $this->unit->id]);

    $this->actingAs($member)
        ->get(route('profile.show'))
        ->assertOk()
        ->assertDontSee('YOUR RED CROSS UNIT')
        ->assertDontSee(route('profile.red-cross-unit', $this->unit), false);
});

test('profile/show labels an RCU payment in the payment history as on behalf of the unit', function () {
    rcuProfilePayment();

    $this->actingAs($this->leader)
        ->get(route('profile.show'))
        ->assertOk()
        ->assertSeeInOrder(['Unit Annual', 'On behalf of Unit Profile']);
});

/*
|--------------------------------------------------------------------------
| profile.red-cross-unit page
|--------------------------------------------------------------------------
*/

test('the unit page is 403 for someone who does not lead the unit', function () {
    $member = User::factory()->create(['red_cross_unit_id' => $this->unit->id]);

    $this->actingAs($member)
        ->get(route('profile.red-cross-unit', $this->unit))
        ->assertForbidden();
});

test('an unpaid unit shows Not Paid and a Make a Payment link into the locked Paystack form', function () {
    $paymentUrl = route('make-payment.show', ['payment_type' => 'membership', 'red_cross_unit_id' => $this->unit->id]);

    $this->actingAs($this->leader)
        ->get(route('profile.red-cross-unit', $this->unit))
        ->assertOk()
        ->assertSee('Annual Fee Not Paid')
        ->assertSee('Make a Payment')
        ->assertSee(e($paymentUrl), false)
        ->assertDontSee('Donation');

    // The link lands on Group 3's locked form.
    $this->actingAs($this->leader)
        ->get($paymentUrl)
        ->assertOk()
        ->assertSee('Paying the annual fee for')
        ->assertSee('Unit Annual');
});

test('a paid unit shows its current status and history, with pending payments flagged', function () {
    rcuProfilePayment();
    MembershipPayment::factory()->create([ // pending (factory default), e.g. staff-registered
        'user_id' => $this->assistant->id,
        'red_cross_unit_id' => $this->unit->id,
        'membership_fee_id' => $this->fee->id,
        'payment_date' => now()->toDateString(),
        'expiry_date' => now()->addYear()->toDateString(),
    ]);

    $response = $this->actingAs($this->assistant)
        ->get(route('profile.red-cross-unit', $this->unit))
        ->assertOk()
        ->assertSee('Paid')
        ->assertDontSee('Annual Fee Not Paid')
        ->assertSee('₦20,000.00');

    expect($response->viewData('membershipPayments'))->toHaveCount(2)
        ->and($response->viewData('membershipPayments')->where('is_pending', true))->toHaveCount(1)
        ->and($response->viewData('currentMembership')['membership_type'])->toBe('Unit Annual');
});

test('a leader with no email gets the add-an-email prompt instead of the payment button', function () {
    $this->leader->update(['email' => null]);

    $this->actingAs($this->leader)
        ->get(route('profile.red-cross-unit', $this->unit))
        ->assertOk()
        ->assertSee('Add an email address to your profile to pay online, or contact your branch to pay directly.')
        ->assertDontSee('Make a Payment');
});

test('an archived unit shows an archived note instead of the payment button', function () {
    $this->unit->update(['is_active' => false]);

    $this->actingAs($this->leader)
        ->get(route('profile.red-cross-unit', $this->unit))
        ->assertOk()
        ->assertSee('This Red Cross Unit is archived')
        ->assertDontSee('Make a Payment');
});
