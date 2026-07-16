<?php

/**
 * Feature tests for the four-eyes approval workflow on the Training module,
 * mirroring tests/Feature/Approval/DonationApprovalTest.php and
 * MembershipPaymentApprovalTest.php.
 *
 * Differences from the other two modules worth calling out:
 *  - submitterColumn() is the Approvable default 'submitted_by_user_id',
 *    same column name as MembershipPayment, but NULLABLE here (unlike
 *    MembershipPayment's NOT NULL).
 *  - division_id is Training's only hard-required NOT NULL column with no
 *    default (handled by TrainingFactory).
 *  - Training does NOT use SoftDeletes (confirmed: Training.php only
 *    `use Approvable, HasFactory;`), so withdraw()'s guarded delete() is a
 *    genuine hard delete — same as MembershipPayment, unlike Donation's
 *    removed_date soft delete.
 *  - Training::afterApproved()/afterDemoted() recompute the trainee's
 *    denormalised last_first_aid_at — logic no other module has.
 */

use App\Models\Branch;
use App\Models\Training;
use App\Models\TrainingType;
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

/** Re-fetch a training bypassing the ApprovedScope global scope. */
function freshTraining(int $id): ?Training
{
    return Training::withAnyApprovalStatus()->find($id);
}

beforeEach(function () {
    // The admin layout loads assets via @vite; no build manifest exists in CI/test.
    $this->withoutVite();

    $permissions = [
        'manage-admin-panel',
        'approve_training',
        'view_trainings',
        'add_trainings',
        'edit_trainings',
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
        'view_trainings',
        'add_trainings',
        'edit_trainings',
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

test('an approver can approve a training submitted by someone else', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $member = User::factory()->inBranch($this->branchA)->create();

    $training = Training::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);
    expect($training->approval_status)->toBe(Training::PENDING);

    $response = $this->actingAs($approver)
        ->from(route('trainings.approvals'))
        ->post(route('trainings.approve', $training->id));

    $response->assertRedirect(route('trainings.approvals'));
    $response->assertSessionHas('success');

    $fresh = freshTraining($training->id);
    expect($fresh->approval_status)->toBe(Training::APPROVED)
        ->and($fresh->decided_by_user_id)->toBe($approver->id)
        ->and($fresh->decided_at)->not->toBeNull();
});

test('the submitter cannot approve their own training at the model level (guardNotSelf)', function () {
    $secretary = User::factory()->inBranch($this->branchA)->create();
    $secretary->assignRole('branch_secretary');
    $member = User::factory()->inBranch($this->branchA)->create();

    // Submitted by the secretary themself — even a fully-permissioned approver
    // must be refused on their own submission.
    $training = Training::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $secretary->id,
        'branch_id' => $this->branchA->id,
    ]);

    expect(fn () => $training->approve($secretary))
        ->toThrow(RuntimeException::class, 'same user who submitted');

    $fresh = freshTraining($training->id);
    expect($fresh->approval_status)->toBe(Training::PENDING)
        ->and($fresh->decided_by_user_id)->toBeNull();
});

test('a user without approve_training is blocked by route middleware even when not the submitter', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_secretary');
    $assistant = User::factory()->inBranch($this->branchA)->create();
    $assistant->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();

    $training = Training::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);

    $this->actingAs($assistant)
        ->post(route('trainings.approve', $training->id))
        ->assertForbidden();

    expect(freshTraining($training->id)->approval_status)->toBe(Training::PENDING);
});

test('an approver outside the record branch is rejected by authorizeApprovalScope', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();
    $outsideApprover = User::factory()->inBranch($this->branchB)->create();
    $outsideApprover->assignRole('branch_secretary');

    $training = Training::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);

    $this->actingAs($outsideApprover)
        ->post(route('trainings.approve', $training->id))
        ->assertForbidden();

    expect(freshTraining($training->id)->approval_status)->toBe(Training::PENDING);
});

test('bulk approve processes eligible trainings and skips self-submitted ones without erroring', function () {
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();

    $eligible = Training::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);
    $ownSubmission = Training::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $approver->id,
        'branch_id' => $this->branchA->id,
    ]);

    $response = $this->actingAs($approver)
        ->from(route('trainings.approvals'))
        ->post(route('trainings.bulk-approve'), [
            'ids' => [$eligible->id, $ownSubmission->id],
        ]);

    $response->assertRedirect(route('trainings.approvals'));
    $response->assertSessionHas('bulk_result');

    $bulk = session('bulk_result');
    expect($bulk['approved'])->toBe(1)
        ->and($bulk['skipped'])->toHaveCount(1)
        ->and($bulk['skipped'][0]['id'])->toBe($ownSubmission->id);

    expect(freshTraining($eligible->id)->approval_status)->toBe(Training::APPROVED)
        ->and(freshTraining($ownSubmission->id)->approval_status)->toBe(Training::PENDING);
});

