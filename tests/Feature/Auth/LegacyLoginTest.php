<?php

/**
 * The legacy md5 → bcrypt login path must go through the same post-login
 * checks as a normal Auth::attempt() — in particular the archived check, so
 * an archived account (e.g. a losing phone duplicate) can never be used to
 * log in, whichever hash it still has.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Migrated accounts: empty `password`, md5 in legacy_password_hash. Written
// straight to the table because the 'hashed' cast would hash the ''.
function legacyUser(array $attributes, string $plainPassword = 'old-secret'): User
{
    $user = User::factory()->create($attributes);

    DB::table('users')->where('id', $user->id)->update([
        'password' => '',
        'legacy_password_hash' => md5($plainPassword),
    ]);

    return $user->fresh();
}

test('an archived user with a correct legacy password is sent to the deactivated page, not logged in (email)', function () {
    $user = legacyUser(['email' => 'archived@example.com', 'lifecycle_status' => 'archived']);

    $this->post('/login', ['login' => 'archived@example.com', 'password' => 'old-secret'])
        ->assertRedirectContains(route('archived-account.show', [], false));

    $this->assertGuest();
    expect(session('archived_db_ref'))->toBe($user->user_id_reference);
});

test('an archived user with a correct legacy password is sent to the deactivated page, not logged in (phone)', function () {
    legacyUser(['email' => null, 'telephone1' => '08033333333', 'lifecycle_status' => 'archived']);

    $this->post('/login', ['login' => '08033333333', 'password' => 'old-secret'])
        ->assertRedirectContains(route('archived-account.show', [], false));

    $this->assertGuest();
});

test('an archived user reached through the phone-collision flow is sent to the deactivated page', function () {
    // Both email-less duplicates archived: the flow still runs, and must still end at the deactivated page.
    $archived = legacyUser(['email' => null, 'telephone1' => '08022222222', 'lifecycle_status' => 'archived']);
    legacyUser(['email' => null, 'telephone1' => '08022222222', 'lifecycle_status' => 'archived']);

    $this->post('/login', ['login' => '08022222222', 'password' => 'old-secret'])
        ->assertRedirect(route('login.phone'));
    $this->post(route('login.phone'), ['db_number' => 'DB-'.$archived->id]);

    $this->post(route('login.phone'), ['password' => 'old-secret'])
        ->assertRedirectContains(route('archived-account.show', [], false));

    $this->assertGuest();
});

test('a live user with a correct legacy password still logs in and is upgraded to bcrypt', function () {
    $user = legacyUser(['email' => 'live@example.com', 'lifecycle_status' => 'active']);

    $this->post('/login', ['login' => 'live@example.com', 'password' => 'old-secret'])
        ->assertRedirect('/profile');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->legacy_password_hash)->toBeNull();
});
