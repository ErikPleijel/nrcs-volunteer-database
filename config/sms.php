<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMS price per page (segment)
    |--------------------------------------------------------------------------
    | Used only to show an estimated cost on the campaign review and approval
    | screens. Leave SMS_PRICE_PER_SEGMENT empty to hide the estimate.
    */
    'price_per_segment' => is_numeric(env('SMS_PRICE_PER_SEGMENT')) ? (float) env('SMS_PRICE_PER_SEGMENT') : null,

    'currency' => env('SMS_CURRENCY', 'NGN'),
];
