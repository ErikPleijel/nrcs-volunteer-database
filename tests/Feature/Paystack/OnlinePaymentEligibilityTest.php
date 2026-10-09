<?php

/**
 * Online membership payment eligibility (App\Services\OnlinePaymentEligibility):
 * the profile page's membership button, the make-payment page and initiate()
 * all follow the same rules — the on/off switch, who may pay their own
 * membership fee online, and which fees they may choose.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\PaymentTransaction;
use App\Models\RedCrossUnit;
use App\Models\User;
use App\Services\OnlinePaymentEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config(['paystack.enabled' => true, 'paystack.public_key' => 'pk_test_public', 'paystack.secret_key' => 'sk_test_secret']);

    $branch = Branch::create(['name' => 'Eligibility Branch', 'code' => 'ELB']);
    $division = Division::create(['name' => 'Eligibility Division', 'branch_id' => $branch->id]);
    $this->unit = RedCrossUnit::create(['name' => 'Eligibility Unit', 'division_id' => $division->id, 'is_active' => true]);

    $this->supportingFee = MembershipFee::factory()->create(['name' => 'Supporting Silver', 'amount' => 5000, 'is_volunteer_fee' => false]);
    $this->volunteerFee = MembershipFee::factory()->create(['name' => 'Volunteer Detachment', 'amount' => 200, 'is_volunteer_fee' => true]);
    $this->inactiveFee = MembershipFee::factory()->create(['name' => 'Retired Fee', 'amount' => 3000, 'is_active' => false, 'is_volunteer_fee' => false]);
    $this->orgFee = MembershipFee::factory()->create(['name' => 'Corporate Fee', 'amount' => 15000, 'for_organizations' => true, 'is_volunteer_fee' => false]);
});

function eligibilityUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'lifecycle_status' => 'active',
        'can_contribute_volunteering' => false,
        'can_contribute_member' => true,
        'red_cross_unit_id' => null,
    ], $attributes));
}

function eligibilityPersonalPayment(User $user, string $expiry, array $attributes = [], bool $approved = true): MembershipPayment
{
    $factory = MembershipPayment::factory();
    if ($approved) {
        $factory = $factory->approved();
    }

    return $factory->create(array_merge([
        'user_id' => $user->id,
        'membership_fee_id' => test()->supportingFee->id,
        'payment_date' => now()->subYear()->toDateString(),
        'expiry_date' => $expiry,
    ], $attributes));
}

function eligibilityFakePaystack(): void
{
    Http::fake([
        'api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => ['authorization_url' => 'https://checkout.paystack.com/xyz'],
        ], 200),
    ]);
}

/*
|--------------------------------------------------------------------------
| Rule B — who gets the membership button on the profile
|--------------------------------------------------------------------------
*/

test('an archived user gets no button and the contact-your-branch text', function () {
    $user = eligibilityUser(['lifecycle_status' => 'archived']);

    $this->actingAs($user)->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Your account is archived. Please contact your branch or Red Cross Unit directly to renew your membership.')
        ->assertDontSee('Make a Payment');

    expect(OnlinePaymentEligibility::for($user)->reason)->toBe('archived');
});

test('a user with no email gets no button', function () {
    $user = eligibilityUser(['email' => null]);

    $this->actingAs($user)->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Pay your membership fee directly at your branch.')
        ->assertDontSee('Make a Payment');

    expect(OnlinePaymentEligibility::for($user)->reason)->toBe('no_email');
});

test('a pending_engagement volunteering-only user is told to contact their branch, with no button', function () {
    $user = eligibilityUser([
        'lifecycle_status' => 'pending_engagement',
        'can_contribute_volunteering' => true,
        'can_contribute_member' => false,
    ]);

    $this->actingAs($user)->get(route('profile.show'))
        ->assertOk()
        ->assertSee('You have registered interest in volunteering. Please contact your branch first, so they can place you in a Red Cross Unit.')
        ->assertDontSee('Make a Payment');

    expect(OnlinePaymentEligibility::for($user)->reason)->toBe('volunteer_contact_branch');
});

test('pending_engagement users with member only, both boxes, or neither box ticked get the button', function (bool $volunteering, bool $member) {
    $user = eligibilityUser([
        'lifecycle_status' => 'pending_engagement',
        'can_contribute_volunteering' => $volunteering,
        'can_contribute_member' => $member,
    ]);

    $this->actingAs($user)->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Make a Payment');
})->with([
    'member only' => [false, true],
    'both boxes' => [true, true],
    'neither box' => [false, false],
]);

