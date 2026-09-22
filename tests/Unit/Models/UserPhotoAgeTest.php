<?php

/**
 * Unit tests for the photo-age accessors on User — no database, no HTTP:
 * these are pure attribute calculations off image_upload_date, exercised
 * on an unsaved, in-memory User instance via forceFill().
 *
 * Covers User::getImageAgeStatusAttribute() (the single source of truth for
 * <x-photo-age-indicator>'s 3-tier color coding) including the exact
 * boundary years (3 and 5), plus its two siblings: getImageAgeInYearsAttribute()
 * and getImageIsTooOldAttribute() (now implemented in terms of image_age_status).
 */

use App\Models\User;
use Illuminate\Support\Carbon;

uses(Tests\TestCase::class);

function userWithUploadDate(?string $date): User
{
    $user = new User();
    $user->forceFill(['image_upload_date' => $date]);

    return $user;
}

test('image_age_in_years is null when no upload date exists', function () {
    $user = userWithUploadDate(null);

    expect($user->image_age_in_years)->toBeNull();
});

test('image_age_in_years rounds to the nearest whole year', function () {
    $user = userWithUploadDate(Carbon::now()->subMonths(2)->toDateTimeString());
    expect($user->image_age_in_years)->toBe(0.0);

    $user = userWithUploadDate(Carbon::now()->subYears(2)->toDateTimeString());
    expect($user->image_age_in_years)->toBe(2.0);
});

test('image_age_status is null when the age is unknown', function () {
    $user = userWithUploadDate(null);

    expect($user->image_age_status)->toBeNull();
});

test('image_age_status is fresh under 3 years', function () {
    $user = userWithUploadDate(Carbon::now()->subMonths(6)->toDateTimeString());
    expect($user->image_age_status)->toBe('fresh');

    $user = userWithUploadDate(Carbon::now()->subYears(2)->toDateTimeString());
    expect($user->image_age_status)->toBe('fresh');
});

test('image_age_status is aging from 3 up to (not including) 5 years', function () {
    $user = userWithUploadDate(Carbon::now()->subYears(4)->toDateTimeString());
    expect($user->image_age_status)->toBe('aging');
});

test('image_age_status is stale at 5+ years', function () {
    $user = userWithUploadDate(Carbon::now()->subYears(6)->toDateTimeString());
    expect($user->image_age_status)->toBe('stale');
});

test('image_age_status boundary: exactly 3 years is aging, not fresh', function () {
    $user = userWithUploadDate(Carbon::now()->subYears(3)->toDateTimeString());

    expect($user->image_age_in_years)->toBe(3.0)
        ->and($user->image_age_status)->toBe('aging');
});

test('image_age_status boundary: exactly 5 years is stale, not aging', function () {
    $user = userWithUploadDate(Carbon::now()->subYears(5)->toDateTimeString());

    expect($user->image_age_in_years)->toBe(5.0)
        ->and($user->image_age_status)->toBe('stale');
});

test('image_is_too_old matches image_age_status === stale', function () {
    expect(userWithUploadDate(Carbon::now()->subYears(2)->toDateTimeString())->image_is_too_old)->toBeFalse()
        ->and(userWithUploadDate(Carbon::now()->subYears(4)->toDateTimeString())->image_is_too_old)->toBeFalse()
        ->and(userWithUploadDate(Carbon::now()->subYears(5)->toDateTimeString())->image_is_too_old)->toBeTrue()
        ->and(userWithUploadDate(Carbon::now()->subYears(6)->toDateTimeString())->image_is_too_old)->toBeTrue()
        ->and(userWithUploadDate(null)->image_is_too_old)->toBeFalse();
});
