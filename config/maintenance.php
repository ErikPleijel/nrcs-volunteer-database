<?php

return [
    'login_gate_enabled' => env('MAINTENANCE_LOGIN_GATE', false),
    'allowed_user_ids' => array_filter(array_map('trim', explode(',', env('MAINTENANCE_ALLOWED_USER_IDS', '')))),
];
