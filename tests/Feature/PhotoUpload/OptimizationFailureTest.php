<?php

/**
 * Coverage for a previously-silent failure mode in
 * App\Traits\HandlesImageUploads: when the source file passes Laravel's
 * request-level "image"/"mimes" validation (which trusts the declared
 * MIME type / extension) but cannot actually be decoded as an image by
 * GD (createOptimizedImage() fails), the trait used to still report the
 * upload as a success — recording a filename in the DB that
 * PhotoController::show() could never serve (falling through to either
 * a stale remote-fetch 404 or, worse, a blank/black canvas written by a
 * silently-failed imagecopyresampled()).
 *
 * These tests confirm the fixed behavior: the upload is reported as a
 * failure, the user's `picture` column is left untouched, and no
 * orphaned file (original/ or web/) is left behind in storage.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // The admin layout loads assets via @vite; no build manifest exists in CI/test.
    $this->withoutVite();

    Storage::fake('local');

    foreach (['manage-admin-panel', 'view_user', 'edit_user'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions([
        'manage-admin-panel', 'view_user', 'edit_user',
    ]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('an upload that fails GD optimization is reported as a failure, not a success', function () {
    $admin = User::factory()->create();
    $admin->assignRole('national_db_administrator');

    $target = User::factory()->create(['picture' => null]);

    // Passes Laravel's `image`/`mimes:jpeg,png,jpg,gif` validation — UploadedFile::fake()->create()
    // sets the declared MIME type/extension directly rather than sniffing real content — but the
    // body is random padding bytes, not a real JPEG, so GD cannot decode it.
    $corruptFile = UploadedFile::fake()->create('corrupt.jpg', 5, 'image/jpeg');

    $response = $this->actingAs($admin)->post(
        route('users.update-profile-picture', $target),
        ['picture' => $corruptFile]
    );

    $response->assertRedirect();
    $response->assertSessionHas('error');
    $response->assertSessionMissing('success');
});

test('a failed optimization does not update the user picture column', function () {
    $admin = User::factory()->create();
    $admin->assignRole('national_db_administrator');

    $target = User::factory()->create(['picture' => null]);

    $corruptFile = UploadedFile::fake()->create('corrupt.jpg', 5, 'image/jpeg');

    $this->actingAs($admin)->post(
        route('users.update-profile-picture', $target),
        ['picture' => $corruptFile]
    );

    $target->refresh();

    expect($target->picture)->toBeNull();
});

test('a failed optimization leaves no orphaned file in storage', function () {
    $admin = User::factory()->create();
    $admin->assignRole('national_db_administrator');

    $target = User::factory()->create(['picture' => null]);

    $corruptFile = UploadedFile::fake()->create('corrupt.jpg', 5, 'image/jpeg');

    $this->actingAs($admin)->post(
        route('users.update-profile-picture', $target),
        ['picture' => $corruptFile]
    );

    expect(Storage::disk('local')->files('photos/profile/original'))->toBeEmpty();
    expect(Storage::disk('local')->files('photos/profile/web'))->toBeEmpty();
});

test('a genuinely valid image upload still succeeds after the fix', function () {
    $admin = User::factory()->create();
    $admin->assignRole('national_db_administrator');

    $target = User::factory()->create(['picture' => null]);

    // A real, GD-decodable JPEG (UploadedFile::fake()->image() renders actual image bytes via GD).
    $validFile = UploadedFile::fake()->image('avatar.jpg', 300, 300);

    $response = $this->actingAs($admin)->post(
        route('users.update-profile-picture', $target),
        ['picture' => $validFile]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $response->assertSessionMissing('error');

    $target->refresh();

    expect($target->picture)->not->toBeNull();
    expect(Storage::disk('local')->files('photos/profile/original'))->not->toBeEmpty();
    expect(Storage::disk('local')->files('photos/profile/web'))->not->toBeEmpty();
});
