<?php

/**
 * Feature tests for PaystackPaymentController: the member/org-contact
 * self-service "make a payment" flow (show -> initiate -> Paystack ->
 * callback) plus the server-to-server webhook that actually fulfils the
 * payment. Http::fake() stands in for Paystack's API throughout; no real
 * network calls are made.
 */

use App\Models\Donation;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config(['paystack.secret_key' => 'sk_test_secret']);
});

/**
 * Build the exact raw JSON body Laravel's postJson() will send, and the
 * matching x-paystack-signature header — both derived from the same
 * json_encode($payload) call so the controller's HMAC check passes.
 */
function paystackWebhookHeaders(array $payload): array
{
    $rawBody = json_encode($payload);

    return ['x-paystack-signature' => hash_hmac('sha512', $rawBody, config('paystack.secret_key'))];
}

/*
|--------------------------------------------------------------------------
| show()
|--------------------------------------------------------------------------
*/

test('show renders the make-a-payment page with personal and organisation fee lists', function () {
    $user = User::factory()->create();
    MembershipFee::factory()->create(['name' => 'Personal Annual Fee', 'for_organizations' => false, 'is_active' => true]);
    MembershipFee::factory()->create(['name' => 'Corporate Annual Fee', 'for_organizations' => true, 'is_active' => true]);
    MembershipFee::factory()->create(['name' => 'Retired Inactive Fee', 'for_organizations' => false, 'is_active' => false]);

    $this->actingAs($user)
        ->get(route('make-payment.show'))
        ->assertOk()
        ->assertSee('Personal Annual Fee')
        ->assertSee('Corporate Annual Fee')
        ->assertDontSee('Retired Inactive Fee');
});

/*
|--------------------------------------------------------------------------
| initiate() — guards
|--------------------------------------------------------------------------
*/

test('initiate rejects an organisation_id the payer is not linked to', function () {
    $user = User::factory()->create();
    $organisation = Organisation::create(['name' => 'Unlinked Org']);

    $response = $this->actingAs($user)
        ->from(route('make-payment.show'))
        ->post(route('make-payment.initiate'), [
            'payment_type' => 'donation',
            'amount' => 1000,
            'organisation_id' => $organisation->id,
        ]);

    $response->assertRedirect(route('make-payment.show'))
        ->assertSessionHas('error', 'You are not linked to that organisation.');

    expect(PaymentTransaction::count())->toBe(0);
});

test('initiate refuses to start a payment for a user with no email on file', function () {
    $user = User::factory()->create(['email' => null]);

    $response = $this->actingAs($user)
        ->from(route('make-payment.show'))
        ->post(route('make-payment.initiate'), [
            'payment_type' => 'donation',
            'amount' => 1000,
        ]);

    $response->assertSessionHas('error', 'Please add an email address to your profile before making a payment.');
    expect(PaymentTransaction::count())->toBe(0);
});

test('initiate refuses a personal membership payment for an archived member', function () {
    $user = User::factory()->create(['lifecycle_status' => 'archived']);
    $fee = MembershipFee::factory()->create();

    $response = $this->actingAs($user)
        ->from(route('make-payment.show'))
        ->post(route('make-payment.initiate'), [
            'payment_type' => 'membership',
            'membership_fee_id' => $fee->id,
        ]);

    $response->assertSessionHas('error');
    expect(session('error'))->toContain('archived');
    expect(PaymentTransaction::count())->toBe(0);
});

