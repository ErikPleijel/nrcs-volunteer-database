<?php

/**
 * Backfill of archived_at for accounts archived before the column existed
 * (2026_10_08_140000_backfill_archived_at_on_users), and the "Archived on
 * {date} by {who}" line on users/show and users/edit.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const ARCHIVED_AT_BACKFILL = 'database/migrations/2026_10_08_140000_backfill_archived_at_on_users.php';

afterEach(function () {
    Carbon::setTestNow();
});

/** An account archived the old way: status only, no date. */
function legacyArchivedUser(): User
{
    $user = User::factory()->create(['lifecycle_status' => 'archived']);
    expect($user->archived_at)->toBeNull();

    return $user;
}

function archiveAuditRow(User $subject, string $action, string $createdAt, ?int $byId = null): void
{
    DB::table('logs')->insert([
        'user_id' => $byId,
        'action' => $action,
        'subject_type' => User::class,
        'subject_id' => $subject->id,
        'description' => "DB-{$subject->id} archived.",
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

/*
|--------------------------------------------------------------------------
| Backfill
|--------------------------------------------------------------------------
*/

test('the backfill takes the latest archive audit entry, or now when there is none', function () {
    Carbon::setTestNow('2026-10-08 12:00:00');

    $selfArchived = legacyArchivedUser();
    archiveAuditRow($selfArchived, 'user_self_archived', '2026-03-01 08:00:00', $selfArchived->id);

    $archiver = User::factory()->create();
    $twiceArchived = legacyArchivedUser();
    archiveAuditRow($twiceArchived, 'user_phone_duplicate_archived', '2026-01-10 10:00:00');
    archiveAuditRow($twiceArchived, 'user_archived', '2026-05-20 15:45:00', $archiver->id);

    $noAudit = legacyArchivedUser();
    // An unrelated action must not count as an archive date.
    archiveAuditRow($noAudit, 'member_branch_division_changed', '2025-06-01 09:00:00', $archiver->id);

    $active = User::factory()->create(['lifecycle_status' => 'active']);

    (require base_path(ARCHIVED_AT_BACKFILL))->up();

    expect($selfArchived->fresh()->archived_at->toDateTimeString())->toBe('2026-03-01 08:00:00')
        ->and($selfArchived->fresh()->archived_by_id)->toBe($selfArchived->id)
        ->and($twiceArchived->fresh()->archived_at->toDateTimeString())->toBe('2026-05-20 15:45:00')
        ->and($twiceArchived->fresh()->archived_by_id)->toBe($archiver->id)
        ->and($noAudit->fresh()->archived_at->toDateTimeString())->toBe('2026-10-08 12:00:00')
        ->and($noAudit->fresh()->archived_by_id)->toBeNull()
        ->and($active->fresh()->archived_at)->toBeNull();
});

test('the backfill never overwrites a recorded archive date', function () {
    Carbon::setTestNow('2026-10-08 12:00:00');
    $user = User::factory()->create(['lifecycle_status' => 'active']);
    $user->markArchived(null)->save();

    Carbon::setTestNow('2027-02-02 00:00:00');
    (require base_path(ARCHIVED_AT_BACKFILL))->up();

    expect($user->fresh()->archived_at->toDateTimeString())->toBe('2026-10-08 12:00:00');
});

/*
|--------------------------------------------------------------------------
| Display on users/show and users/edit
|--------------------------------------------------------------------------
*/

function archiveDisplayAdmin(): User
{
    test()->withoutVite();

    foreach (['manage-admin-panel', 'view_user', 'edit_user'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')
        ->syncPermissions(['manage-admin-panel', 'view_user', 'edit_user']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $admin = User::factory()->create(['first_name' => 'Nora', 'last_name' => 'National']);
    $admin->assignRole('national_db_administrator');

    return $admin;
}

test('users/show and users/edit say when and by whom the account was archived', function () {
    $admin = archiveDisplayAdmin();
    Carbon::setTestNow('2026-10-08 12:00:00');

    $byAdmin = User::factory()->create(['lifecycle_status' => 'active']);
    $byAdmin->markArchived($admin)->save();

    $bySelf = User::factory()->create(['lifecycle_status' => 'active']);
    $bySelf->markArchived($bySelf)->save();

    $bySystem = User::factory()->create(['lifecycle_status' => 'active']);
    $bySystem->markArchived(null)->save();

    $expected = [
        [$byAdmin, "Archived on Oct 08, 2026 by Nora National DB-{$admin->id}"],
        [$bySelf, 'Archived on Oct 08, 2026 by self'],
        [$bySystem, 'Archived on Oct 08, 2026 by system'],
    ];

    foreach ($expected as [$user, $line]) {
        $this->actingAs($admin)->get(route('users.show', $user))->assertOk()->assertSee($line);
        $this->actingAs($admin)->get(route('users.edit', $user))->assertOk()->assertSee($line);
    }
});

test('a user who is not archived shows no archive line', function () {
    $admin = archiveDisplayAdmin();
    $user = User::factory()->create(['lifecycle_status' => 'active']);

    $this->actingAs($admin)->get(route('users.show', $user))->assertOk()->assertDontSee('Archived on');
});