test('bulk approve never approves a training for an archived member', function () {
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $archivedMember = User::factory()->inBranch($this->branchA)->create([
        'lifecycle_status' => 'archived',
    ]);

    $training = Training::factory()->create([
        'user_id' => $archivedMember->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);

    $this->actingAs($approver)
        ->from(route('trainings.approvals'))
        ->post(route('trainings.bulk-approve'), ['ids' => [$training->id]]);

    $bulk = session('bulk_result');
    expect($bulk['approved'])->toBe(0)
        ->and($bulk['skipped'])->toHaveCount(1)
        ->and($bulk['skipped'][0]['reason'])->toContain('archived');

    expect(freshTraining($training->id)->approval_status)->toBe(Training::PENDING)
        ->and($archivedMember->refresh()->lifecycle_status)->toBe('archived');
});

test('eligibleForApproval never includes the viewer\'s own submissions', function () {
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();

    $othersTraining = Training::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);
    $ownTraining = Training::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $approver->id,
        'branch_id' => $this->branchA->id,
    ]);
    $otherBranchTraining = Training::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchB->id,
    ]);

    $eligibleIds = Training::eligibleForApproval($approver)->pluck('id');

    expect($eligibleIds)->toContain($othersTraining->id)
        ->and($eligibleIds)->not->toContain($ownTraining->id)
        ->and($eligibleIds)->not->toContain($otherBranchTraining->id);

    // The approvals tab renders from the same scope: own submission absent.
    $this->actingAs($approver)
        ->get(route('trainings.approvals'))
        ->assertOk()
        ->assertDontSee(route('trainings.review', $ownTraining->id));
});

test('the submitter sees withdraw (not approve/reject) on the review page, and withdraw hard-deletes the record', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();

    $training = Training::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);

    $page = $this->actingAs($submitter)->get(route('trainings.review', $training->id));

    $page->assertOk()
        ->assertSee('Awaiting review')
        ->assertSee('Withdraw')
        ->assertDontSee(route('trainings.approve', $training->id))
        ->assertDontSee(route('trainings.reject', $training->id));

    $response = $this->actingAs($submitter)
        ->from(route('trainings.review', $training->id))
        ->post(route('trainings.withdraw', $training->id));

    $response->assertSessionHas('success');

    // Training does NOT use SoftDeletes (unlike Donation), so withdraw's guarded
    // delete() is a genuine hard delete — the row must be gone entirely, not
    // merely flagged, from every scope including withAnyApprovalStatus().
    expect(freshTraining($training->id))->toBeNull();

    $raw = DB::table('trainings')->where('id', $training->id)->first();
    expect($raw)->toBeNull();
});

test('a self-directed training (beneficiary == submitter) is still approvable by a different user', function () {
    // isSelfDirected is a UI warning, not a four-eyes block: a clerk entering a
    // training in their own name can still be approved by someone else.
    $clerk = User::factory()->inBranch($this->branchA)->create();
    $clerk->assignRole('branch_db_assistant');
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');

    $training = Training::factory()->create([
        'user_id' => $clerk->id,
        'submitted_by_user_id' => $clerk->id,
        'branch_id' => $this->branchA->id,
    ]);

    expect($training->isSelfDirected)->toBeTrue();

    $this->actingAs($approver)
        ->from(route('trainings.approvals'))
        ->post(route('trainings.approve', $training->id))
        ->assertSessionHas('success');

    $fresh = freshTraining($training->id);
    expect($fresh->approval_status)->toBe(Training::APPROVED)
        ->and($fresh->decided_by_user_id)->toBe($approver->id);
});

/*
|--------------------------------------------------------------------------
| PART 2 — resetApprovalOnEdit() (shared Approvable fix): expected to PASS
|--------------------------------------------------------------------------
*/

test('editing an approved training resets it to pending and clears the previous decision', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $member = User::factory()->inBranch($this->branchA)->create();
    $type = TrainingType::factory()->create();

    $training = Training::factory()->ofType($type)->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);
    $training->approve($approver);

    // The original approver edits the approved record via the normal update route.
    $this->actingAs($approver)
        ->put(route('trainings.update', $training->id), [
            'user_id' => $member->id,
            'training_type_id' => $type->id,
            'training_date' => $training->training_date->toDateString(),
            'duration' => $training->duration,
            'reference' => 'Corrected after approval',
            'branch_id' => $this->branchA->id,
        ])
        ->assertRedirect(route('trainings.index'));

    $fresh = freshTraining($training->id);

    expect($fresh->approval_status)->toBe(Training::PENDING)
        ->and($fresh->decided_by_user_id)->toBeNull()
        ->and($fresh->decided_at)->toBeNull()
        ->and($fresh->rejection_reason)->toBeNull();
});

