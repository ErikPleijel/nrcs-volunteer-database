<?php

/**
 * Feature tests for the four-eyes approval workflow on the Activity
 * (volunteering) module, mirroring DonationApprovalTest,
 * MembershipPaymentApprovalTest, and TrainingApprovalTest.
 *
 * Differences from the other modules worth calling out:
 *  - submitterColumn() is the Approvable default 'submitted_by_user_id',
 *    nullable — same shape as Training, unlike MembershipPayment's NOT NULL
 *    column.
 *  - division_id is a hard-required NOT NULL column with no default (same
 *    as Training; handled by ActivityFactory), unlike Donation's nullable one.
 *  - Activity does NOT use SoftDeletes (confirmed: Activity.php only
 *    `use Approvable, HasFactory;`), so withdraw()'s guarded delete() is a
 *    genuine hard delete — same as MembershipPayment/Training, unlike
 *    Donation's removed_date soft delete.
 *  - Unique to this module: a polymorphic assignable pair (RedCrossUnit or
 *    TaskForce), always optional.
 */

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Branch;
use App\Models\RedCrossUnit;
use App\Models\TaskForce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/** Re-fetch an activity bypassing the ApprovedScope global scope. */
function freshActivity(int $id): ?Activity
{
    return Activity::withAnyApprovalStatus()->find($id);
}

beforeEach(function () {
    // The admin layout loads assets via @vite; no build manifest exists in CI/test.
    $this->withoutVite();

    $permissions = [
        'manage-admin-panel',
        'approve_volunteering',
        'view_volunteering',
        'add_volunteering',
        'edit_volunteering',
    ];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    // Mirrors PermissionsTableSeeder: branch_secretary approves; the
    // assistant can add/edit/view but never approve.
    Role::findOrCreate('branch_secretary', 'web')->syncPermissions($permissions);
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    Role::findOrCreate('branch_db_assistant', 'web')->syncPermissions([
        'manage-admin-panel',
        'view_volunteering',
        'add_volunteering',
        'edit_volunteering',
    ]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branchA = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->branchB = Branch::create(['name' => 'Beta Branch', 'code' => 'BET']);
});

/*
|--------------------------------------------------------------------------
| PART 1 — current behaviour (expected to pass)
|--------------------------------------------------------------------------
*/

test('an approver can approve an activity submitted by someone else', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $member = User::factory()->inBranch($this->branchA)->create();

    $activity = Activity::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);
    expect($activity->approval_status)->toBe(Activity::PENDING);

    $response = $this->actingAs($approver)
        ->from(route('activities.approvals'))
        ->post(route('activities.approve', $activity->id));

    $response->assertRedirect(route('activities.approvals'));
    $response->assertSessionHas('success');

    $fresh = freshActivity($activity->id);
    expect($fresh->approval_status)->toBe(Activity::APPROVED)
        ->and($fresh->decided_by_user_id)->toBe($approver->id)
        ->and($fresh->decided_at)->not->toBeNull();
});

test('the submitter cannot approve their own activity at the model level (guardNotSelf)', function () {
    $secretary = User::factory()->inBranch($this->branchA)->create();
    $secretary->assignRole('branch_secretary');
    $member = User::factory()->inBranch($this->branchA)->create();

    // Submitted by the secretary themself — even a fully-permissioned approver
    // must be refused on their own submission.
    $activity = Activity::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $secretary->id,
        'branch_id' => $this->branchA->id,
    ]);

    expect(fn () => $activity->approve($secretary))
        ->toThrow(RuntimeException::class, 'same user who submitted');

    $fresh = freshActivity($activity->id);
    expect($fresh->approval_status)->toBe(Activity::PENDING)
        ->and($fresh->decided_by_user_id)->toBeNull();
});

test('a user without approve_volunteering is blocked by route middleware even when not the submitter', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_secretary');
    $assistant = User::factory()->inBranch($this->branchA)->create();
    $assistant->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();

    $activity = Activity::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);

    $this->actingAs($assistant)
        ->post(route('activities.approve', $activity->id))
        ->assertForbidden();

    expect(freshActivity($activity->id)->approval_status)->toBe(Activity::PENDING);
});

test('an approver outside the record branch is rejected by authorizeApprovalScope', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();
    $outsideApprover = User::factory()->inBranch($this->branchB)->create();
    $outsideApprover->assignRole('branch_secretary');

    $activity = Activity::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);

    $this->actingAs($outsideApprover)
        ->post(route('activities.approve', $activity->id))
        ->assertForbidden();

    expect(freshActivity($activity->id)->approval_status)->toBe(Activity::PENDING);
});

test('bulk approve processes eligible activities and skips self-submitted ones without erroring', function () {
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();

    $eligible = Activity::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);
    $ownSubmission = Activity::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $approver->id,
        'branch_id' => $this->branchA->id,
    ]);

    $response = $this->actingAs($approver)
        ->from(route('activities.approvals'))
        ->post(route('activities.bulk-approve'), [
            'ids' => [$eligible->id, $ownSubmission->id],
        ]);

    $response->assertRedirect(route('activities.approvals'));
    $response->assertSessionHas('bulk_result');

    $bulk = session('bulk_result');
    expect($bulk['approved'])->toBe(1)
        ->and($bulk['skipped'])->toHaveCount(1)
        ->and($bulk['skipped'][0]['id'])->toBe($ownSubmission->id);

    expect(freshActivity($eligible->id)->approval_status)->toBe(Activity::APPROVED)
        ->and(freshActivity($ownSubmission->id)->approval_status)->toBe(Activity::PENDING);
});

