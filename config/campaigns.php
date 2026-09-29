<?php

return [
    'delivery' => [
        // later: swap the email one to a real channel (e.g. SendGrid) as well.
        'channels' => [
            \App\Campaigns\Delivery\LogEmailChannel::class,

            // CAMPAIGN_SMS_CHANNEL=log (default, log only) | smslive247 (see config/smslive247.php,
            // which itself stays in dry run until SMSLIVE247_DRY_RUN=false).
            env('CAMPAIGN_SMS_CHANNEL', 'log') === 'smslive247'
                ? \App\Campaigns\Delivery\SmsLive247Channel::class
                : \App\Campaigns\Delivery\LogSmsChannel::class,
        ],

        // if your campaign "both" should be considered success when:
        'both_success_rule' => env('CAMPAIGN_BOTH_SUCCESS_RULE', 'at_least_one'), // or "all"

        'mail_from_email' => env('CAMPAIGNS_FROM_EMAIL', 'info@nrcs.org'),
    ],

    'default_from_name' => env('CAMPAIGN_FROM_NAME', 'Nigerian Red Cross Society'),
    'default_reply_to_email' => env('CAMPAIGN_REPLY_TO_EMAIL', 'no-reply@nrcs.org'),
];
