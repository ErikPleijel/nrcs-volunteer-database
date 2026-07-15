<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsTableSeeder extends Seeder
{
    public function run(): void
    {
        Setting::updateOrCreate(
            ['key' => 'membership.dormant_after_months'],
            [
                'value'       => '12',
                'type'        => 'int',
                'group'       => 'membership',
                'label'       => 'Months of inactivity before user becomes dormant',
                'description' => 'Controls how long a user must be inactive before being marked dormant.',
                'autoload'    => true,
            ]
        );

        Setting::updateOrCreate(
            ['key' => 'site.motto'],
            [
                'value'       => 'A Sure Sign of Hope',
                'type'        => 'string',
                'group'       => 'site',
                'label'       => 'Site Motto',
                'description' => 'Displayed in the footer and public pages.',
                'autoload'    => true,
            ]
        );

        Setting::updateOrCreate(
            ['key' => 'social.share_description'],
            [
                'value'       => 'Join the Red Cross today!',
                'type'        => 'string',
                'group'       => 'social',
                'label'       => 'Social Share Description',
                'description' => 'Short text used as the page description when this site is shared on social media.',
                'autoload'    => true,
            ]
        );

        Setting::updateOrCreate(
            ['key' => 'campaign.allowed_domains'],
            [
                'value'       => 'example.org, example.com, example.net',
                'type'        => 'string',
                'group'       => 'campaign',
                'label'       => 'Allowed link domains (comma-separated)',
                'description' => 'Domains allowed in campaign content links. Comma-separated, case-insensitive.',
                'autoload'    => true,
            ]
        );

        Setting::updateOrCreate(
            ['key' => 'campaigns_daily_email_cap'],
            [
                'value'       => '500',
                'type'        => 'int',
                'group'       => 'campaign',
                'label'       => 'Daily email sending cap',
                'description' => 'Maximum number of campaign emails that can be sent per day before pausing sends.',
                'autoload'    => true,
            ]
        );

        Setting::updateOrCreate(
            ['key' => 'campaigns_daily_sms_cap'],
            [
                'value'       => '200',
                'type'        => 'int',
                'group'       => 'campaign',
                'label'       => 'Daily SMS sending cap',
                'description' => 'Maximum number of campaign SMS that can be sent per day before pausing sends.',
                'autoload'    => true,
            ]
        );
    }
}
