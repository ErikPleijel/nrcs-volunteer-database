<?php

/**
 * Per-identifier failed-login lockout (App\Support\LoginThrottle), layered
 * on top of the per-IP throttle:5,1 on POST /login. Most tests vary
 * REMOTE_ADDR per request so the per-IP throttle stays out of the way and
 * only the per-identifier limit is exercised.
 */

use App\Models\User;
use App\Support\LoginThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function attemptLoginFrom(string $ip, string $login, string $password)
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->from('/login')
        ->post('/login', ['login' => $login, 'password' => $password]);
}

function loginError(): ?string
{
    return session('errors')?->get('login')[0] ?? null;
}

beforeEach(function () {
    config([
        'auth.login_throttle.max_attempts' => 5,
        'auth.login_throttle.decay_minutes' => 15,
    ]);
});

test('exceeding the per-identifier limit locks the identifier out regardless of IP', function () {
    $user = User::factory()->create(['email' => 'amina@example.com']);

    foreach (range(1, 4) as $i) {
        attemptLoginFrom("10.0.0.$i", 'amina@example.com', 'wrong-password');
        expect(loginError())->toBe(trans('auth.failed'));
    }

    // The 5th failure is the one that locks it — say so straight away.
    attemptLoginFrom('10.0.0.5', 'amina@example.com', 'wrong-password');
    expect(loginError())->toContain('Too many failed sign-in attempts')
        ->toContain('15 more minutes');

    // Even the correct password, from yet another IP, is refused while locked.
    attemptLoginFrom('10.0.0.6', 'amina@example.com', 'password')->assertRedirect('/login');
    $this->assertGuest();
    expect(loginError())->toContain('Too many failed sign-in attempts');
});

test('the lockout expires after the configured window', function () {
    User::factory()->create(['email' => 'amina@example.com']);

    foreach (range(1, 5) as $i) {
        attemptLoginFrom("10.0.0.$i", 'amina@example.com', 'wrong-password');
    }

    $this->travel(16)->minutes();

    attemptLoginFrom('10.0.0.9', 'amina@example.com', 'password')->assertRedirect('/profile');
    $this->assertAuthenticated();
});

test('email identifiers are matched case-insensitively for the lockout', function () {
    User::factory()->create(['email' => 'amina@example.com']);

    foreach (range(1, 5) as $i) {
        attemptLoginFrom("10.0.0.$i", $i % 2 ? 'AMINA@example.com' : ' amina@EXAMPLE.com ', 'wrong-password');
    }

    attemptLoginFrom('10.0.0.9', 'amina@example.com', 'password');
    $this->assertGuest();
    expect(loginError())->toContain('Too many failed sign-in attempts');
});

test("a different identifier from the same IP is not blocked by another identifier's lockout", function () {
    User::factory()->create(['email' => 'amina@example.com']);
    $other = User::factory()->create(['email' => 'bello@example.com']);

    // All from one IP: 5 posts fits exactly within throttle:5,1.
    foreach (range(1, 5) as $i) {
        attemptLoginFrom('10.0.0.1', 'amina@example.com', 'wrong-password');
    }
    expect(loginError())->toContain('Too many failed sign-in attempts');

    // Past the per-IP minute, but well inside the 15-minute identifier lock.
    $this->travel(2)->minutes();

    attemptLoginFrom('10.0.0.1', 'bello@example.com', 'password')->assertRedirect('/profile');
    $this->assertAuthenticatedAs($other);

    auth()->logout();
    attemptLoginFrom('10.0.0.1', 'amina@example.com', 'password');
    $this->assertGuest();
    expect(loginError())->toContain('Too many failed sign-in attempts');
});