test('active and dormant users get the button with or without a Red Cross Unit', function (string $status, bool $inUnit) {
    $user = eligibilityUser([
        'lifecycle_status' => $status,
        'can_contribute_volunteering' => $inUnit,
        'red_cross_unit_id' => $inUnit ? test()->unit->id : null,
    ]);

    $this->actingAs($user)->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Make a Payment')
        ->assertSee(route('make-payment.show', ['payment_type' => 'membership']), false);
})->with([
    'active, RCU member' => ['active', true],
    'active, no unit' => ['active', false],
    'dormant, RCU member' => ['dormant', true],
    'dormant, no unit' => ['dormant', false],
]);

test('a lapsed member gets Renew Membership', function () {
    $user = eligibilityUser();
    eligibilityPersonalPayment($user, now()->subDays(10)->toDateString());

    $this->actingAs($user)->get(route('profile.show'))
        ->assertSee('Renew Membership')
        ->assertDontSee('Make a Payment');
});

test('a member expiring within 28 days gets Renew Early', function () {
    $user = eligibilityUser();
    eligibilityPersonalPayment($user, now()->addDays(10)->toDateString());

    $this->actingAs($user)->get(route('profile.show'))
        ->assertSee('Renew Early')
        ->assertSee('Your membership expires in 10 days.');
});

test('a member with a valid membership gets no button, only the days to renewal, and show() refuses', function () {
    $user = eligibilityUser();
    eligibilityPersonalPayment($user, now()->addDays(100)->toDateString());

    $this->actingAs($user)->get(route('profile.show'))
        ->assertSee('100 days to renewal')
        ->assertDontSee('Renew Early')
        ->assertDontSee('Make a Payment');

    $eligibility = OnlinePaymentEligibility::for($user);
    expect($eligibility->canPay)->toBeFalse()
        ->and($eligibility->reason)->toBe('membership_valid');

    $this->actingAs($user)->get(route('make-payment.show', ['payment_type' => 'membership']))
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('error', $eligibility->message());
});

/*
|--------------------------------------------------------------------------
| Rule B4 — a manual payment awaiting approval
|--------------------------------------------------------------------------
*/

test('a pending manual payment hides the button and blocks show() and initiate()', function () {
    $user = eligibilityUser();
    eligibilityPersonalPayment($user, now()->addYear()->toDateString(), approved: false);

    $this->actingAs($user)->get(route('profile.show'))
        ->assertSee('A payment is awaiting approval at your branch.')
        ->assertDontSee('Make a Payment');

    $this->actingAs($user)->get(route('make-payment.show', ['payment_type' => 'membership']))
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('error', 'A payment is awaiting approval at your branch.');

    $this->actingAs($user)
        ->from(route('make-payment.show'))
        ->post(route('make-payment.initiate'), ['payment_type' => 'membership', 'membership_fee_id' => $this->supportingFee->id])
        ->assertSessionHas('error', 'A payment is awaiting approval at your branch.');

    expect(PaymentTransaction::count())->toBe(0);
});

test('a rejected or deleted pending payment does not block the button', function () {
    $user = eligibilityUser();
    eligibilityPersonalPayment($user, now()->addYear()->toDateString(), ['approval_status' => MembershipPayment::REJECTED], approved: false);
    eligibilityPersonalPayment($user, now()->addYear()->toDateString(), ['is_deleted' => true], approved: false);

    $this->actingAs($user)->get(route('profile.show'))
        ->assertSee('Make a Payment');
});