test('initiate allows an org-sponsored membership payment even for an archived contact person', function () {
    $user = User::factory()->create(['lifecycle_status' => 'archived']);
    $organisation = Organisation::create(['name' => 'Sponsor Org']);
    $organisation->users()->attach($user->id, ['is_primary_contact' => true, 'linked_at' => now()]);
    $fee = MembershipFee::factory()->create(['for_organizations' => true]);

    Http::fake([
        'api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => ['authorization_url' => 'https://checkout.paystack.com/xyz'],
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->post(route('make-payment.initiate'), [
            'payment_type' => 'membership',
            'membership_fee_id' => $fee->id,
            'organisation_id' => $organisation->id,
        ]);

    $response->assertRedirect('https://checkout.paystack.com/xyz');
    expect(PaymentTransaction::count())->toBe(1);
});

test('initiate requires an amount for a donation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from(route('make-payment.show'))
        ->post(route('make-payment.initiate'), [
            'payment_type' => 'donation',
        ]);

    $response->assertSessionHasErrors('amount');
    expect(PaymentTransaction::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| initiate() — happy path
|--------------------------------------------------------------------------
*/

test('initiate creates an initiated PaymentTransaction and redirects to Paystack for a donation', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => ['authorization_url' => 'https://checkout.paystack.com/abc'],
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->post(route('make-payment.initiate'), [
            'payment_type' => 'donation',
            'amount' => 2500,
            'purpose' => 'Flood relief',
        ]);

    $response->assertRedirect('https://checkout.paystack.com/abc');

    $transaction = PaymentTransaction::sole();
    expect($transaction->user_id)->toBe($user->id)
        ->and($transaction->payable_type)->toBe('donation')
        ->and($transaction->status)->toBe('initiated')
        ->and($transaction->amount)->toBe(250000) // naira -> kobo
        ->and($transaction->organisation_id)->toBeNull()
        ->and($transaction->meta['donation_purpose'])->toBe('Flood relief')
        ->and($transaction->reference)->toStartWith('PSK-');

    Http::assertSent(fn ($request) => $request['amount'] === 250000 && $request['email'] === $user->email);
});

test('initiate creates an initiated PaymentTransaction for a personal membership payment', function () {
    $user = User::factory()->create();
    $fee = MembershipFee::factory()->create(['amount' => 5000]);

    Http::fake([
        'api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => ['authorization_url' => 'https://checkout.paystack.com/def'],
        ], 200),
    ]);

    $this->actingAs($user)->post(route('make-payment.initiate'), [
        'payment_type' => 'membership',
        'membership_fee_id' => $fee->id,
    ]);

    $transaction = PaymentTransaction::sole();
    expect($transaction->payable_type)->toBe('membership_payment')
        ->and($transaction->amount)->toBe(500000)
        ->and($transaction->meta['membership_fee_id'])->toBe($fee->id);
});

test('initiate shows a generic error and does not redirect away when Paystack initialization fails', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Invalid public key'], 400),
    ]);

    $response = $this->actingAs($user)
        ->from(route('make-payment.show'))
        ->post(route('make-payment.initiate'), [
            'payment_type' => 'donation',
            'amount' => 1000,
        ]);

    $response->assertRedirect(route('make-payment.show'))
        ->assertSessionHas('error', 'We could not start your payment right now. Please try again shortly.');

    // The PaymentTransaction row is still created (before the API call) and
    // left at its initial 'initiated' status — not retried automatically.
    $transaction = PaymentTransaction::sole();
    expect($transaction->status)->toBe('initiated');
});

/*
|--------------------------------------------------------------------------
| callback()
|--------------------------------------------------------------------------
*/

test('callback shows the matching transaction by reference', function () {
    $user = User::factory()->create();
    $transaction = PaymentTransaction::create([
        'user_id' => $user->id,
        'payable_type' => 'donation',
        'reference' => 'PSK-callback-1',
        'amount' => 100000,
        'status' => 'success',
    ]);

    $this->actingAs($user)
        ->get(route('make-payment.callback', ['reference' => 'PSK-callback-1']))
        ->assertOk()
        ->assertSee('Payment received');
});

test('callback shows a not-found state for an unrecognised reference', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('make-payment.callback', ['reference' => 'does-not-exist']))
        ->assertOk()
        ->assertSee('We could not find this payment');
});

/*
|--------------------------------------------------------------------------
| webhook() — signature / event filtering
|--------------------------------------------------------------------------
*/

test('webhook rejects a request with no signature header', function () {
    $payload = ['event' => 'charge.success', 'data' => ['reference' => 'PSK-1']];

    $this->postJson('/webhooks/paystack', $payload)
        ->assertStatus(400)
        ->assertJson(['status' => 'invalid signature']);
});

test('webhook rejects a request with an incorrect signature', function () {
    $payload = ['event' => 'charge.success', 'data' => ['reference' => 'PSK-1']];

    $this->postJson('/webhooks/paystack', $payload, ['x-paystack-signature' => 'not-the-right-signature'])
        ->assertStatus(400)
        ->assertJson(['status' => 'invalid signature']);
});

test('webhook ignores a correctly-signed event other than charge.success', function () {
    $payload = ['event' => 'subscription.disable', 'data' => ['reference' => 'PSK-1']];

    $this->postJson('/webhooks/paystack', $payload, paystackWebhookHeaders($payload))
        ->assertOk()
        ->assertJson(['status' => 'ignored']);
});

