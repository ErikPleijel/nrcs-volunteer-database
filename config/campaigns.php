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
];
