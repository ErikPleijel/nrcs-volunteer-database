<?php

/**
 * Super-admin is only granted by SuperAdminSeeder. UserObserver no longer
 * grants it on create (a self-registration with a listed email must not
 * become super-admin); it only revokes role AND is_super_admin together
 * when a super-admin's email leaves the list.
 */

use App\Models\Log as AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('super-admin', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    config(['superadmin.emails' => ['sg@example.org']]);
});

test('creating an account with a listed email does not grant super-admin', function () {
    $user = User::factory()->create(['email' => 'sg@example.org']);

    expect($user->fresh()->hasRole('super-admin'))->toBeFalse()
        ->and($user->fresh()->is_super_admin)->toBeFalsy();
});

test('changing a super-admin email off the list revokes the role and the flag, audited', function () {
    $user = User::factory()->create(['email' => 'sg@example.org', 'is_super_admin' => true]);
    $user->assignRole('super-admin');

    $user->update(['email' => 'someone.else@example.org']);

    expect($user->fresh()->hasRole('super-admin'))->toBeFalse()
        ->and($user->fresh()->is_super_admin)->toBeFalsy()
        ->and(AuditLog::where('action', 'super_admin_auto_revoked')->where('subject_id', $user->id)->exists())->toBeTrue();
});

test('a listed email that only changes case keeps super-admin', function () {
    $user = User::factory()->create(['email' => 'sg@example.org', 'is_super_admin' => true]);
    $user->assignRole('super-admin');

    $user->update(['email' => 'SG@Example.org']);

    expect($user->fresh()->hasRole('super-admin'))->toBeTrue()
        ->and($user->fresh()->is_super_admin)->toBeTruthy();
});

test('the legacy campaigns.send route is gone', function () {
    expect(Route::has('campaigns.send'))->toBeFalse();
});
