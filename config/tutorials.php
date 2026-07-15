<?php

return [
    'levels' => [
        0 => ['title' => 'Getting Oriented',           'icon' => 'fa-hand-wave',    'min_access' => ['division', 'branch', 'national']],
        1 => ['title' => 'Records & Reporting Basics', 'icon' => 'fa-rocket',       'min_access' => ['division', 'branch', 'national']],
        2 => ['title' => 'Branch Administration',      'icon' => 'fa-sitemap',      'min_access' => ['division', 'branch', 'national']],
        3 => ['title' => 'National Administration',    'icon' => 'fa-globe-africa', 'min_access' => ['branch', 'national']],
    ],

    'lessons' => [
        'level0.welcome' => [
            'level' => 0,
            'order' => 1,
            'title' => 'Welcome & Overview',
            'type'  => 'slides',
            'view'  => 'tutorials.lessons.level0.welcome',
        ],

        'level1.find_people' => [
            'level' => 1,
            'order' => 2,
            'title' => 'Finding People',
            'type'  => 'slides',
            'view'  => 'tutorials.lessons.level1.find_people',
        ],
        'level1.registering' => [
            'level' => 1,
            'order' => 3,
            'title' => 'Registering Records',
            'type'  => 'slides',
            'view'  => 'tutorials.lessons.level1.registering',
        ],
        'level1.exploring' => [
            'level' => 1,
            'order' => 4,
            'title' => 'Exploring the Database',
            'type'  => 'slides',
            'view'  => 'tutorials.lessons.level1.exploring',
        ],

        'level2.managing_person' => [
            'level' => 2,
            'order' => 1,
            'title' => "Managing a Person's Record",
            'type'  => 'slides',
            'view'  => 'tutorials.lessons.level2.managing_person',
        ],

        'level2.managing_groups' => [
            'level' => 2,
            'order' => 2,
            'title' => 'Managing Groups & Structure',
            'type'  => 'slides',
            'view'  => 'tutorials.lessons.level2.managing_groups',
        ],

        'level2.approvals_certificates' => [
            'level' => 2,
            'order' => 3,
            'title' => 'Approvals & Certificates',
            'type'  => 'slides',
            'view'  => 'tutorials.lessons.level2.approvals_certificates',
        ],

        'level2.database_cleanup' => [
            'level' => 2,
            'order' => 4,
            'title' => 'Keeping the Database Clean',
            'type'  => 'slides',
            'view'  => 'tutorials.lessons.level2.database_cleanup',
        ],

        'level2.campaigns' => [
            'level' => 2,
            'order' => 5,
            'title' => 'Campaigns Overview',
            'type'  => 'slides',
            'view'  => 'tutorials.lessons.level2.campaigns',
        ],

        'level2.authorizations' => [
            'level' => 2,
            'order' => 6,
            'title' => 'Authorizations',
            'type'  => 'slides',
            'view'  => 'tutorials.lessons.level2.authorizations',
        ],

        'level3.national_overview' => [
            'level' => 3,
            'order' => 1,
            'title' => 'National Administration Overview',
            'type'  => 'slides',
            'view'  => 'tutorials.lessons.level3.national_overview',
        ],
    ],

];
