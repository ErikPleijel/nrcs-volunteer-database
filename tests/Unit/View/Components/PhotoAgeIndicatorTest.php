<?php

/**
 * Unit tests for <x-photo-age-indicator> — rendered via Blade::render() on
 * an unsaved, in-memory User (no database needed; the component only reads
 * User::picture / image_age_in_years / image_age_status).
 */

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;

uses(Tests\TestCase::class);

function photoAgeUser(?string $uploadDate, bool $hasPicture = true): User
{
    $user = new User();
    $user->forceFill([
        'picture' => $hasPicture ? 'somefile.jpg' : null,
        'image_upload_date' => $uploadDate,
    ]);

    return $user;
}

function renderPhotoAgeIndicator(User $user, array $extraProps = []): string
{
    $attrs = collect($extraProps)->map(fn ($v, $k) => is_bool($v) ? ($v ? $k : '') : "{$k}=\"{$v}\"")->implode(' ');

    return trim(Blade::render(
        "<x-photo-age-indicator :user=\"\$user\" {$attrs} />",
        ['user' => $user]
    ));
}

test('renders nothing when the user has no photo at all', function () {
    $html = renderPhotoAgeIndicator(photoAgeUser(null, hasPicture: false));

    expect($html)->toBe('');
});

test('renders nothing when there is no photo even if an upload date somehow exists', function () {
    // Defensive: picture is the gate, not the date.
    $html = renderPhotoAgeIndicator(photoAgeUser(Carbon::now()->subYear()->toDateTimeString(), hasPicture: false));

    expect($html)->toBe('');
});

test('renders the gray "no date" fallback when a photo exists but has no upload date', function () {
    $html = renderPhotoAgeIndicator(photoAgeUser(null, hasPicture: true));

    expect($html)
        ->toContain('no date')
        ->toContain('text-gray-400')
        ->not->toContain('fa-circle-check')
        ->not->toContain('fa-circle-exclamation')
        ->not->toContain('fa-triangle-exclamation');
});

test('renders green fa-circle-check for a fresh photo', function () {
    $html = renderPhotoAgeIndicator(photoAgeUser(Carbon::now()->subYear()->toDateTimeString()));

    expect($html)
        ->toContain('fa-circle-check')
        ->toContain('text-green-600')
        ->toContain('1 yrs')
        ->toContain('text-[10px]'); // small size below the stale threshold
});

test('renders amber fa-circle-exclamation for an aging photo', function () {
    $html = renderPhotoAgeIndicator(photoAgeUser(Carbon::now()->subYears(4)->toDateTimeString()));

    expect($html)
        ->toContain('fa-circle-exclamation')
        ->toContain('text-yellow-600')
        ->toContain('4 yrs');
});

test('renders red fa-triangle-exclamation at a larger size for a stale photo', function () {
    $html = renderPhotoAgeIndicator(photoAgeUser(Carbon::now()->subYears(6)->toDateTimeString()));

    expect($html)
        ->toContain('fa-triangle-exclamation')
        ->toContain('text-red-600')
        ->toContain('text-sm') // size bump matching the original my-unit-report badge
        ->toContain('6 yrs');
});

test('renders "< 1 yr" for a photo uploaded within the last year', function () {
    $html = renderPhotoAgeIndicator(photoAgeUser(Carbon::now()->subMonths(2)->toDateTimeString()));

    expect($html)->toContain('&lt; 1 yr');
});

test('compact prop swaps the card-layout wrapper for an inline-friendly one', function () {
    $default = renderPhotoAgeIndicator(photoAgeUser(Carbon::now()->subYear()->toDateTimeString()));
    $compact = renderPhotoAgeIndicator(photoAgeUser(Carbon::now()->subYear()->toDateTimeString()), ['compact' => true]);

    expect($default)
        ->toContain('text-center')
        ->toContain('mt-0.5')
        ->and($compact)
        ->toContain('inline-flex')
        ->not->toContain('mt-0.5');
});

test('label prop prefixes a caption when provided, and is absent by default', function () {
    $withoutLabel = renderPhotoAgeIndicator(photoAgeUser(Carbon::now()->subYear()->toDateTimeString()));
    $withLabel = renderPhotoAgeIndicator(photoAgeUser(Carbon::now()->subYear()->toDateTimeString()), ['label' => 'Profile photo']);

    expect($withoutLabel)->not->toContain('Profile photo')
        ->and($withLabel)->toContain('Profile photo');
});

test('boundary years match User::image_age_status exactly (3 = aging, 5 = stale)', function () {
    $atThree = renderPhotoAgeIndicator(photoAgeUser(Carbon::now()->subYears(3)->toDateTimeString()));
    $atFive = renderPhotoAgeIndicator(photoAgeUser(Carbon::now()->subYears(5)->toDateTimeString()));

    expect($atThree)->toContain('fa-circle-exclamation')->toContain('text-yellow-600')
        ->and($atFive)->toContain('fa-triangle-exclamation')->toContain('text-red-600');
});

test('verbose prop writes a full sentence and asks for a new photo only when stale', function () {
    $recent = renderPhotoAgeIndicator(photoAgeUser(Carbon::now()->subMonths(3)->toDateTimeString()), ['verbose' => true]);
    $one = renderPhotoAgeIndicator(photoAgeUser(Carbon::now()->subYear()->toDateTimeString()), ['verbose' => true]);
    $aging = renderPhotoAgeIndicator(photoAgeUser(Carbon::now()->subYears(4)->toDateTimeString()), ['verbose' => true]);
    $stale = renderPhotoAgeIndicator(photoAgeUser(Carbon::now()->subYears(8)->toDateTimeString()), ['verbose' => true]);
    $noDate = renderPhotoAgeIndicator(photoAgeUser(null), ['verbose' => true]);

    expect($recent)->toContain('Photo uploaded less than a year ago')->toContain('text-green-600')
        ->and($one)->toContain('Photo uploaded 1 year ago')
        ->and($aging)->toContain('Photo uploaded 4 years ago')->toContain('text-yellow-600')
        ->not->toContain('Please upload a new photo')
        ->and($stale)->toContain('Photo uploaded 8 years ago')->toContain('text-red-600')
        ->toContain('Please upload a new photo.')
        ->not->toContain('yrs')
        ->and($noDate)->toContain('Upload date unknown');
});
