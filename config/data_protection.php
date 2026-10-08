<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Anonymization of archived accounts
    |--------------------------------------------------------------------------
    |
    | An archived account is anonymized by users:anonymize-expired once it has
    | been archived (users.archived_at) for this many years. The privacy
    | policy states 7 years; change both together. See Decisions.md
    | 2026-10-08 "Anonymization of archived accounts".
    |
    */

    'anonymize_after_years' => 7,

];
