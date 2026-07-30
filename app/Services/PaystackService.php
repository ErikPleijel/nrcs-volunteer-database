<?php

namespace App\Services;

use App\Exceptions\PaystackException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the Paystack Transactions API. Takes no constructor
 * arguments — reads config/paystack.php internally — so it's trivial to
 * instantiate directly in tests and fake with Http::fake().
 *
 * This class never decides whether a payment "succeeded" in the app's own
 * terms; it only talks to Paystack and returns what Paystack said, or throws
 * PaystackException when Paystack itself reports a failure.
 */
class PaystackService
{
    /**
     * Initialize a Paystack transaction.
     *
     * $amountInKobo must already be converted to the smallest currency unit
     * (kobo for NGN, pesewas for GHS, cents for ZAR) by the CALLER — this
     * method performs no currency conversion.
     *
     * $callbackUrl, when omitted, is left out of the request body entirely;
     * Paystack then falls back to the callback URL configured on the
     * account's dashboard. Pass it explicitly when a specific redirect
     * target is needed for a given flow.
     *
     * @return array Decoded Paystack response body (includes an
     *               "authorization_url" to redirect the payer to).
     *
     * @throws PaystackException
     */
    public function initializeTransaction(
        string $email,
        int $amountInKobo,
        string $reference,
        array $metadata = [],
        ?string $callbackUrl = null
    ): array {
        $payload = [
            'email' => $email,
            'amount' => $amountInKobo,
            'reference' => $reference,
            'metadata' => $metadata,
        ];

        if ($callbackUrl !== null) {
            $payload['callback_url'] = $callbackUrl;
        }

        $response = $this->client()->post('/transaction/initialize', $payload);

        return $this->decodeOrThrow($response);
    }

    /**
     * Fetch Paystack's record of a transaction by reference.
     *
     * Does NOT decide success/failure — the caller inspects the returned
     * array (e.g. $result['data']['status'] === 'success') themselves.
     *
     * @throws PaystackException
     */
    public function verifyTransaction(string $reference): array
    {
        $response = $this->client()->get('/transaction/verify/'.rawurlencode($reference));

        return $this->decodeOrThrow($response);
    }

    /**
     * Verify that a webhook payload genuinely came from Paystack.
     *
     * Paystack signs the raw request body with HMAC SHA512 using the
     * account's SECRET key (not a separate webhook secret) and sends the
     * result in the `x-paystack-signature` header. $payload must be the
     * exact raw request body (e.g. $request->getContent()), not a
     * re-encoded/decoded version, since re-encoding can change byte-for-byte
     * whitespace/ordering and break the comparison.
     *
     * No side effects, no logging — in particular, never logs the secret key.
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): bool
    {
        $computedSignature = hash_hmac('sha512', $payload, (string) config('paystack.secret_key'));

        return hash_equals($computedSignature, $signatureHeader);
    }

    /**
     * Base HTTP client: Bearer-authenticated with the secret key, scoped to
     * Paystack's base URL. Centralised here so no method repeats the auth
     * header setup.
     */
    private function client(): PendingRequest
    {
        return Http::withToken((string) config('paystack.secret_key'))
            ->baseUrl((string) config('paystack.base_url'))
            ->acceptJson();
    }

    /**
     * Decode a Paystack response, throwing PaystackException (with
     * Paystack's own message) on a non-2xx HTTP status or a 2xx body that
     * itself carries {"status": false}.
     *
     * @throws PaystackException
     */
    private function decodeOrThrow(Response $response): array
    {
        $body = $response->json() ?? [];

        if ($response->failed() || ($body['status'] ?? false) === false) {
            throw new PaystackException(
                $body['message'] ?? 'Paystack request failed with no message.'
            );
        }

        return $body;
    }
}
