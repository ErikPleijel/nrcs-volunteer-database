<?php

/**
 * Regression coverage for the phone-login matching fix in
 * LoginController::login() / normalizePhone(). Previously, matching used
 * an unbounded ltrim($digits, '0') + LIKE '%suffix' comparison, which let
 * a genuinely different, unrelated number (e.g. '0123456789') falsely
 * collide with a longer number that merely ends in the same digits
 * (e.g. '080123456789') — reproduced against real data as users
 * id=82228 and id=16879. The fix normalises both sides through the same
 * bounded prefix-stripping (at most one of '234' or a single leading '0')
 * and requires exact equality.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a phone number is not falsely matched against an unrelated number sharing a digit suffix', function () {
    $user = User::factory()->create([
        'email' => null,
        'telephone1' => '0123456789',
        'lifecycle_status' => 'active',
    ]);

    // Different, unrelated number that merely ends in the same 9 digits.
    User::factory()->create([
        'email' => null,
        'telephone1' => '080123456789',
        'lifecycle_status' => 'active',
    ]);

    $response = $this->post('/login', [
        'login' => '0123456789',
        'password' => 'password',
    ]);

    $response->assertRedirect('/profile');
    $this->assertAuthenticatedAs($user);
});

test('a country-code variant of a phone number still resolves to the correct single account', function () {
    User::factory()->create([
        'email' => null,
        'telephone1' => '0123456789',
        'lifecycle_status' => 'active',
    ]);

    $other = User::factory()->create([
        'email' => null,
        'telephone1' => '080123456789',
        'lifecycle_status' => 'active',
    ]);

    $response = $this->post('/login', [
        // Same subscriber number as '080123456789', written with the '234'
        // country code instead of the local leading '0'.
        'login' => '23480123456789',
        'password' => 'password',
    ]);

    $response->assertRedirect('/profile');
    $this->assertAuthenticatedAs($other);
});

test('two accounts with the exact same normalised phone number start the disambiguation flow', function () {
    User::factory()->create([
        'email' => null,
        'telephone1' => '08099999999',
        'lifecycle_status' => 'active',
    ]);

    User::factory()->create([
        'email' => null,
        'telephone1' => '2348099999999',
        'lifecycle_status' => 'active',
    ]);

    $response = $this->from('/login')->post('/login', [
        'login' => '08099999999',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('login.phone'));
    $this->assertGuest();
});

test('emailed accounts sharing the number do not block the one email-less account', function () {
    $phoneOnly = User::factory()->create([
        'email' => null,
        'telephone1' => '08077777777',
        'lifecycle_status' => 'active',
    ]);

    User::factory()->count(2)->create([
        'telephone1' => '+234 807 777 7777',
        'lifecycle_status' => 'active',
    ]);

    $response = $this->post('/login', [
        'login' => '08077777777',
        'password' => 'password',
    ]);

    $response->assertRedirect('/profile');
    $this->assertAuthenticatedAs($phoneOnly);
});

test('two email-less accounts still collide even alongside an emailed one', function () {
    User::factory()->count(2)->create([
        'email' => null,
        'telephone1' => '08066666666',
        'lifecycle_status' => 'active',
    ]);

    User::factory()->create([
        'telephone1' => '08066666666',
        'lifecycle_status' => 'active',
    ]);

    $response = $this->from('/login')->post('/login', [
        'login' => '08066666666',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('login.phone'));
    $this->assertGuest();
});

test('a number held only by emailed accounts points to email login, not the multiple-accounts error', function () {
    User::factory()->count(2)->create([
        'telephone1' => '08055555555',
        'lifecycle_status' => 'active',
    ]);

    $response = $this->from('/login')->post('/login', [
        'login' => '08055555555',
        'password' => 'password',
    ]);

    $response->assertRedirect('/login');
    $this->assertGuest();

    expect(session('errors')->get('login')[0])
        ->toContain('This account has an email address');
});

test('an archived email-less duplicate does not block the live account', function () {
    User::factory()->create([
        'email' => null,
        'telephone1' => '08044444444',
        'lifecycle_status' => 'archived',
    ]);

    $live = User::factory()->create([
        'email' => null,
        'telephone1' => '08044444444',
        'lifecycle_status' => 'dormant',
    ]);

    $response = $this->post('/login', [
        'login' => '08044444444',
        'password' => 'password',
    ]);

    $response->assertRedirect('/profile');
    $this->assertAuthenticatedAs($live);
});

test('a lone archived email-less account still reaches the archived-account page', function () {
    User::factory()->create([
        'email' => null,
        'telephone1' => '08033333333',
        'lifecycle_status' => 'archived',
    ]);

    $response = $this->post('/login', [
        'login' => '08033333333',
        'password' => 'password',
    ]);

    $response->assertRedirectContains(route('archived-account.show', [], false));
    $this->assertGuest();
});