test('a successful login resets the identifier failure counter', function () {
    $user = User::factory()->create(['email' => 'amina@example.com']);

    foreach (range(1, 4) as $i) {
        attemptLoginFrom("10.0.0.$i", 'amina@example.com', 'wrong-password');
    }

    attemptLoginFrom('10.0.1.1', 'amina@example.com', 'password')->assertRedirect('/profile');
    $this->assertAuthenticatedAs($user);
    expect(RateLimiter::attempts(LoginThrottle::emailKey('amina@example.com')))->toBe(0);

    auth()->logout();

    // Another 4 failures would have locked it had the earlier 4 still counted.
    foreach (range(1, 4) as $i) {
        attemptLoginFrom("10.0.2.$i", 'amina@example.com', 'wrong-password');
        expect(loginError())->toBe(trans('auth.failed'));
    }

    attemptLoginFrom('10.0.3.1', 'amina@example.com', 'password')->assertRedirect('/profile');
    $this->assertAuthenticatedAs($user);
});

test('validation errors do not count toward the identifier lockout', function () {
    $user = User::factory()->create(['email' => 'amina@example.com']);

    foreach (range(1, 6) as $i) {
        attemptLoginFrom("10.0.0.$i", 'amina@example.com', '');
    }

    expect(RateLimiter::attempts(LoginThrottle::emailKey('amina@example.com')))->toBe(0);

    attemptLoginFrom('10.0.1.1', 'amina@example.com', 'password')->assertRedirect('/profile');
    $this->assertAuthenticatedAs($user);
});

test('a single phone-login account is locked out by its normalised phone number', function () {
    User::factory()->create([
        'email' => null,
        'telephone1' => '08012345678',
    ]);

    // Different spellings of the same number share one counter.
    foreach (['08012345678', '+234 801 234 5678', '2348012345678', '0801-234-5678', '8012345678'] as $i => $spelling) {
        attemptLoginFrom("10.0.0.$i", $spelling, 'wrong-password');
    }

    attemptLoginFrom('10.0.1.1', '08012345678', 'password');
    $this->assertGuest();
    expect(loginError())->toContain('Too many failed sign-in attempts');
});

test('the per-IP throttle still applies alongside the identifier lockout', function () {
    foreach (range(1, 5) as $i) {
        attemptLoginFrom('10.9.9.9', "nobody$i@example.com", 'wrong-password')->assertStatus(302);
    }

    // Six distinct identifiers, none locked — only the IP limit can refuse this.
    attemptLoginFrom('10.9.9.9', 'nobody6@example.com', 'wrong-password')
        ->assertStatus(429)
        ->assertSee('Too Many Attempts');
});

test('registration dropdown lookups do not use up the login budget from the same IP', function () {
    // Twice the login route's 5/min: under the old shared throttle:N,1
    // counter this alone would have 429'd the very next login attempt.
    foreach (range(1, 10) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.8.8.8'])
            ->getJson(route('register.divisions.by-branch', ['branch_id' => 1]))
            ->assertStatus(200);
    }

    foreach (range(1, 5) as $i) {
        attemptLoginFrom('10.8.8.8', "nobody$i@example.com", 'wrong-password')->assertStatus(302);
    }

    // The login limit itself is still enforced.
    attemptLoginFrom('10.8.8.8', 'nobody6@example.com', 'wrong-password')->assertStatus(429);
});

test('the registration dropdown keeps its own 20-per-minute limit', function () {
    foreach (range(1, 20) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.7.7.7'])
            ->getJson(route('register.divisions.by-branch', ['branch_id' => 1]))
            ->assertStatus(200);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.7.7.7'])
        ->getJson(route('register.divisions.by-branch', ['branch_id' => 1]))
        ->assertStatus(429);
});

test('a normal email user logging in correctly is unaffected', function () {
    $user = User::factory()->create(['email' => 'amina@example.com']);

    attemptLoginFrom('10.0.0.1', 'amina@example.com', 'password')->assertRedirect('/profile');
    $this->assertAuthenticatedAs($user);
});

test('one or two mistaken attempts do not lock a normal user out', function () {
    $user = User::factory()->create(['email' => 'amina@example.com']);

    foreach (range(1, 2) as $i) {
        attemptLoginFrom('10.0.0.1', 'amina@example.com', 'typo-password');
        expect(loginError())->toBe(trans('auth.failed'));
    }

    attemptLoginFrom('10.0.0.1', 'amina@example.com', 'password')->assertRedirect('/profile');
    $this->assertAuthenticatedAs($user);
});
