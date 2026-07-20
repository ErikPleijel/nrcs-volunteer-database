<?php

return [
    'delivery' => [
        // later: swap these to SendGridChannel::class / TwilioSmsChannel::class etc.
        'channels' => [
            \App\Campaigns\Delivery\LogEmailChannel::class,
            \App\Campaigns\Delivery\LogSmsChannel::class,
        ],

        // if your campaign "both" should be considered success when:
        'both_success_rule' => env('CAMPAIGN_BOTH_SUCCESS_RULE', 'at_least_one'), // or "all"

        'mail_from_email' => env('CAMPAIGNS_FROM_EMAIL', 'info@nrcs.org'),
    ],

    'default_from_name' => env('CAMPAIGN_FROM_NAME', 'Nigerian Red Cross Society'),
    'default_reply_to_email' => env('CAMPAIGN_REPLY_TO_EMAIL', 'no-reply@nrcs.org'),
];
