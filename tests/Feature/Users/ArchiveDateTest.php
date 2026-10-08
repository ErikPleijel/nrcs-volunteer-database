<?php

/**
 * archived_at / archived_by_id (2026-10-08): every archive path goes
 * through User::markArchived() and writes an audit entry; leaving
 * 'archived' clears both columns and writes user_unarchived (User's
 * updating hook). archived_at starts the 7-year anonymization clock.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\Log as AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Carbon::setTestNow('2026-10-08 09:30:00');

    foreach (['manage-admin-panel', 'edit_user', 'use_archive_tool'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')
        ->syncPermissions(['manage-admin-panel', 'edit_user', 'use_archive_tool']);
    Role::findOrCreate('branch_db_assistant', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function archiveTarget(array $overrides = []): User
{
    return User::factory()->create([
        'branch_id' => test()->branch->id,
        'division_id' => test()->division->id,
        ...$overrides,
    ]);
}

/** Minimum valid users.update payload; is_inactive is the "Archive this user" checkbox. */
function archiveUpdatePayload(bool $archived): array
{
    return [
        'first_name' => 'Test',
        'last_name' => 'User',
        'gender' => 'male',
        'birth_year' => 1990,
        'branch_id' => test()->branch->id,
        'division_id' => test()->division->id,
        'contribution_type' => 'member',
        'is_inactive' => $archived ? '1' : '0',
    ];
}

function expectArchivedBy(User $user, ?int $byId): void
{
    $user->refresh();
    expect($user->lifecycle_status)->toBe('archived')
        ->and($user->archived_at?->toDateTimeString())->toBe('2026-10-08 09:30:00')
        ->and($user->archived_by_id)->toBe($byId);
}

/*
|--------------------------------------------------------------------------
| The four archive paths
|--------------------------------------------------------------------------
*/

test('archiving on users/edit records the date, the admin and a user_archived entry', function () {
    $user = archiveTarget(['lifecycle_status' => 'active']);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), archiveUpdatePayload(true))
        ->assertRedirect(route('users.show', $user));

    expectArchivedBy($user, $this->admin->id);

    $log = AuditLog::where('action', 'user_archived')->where('subject_id', $user->id)->sole();
    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->old_values)->toBe(['lifecycle_status' => 'active'])
        ->and($log->description)->toBe("DB-{$user->id} archived on users/edit by DB-{$this->admin->id}.");
});

test('self-archive records the date, the user themselves and a user_self_archived entry', function () {
    $user = archiveTarget(['lifecycle_status' => 'active', 'first_name' => 'Selma']);

    $this->actingAs($user)
        ->post(route('profile.self-archive'), ['confirmation' => 'archive'])
        ->assertRedirect();

    expectArchivedBy($user, $user->id);

    $log = AuditLog::where('action', 'user_self_archived')->where('subject_id', $user->id)->sole();
    expect($log->description)->toBe("DB-{$user->id} archived their own account.")
        ->and($log->description)->not->toContain('Selma');
});

test('the Archive Tool records the date, the admin and one user_archived entry per user', function () {
    $a = archiveTarget(['lifecycle_status' => 'dormant']);
    $b = archiveTarget(['lifecycle_status' => 'dormant']);

    $this->actingAs($this->admin)
        ->post(route('dormant-users.archive'), ['user_ids' => [$a->id, $b->id]])
        ->assertRedirect(route('dormant-users.index'))
        ->assertSessionHas('success');

    expectArchivedBy($a, $this->admin->id);
    expectArchivedBy($b, $this->admin->id);

    foreach ([$a, $b] as $user) {
        $log = AuditLog::where('action', 'user_archived')->where('subject_id', $user->id)->sole();
        expect($log->old_values)->toBe(['lifecycle_status' => 'dormant'])
            ->and($log->description)->toBe("DB-{$user->id} archived with the archive tool by DB-{$this->admin->id}.");
    }
});

test('the phone-duplicate merge records the date, no archiver (system) and its audit entry', function () {
    // Same person twice (same name, gender, phone, no email); the newest account wins.
    $twin = ['first_name' => 'Amina', 'last_name' => 'Bello', 'gender' => 'female',
        'telephone1' => '08066666666', 'email' => null, 'lifecycle_status' => 'dormant'];
    $loser = archiveTarget($twin);
    $winner = archiveTarget($twin);

    $this->artisan('users:archive-phone-duplicates', ['--commit' => true])->assertSuccessful();

    expectArchivedBy($loser, null);
    expect($winner->fresh()->archived_at)->toBeNull()
        ->and(AuditLog::where('action', 'user_phone_duplicate_archived')->where('subject_id', $loser->id)->exists())->toBeTrue();
});

test('markArchived keeps the original date of an account that is already archived', function () {
    $user = archiveTarget(['lifecycle_status' => 'active']);
    $user->markArchived($this->admin)->save();

    Carbon::setTestNow('2027-01-01 00:00:00');
    $user->markArchived(null)->save();

    expect($user->fresh()->archived_at->toDateTimeString())->toBe('2026-10-08 09:30:00')
        ->and($user->fresh()->archived_by_id)->toBe($this->admin->id);
});

/*
|--------------------------------------------------------------------------
| Restoring
|--------------------------------------------------------------------------
*/

test('restoring on users/edit clears the archive date and writes user_unarchived', function () {
    $user = archiveTarget(['lifecycle_status' => 'active']);
    $user->markArchived($this->admin)->save();

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), archiveUpdatePayload(false))
        ->assertRedirect(route('users.show', $user));

    $user->refresh();
    expect($user->lifecycle_status)->not->toBe('archived')
        ->and($user->archived_at)->toBeNull()
        ->and($user->archived_by_id)->toBeNull();

    $log = AuditLog::where('action', 'user_unarchived')->where('subject_id', $user->id)->sole();
    expect($log->old_values)->toBe([
        'lifecycle_status' => 'archived',
        'archived_at' => '2026-10-08 09:30:00',
        'archived_by_id' => $this->admin->id,
    ])->and($log->description)->toBe("DB-{$user->id} restored from archive.");
});

test('any Eloquent restore (e.g. approving a record for an archived member) clears the date too', function () {
    $user = archiveTarget(['lifecycle_status' => 'active']);
    $user->markArchived($this->admin)->save();

    $user->update(['lifecycle_status' => 'active']);

    expect($user->fresh()->archived_at)->toBeNull()
        ->and($user->fresh()->archived_by_id)->toBeNull()
        ->and(AuditLog::where('action', 'user_unarchived')->where('subject_id', $user->id)->exists())->toBeTrue();
});
