<?php

/**
 * users:anonymize-expired — anonymizes accounts archived longer than
 * data_protection.anonymize_after_years (7) ago. Dry run by default;
 * role holders are skipped and reported. Scheduled daily at 03:30.
 */

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-10-08 03:30:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function archivedSince(string $archivedAt, array $overrides = []): User
{
    $user = User::factory()->create(['lifecycle_status' => 'active', ...$overrides]);
    $user->markArchived(null)->save();
    DB::table('users')->where('id', $user->id)->update(['archived_at' => $archivedAt]);

    return $user->fresh();
}

function isAnonymized(User $user): bool
{
    return DB::table('users')->where('id', $user->id)->value('anonymized_at') !== null;
}

test('a dry run reports the expired accounts and changes nothing', function () {
    $expired = archivedSince('2019-10-01 00:00:00', ['first_name' => 'Old']);

    $this->artisan('users:anonymize-expired')
        ->expectsOutputToContain('Would anonymize: 1')
        ->assertSuccessful();

    expect(isAnonymized($expired))->toBeFalse()
        ->and($expired->fresh()->first_name)->toBe('Old');
});

test('--apply anonymizes only accounts archived more than 7 years ago', function () {
    $expired = archivedSince('2019-10-01 00:00:00');
    $justExpired = archivedSince('2019-10-08 03:30:00');
    $recent = archivedSince('2019-10-09 00:00:00');
    $active = User::factory()->create(['lifecycle_status' => 'active']);
    DB::table('users')->where('id', $active->id)->update(['archived_at' => null]);

    $this->artisan('users:anonymize-expired', ['--apply' => true])
        ->expectsOutputToContain('Anonymized: 2')
        ->assertSuccessful();

    expect(isAnonymized($expired))->toBeTrue()
        ->and(isAnonymized($justExpired))->toBeTrue()
        ->and(isAnonymized($recent))->toBeFalse()
        ->and(isAnonymized($active))->toBeFalse()
        ->and(DB::table('logs')->where('action', 'user_anonymized')->where('subject_id', $expired->id)->value('description'))
        ->toBe("DB-{$expired->id} anonymized (scheduled)");
});

test('role holders are skipped and reported', function () {
    Role::findOrCreate('branch_db_assistant', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $staff = archivedSince('2015-01-01 00:00:00');
    $staff->assignRole('branch_db_assistant');
    $plain = archivedSince('2015-01-01 00:00:00');

    $this->artisan('users:anonymize-expired', ['--apply' => true])
        ->expectsOutputToContain('Anonymized: 1')
        ->expectsOutputToContain("Skipped (hold an administrative role): 1 — DB-{$staff->id}")
        ->assertSuccessful();

    expect(isAnonymized($plain))->toBeTrue()
        ->and(isAnonymized($staff))->toBeFalse();
});

test('the job is scheduled daily at 03:30', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command ?? '', 'users:anonymize-expired --apply'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('30 3 * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
