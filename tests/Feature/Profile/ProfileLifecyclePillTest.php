<?php

/**
 * My Profile shows the person's lifecycle status in plain words below the
 * Volunteer / Member pill, with the colours of UserLifecycleStatusBadge, and
 * the long photo-age sentence under the photo.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('the profile shows a plain-words lifecycle pill', function (string $status, string $label, string $styles, ?string $hint) {
    $user = User::factory()->create(['lifecycle_status' => $status]);

    $html = $this->actingAs($user)->get(route('profile.show'))->assertOk()->getContent();

    preg_match('/data-lifecycle-pill>\s*<span class="([^"]*)">\s*<i[^>]*><\/i>(.*?)\s*<\/span>/s', $html, $pill);

    expect($pill[2] ?? null)->toBe($label)
        ->and($pill[1] ?? '')->toContain($styles);

    if ($hint) {
        expect($html)->toContain($hint);
    }
})->with([
    ['pending_engagement', 'Not yet active', 'bg-yellow-100 text-yellow-800', 'You become active when your first activity, training or membership payment is recorded.'],
    ['active', 'Active', 'bg-green-100 text-green-800', null],
    ['dormant', 'Inactive for a while', 'bg-gray-200 text-gray-800', 'Take part in an activity to become active again.'],
]);

test('the profile photo shows when it was uploaded, in words', function () {
    $user = User::factory()->create([
        'picture' => 'somefile.jpg',
        'image_upload_date' => now()->subYears(8),
    ]);

    $this->actingAs($user)->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Photo uploaded 8 years ago')
        ->assertSee('Please upload a new photo.')
        ->assertDontSee('8 yrs');
});
