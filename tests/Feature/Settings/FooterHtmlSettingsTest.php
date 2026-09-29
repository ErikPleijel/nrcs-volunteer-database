<?php

/**
 * Editable footer sections: the 'html' settings site.footer_contact_html and
 * site.footer_quick_links_html — sanitized on save (SettingController) and on
 * render (components.footer), seeded idempotently by a data migration and
 * SettingsTableSeeder, and edited through a Code / Preview field.
 */

use App\Models\Setting;
use App\Models\User;
use App\Support\FooterDefaults;
use Database\Seeders\SettingsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const FOOTER_MIGRATION = 'database/migrations/2026_09_29_140000_add_footer_html_settings.php';

const HOSTILE_HTML = '<p class="x" style="color:red" onclick="steal()"><strong>Call us</strong></p>'
    .'<script>alert(1)</script>'
    .'<a href="javascript:alert(2)">bad link</a>'
    .'<a href="https://www.redcrossnigeria.org" target="_blank" rel="noopener" class="btn">good link</a>'
    .'<img src="x" onerror="steal()">'
    .'<div>wrapped text</div>';

beforeEach(function () {
    Cache::flush();
});

function settingsAdmin(): User
{
    Permission::findOrCreate('change_settings', 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo('change_settings');

    return $admin;
}

function renderFooter(): string
{
    return view('components.footer')->render();
}

/** Just the editable Contact Information block (the footer has its own <img> etc.). */
function footerContactBlock(): string
{
    $html = renderFooter();
    $start = strpos($html, '<div class="footer-html footer-contact');

    return substr($html, $start, strpos($html, 'Quick Links') - $start);
}

function expectHostileStripped(string $html): void
{
    expect($html)
        ->toContain('<p><strong>Call us</strong></p>')
        ->toContain('<a href="https://www.redcrossnigeria.org" target="_blank" rel="noopener">good link</a>')
        ->toContain('<a>bad link</a>')
        ->toContain('wrapped text')
        ->not->toContain('<script')
        ->not->toContain('alert(')
        ->not->toContain('onclick')
        ->not->toContain('onerror')
        ->not->toContain('javascript:')
        ->not->toContain('style="color:red"')
        ->not->toContain('class="btn"')
        ->not->toContain('<img')
        ->not->toContain('<div>wrapped');
}

/*
|--------------------------------------------------------------------------
| Model and seeding
|--------------------------------------------------------------------------
*/

test('the migration installs both footer settings as html in the site group, with the former footer content', function () {
    $contact = Setting::where('key', 'site.footer_contact_html')->first();
    $links = Setting::where('key', 'site.footer_quick_links_html')->first();

    expect($contact)->type->toBe('html')->group->toBe('site')->label->toBe('Footer: Contact Information')
        ->and($contact->value)->toBe(FooterDefaults::CONTACT_HTML)
        ->and($links)->type->toBe('html')->group->toBe('site')->label->toBe('Footer: Quick Links')
        ->and($links->value)->toBe(FooterDefaults::QUICK_LINKS_HTML);
});

test('an html setting reads and writes as a plain string', function () {
    Setting::create(['key' => 'site.test_html', 'type' => 'html', 'group' => 'site', 'value' => '<p><strong>Hi</strong> &amp; bye</p>']);

    expect(Setting::get('site.test_html'))->toBe('<p><strong>Hi</strong> &amp; bye</p>')
        ->and(Setting::where('key', 'site.test_html')->first()->castValue())->toBeString();
});

test('re-running the migration or the seeder never resets an admin edit', function () {
    Setting::where('key', 'site.footer_contact_html')->first()->update(['value' => '<p>EDITED CONTACT</p>']);
    Setting::where('key', 'site.footer_quick_links_html')->first()->update(['value' => '<ul><li>EDITED LINKS</li></ul>']);

    (require base_path(FOOTER_MIGRATION))->up();
    (require base_path(FOOTER_MIGRATION))->up();
    $this->seed(SettingsTableSeeder::class);

    expect(Setting::where('key', 'site.footer_contact_html')->count())->toBe(1)
        ->and(Setting::where('key', 'site.footer_quick_links_html')->count())->toBe(1)
        ->and(Setting::where('key', 'site.footer_contact_html')->value('value'))->toBe('<p>EDITED CONTACT</p>')
        ->and(Setting::where('key', 'site.footer_quick_links_html')->value('value'))->toBe('<ul><li>EDITED LINKS</li></ul>');
});

test('the migration recreates missing rows and clears a cached fallback', function () {
    Setting::whereIn('key', ['site.footer_contact_html', 'site.footer_quick_links_html'])->delete();
    Setting::get('site.footer_contact_html', 'STALE CACHED DEFAULT'); // cached "forever"

    (require base_path(FOOTER_MIGRATION))->up();

    expect(Setting::get('site.footer_contact_html'))->toBe(FooterDefaults::CONTACT_HTML)
        ->and(Setting::where('key', 'site.footer_quick_links_html')->value('value'))->toBe(FooterDefaults::QUICK_LINKS_HTML);
});

/*
|--------------------------------------------------------------------------
| Saving through admin/settings
|--------------------------------------------------------------------------
*/

test('saving an html setting stores only the allowlisted markup', function () {
    $this->actingAs(settingsAdmin())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.settings.update'), ['settings' => [
            'site.footer_contact_html' => HOSTILE_HTML,
            'site.footer_quick_links_html' => '<ul><li><a href="mailto:info@redcrossnigeria.org">Email us</a></li></ul><p>(+234) 803 = <em>free</em></p>',
        ]])
        ->assertRedirect(route('admin.settings.index'));

    expectHostileStripped(Setting::where('key', 'site.footer_contact_html')->value('value'));

    // Allowed tags, mailto, and + @ = kept readable (not &#43; / &#64; / &#61;).
    expect(Setting::where('key', 'site.footer_quick_links_html')->value('value'))
        ->toBe('<ul><li><a href="mailto:info@redcrossnigeria.org">Email us</a></li></ul><p>(+234) 803 = <em>free</em></p>');

    // Audited with the full values in old/new_values, and a short description
    // (logs.description is varchar(255) — the HTML used to overflow it).
    $log = \App\Models\Log::where('action', 'setting_changed')->latest('id')->first();
    expect($log->description)->toBe('Setting "site.footer_quick_links_html" updated.')
        ->and($log->new_values)->toBe(['site.footer_quick_links_html' => Setting::where('key', 'site.footer_quick_links_html')->value('value')]);
});

