<?php

return [

    'super-admin' => [
        'title' => 'System Administrator',
        'points' => [
            'Appoint and remove <strong>National Database Administrators</strong> — this is its only purpose.',
            'Cannot edit records, make payments, run campaigns, or approve anything.',
            'Has no personal profile.',
        ],
        'notice' => 'This account exists solely to authorize National Database Administrators. All actions are logged. This data is confidential — do not share it with anyone outside the administrative team.',
    ],

    'national_db_administrator' => [
        'title' => 'National Database Administrator',
        'points' => [
            'View, enter, and edit records for <strong>all persons</strong> across all branches and divisions.',
            'Assign roles to others, including branch secretaries and database assistants at all levels.',
            'Access all <strong>national reports and statistics</strong>.',
            'Authorize and oversee the work of branch and division administrators.',
            'Approve <strong>Payments, Donations, Trainings, and Volunteering</strong> records submitted by other staff (four-eyes verification).',
        ],
        'notice' => 'You have national-level access. This data is confidential — do not share it with anyone outside the administrative team.',
    ],

    'national_db_assistant' => [
        'title' => 'National Database Assistant',
        'points' => [
            'Enter and correct records at <strong>national level</strong> under the direction of the national database administrator.',
            'Search, filter, and view records for all persons in the database.',
            'View <strong>national reports and statistics</strong>.',
        ],
        'notice' => 'You have national-level access. This data is confidential — do not share it with anyone outside the administrative team.',
    ],

    'branch_secretary' => [
        'title' => 'Branch Secretary',
        'points' => [
            'View, enter, and edit records for persons in <strong>your branch</strong>.',
            'Authorize and assign database assistants within your branch and its divisions.',
            'Access <strong>reports and statistics</strong> for your branch.',
            'Oversee the quality and accuracy of data within your branch.',
            'Approve <strong>Payments, Donations, Trainings, and Volunteering</strong> records submitted by other branch staff (four-eyes verification).',
        ],
        'notice' => 'You have branch-level administrative access. This data is confidential — do not share it with anyone outside the administrative team.',
    ],

    'branch_db_administrator' => [
        'title' => 'Branch Database Administrator',
        'points' => [
            'View, enter, and edit records for persons in <strong>your branch</strong>.',
            'Assign database assistants within your branch and its divisions.',
            'Access <strong>reports and statistics</strong> for your branch.',
            'Approve <strong>Payments, Donations, Trainings, and Volunteering</strong> records submitted by other branch staff (four-eyes verification).',
        ],
        'notice' => 'You have branch-level administrative access. This data is confidential — do not share it with anyone outside the administrative team.',
    ],

    'branch_db_assistant' => [
        'title' => 'Branch Database Assistant',
        'points' => [
            'Enter and maintain records for persons in <strong>your branch</strong>.',
            'Search and filter persons in the database and view their records.',
            'View <strong>reports and statistics</strong> for your branch.',
        ],
        'notice' => 'You have branch-level access. This data is confidential — do not share it with anyone outside the administrative team.',
    ],

    'division_db_assistant_finance' => [
        'title' => 'Division Database Assistant (Finance & Operations)',
        'points' => [
            'Enter <strong>Membership Payments</strong> and <strong>Donations</strong> for persons in your division.',
            'Enter <strong>Volunteering Logs</strong> and <strong>Training Records</strong> for persons in your division.',
            'Search and filter persons in the database and view their records.',
            'View statistics and reports for all NRCS branches.',
        ],
        'notice' => 'You have division-level access including financial records. This data is confidential — do not share it with anyone outside the administrative team.',
    ],

    'division_db_assistant_operations' => [
        'title' => 'Division Database Assistant (Operations)',
        'points' => [
            'Enter <strong>Volunteering Logs</strong> and <strong>Training Records</strong> for persons in your division.',
            'Search and filter persons in the database and view their records.',
            'View statistics and reports for all NRCS branches.',
        ],
        'notice' => 'You have division-level access. This data is confidential — do not share it with anyone outside the administrative team.',
    ],

    'observer_national_level' => [
        'title' => 'National Observer',
        'points' => [
            'View national data, reports, and statistics in <strong>read-only</strong> mode.',
            'Search and filter persons in the database.',
        ],
        'notice' => 'You have read-only access — you cannot edit, add, or delete any records. This data is confidential — do not share it with anyone outside the administrative team.',
    ],

];
