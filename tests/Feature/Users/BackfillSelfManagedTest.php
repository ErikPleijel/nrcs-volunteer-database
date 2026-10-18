<?php

/**
 * users:backfill-self-managed fills self_managed_at from the last login, or
 * from consent the person gave themselves, and only where it is empty.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('it fills from the last login and own consent, and leaves the rest', function () {
    $login = now()->subYear()->startOfSecond();
    $consent = now()->subMonth()->startOfSecond();
    $kept = now()->subDays(3)->startOfSecond();

    $loggedIn = User::factory()->create(['last_login_at' => $login]);
    $selfRegistered = User::factory()->create();
    $selfRegistered->forceFill(['consent_obtained_by_id' => $selfRegistered->id, 'consent_obtained_at' => $consent])->save();
    $staffRegistered = User::factory()->create(['consent_obtained_by_id' => $loggedIn->id]);
    $neverLoggedIn = User::factory()->create();
    $alreadySet = User::factory()->create(['last_login_at' => $login, 'self_managed_at' => $kept]);

    $this->artisan('users:backfill-self-managed')
        ->expectsOutput('self_managed_at set: 1 from last login, 1 from own consent.')
        ->assertSuccessful();

    expect($loggedIn->fresh()->self_managed_at->equalTo($login))->toBeTrue()
        ->and($selfRegistered->fresh()->self_managed_at->equalTo($consent))->toBeTrue()
        ->and($staffRegistered->fresh()->self_managed_at)->toBeNull()
        ->and($neverLoggedIn->fresh()->self_managed_at)->toBeNull()
        ->and($alreadySet->fresh()->self_managed_at->equalTo($kept))->toBeTrue();

    $this->artisan('users:backfill-self-managed')
        ->expectsOutput('self_managed_at set: 0 from last login, 0 from own consent.');
});