test('a long plain-text setting change is still audited within the description column', function () {
    Setting::create(['key' => 'site.hq_address_test', 'type' => 'string', 'group' => 'site', 'value' => str_repeat('a', 200)]);

    $this->actingAs(settingsAdmin())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.settings.update'), ['settings' => ['site.hq_address_test' => str_repeat('b', 200)]])
        ->assertRedirect(route('admin.settings.index'));

    expect(mb_strlen(\App\Models\Log::where('action', 'setting_changed')->latest('id')->value('description')))->toBeLessThanOrEqual(255)
        ->and(Setting::where('key', 'site.hq_address_test')->value('value'))->toBe(str_repeat('b', 200));
});

test('non-html settings are still saved exactly as entered', function () {
    Setting::create(['key' => 'site.motto', 'type' => 'string', 'group' => 'site', 'value' => 'A Sure Sign of Hope']);

    $this->actingAs(settingsAdmin())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.settings.update'), ['settings' => ['site.motto' => 'Serve <b>all</b>']]);

    expect(Setting::where('key', 'site.motto')->value('value'))->toBe('Serve <b>all</b>');
});

test('the settings page offers Code and Preview for html settings', function () {
    $this->actingAs(settingsAdmin())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertSee('Footer: Contact Information')
        ->assertSee('Footer: Quick Links')
        ->assertSee('data-html-setting', false)
        ->assertSee('data-html-tab="preview"', false)
        ->assertSee('name="settings[site.footer_contact_html]"', false)
        // The raw HTML sits escaped inside the textarea, not rendered.
        ->assertSee(e('<p><strong>Email:</strong> info@redcrossnigeria.org</p>'), false);
});

/*
|--------------------------------------------------------------------------
| Footer rendering
|--------------------------------------------------------------------------
*/

test('the footer renders both settings under their fixed headings', function () {
    Setting::where('key', 'site.footer_contact_html')->first()->update(['value' => '<p><strong>Email:</strong> help@example.org</p>']);
    Setting::where('key', 'site.footer_quick_links_html')->first()->update(['value' => '<ul><li><a href="https://example.org">Example</a></li></ul>']);

    expect(renderFooter())
        ->toContain('Contact Information')
        ->toContain('Quick Links')
        ->toContain('<div class="footer-html footer-contact text-base text-gray-700">')
        ->toContain('<p><strong>Email:</strong> help@example.org</p>')
        ->toContain('<ul><li><a href="https://example.org">Example</a></li></ul>')
        ->not->toContain('info@redcrossnigeria.org');
});

test('the footer sanitizes again on render, even if hostile HTML reached the database directly', function () {
    Setting::where('key', 'site.footer_contact_html')->first()->update(['value' => HOSTILE_HTML]);

    expectHostileStripped(footerContactBlock());
});

test('the footer falls back to the default content when the rows are missing', function () {
    Setting::whereIn('key', ['site.footer_contact_html', 'site.footer_quick_links_html'])->delete();

    expect(renderFooter())
        ->toContain('<p><strong>Email:</strong> info@redcrossnigeria.org</p>')
        ->toContain('(+234) 803 123 0430 (MTN)')
        ->toContain('(+234) 809 993 7357 (9Mobile)')
        ->toContain('href="https://www.redcrossnigeria.org/contact-us"')
        ->toContain('Nigerian Red Cross website')
        ->toContain('href="https://www.redcrossnigeria.org/about-us"');
});