test('a pending organisation-attributed payment does not block the personal button', function () {
    $user = eligibilityUser();
    $organisation = \App\Models\Organisation::create(['name' => 'Pending Org']);
    eligibilityPersonalPayment($user, now()->addYear()->toDateString(), ['organisation_id' => $organisation->id], approved: false);

    expect(OnlinePaymentEligibility::for($user)->canPay)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Rule C — which fees appear
|--------------------------------------------------------------------------
*/

// The organisation fee list is always rendered (hidden) next to the personal
// one, so assert on the personal list itself rather than the whole page.
// Every individual sees every active personal fee, member and volunteer
// fees alike (Decisions.md 2026-10-09); only the group order differs.
test('an RCU member sees member and volunteer fees, volunteer fees first', function () {
    $user = eligibilityUser(['red_cross_unit_id' => $this->unit->id]);

    $this->actingAs($user)->get(route('make-payment.show', ['payment_type' => 'membership']))
        ->assertOk()
        ->assertSee('Volunteer Detachment')
        ->assertSee('Supporting Silver')
        ->assertViewHas('personalMembershipFees', fn ($fees) => $fees->pluck('name')->sort()->values()->all() === ['Supporting Silver', 'Volunteer Detachment'])
        ->assertViewHas('personalFeeGroups', fn ($groups) => array_column($groups, 'label') === ['Volunteer fees', 'Member fees']);
});

test('a user without a Red Cross Unit also sees volunteer fees, member fees first', function () {
    $user = eligibilityUser();

    $this->actingAs($user)->get(route('make-payment.show', ['payment_type' => 'membership']))
        ->assertOk()
        ->assertSee('Supporting Silver')
        ->assertSee('Volunteer Detachment')
        ->assertViewHas('personalMembershipFees', fn ($fees) => $fees->pluck('name')->sort()->values()->all() === ['Supporting Silver', 'Volunteer Detachment'])
        ->assertViewHas('personalFeeGroups', fn ($groups) => array_column($groups, 'label') === ['Member fees', 'Volunteer fees']);
});

test('the payment type radio is labelled Membership fee', function () {
    $this->actingAs(eligibilityUser())->get(route('make-payment.show'))
        ->assertSee('Membership fee')
        ->assertDontSee('Membership Renewal');
});

/*
|--------------------------------------------------------------------------
| Rule E — initiate() only accepts allowed fees
|--------------------------------------------------------------------------
*/

test('initiate rejects a fee outside the payer\'s allowed set with a validation error', function (string $feeProperty, bool $inUnit) {
    $user = eligibilityUser(['red_cross_unit_id' => $inUnit ? test()->unit->id : null]);
    eligibilityFakePaystack();

    $this->actingAs($user)
        ->from(route('make-payment.show'))
        ->post(route('make-payment.initiate'), ['payment_type' => 'membership', 'membership_fee_id' => test()->{$feeProperty}->id])
        ->assertRedirect(route('make-payment.show'))
        ->assertSessionHasErrors(['membership_fee_id' => 'That membership fee is not available to you.']);

    expect(PaymentTransaction::count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'inactive fee, no unit' => ['inactiveFee', false],
    'inactive fee' => ['inactiveFee', true],
    'organisation fee as a personal payment' => ['orgFee', true],
]);

test('initiate accepts member and volunteer fees whatever the payer\'s unit', function (string $feeProperty, bool $inUnit) {
    $user = eligibilityUser(['red_cross_unit_id' => $inUnit ? test()->unit->id : null]);
    eligibilityFakePaystack();

    $this->actingAs($user)
        ->post(route('make-payment.initiate'), ['payment_type' => 'membership', 'membership_fee_id' => test()->{$feeProperty}->id])
        ->assertRedirect('https://checkout.paystack.com/xyz');

    expect(PaymentTransaction::sole()->meta['membership_fee_id'])->toBe(test()->{$feeProperty}->id);
})->with([
    'volunteer fee, RCU member' => ['volunteerFee', true],
    'volunteer fee, no unit' => ['volunteerFee', false],
    'member fee, RCU member' => ['supportingFee', true],
    'member fee, no unit' => ['supportingFee', false],
]);

test('initiate refuses a personal membership payment from a volunteering-only pending user', function () {
    $user = eligibilityUser([
        'lifecycle_status' => 'pending_engagement',
        'can_contribute_volunteering' => true,
        'can_contribute_member' => false,
    ]);

    $this->actingAs($user)
        ->from(route('make-payment.show'))
        ->post(route('make-payment.initiate'), ['payment_type' => 'membership', 'membership_fee_id' => $this->supportingFee->id])
        ->assertSessionHas('error', 'You have registered interest in volunteering. Please contact your branch first, so they can place you in a Red Cross Unit.');

    expect(PaymentTransaction::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Donations ignore membership eligibility
|--------------------------------------------------------------------------
*/

test('users who cannot pay a membership fee online can still donate', function (string $case) {
    $user = $case === 'volunteer'
        ? eligibilityUser(['lifecycle_status' => 'pending_engagement', 'can_contribute_volunteering' => true, 'can_contribute_member' => false])
        : eligibilityUser();
    if ($case === 'valid') {
        eligibilityPersonalPayment($user, now()->addDays(200)->toDateString());
    }
    eligibilityFakePaystack();

    $this->actingAs($user)->get(route('profile.show'))->assertSee('Make a Donation');

    $this->actingAs($user)->get(route('make-payment.show', ['payment_type' => 'donation']))->assertOk();

    $this->actingAs($user)
        ->post(route('make-payment.initiate'), ['payment_type' => 'donation', 'amount' => 1000])
        ->assertRedirect('https://checkout.paystack.com/xyz');
})->with(['volunteer', 'valid']);

test('the unlocked payment page still works for donations when the payer cannot pay a membership fee', function () {
    $user = eligibilityUser(['lifecycle_status' => 'pending_engagement', 'can_contribute_volunteering' => true, 'can_contribute_member' => false]);

    $this->actingAs($user)->get(route('make-payment.show'))
        ->assertOk()
        ->assertSee('Please contact your branch first')
        ->assertDontSee('Supporting Silver');
});

/*
|--------------------------------------------------------------------------
| Rule A — the on/off switch
|--------------------------------------------------------------------------
*/

test('with online payment switched off or keys missing there are no payment buttons and the payment routes redirect', function (array $config) {
    config($config);
    $user = eligibilityUser();

    expect(OnlinePaymentEligibility::isAvailable())->toBeFalse();

    $this->actingAs($user)->get(route('profile.show'))
        ->assertOk()
        ->assertSee(OnlinePaymentEligibility::NOT_AVAILABLE_MESSAGE)
        ->assertDontSee('Make a Payment')
        ->assertDontSee('Make a Donation');

    expect(OnlinePaymentEligibility::for($user)->reason)->toBe('payments_disabled');

    $this->actingAs($user)->get(route('make-payment.show', ['payment_type' => 'membership']))
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('error', OnlinePaymentEligibility::NOT_AVAILABLE_MESSAGE);

    $this->actingAs($user)->get(route('make-payment.show', ['payment_type' => 'donation']))
        ->assertRedirect(route('profile.show'));

    $this->actingAs($user)
        ->post(route('make-payment.initiate'), ['payment_type' => 'donation', 'amount' => 1000])
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('error', OnlinePaymentEligibility::NOT_AVAILABLE_MESSAGE);

    expect(PaymentTransaction::count())->toBe(0);
})->with([
    'switch off' => [['paystack.enabled' => false]],
    'public key missing' => [['paystack.public_key' => null]],
    'secret key missing' => [['paystack.secret_key' => '']],
]);

test('with the switch off a volunteering-only pending user still gets the contact-branch text; only donations say not available', function () {
    config(['paystack.enabled' => false]);
    $user = eligibilityUser([
        'lifecycle_status' => 'pending_engagement',
        'can_contribute_volunteering' => true,
        'can_contribute_member' => false,
    ]);

    expect(OnlinePaymentEligibility::for($user)->reason)->toBe('volunteer_contact_branch');

    $html = $this->actingAs($user)->get(route('profile.show'))->assertOk()->getContent();
    $membershipSection = \Illuminate\Support\Str::between($html, 'YOUR MEMBERSHIP', 'YOUR DONATIONS');
    $donationSection = \Illuminate\Support\Str::after($html, 'YOUR DONATIONS');

    expect($membershipSection)->toContain('Please contact your branch first')
        ->not->toContain(OnlinePaymentEligibility::NOT_AVAILABLE_MESSAGE)
        ->and($donationSection)->toContain(OnlinePaymentEligibility::NOT_AVAILABLE_MESSAGE);
});

test('the switch also blocks Red Cross Unit payments', function () {
    config(['paystack.enabled' => false]);
    $leader = eligibilityUser();
    $this->unit->update(['team_leader_user_id' => $leader->id]);

    $this->actingAs($leader)->get(route('make-payment.show', ['payment_type' => 'membership', 'red_cross_unit_id' => $this->unit->id]))
        ->assertRedirect(route('profile.show'));

    $this->actingAs($leader)->get(route('profile.red-cross-unit', $this->unit))
        ->assertOk()
        ->assertSee(OnlinePaymentEligibility::NOT_AVAILABLE_MESSAGE)
        ->assertDontSee('Make a Payment');
});

/*
|--------------------------------------------------------------------------
| Rule F — smaller fixes
|--------------------------------------------------------------------------
*/

test('a soft-deleted payment does not count as ever paid', function () {
    $user = eligibilityUser();
    eligibilityPersonalPayment($user, now()->subDays(30)->toDateString(), ['is_deleted' => true]);

    expect(OnlinePaymentEligibility::for($user)->subcase)->toBe('new');

    $this->actingAs($user)->get(route('profile.show'))
        ->assertSee('Make a Payment')
        ->assertDontSee('Renew Membership');
});

test('a membership is still valid on its expiry date', function () {
    $user = eligibilityUser();
    $payment = eligibilityPersonalPayment($user, now()->toDateString());

    expect($payment->isValid())->toBeTrue()
        ->and($payment->isExpired())->toBeFalse()
        ->and($payment->expiresSoon(28))->toBeTrue()
        ->and($payment->days_until_expiry)->toBe(0)
        ->and(OnlinePaymentEligibility::for($user)->subcase)->toBe('expiring_soon');

    $this->actingAs($user)->get(route('profile.show'))
        ->assertSee('Active')
        ->assertSee('Renew Early');

    $this->travel(1)->days();

    expect($payment->fresh()->isValid())->toBeFalse()
        ->and($payment->fresh()->isExpired())->toBeTrue()
        ->and(OnlinePaymentEligibility::for($user)->subcase)->toBe('lapsed');
});
