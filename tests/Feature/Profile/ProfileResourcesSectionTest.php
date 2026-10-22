<?php

/**
 * My Profile ends with full-width "Useful links" and "Downloads" sections
 * read from the profile_resources settings; a section with no items is
 * hidden.
 */

use App\Models\Setting;
use App\Models\User;
use App\Services\ProfileResources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->withoutVite();
});

function setProfileResource(string $key, array $rows): void
{
    $setting = Setting::where('key', $key)->first();
    $setting->value = $rows;
    $setting->save();
    Cache::forget("setting.{$key}");
}

function profileHtml(): string
{
    return test()->actingAs(User::factory()->create())
        ->get(route('profile.show'))
        ->assertOk()
        ->getContent();
}

test('the profile lists the useful links in a new tab, with their domain', function () {
    $html = profileHtml();

    expect($html)->toContain('data-useful-links')
        ->and($html)->toContain('href="https://www.ifrc.org/" target="_blank" rel="noopener noreferrer"')
        ->and($html)->toContain('fa-arrow-up-right-from-square')
        ->and($html)->toContain('linkedin.com')
        ->and($html)->not->toContain('data-downloads')
        // Below the two-column layout, in the seeded order.
        ->and(strpos($html, 'Communities of Practice'))->toBeGreaterThan(strpos($html, 'Your privacy'))
        ->and(strpos($html, 'Red Hub Solutions'))->toBeLessThan(strpos($html, 'REDpreneur'));
});

test('titles and descriptions are escaped', function () {
    setProfileResource(ProfileResources::LINKS_KEY, [
        ['title' => '<script>alert(1)</script>', 'url' => 'https://ifrc.org/', 'description' => '<b>bold</b>'],
    ]);

    $html = profileHtml();

    expect($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('&lt;b&gt;bold&lt;/b&gt;');
});

test('a stored link that is not http or https is never shown', function () {
    setProfileResource(ProfileResources::LINKS_KEY, [
        ['title' => 'Bad', 'url' => 'javascript:alert(1)', 'description' => ''],
        ['title' => 'Good', 'url' => 'https://ifrc.org/', 'description' => ''],
    ]);

    $html = profileHtml();

    expect($html)->not->toContain('javascript:alert(1)')
        ->and($html)->toContain('Good');
});

test('both sections are hidden when their lists are empty', function () {
    setProfileResource(ProfileResources::LINKS_KEY, []);

    $html = profileHtml();

    expect($html)->not->toContain('data-useful-links')
        ->and($html)->not->toContain('data-downloads');
});

test('downloads show the file type and link to the download route or the external address', function () {
    setProfileResource(ProfileResources::DOWNLOADS_KEY, [
        ['id' => 'abc123', 'title' => 'Volunteer Diary', 'url' => null, 'path' => 'profile-downloads/x.pdf', 'file_name' => 'diary.pdf', 'description' => ''],
        ['id' => 'def456', 'title' => 'Annual Report', 'url' => 'https://www.ifrc.org/report.pdf', 'path' => null, 'file_name' => null, 'description' => ''],
    ]);

    $html = profileHtml();

    expect($html)->toContain('data-downloads')
        ->and($html)->toContain('href="'.route('downloads.show', 'abc123').'" target="_blank" rel="noopener noreferrer"')
        ->and($html)->toContain('href="https://www.ifrc.org/report.pdf" target="_blank" rel="noopener noreferrer"')
        ->and($html)->toContain('fa-file-pdf')
        ->and($html)->toContain('PDF · ifrc.org');
});