test('bulk approve never approves an activity for an archived member', function () {
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $archivedMember = User::factory()->inBranch($this->branchA)->create([
        'lifecycle_status' => 'archived',
    ]);

    $activity = Activity::factory()->create([
        'user_id' => $archivedMember->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);

    $this->actingAs($approver)
        ->from(route('activities.approvals'))
        ->post(route('activities.bulk-approve'), ['ids' => [$activity->id]]);

    $bulk = session('bulk_result');
    expect($bulk['approved'])->toBe(0)
        ->and($bulk['skipped'])->toHaveCount(1)
        ->and($bulk['skipped'][0]['reason'])->toContain('archived');

    expect(freshActivity($activity->id)->approval_status)->toBe(Activity::PENDING)
        ->and($archivedMember->refresh()->lifecycle_status)->toBe('archived');
});

test('eligibleForApproval never includes the viewer\'s own submissions', function () {
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();

    $othersActivity = Activity::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);
    $ownActivity = Activity::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $approver->id,
        'branch_id' => $this->branchA->id,
    ]);
    $otherBranchActivity = Activity::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchB->id,
    ]);

    $eligibleIds = Activity::eligibleForApproval($approver)->pluck('id');

    expect($eligibleIds)->toContain($othersActivity->id)
        ->and($eligibleIds)->not->toContain($ownActivity->id)
        ->and($eligibleIds)->not->toContain($otherBranchActivity->id);

    // The approvals tab renders from the same scope: own submission absent.
    $this->actingAs($approver)
        ->get(route('activities.approvals'))
        ->assertOk()
        ->assertDontSee(route('activities.review', $ownActivity->id));
});

test('the submitter sees withdraw (not approve/reject) on the review page, and withdraw hard-deletes the record', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();

    $activity = Activity::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);

    $page = $this->actingAs($submitter)->get(route('activities.review', $activity->id));

    $page->assertOk()
        ->assertSee('Awaiting review')
        ->assertSee('Withdraw')
        ->assertDontSee(route('activities.approve', $activity->id))
        ->assertDontSee(route('activities.reject', $activity->id));

    $response = $this->actingAs($submitter)
        ->from(route('activities.review', $activity->id))
        ->post(route('activities.withdraw', $activity->id));

    $response->assertSessionHas('success');

    // Activity does NOT use SoftDeletes (unlike Donation), so withdraw's guarded
    // delete() is a genuine hard delete — the row must be gone entirely, not
    // merely flagged, from every scope including withAnyApprovalStatus().
    expect(freshActivity($activity->id))->toBeNull();

    $raw = DB::table('activities')->where('id', $activity->id)->first();
    expect($raw)->toBeNull();
});

test('a self-directed activity (beneficiary == submitter) is still approvable by a different user', function () {
    // isSelfDirected is a UI warning, not a four-eyes block: a clerk entering an
    // activity in their own name can still be approved by someone else.
    $clerk = User::factory()->inBranch($this->branchA)->create();
    $clerk->assignRole('branch_db_assistant');
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');

    $activity = Activity::factory()->create([
        'user_id' => $clerk->id,
        'submitted_by_user_id' => $clerk->id,
        'branch_id' => $this->branchA->id,
    ]);

    expect($activity->isSelfDirected)->toBeTrue();

    $this->actingAs($approver)
        ->from(route('activities.approvals'))
        ->post(route('activities.approve', $activity->id))
        ->assertSessionHas('success');

    $fresh = freshActivity($activity->id);
    expect($fresh->approval_status)->toBe(Activity::APPROVED)
        ->and($fresh->decided_by_user_id)->toBe($approver->id);
});

/*
|--------------------------------------------------------------------------
| PART 2 — module-specific: the polymorphic assignable pair
|--------------------------------------------------------------------------
*/

test('forRedCrossUnit() sets the polymorphic assignable pair together', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $member = User::factory()->inBranch($this->branchA)->create();
    $unit = RedCrossUnit::create(['name' => 'Test RC Unit']);

    $activity = Activity::factory()->forRedCrossUnit($unit)->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);

    expect($activity->assignable_type)->toBe(RedCrossUnit::class)
        ->and($activity->assignable_id)->toBe($unit->id)
        ->and($activity->assignable)->toBeInstanceOf(RedCrossUnit::class)
        ->and($activity->assignable->id)->toBe($unit->id)
        ->and($activity->unit_type)->toBe('red_cross_unit');
});

test('forTaskForce() sets the polymorphic assignable pair together', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $member = User::factory()->inBranch($this->branchA)->create();
    $taskForce = TaskForce::create([
        'name' => 'Test Task Force',
        'task_force_type_id' => 1,
        'branch_id' => $this->branchA->id,
    ]);

    $activity = Activity::factory()->forTaskForce($taskForce)->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);

    expect($activity->assignable_type)->toBe(TaskForce::class)
        ->and($activity->assignable_id)->toBe($taskForce->id)
        ->and($activity->assignable)->toBeInstanceOf(TaskForce::class)
        ->and($activity->assignable->id)->toBe($taskForce->id)
        ->and($activity->unit_type)->toBe('task_force');
});
