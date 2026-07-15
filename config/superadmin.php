<?php

return [
    'emails' => array_filter(array_map('trim', explode(',', env('SUPER_ADMIN_EMAILS', '')))),
];
