<?php

/**
 * Unit tests for PaystackService — the thin Paystack API wrapper. No
 * database, no HTTP server: Http::fake() intercepts every outbound call.
 */

use App\Exceptions\PaystackException;
use App\Services\PaystackService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['paystack.secret_key' => 'sk_test_secret']);
    config(['paystack.base_url' => 'https://api.paystack.co']);
});

test('initializeTransaction posts amount/email/reference and returns the decoded body', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'message' => 'Authorization URL created',
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/abc123',
                'access_code' => 'abc123',
                'reference' => 'PSK-1',
            ],
        ], 200),
    ]);

    $result = (new PaystackService)->initializeTransaction(
        email: 'payer@example.com',
        amountInKobo: 500000,
        reference: 'PSK-1',
        metadata: ['payment_type' => 'donation'],
    );

    expect($result['data']['authorization_url'])->toBe('https://checkout.paystack.com/abc123');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.paystack.co/transaction/initialize'
            && $request->hasHeader('Authorization', 'Bearer sk_test_secret')
            && $request['email'] === 'payer@example.com'
            && $request['amount'] === 500000
            && $request['reference'] === 'PSK-1'
            && $request['metadata'] === ['payment_type' => 'donation'];
    });
});

test('initializeTransaction omits callback_url from the payload when not given', function () {
    Http::fake([
        'api.paystack.co/*' => Http::response(['status' => true, 'data' => []], 200),
    ]);

    (new PaystackService)->initializeTransaction('payer@example.com', 1000, 'PSK-2');

    Http::assertSent(fn ($request) => ! array_key_exists('callback_url', $request->data()));
});

test('initializeTransaction includes callback_url when explicitly passed', function () {
    Http::fake([
        'api.paystack.co/*' => Http::response(['status' => true, 'data' => []], 200),
    ]);

    (new PaystackService)->initializeTransaction(
        'payer@example.com',
        1000,
        'PSK-3',
        [],
        'https://app.example.com/make-payment/callback'
    );

    Http::assertSent(fn ($request) => $request['callback_url'] === 'https://app.example.com/make-payment/callback');
});

test('initializeTransaction throws PaystackException with Paystack\'s message on a non-2xx response', function () {
    Http::fake([
        'api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Invalid key'], 401),
    ]);

    expect(fn () => (new PaystackService)->initializeTransaction('payer@example.com', 1000, 'PSK-4'))
        ->toThrow(PaystackException::class, 'Invalid key');
});

test('initializeTransaction throws PaystackException on a 2xx body carrying status:false', function () {
    Http::fake([
        'api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Amount too low'], 200),
    ]);

    expect(fn () => (new PaystackService)->initializeTransaction('payer@example.com', 1, 'PSK-5'))
        ->toThrow(PaystackException::class, 'Amount too low');
});

test('initializeTransaction throws a generic message when Paystack gives no message at all', function () {
    Http::fake([
        'api.paystack.co/*' => Http::response('', 500),
    ]);

    expect(fn () => (new PaystackService)->initializeTransaction('payer@example.com', 1000, 'PSK-6'))
        ->toThrow(PaystackException::class, 'Paystack request failed with no message.');
});

test('verifyTransaction GETs the verify endpoint with the reference URL-encoded', function () {
    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'success', 'amount' => 500000, 'reference' => 'PSK-7'],
        ], 200),
    ]);

    $result = (new PaystackService)->verifyTransaction('PSK/7 weird');

    expect($result['data']['status'])->toBe('success');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'transaction/verify/PSK%2F7%20weird'));
});

test('verifyTransaction throws PaystackException when Paystack reports the transaction failed', function () {
    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response(['status' => false, 'message' => 'Transaction not found'], 200),
    ]);

    expect(fn () => (new PaystackService)->verifyTransaction('unknown-ref'))
        ->toThrow(PaystackException::class, 'Transaction not found');
});

test('verifyWebhookSignature accepts a signature that matches HMAC SHA512 of the raw body with the secret key', function () {
    $payload = '{"event":"charge.success","data":{"reference":"PSK-8"}}';
    $validSignature = hash_hmac('sha512', $payload, 'sk_test_secret');

    expect((new PaystackService)->verifyWebhookSignature($payload, $validSignature))->toBeTrue();
});

test('verifyWebhookSignature rejects a signature computed with the wrong key', function () {
    $payload = '{"event":"charge.success","data":{"reference":"PSK-8"}}';
    $wrongSignature = hash_hmac('sha512', $payload, 'not-the-secret');

    expect((new PaystackService)->verifyWebhookSignature($payload, $wrongSignature))->toBeFalse();
});

test('verifyWebhookSignature rejects a signature when the payload has been altered', function () {
    $originalPayload = '{"event":"charge.success","data":{"reference":"PSK-8","amount":500000}}';
    $tamperedPayload = '{"event":"charge.success","data":{"reference":"PSK-8","amount":1}}';
    $signatureForOriginal = hash_hmac('sha512', $originalPayload, 'sk_test_secret');

    expect((new PaystackService)->verifyWebhookSignature($tamperedPayload, $signatureForOriginal))->toBeFalse();
});
