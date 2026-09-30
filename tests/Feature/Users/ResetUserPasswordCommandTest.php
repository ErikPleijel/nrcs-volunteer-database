<?php

/**
 * users:reset-password (alias resetpw) — sets the password through the
 * 'hashed' cast, clears the legacy md5 hash, rotates remember_token,
 * deletes the user's sessions, clears their login lockout and writes an
 * audit row without the password. Unknown IDs and weak passwords fail,
 * production needs --force, and email_verified_at is left alone.
 */

use App\Models\Log as AuditLog;
use App\Models\User;
use App\Support\LoginThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['session.driver' => 'database']);
});

function resetPwSession(User $user, string $id): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => '',
        'last_activity' => time(),
    ]);
}

it('sets a password that verifies with Hash::check', function () {
    $user = User::factory()->create();

    test()->artisan('users:reset-password', ['id' => $user->id, 'password' => 'NewSecret123', '--yes' => true])
        ->assertSuccessful();

    expect(Hash::check('NewSecret123', $user->fresh()->password))->toBeTrue();
});

it('works through the resetpw alias', function () {
    $user = User::factory()->create();

    test()->artisan('resetpw', ['id' => $user->id, 'password' => 'NewSecret123', '--yes' => true])
        ->assertSuccessful();

    expect(Hash::check('NewSecret123', $user->fresh()->password))->toBeTrue();
});

it('prompts twice for the password when it is omitted', function () {
    $user = User::factory()->create();

    test()->artisan('users:reset-password', ['id' => $user->id, '--yes' => true])
        ->expectsQuestion('New password', 'Prompted123')
        ->expectsQuestion('Confirm new password', 'Prompted123')
        ->doesntExpectOutputToContain('shell history')
        ->assertSuccessful();

    expect(Hash::check('Prompted123', $user->fresh()->password))->toBeTrue();
});

it('fails when the prompted passwords do not match', function () {
    $user = User::factory()->create();
    $before = $user->password;

    test()->artisan('users:reset-password', ['id' => $user->id, '--yes' => true])
        ->expectsQuestion('New password', 'Prompted123')
        ->expectsQuestion('Confirm new password', 'Different123')
        ->assertFailed();

    expect($user->fresh()->password)->toBe($before);
});

it('warns when the password is passed as an argument', function () {
    $user = User::factory()->create();

    test()->artisan('users:reset-password', ['id' => $user->id, 'password' => 'NewSecret123', '--yes' => true])
        ->expectsOutputToContain('shell history')
        ->assertSuccessful();
});

it('clears the legacy hash and rotates the remember token', function () {
    $user = User::factory()->create(['legacy_password_hash' => md5('old')]);
    $user->forceFill(['remember_token' => 'old-token'])->save();

    test()->artisan('users:reset-password', ['id' => $user->id, 'password' => 'NewSecret123', '--yes' => true])
        ->assertSuccessful();

    $user->refresh();
    expect($user->legacy_password_hash)->toBeNull()
        ->and($user->remember_token)->not->toBe('old-token');
});

it('deletes only that user\'s sessions', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    resetPwSession($user, 'mine-1');
    resetPwSession($user, 'mine-2');
    resetPwSession($other, 'theirs');

    test()->artisan('users:reset-password', ['id' => $user->id, 'password' => 'NewSecret123', '--yes' => true])
        ->assertSuccessful();

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', 'theirs')->exists())->toBeTrue();
});

it('clears the email and phone login lockouts', function () {
    $user = User::factory()->create(['email' => 'Locked@Example.com', 'telephone1' => '0803 123 4567']);
    $emailKey = LoginThrottle::emailKey('locked@example.com');
    $phoneKey = LoginThrottle::phoneKey('8031234567');
    foreach (range(1, 10) as $i) {
        LoginThrottle::hit($emailKey);
        LoginThrottle::hit($phoneKey);
    }

    test()->artisan('users:reset-password', ['id' => $user->id, 'password' => 'NewSecret123', '--yes' => true])
        ->assertSuccessful();

    expect(LoginThrottle::tooManyAttempts($emailKey))->toBeFalse()
        ->and(LoginThrottle::tooManyAttempts($phoneKey))->toBeFalse();
});

it('writes an audit row that never contains the password', function () {
    $user = User::factory()->create();

    test()->artisan('users:reset-password', ['id' => $user->id, 'password' => 'NewSecret123', '--yes' => true])
        ->assertSuccessful();

    $log = AuditLog::where('action', 'password_reset_cli')->where('subject_id', $user->id)->sole();

    expect($log->subject_type)->toBe(User::class)
        ->and($log->new_values)->toHaveKeys(['os_user', 'host'])
        ->and(json_encode($log->getAttributes()))->not->toContain('NewSecret123')
        ->and(json_encode($log->getAttributes()))->not->toContain($user->fresh()->password);
});

it('fails for an unknown user ID', function () {
    test()->artisan('users:reset-password', ['id' => 999999, 'password' => 'NewSecret123', '--yes' => true])
        ->expectsOutputToContain('not found')
        ->assertFailed();

    expect(AuditLog::where('action', 'password_reset_cli')->exists())->toBeFalse();
});

it('rejects a password that breaks the password policy', function () {
    $user = User::factory()->create();
    $before = $user->password;

    test()->artisan('users:reset-password', ['id' => $user->id, 'password' => 'short', '--yes' => true])
        ->assertFailed();

    expect($user->fresh()->password)->toBe($before)
        ->and(AuditLog::where('action', 'password_reset_cli')->exists())->toBeFalse();
});

it('aborts in production without --force', function () {
    app()->detectEnvironment(fn () => 'production');
    $user = User::factory()->create();
    $before = $user->password;

    test()->artisan('users:reset-password', ['id' => $user->id, 'password' => 'NewSecret123', '--yes' => true])
        ->expectsConfirmation('Are you sure you want to run this command?', 'no')
        ->assertFailed();

    expect($user->fresh()->password)->toBe($before);
});

it('runs in production with --force', function () {
    app()->detectEnvironment(fn () => 'production');
    $user = User::factory()->create();

    test()->artisan('users:reset-password', ['id' => $user->id, 'password' => 'NewSecret123', '--yes' => true, '--force' => true])
        ->assertSuccessful();

    expect(Hash::check('NewSecret123', $user->fresh()->password))->toBeTrue();
});

it('asks for confirmation without --yes and changes nothing when declined', function () {
    $user = User::factory()->create();
    $before = $user->password;

    test()->artisan('users:reset-password', ['id' => $user->id, 'password' => 'NewSecret123'])
        ->expectsConfirmation("Reset the password for user #{$user->id}?", 'no')
        ->assertFailed();

    expect($user->fresh()->password)->toBe($before);
});

it('warns about an archived user but still resets and keeps the status', function () {
    $user = User::factory()->create(['lifecycle_status' => 'archived']);

    test()->artisan('users:reset-password', ['id' => $user->id, 'password' => 'NewSecret123', '--yes' => true])
        ->expectsOutputToContain('archived')
        ->assertSuccessful();

    expect($user->fresh()->lifecycle_status)->toBe('archived');
});

it('leaves email_verified_at unchanged', function () {
    $user = User::factory()->create(['email_verified_at' => '2026-01-15 10:00:00']);

    test()->artisan('users:reset-password', ['id' => $user->id, 'password' => 'NewSecret123', '--yes' => true])
        ->assertSuccessful();

    expect($user->fresh()->email_verified_at->toDateTimeString())->toBe('2026-01-15 10:00:00');
});