test('webhook returns unknown-reference for a charge.success event with no matching PaymentTransaction', function () {
    $payload = ['event' => 'charge.success', 'data' => ['reference' => 'no-such-reference']];

    $this->postJson('/webhooks/paystack', $payload, paystackWebhookHeaders($payload))
        ->assertOk()
        ->assertJson(['status' => 'unknown reference']);
});

test('webhook is idempotent: a second delivery for an already-processed transaction is a no-op', function () {
    $user = User::factory()->create();
    $transaction = PaymentTransaction::create([
        'user_id' => $user->id,
        'payable_type' => 'donation',
        'reference' => 'PSK-already-done',
        'amount' => 100000,
        'status' => 'success',
        'meta' => ['payment_type' => 'donation', 'donation_purpose' => null],
    ]);

    $payload = ['event' => 'charge.success', 'data' => ['reference' => 'PSK-already-done']];

    $this->postJson('/webhooks/paystack', $payload, paystackWebhookHeaders($payload))
        ->assertOk()
        ->assertJson(['status' => 'already processed']);

    expect(Donation::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| webhook() — fulfilment
|--------------------------------------------------------------------------
*/

test('webhook fulfils a donation when Paystack verification confirms success and the amount matches', function () {
    $user = User::factory()->create(['lifecycle_status' => 'pending_engagement']);
    $transaction = PaymentTransaction::create([
        'user_id' => $user->id,
        'payable_type' => 'donation',
        'reference' => 'PSK-donation-ok',
        'amount' => 250000,
        'status' => 'initiated',
        'meta' => ['payment_type' => 'donation', 'donation_purpose' => 'Flood relief'],
    ]);

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'success', 'amount' => 250000, 'reference' => 'PSK-donation-ok'],
        ], 200),
    ]);

    $payload = ['event' => 'charge.success', 'data' => ['reference' => 'PSK-donation-ok']];

    $this->postJson('/webhooks/paystack', $payload, paystackWebhookHeaders($payload))
        ->assertOk()
        ->assertJson(['status' => 'processed']);

    $donation = Donation::withAnyApprovalStatus()->sole();
    expect($donation->user_id)->toBe($user->id)
        ->and($donation->amount)->toBe(2500) // kobo -> naira
        ->and($donation->purpose)->toBe('Flood relief')
        ->and($donation->payment_channel)->toBe('paystack')
        ->and($donation->gateway_reference)->toBe('PSK-donation-ok')
        ->and($donation->approval_status)->toBe(Donation::APPROVED)
        ->and($donation->decided_by_user_id)->toBeNull();

    $transaction->refresh();
    expect($transaction->status)->toBe('success')
        ->and($transaction->donation_id)->toBe($donation->id)
        ->and($transaction->membership_payment_id)->toBeNull();

    // Donation::promotesFromPendingEngagement() is false — a single donation
    // alone must not lift a pending_engagement donor to active.
    expect($user->refresh()->lifecycle_status)->toBe('pending_engagement');
});

test('webhook fulfils a personal membership payment and promotes a pending_engagement member', function () {
    $user = User::factory()->create(['lifecycle_status' => 'pending_engagement']);
    $fee = MembershipFee::factory()->create(['amount' => 5000, 'validity_years' => 2]);
    $transaction = PaymentTransaction::create([
        'user_id' => $user->id,
        'payable_type' => 'membership_payment',
        'reference' => 'PSK-membership-ok',
        'amount' => 500000,
        'status' => 'initiated',
        'meta' => ['payment_type' => 'membership', 'membership_fee_id' => $fee->id],
    ]);

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'success', 'amount' => 500000, 'reference' => 'PSK-membership-ok'],
        ], 200),
    ]);

    $payload = ['event' => 'charge.success', 'data' => ['reference' => 'PSK-membership-ok']];

    $this->postJson('/webhooks/paystack', $payload, paystackWebhookHeaders($payload))
        ->assertOk()
        ->assertJson(['status' => 'processed']);

    $payment = MembershipPayment::withAnyApprovalStatus()->sole();
    expect($payment->user_id)->toBe($user->id)
        ->and($payment->membership_fee_id)->toBe($fee->id)
        ->and($payment->payment_channel)->toBe('paystack')
        ->and($payment->gateway_reference)->toBe('PSK-membership-ok')
        ->and($payment->approval_status)->toBe(MembershipPayment::APPROVED)
        ->and($payment->expiry_date->toDateString())->toBe(now()->addYears(2)->toDateString());

    expect($user->refresh()->lifecycle_status)->toBe('active');

    $transaction->refresh();
    expect($transaction->status)->toBe('success')
        ->and($transaction->membership_payment_id)->toBe($payment->id);
});

