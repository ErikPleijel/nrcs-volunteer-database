<?php

namespace App\Services;

use App\Exceptions\SmsLive247Exception;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Thin wrapper around the SMSLive247 v4 REST API, following PaystackService: no
 * constructor arguments (reads config/smslive247.php), faked with Http::fake() in tests.
 *
 * UNCONFIRMED until we have an account: the auth header format (isolated in client()),
 * the response field holding the message id (isolated in messageIdFrom()), and the
 * status/balance response shapes (returned as decoded arrays, not interpreted).
 * Delivery-status polling is deliberately not built yet.
 *
 * In dry run (config smslive247.dry_run, or $dryRun) no HTTP request is made: sendSms()
 * returns a fake "dryrun-…" id and logs a masked line.
 */
class SmsLive247Service
{
    /**
     * Send one SMS.
     *
     * @param  string  $e164  +234XXXXXXXXXX (see PhoneNumber::toE164)
     * @return array{message_id: ?string, dry_run: bool, response: array}
     *
     * @throws SmsLive247Exception
     */
    public function sendSms(string $e164, string $text, bool $dryRun = false): array
    {
        if ($dryRun || $this->dryRun()) {
            $id = 'dryrun-'.Str::uuid()->toString();

            Log::channel('campaign_deliveries')->info('SmsLive247Service: dry run, SMS not sent', [
                'to' => PhoneNumber::mask($e164),
                'length' => mb_strlen($text),
                'message_id' => $id,
            ]);

            return ['message_id' => $id, 'dry_run' => true, 'response' => []];
        }

        $senderId = (string) config('smslive247.sender_id');
        if ($senderId === '') {
            throw new SmsLive247Exception('SMSLive247 sender ID is not configured (SMSLIVE247_SENDER_ID).');
        }

        // Never retried: a send that timed out may already have gone out.
        $body = $this->decodeOrThrow(fn () => $this->client()->post('sms', [
            'senderID' => $senderId,
            'mobileNumber' => $e164,
            'messageText' => $text,
        ]));

        return ['message_id' => $this->messageIdFrom($body), 'dry_run' => false, 'response' => $body];
    }

    /**
     * Raw status response for one message (shape unconfirmed).
     *
     * @throws SmsLive247Exception
     */
    public function getMessageStatus(string $messageId): array
    {
        return $this->decodeOrThrow(fn () => $this->client(retry: true)->get('sms/'.rawurlencode($messageId)));
    }

    /**
     * Raw account/balance response (shape unconfirmed).
     *
     * @throws SmsLive247Exception
     */
    public function getBalance(): array
    {
        return $this->decodeOrThrow(fn () => $this->client(retry: true)->get('accounts'));
    }

    public function dryRun(): bool
    {
        return (bool) config('smslive247.dry_run', true);
    }

    /**
     * Base HTTP client. The ONLY place the auth header is set — the documented format is
     * "Authorization: <API key>" (no "Bearer"), unconfirmed until we have an account.
     * $retry: connection errors and 5xx only — never 4xx, and never for sends.
     */
    private function client(bool $retry = false): PendingRequest
    {
        $apiKey = (string) config('smslive247.api_key');
        if ($apiKey === '') {
            throw new SmsLive247Exception('SMSLive247 API key is not configured (SMSLIVE247_API_KEY).');
        }

        $client = Http::baseUrl(rtrim((string) config('smslive247.base_url'), '/').'/')
            ->withHeaders(['Authorization' => $apiKey])
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('smslive247.timeout', 15));

        if ($retry && (int) config('smslive247.retries', 0) > 0) {
            $client->retry(
                (int) config('smslive247.retries') + 1,
                500,
                fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()),
                throw: false
            );
        }

        return $client;
    }

    /**
     * Run a request and return its decoded body, or throw SmsLive247Exception with the
     * provider's own message on a connection error or non-2xx response.
     *
     * @param  callable(): Response  $request
     */
    private function decodeOrThrow(callable $request): array
    {
        try {
            $response = $request();
        } catch (ConnectionException $e) {
            throw new SmsLive247Exception('Could not reach SMSLive247: '.$e->getMessage());
        }

        $body = $response->json() ?? [];

        if ($response->failed()) {
            $message = is_array($body)
                ? ($body['message'] ?? $body['error'] ?? $body['title'] ?? null)
                : null;

            throw new SmsLive247Exception(
                'SMSLive247 request failed ('.$response->status().'): '.($message ?: 'no message'),
                $response->status()
            );
        }

        return is_array($body) ? $body : [];
    }

    /**
     * The provider message id from a send response. Field name unconfirmed, so a few
     * likely spellings are tried; null when none is present (the SMS may still have been
     * accepted, so this is NOT treated as a failure).
     */
    private function messageIdFrom(array $body): ?string
    {
        foreach (['messageId', 'messageID', 'MessageId', 'id', 'data.messageId', 'data.messageID', 'data.id'] as $key) {
            $value = data_get($body, $key);
            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        Log::channel('campaign_deliveries')->warning('SmsLive247Service: send accepted but no message id recognised', [
            'response_keys' => array_keys($body),
        ]);

        return null;
    }
}
