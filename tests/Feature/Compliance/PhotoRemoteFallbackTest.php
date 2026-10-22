<?php

/**
 * The remote photo fallback (old system at nrcsvdb.org) runs only on a local or
 * development machine. On a server a missing file shows the placeholder: after the
 * switch nrcsvdb.org points at this app, so the fetch would call itself.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create([
        'picture' => 'images/pictures/not-migrated-'.uniqid().'.jpg',
        'signature' => 'images/pictures/not-migrated-sig-'.uniqid().'.jpg',
    ]);
});

test('in production a missing photo shows the placeholder without calling the old server', function (string $type) {
    Http::fake();
    app()['env'] = 'production';

    $response = $this->actingAs($this->user)->get(route('photos.show', [$this->user, $type]));

    $response->assertOk();
    $placeholder = $type === 'signature' ? 'signature-placeholder.jpg' : 'profile-placeholder.png';
    expect($response->baseResponse->getFile()->getFilename())->toBe($placeholder);
    Http::assertNothingSent();
})->with(['profile', 'signature']);

test('on a local machine a missing photo is still fetched from the old server', function () {
    Http::fake(['nrcsvdb.org/*' => Http::response('legacy-image-bytes', 200, ['Content-Type' => 'image/jpeg'])]);
    app()['env'] = 'local';

    $response = $this->actingAs($this->user)->get(route('photos.show', [$this->user, 'profile']));

    $response->assertOk();
    expect($response->getContent())->toBe('legacy-image-bytes');
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://nrcsvdb.org/nrcs/database/images/pictures/'));
});

test('in the test environment the old server is never called', function () {
    Http::fake();

    $this->actingAs($this->user)->get(route('photos.show', [$this->user, 'profile']))->assertOk();

    Http::assertNothingSent();
});