test('webhook fulfils an org-sponsored membership payment scoped to the organisation\'s branch', function () {
    $branch = \App\Models\Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $user = User::factory()->create();
    $organisation = Organisation::create(['name' => 'Sponsor Org', 'branch_id' => $branch->id]);
    $fee = MembershipFee::factory()->create(['for_organizations' => true, 'amount' => 20000]);

    $transaction = PaymentTransaction::create([
        'user_id' => $user->id,
        'organisation_id' => $organisation->id,
        'payable_type' => 'membership_payment',
        'reference' => 'PSK-org-ok',
        'amount' => 2000000,
        'status' => 'initiated',
        'meta' => ['payment_type' => 'membership', 'membership_fee_id' => $fee->id, 'organisation_id' => $organisation->id],
    ]);

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'success', 'amount' => 2000000, 'reference' => 'PSK-org-ok'],
        ], 200),
    ]);

    $payload = ['event' => 'charge.success', 'data' => ['reference' => 'PSK-org-ok']];

    $this->postJson('/webhooks/paystack', $payload, paystackWebhookHeaders($payload))
        ->assertOk();

    $payment = MembershipPayment::withAnyApprovalStatus()->sole();
    expect($payment->organisation_id)->toBe($organisation->id)
        ->and($payment->branch_id)->toBe($branch->id)
        ->and($payment->division_id)->toBeNull();
});

test('webhook marks the transaction failed (without creating a record) when the verified amount does not match', function () {
    $user = User::factory()->create();
    $transaction = PaymentTransaction::create([
        'user_id' => $user->id,
        'payable_type' => 'donation',
        'reference' => 'PSK-amount-mismatch',
        'amount' => 250000,
        'status' => 'initiated',
        'meta' => ['payment_type' => 'donation'],
    ]);

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            // Paystack confirms success, but for a different (lower) amount than charged.
            'data' => ['status' => 'success', 'amount' => 100, 'reference' => 'PSK-amount-mismatch'],
        ], 200),
    ]);

    $payload = ['event' => 'charge.success', 'data' => ['reference' => 'PSK-amount-mismatch']];

    $this->postJson('/webhooks/paystack', $payload, paystackWebhookHeaders($payload))
        ->assertOk();

    expect(Donation::count())->toBe(0);
    expect($transaction->refresh()->status)->toBe('failed');
});

test('webhook marks the transaction failed when Paystack itself reports the charge did not succeed', function () {
    $user = User::factory()->create();
    $transaction = PaymentTransaction::create([
        'user_id' => $user->id,
        'payable_type' => 'donation',
        'reference' => 'PSK-not-successful',
        'amount' => 250000,
        'status' => 'initiated',
        'meta' => ['payment_type' => 'donation'],
    ]);

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'abandoned', 'amount' => 250000, 'reference' => 'PSK-not-successful'],
        ], 200),
    ]);

    $payload = ['event' => 'charge.success', 'data' => ['reference' => 'PSK-not-successful']];

    $this->postJson('/webhooks/paystack', $payload, paystackWebhookHeaders($payload))
        ->assertOk();

    expect(Donation::count())->toBe(0);
    expect($transaction->refresh()->status)->toBe('failed');
});

test('webhook logs the exception and still returns 200 (not a retry-triggering status) when fulfilment throws', function () {
    $user = User::factory()->create();
    // membership_fee_id in meta points at nothing — MembershipFee::findOrFail()
    // inside the fulfilment transaction throws, exercising the catch block.
    $transaction = PaymentTransaction::create([
        'user_id' => $user->id,
        'payable_type' => 'membership_payment',
        'reference' => 'PSK-broken',
        'amount' => 500000,
        'status' => 'initiated',
        'meta' => ['payment_type' => 'membership', 'membership_fee_id' => 999999],
    ]);

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'success', 'amount' => 500000, 'reference' => 'PSK-broken'],
        ], 200),
    ]);

    $payload = ['event' => 'charge.success', 'data' => ['reference' => 'PSK-broken']];

    $this->postJson('/webhooks/paystack', $payload, paystackWebhookHeaders($payload))
        ->assertStatus(200)
        ->assertJson(['status' => 'received']);

    // Never got past the exception to update status — left exactly as it
    // was before this delivery, so Paystack's automatic retry can still
    // reach a human-fixed version of this code later.
    expect($transaction->refresh()->status)->toBe('initiated');
    expect(MembershipPayment::count())->toBe(0);
});
