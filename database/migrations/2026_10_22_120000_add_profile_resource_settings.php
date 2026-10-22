<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * The "Useful links" and "Downloads" lists shown at the bottom of My
 * Profile, as two json settings in the 'profile_resources' group, edited on
 * their own Settings page (see App\Services\ProfileResources). Links start
 * with eight IFRC sites, downloads empty. firstOrCreate, so an existing row
 * (an admin's edit) is never overwritten. 'type' comes before 'value':
 * Setting encodes a json value only once it knows the type.
 */
return new class extends Migration
{
    private const LINKS = [
        ['title' => 'Communities of Practice', 'url' => 'https://communities.ifrc.org/message/6ac77c6dd05e274c86594707', 'description' => ''],
        ['title' => 'IFRC Learning Platform', 'url' => 'https://ifrc.csod.com/client/ifrc/default.aspx', 'description' => ''],
        ['title' => 'IFRC Jobs', 'url' => 'https://careers.ifrc.org/lumesse_jobsearch.html', 'description' => ''],
        ['title' => 'IFRC Official', 'url' => 'https://www.ifrc.org/', 'description' => ''],
        ['title' => 'Red Hub Solutions', 'url' => 'https://solutions.ifrc.org/', 'description' => ''],
        ['title' => 'IFRC LinkedIn', 'url' => 'https://www.linkedin.com/company/ifrc/', 'description' => ''],
        ['title' => 'IFRC GO Platform', 'url' => 'https://go.ifrc.org/', 'description' => ''],
        ['title' => 'REDpreneur', 'url' => 'https://www.redpreneur.org/', 'description' => ''],
    ];

    private const ROWS = [
        'profile.useful_links' => [
            'label' => 'Useful links',
            'description' => 'External links shown at the bottom of My Profile.',
        ],
        'profile.downloads' => [
            'label' => 'Downloads',
            'description' => 'Files and documents shown at the bottom of My Profile.',
        ],
    ];

    public function up(): void
    {
        foreach (self::ROWS as $key => $row) {
            Setting::firstOrCreate(['key' => $key], [
                'type' => 'json',
                'group' => 'profile_resources',
                'label' => $row['label'],
                'description' => $row['description'],
                'value' => $key === 'profile.useful_links' ? self::LINKS : [],
                'autoload' => true,
            ]);

            // Setting::get() caches a missing row's default forever.
            Cache::forget("setting.{$key}");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::ROWS) as $key) {
            Setting::where('key', $key)->delete();
            Cache::forget("setting.{$key}");
        }
    }
};
