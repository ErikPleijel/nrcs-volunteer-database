<?php

/**
 * SmsLive247Service and SmsLive247Channel — no database, no network:
 * Http::fake() intercepts every call (same pattern as PaystackServiceTest).
 */

use App\Campaigns\Delivery\DeliveryMessage;
use App\Campaigns\Delivery\SmsLive247Channel;
use App\Exceptions\SmsLive247Exception;
use App\Models\MessagingRecipient;
use App\Services\SmsLive247Service;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'smslive247.api_key' => 'test-key-123',
        'smslive247.base_url' => 'https://api.smslive247.com/api/v4',
        'smslive247.sender_id' => 'NRCS',
        'smslive247.dry_run' => false,
        'smslive247.timeout' => 5,
        'smslive247.retries' => 2,
    ]);
});

test('sendSms posts senderID, mobileNumber and messageText with the raw API key header', function () {
    Http::fake(['api.smslive247.com/api/v4/sms' => Http::response(['messageId' => 'abc-123'], 200)]);

    $result = (new SmsLive247Service)->sendSms('+2348031234567', 'Hello from the Red Cross');

    expect($result)->toBe(['message_id' => 'abc-123', 'dry_run' => false, 'response' => ['messageId' => 'abc-123']]);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://api.smslive247.com/api/v4/sms'
        && $request->header('Authorization') === ['test-key-123']
        && $request['senderID'] === 'NRCS'
        && $request['mobileNumber'] === '+2348031234567'
        && $request['messageText'] === 'Hello from the Red Cross');
});

test('the message id is found under a nested data key too', function () {
    Http::fake(['*/sms' => Http::response(['data' => ['id' => 987]], 201)]);

    expect((new SmsLive247Service)->sendSms('+2348031234567', 'Hi')['message_id'])->toBe('987');
});

test('an accepted send without a recognisable id is not treated as a failure', function () {
    Http::fake(['*/sms' => Http::response(['status' => 'queued'], 200)]);

    expect((new SmsLive247Service)->sendSms('+2348031234567', 'Hi')['message_id'])->toBeNull();
});

test('dry run makes no HTTP call and returns a dryrun- id', function () {
    Http::fake();
    config(['smslive247.dry_run' => true, 'smslive247.api_key' => null, 'smslive247.sender_id' => null]);

    $result = (new SmsLive247Service)->sendSms('+2348031234567', 'Hi');

    expect($result['message_id'])->toStartWith('dryrun-')
        ->and($result['dry_run'])->toBeTrue();
    Http::assertNothingSent();
});

test('a 4xx is not retried and throws with the provider message', function () {
    Http::fake(['*/accounts' => Http::response(['message' => 'Invalid API key'], 401)]);

    expect(fn () => (new SmsLive247Service)->getBalance())
        ->toThrow(SmsLive247Exception::class, 'SMSLive247 request failed (401): Invalid API key');

    Http::assertSentCount(1);
});

test('a 5xx on a read call is retried, then throws', function () {
    Http::fake(['*/sms/abc-123' => Http::response(['message' => 'Server error'], 503)]);

    expect(fn () => (new SmsLive247Service)->getMessageStatus('abc-123'))
        ->toThrow(SmsLive247Exception::class, '(503)');

    Http::assertSentCount(3); // 1 + 2 retries
});

test('a 5xx on a read call that recovers returns the body', function () {
    Http::fakeSequence('*/accounts')
        ->push(['message' => 'busy'], 502)
        ->push(['balance' => 1500.5, 'currency' => 'NGN'], 200);

    expect((new SmsLive247Service)->getBalance())->toBe(['balance' => 1500.5, 'currency' => 'NGN']);
    Http::assertSentCount(2);
});

test('a send is never retried, even on a 5xx', function () {
    Http::fake(['*/sms' => Http::response(['message' => 'Gateway timeout'], 504)]);

    expect(fn () => (new SmsLive247Service)->sendSms('+2348031234567', 'Hi'))
        ->toThrow(SmsLive247Exception::class, '(504)');

    Http::assertSentCount(1);
});

test('missing credentials fail clearly outside dry run', function () {
    Http::fake();

    config(['smslive247.sender_id' => null]);
    expect(fn () => (new SmsLive247Service)->sendSms('+2348031234567', 'Hi'))
        ->toThrow(SmsLive247Exception::class, 'SMSLIVE247_SENDER_ID');

    config(['smslive247.sender_id' => 'NRCS', 'smslive247.api_key' => null]);
    expect(fn () => (new SmsLive247Service)->sendSms('+2348031234567', 'Hi'))
        ->toThrow(SmsLive247Exception::class, 'SMSLIVE247_API_KEY');

    Http::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| Channel adapter
|--------------------------------------------------------------------------
*/

function smsRecipient(): MessagingRecipient
{
    $r = new MessagingRecipient(['messaging_campaign_id' => 1, 'phone' => '+2348031234567']);
    $r->id = 5;

    return $r;
}

test('the channel maps a successful send to a DeliveryAttempt with provider and id', function () {
    Http::fake(['*/sms' => Http::response(['messageId' => 'abc-123'], 200)]);

    $attempt = (new SmsLive247Channel(new SmsLive247Service))
        ->deliver(smsRecipient(), new DeliveryMessage(subject: null, body: 'Hi', smsBody: 'Hi'));

    expect($attempt->ok)->toBeTrue()
        ->and($attempt->channel)->toBe('sms')
        ->and($attempt->provider)->toBe('smslive247')
        ->and($attempt->providerMessageId)->toBe('abc-123');
});

test('the channel maps a provider error to a failed attempt instead of throwing', function () {
    Http::fake(['*/sms' => Http::response(['message' => 'Insufficient credit'], 402)]);

    $attempt = (new SmsLive247Channel(new SmsLive247Service))
        ->deliver(smsRecipient(), new DeliveryMessage(subject: null, body: 'Hi', smsBody: 'Hi'));

    expect($attempt->ok)->toBeFalse()
        ->and($attempt->errorCode)->toBe('402')
        ->and($attempt->errorMessage)->toContain('Insufficient credit')
        ->and($attempt->provider)->toBe('smslive247');
});

test('a campaign dry run never calls the API, even with provider dry run switched off', function () {
    Http::fake();

    $attempt = (new SmsLive247Channel(new SmsLive247Service))->deliver(
        smsRecipient(),
        new DeliveryMessage(subject: null, body: 'Hi', smsBody: 'Hi', meta: ['dry_run' => true])
    );

    expect($attempt->ok)->toBeTrue()
        ->and($attempt->providerMessageId)->toStartWith('dryrun-')
        ->and($attempt->provider)->toBe('smslive247-dryrun');
    Http::assertNothingSent();
});
