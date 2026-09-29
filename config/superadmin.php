<?php

/*
|--------------------------------------------------------------------------
| Super Admin Emails
|--------------------------------------------------------------------------
| The accounts SuperAdminSeeder creates and grants the 'super-admin' role
| (and is_super_admin). Comma-separated in SUPER_ADMIN_EMAILS. This is the
| only place the list is read — the seeder grants, UserObserver only revokes
| when an account's email leaves the list.
*/
return [
    'emails' => array_values(array_filter(array_map(
        fn ($email) => strtolower(trim($email)),
        explode(',', (string) env('SUPER_ADMIN_EMAILS', ''))
    ))),
];
