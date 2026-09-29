<?php

/**
 * SMSLive247 (v4 REST API). No credentials yet: dry_run defaults to true, so nothing is
 * sent until SMSLIVE247_DRY_RUN=false is set deliberately.
 *
 * Campaigns only use this provider when CAMPAIGN_SMS_CHANNEL=smslive247
 * (config/campaigns.php); the default stays the log-only channel.
 */
return [
    'api_key' => env('SMSLIVE247_API_KEY'),
    'base_url' => env('SMSLIVE247_BASE_URL', 'https://api.smslive247.com/api/v4'),
    'sender_id' => env('SMSLIVE247_SENDER_ID'),
    'dry_run' => filter_var(env('SMSLIVE247_DRY_RUN', true), FILTER_VALIDATE_BOOLEAN),

    // Seconds per HTTP request.
    'timeout' => (int) env('SMSLIVE247_TIMEOUT', 15),

    // Retries for read-only calls (status, balance) on connection errors and 5xx responses.
    // Sending is never retried automatically: a timed-out send may already have been
    // delivered, and campaign sending is at-most-once (see Decisions.md).
    'retries' => (int) env('SMSLIVE247_RETRIES', 2),
];
