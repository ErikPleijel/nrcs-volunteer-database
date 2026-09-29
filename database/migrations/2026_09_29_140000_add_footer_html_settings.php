<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * The footer's editable Contact Information and Quick Links content (type
 * 'html', group 'site'), seeded with what the footer hard-coded before.
 * firstOrCreate, so an existing row — an admin's edit — is never overwritten.
 * The values are a snapshot; App\Support\FooterDefaults holds the same text
 * for the seeder and the footer's fallback.
 */
return new class extends Migration
{
    private const ROWS = [
        'site.footer_contact_html' => [
            'label' => 'Footer: Contact Information',
            'description' => 'Shown under "Contact Information" in the footer of every page. Allowed: strong, b, em, br, p, ul, li and links (http, https, mailto).',
            'value' => <<<'HTML'
<p><strong>Email:</strong> info@redcrossnigeria.org</p>
<p><strong>Phone Number (TOLL FREE)</strong><br>
(+234) 803 123 0430 (MTN)<br>
(+234) 809 993 7357 (9Mobile)</p>
<p><a href="https://www.redcrossnigeria.org/contact-us" target="_blank" rel="noopener">Contact form</a></p>
HTML,
        ],
        'site.footer_quick_links_html' => [
            'label' => 'Footer: Quick Links',
            'description' => 'Shown under "Quick Links" in the footer of every page. Allowed: strong, b, em, br, p, ul, li and links (http, https, mailto).',
            'value' => <<<'HTML'
<ul>
<li><a href="https://www.redcrossnigeria.org" target="_blank" rel="noopener">Nigerian Red Cross website</a></li>
<li><a href="https://www.redcrossnigeria.org/about-us" target="_blank" rel="noopener">About us</a></li>
</ul>
HTML,
        ],
    ];

    public function up(): void
    {
        foreach (self::ROWS as $key => $row) {
            Setting::firstOrCreate(['key' => $key], [
                'type' => 'html',
                'group' => 'site',
                'label' => $row['label'],
                'description' => $row['description'],
                'value' => $row['value'],
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
