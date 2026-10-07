<?php

/**
 * Paystack has no separate webhook-signing secret: webhook payloads are
 * signed with the account's SECRET key itself (HMAC SHA512 over the raw
 * request body, sent in the `x-paystack-signature` header). There is
 * therefore no `webhook_secret` entry here — PaystackService::
 * verifyWebhookSignature() signs with 'secret_key' below, matching current
 * Paystack docs (see PaystackService's implementation report for the source).
 */
return [
    // Master switch for every online-payment flow (personal, organisation,
    // Red Cross Unit, donations). Payments are only offered when this is on
    // AND both keys below are set — see OnlinePaymentEligibility::isAvailable().
    'enabled' => env('PAYSTACK_ENABLED', false),
    'secret_key' => env('PAYSTACK_SECRET_KEY'),
    'public_key' => env('PAYSTACK_PUBLIC_KEY'),
    'base_url' => 'https://api.paystack.co',
    'currency' => env('PAYSTACK_CURRENCY', 'NGN'),
];
