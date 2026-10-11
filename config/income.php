<?php

return [
    /*
     * Income Distribution, Tab 2: the day manual (branch-collected)
     * membership payments start being shared out for real (Y-m-d, Lagos
     * time). Empty: not live yet, every quarter is shown as an estimate.
     * Read through FeeDistributionSettings::manualGoLiveDate().
     */
    'manual_go_live_date' => env('MANUAL_GO_LIVE_DATE'),
];
