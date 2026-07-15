<?php

return [
    // Trailing window for activity/training intensity
    'window_months' => 6,

    // Weights must sum to 1.0. Each factor is normalized to 0–1
    // (relative to the busiest division/branch) BEFORE weighting.
    'weights' => [
        'hours_per_volunteer'     => 0.5,
        'trainings_per_volunteer' => 0.5,
    ],
];