test('resetting an approved training to pending recomputes the member\'s lifecycle status', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    // No red_cross_unit_id, no membership payment: this member's lifecycle policy
    // type is 'neither', governed purely by last_activity_at — which is derived
    // solely from this training once approved (no other activity/payment/donation
    // exists for them in this test).
    $member = User::factory()->inBranch($this->branchA)->create([
        'lifecycle_status' => 'active',
    ]);
    $type = TrainingType::factory()->create();

    $training = Training::factory()->ofType($type)->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
        'training_date' => now()->subMonth()->toDateString(),
    ]);
    $training->approve($approver);

    expect($member->refresh()->lifecycle_status)->toBe('active')
        ->and($member->last_activity_at?->toDateString())->toBe($training->training_date->toDateString());

    // Editing the (only) approved record demotes it back to pending, so it no
    // longer counts toward last_activity_at — resetApprovalOnEdit()'s
    // unconditional recalculateLifecycle() call should observe that and demote
    // the member.
    $this->actingAs($approver)
        ->put(route('trainings.update', $training->id), [
            'user_id' => $member->id,
            'training_type_id' => $type->id,
            'training_date' => $training->training_date->toDateString(),
            'duration' => $training->duration,
            'reference' => 'Touched to trigger reset',
            'branch_id' => $this->branchA->id,
        ]);

    expect(freshTraining($training->id)->approval_status)->toBe(Training::PENDING)
        ->and($member->refresh()->lifecycle_status)->toBe('dormant');
});

/*
|--------------------------------------------------------------------------
| PART 3 — module-specific: first-aid freshness recalculation
|--------------------------------------------------------------------------
*/

test('approving a first-aid training updates the trainee\'s last_first_aid_at', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $member = User::factory()->inBranch($this->branchA)->create();

    $training = Training::factory()->firstAid()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
        'training_date' => now()->subMonths(3)->toDateString(),
    ]);

    // Still pending: excluded by ApprovedScope, so no effect yet.
    expect($member->refresh()->last_first_aid_at)->toBeNull();

    $this->actingAs($approver)
        ->from(route('trainings.approvals'))
        ->post(route('trainings.approve', $training->id))
        ->assertSessionHas('success');

    expect($member->refresh()->last_first_aid_at->toDateString())
        ->toBe($training->training_date->toDateString());
});

test('demoting an approved first-aid training via edit falls back last_first_aid_at to an earlier qualifying training', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $member = User::factory()->inBranch($this->branchA)->create();

    $earlier = Training::factory()->firstAid()->approved()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
        'training_date' => now()->subYears(2)->toDateString(),
    ]);
    $recent = Training::factory()->firstAid()->approved()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
        'training_date' => now()->subMonth()->toDateString(),
    ]);

    // approved() (factory state) only sets the DB columns directly, bypassing
    // approve()'s afterApproved() hook — seed the denormalised column the same
    // way a real approval would have, so the "before" state is correct.
    $member->recalculateLastFirstAidAt();
    expect($member->refresh()->last_first_aid_at->toDateString())
        ->toBe($recent->training_date->toDateString());

    // The more recent training is edited — demoted back to pending, so it no
    // longer qualifies as an approved first-aid record. This is exactly the
    // staleness gap Training::afterDemoted() was built to close: the denormalised
    // date must fall back to the still-approved, earlier qualifying training,
    // not merely stay unchanged.
    $this->actingAs($approver)
        ->put(route('trainings.update', $recent->id), [
            'user_id' => $member->id,
            'training_type_id' => $recent->training_type_id,
            'training_date' => $recent->training_date->toDateString(),
            'duration' => $recent->duration,
            'reference' => 'Correction after approval',
            'branch_id' => $this->branchA->id,
        ]);

    expect(freshTraining($recent->id)->approval_status)->toBe(Training::PENDING)
        ->and($member->refresh()->last_first_aid_at->toDateString())
        ->toBe($earlier->training_date->toDateString());
});

test('demoting a trainee\'s only approved first-aid training via edit clears last_first_aid_at to null', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $member = User::factory()->inBranch($this->branchA)->create();

    $training = Training::factory()->firstAid()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
        'training_date' => now()->subMonths(3)->toDateString(),
    ]);
    $training->approve($approver);

    expect($member->refresh()->last_first_aid_at)->not->toBeNull();

    $this->actingAs($approver)
        ->put(route('trainings.update', $training->id), [
            'user_id' => $member->id,
            'training_type_id' => $training->training_type_id,
            'training_date' => $training->training_date->toDateString(),
            'duration' => $training->duration,
            'reference' => 'Correction after approval',
            'branch_id' => $this->branchA->id,
        ]);

    expect(freshTraining($training->id)->approval_status)->toBe(Training::PENDING)
        ->and($member->refresh()->last_first_aid_at)->toBeNull();
});

test('approving a non-first-aid training does not affect last_first_aid_at', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $member = User::factory()->inBranch($this->branchA)->create();
    $nonFirstAidType = TrainingType::factory()->create(['is_first_aid' => false]);

    $training = Training::factory()->ofType($nonFirstAidType)->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);

    expect($member->refresh()->last_first_aid_at)->toBeNull();

    $this->actingAs($approver)
        ->from(route('trainings.approvals'))
        ->post(route('trainings.approve', $training->id))
        ->assertSessionHas('success');

    expect($member->refresh()->last_first_aid_at)->toBeNull();
});
