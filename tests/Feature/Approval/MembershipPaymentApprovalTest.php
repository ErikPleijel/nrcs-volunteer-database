<?php

/**
 * Feature tests for the four-eyes approval workflow on the MembershipPayment
 * module, mirroring tests/Feature/Approval/DonationApprovalTest.php.
 *
 * Differences from Donation worth calling out:
 *  - submitterColumn() is the Approvable default 'submitted_by_user_id' (not
 *    overridden), and it is NOT NULL on this table — unlike Donation's
 *    nullable entered_by_user_id.
 *  - MembershipPayment does NOT use SoftDeletes, so withdraw() is a genuine
 *    hard delete, not a removed_date soft delete like Donation's.
 *  - resetApprovalOnEdit() (the shared Approvable fix) is exercised here too,
 *    so PART 2 is expected to PASS, not document a known gap.
 */

use App\Models\Branch;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
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

/** Re-fetch a membership payment bypassing the ApprovedScope global scope. */
function freshMembershipPayment(int $id): ?MembershipPayment
{
    return MembershipPayment::withAnyApprovalStatus()->find($id);
}

beforeEach(function () {
    // The admin layout loads assets via @vite; no build manifest exists in CI/test.
    $this->withoutVite();

    $permissions = [
        'manage-admin-panel',
        'approve_payments',
        'view_payments',
        'add_payments',
        'edit_payments',
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
        'view_payments',
        'add_payments',
        'edit_payments',
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

test('an approver can approve a payment submitted by someone else', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $member = User::factory()->inBranch($this->branchA)->create();

    $payment = MembershipPayment::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);
    expect($payment->approval_status)->toBe(MembershipPayment::PENDING);

    $response = $this->actingAs($approver)
        ->from(route('membership-payments.approvals'))
        ->post(route('membership-payments.approve', $payment->id));

    $response->assertRedirect(route('membership-payments.approvals'));
    $response->assertSessionHas('success');

    $fresh = freshMembershipPayment($payment->id);
    expect($fresh->approval_status)->toBe(MembershipPayment::APPROVED)
        ->and($fresh->decided_by_user_id)->toBe($approver->id)
        ->and($fresh->decided_at)->not->toBeNull();
});

test('the submitter cannot approve their own payment at the model level (guardNotSelf)', function () {
    $secretary = User::factory()->inBranch($this->branchA)->create();
    $secretary->assignRole('branch_secretary');
    $member = User::factory()->inBranch($this->branchA)->create();

    // Submitted by the secretary themself — even a fully-permissioned approver
    // must be refused on their own submission.
    $payment = MembershipPayment::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $secretary->id,
        'branch_id' => $this->branchA->id,
    ]);

    expect(fn () => $payment->approve($secretary))
        ->toThrow(RuntimeException::class, 'same user who submitted');

    $fresh = freshMembershipPayment($payment->id);
    expect($fresh->approval_status)->toBe(MembershipPayment::PENDING)
        ->and($fresh->decided_by_user_id)->toBeNull();
});

test('a user without approve_payments is blocked by route middleware even when not the submitter', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_secretary');
    $assistant = User::factory()->inBranch($this->branchA)->create();
    $assistant->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();

    $payment = MembershipPayment::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);

    $this->actingAs($assistant)
        ->post(route('membership-payments.approve', $payment->id))
        ->assertForbidden();

    expect(freshMembershipPayment($payment->id)->approval_status)->toBe(MembershipPayment::PENDING);
});

test('an approver outside the record branch is rejected by authorizeApprovalScope', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();
    $outsideApprover = User::factory()->inBranch($this->branchB)->create();
    $outsideApprover->assignRole('branch_secretary');

    $payment = MembershipPayment::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);

    $this->actingAs($outsideApprover)
        ->post(route('membership-payments.approve', $payment->id))
        ->assertForbidden();

    expect(freshMembershipPayment($payment->id)->approval_status)->toBe(MembershipPayment::PENDING);
});

test('bulk approve processes eligible payments and skips self-submitted ones without erroring', function () {
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();

    $eligible = MembershipPayment::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);
    $ownSubmission = MembershipPayment::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $approver->id,
        'branch_id' => $this->branchA->id,
    ]);

    $response = $this->actingAs($approver)
        ->from(route('membership-payments.approvals'))
        ->post(route('membership-payments.bulk-approve'), [
            'ids' => [$eligible->id, $ownSubmission->id],
        ]);

    $response->assertRedirect(route('membership-payments.approvals'));
    $response->assertSessionHas('bulk_result');

    $bulk = session('bulk_result');
    expect($bulk['approved'])->toBe(1)
        ->and($bulk['skipped'])->toHaveCount(1)
        ->and($bulk['skipped'][0]['id'])->toBe($ownSubmission->id);

    expect(freshMembershipPayment($eligible->id)->approval_status)->toBe(MembershipPayment::APPROVED)
        ->and(freshMembershipPayment($ownSubmission->id)->approval_status)->toBe(MembershipPayment::PENDING);
});

test('bulk approve never approves a payment for an archived member', function () {
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $archivedMember = User::factory()->inBranch($this->branchA)->create([
        'lifecycle_status' => 'archived',
    ]);

    $payment = MembershipPayment::factory()->create([
        'user_id' => $archivedMember->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);

    $this->actingAs($approver)
        ->from(route('membership-payments.approvals'))
        ->post(route('membership-payments.bulk-approve'), ['ids' => [$payment->id]]);

    $bulk = session('bulk_result');
    expect($bulk['approved'])->toBe(0)
        ->and($bulk['skipped'])->toHaveCount(1)
        ->and($bulk['skipped'][0]['reason'])->toContain('archived');

    expect(freshMembershipPayment($payment->id)->approval_status)->toBe(MembershipPayment::PENDING)
        ->and($archivedMember->refresh()->lifecycle_status)->toBe('archived');
});

test('eligibleForApproval never includes the viewer\'s own submissions', function () {
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();

    $othersPayment = MembershipPayment::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);
    $ownPayment = MembershipPayment::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $approver->id,
        'branch_id' => $this->branchA->id,
    ]);
    $otherBranchPayment = MembershipPayment::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchB->id,
    ]);

    $eligibleIds = MembershipPayment::eligibleForApproval($approver)->pluck('id');

    expect($eligibleIds)->toContain($othersPayment->id)
        ->and($eligibleIds)->not->toContain($ownPayment->id)
        ->and($eligibleIds)->not->toContain($otherBranchPayment->id);

    // The approvals tab renders from the same scope: own submission absent.
    $this->actingAs($approver)
        ->get(route('membership-payments.approvals'))
        ->assertOk()
        ->assertDontSee(route('membership-payments.review', $ownPayment->id));
});

test('the submitter sees withdraw (not approve/reject) on the review page, and withdraw hard-deletes the record', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();

    $payment = MembershipPayment::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
    ]);

    $page = $this->actingAs($submitter)->get(route('membership-payments.review', $payment->id));

    $page->assertOk()
        ->assertSee('Awaiting review')
        ->assertSee('Withdraw')
        ->assertDontSee(route('membership-payments.approve', $payment->id))
        ->assertDontSee(route('membership-payments.reject', $payment->id));

    $response = $this->actingAs($submitter)
        ->from(route('membership-payments.review', $payment->id))
        ->post(route('membership-payments.withdraw', $payment->id));

    $response->assertSessionHas('success');

    // MembershipPayment does NOT use SoftDeletes (unlike Donation), so withdraw's
    // guarded delete() is a genuine hard delete — the row must be gone entirely,
    // not merely flagged, from every scope including withAnyApprovalStatus().
    expect(freshMembershipPayment($payment->id))->toBeNull();

    $raw = DB::table('membership_payments')->where('id', $payment->id)->first();
    expect($raw)->toBeNull();
});

test('a self-directed payment (beneficiary == submitter) is still approvable by a different user', function () {
    // isSelfDirected is a UI warning, not a four-eyes block: a clerk entering a
    // payment in their own name can still be approved by someone else.
    $clerk = User::factory()->inBranch($this->branchA)->create();
    $clerk->assignRole('branch_db_assistant');
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');

    $payment = MembershipPayment::factory()->create([
        'user_id' => $clerk->id,
        'submitted_by_user_id' => $clerk->id,
        'branch_id' => $this->branchA->id,
    ]);

    expect($payment->isSelfDirected)->toBeTrue();

    $this->actingAs($approver)
        ->from(route('membership-payments.approvals'))
        ->post(route('membership-payments.approve', $payment->id))
        ->assertSessionHas('success');

    $fresh = freshMembershipPayment($payment->id);
    expect($fresh->approval_status)->toBe(MembershipPayment::APPROVED)
        ->and($fresh->decided_by_user_id)->toBe($approver->id);
});

/*
|--------------------------------------------------------------------------
| PART 2 — module-specific: expiry_date derivation
|--------------------------------------------------------------------------
*/

test('expiry_date is derived from payment_date plus the membership fee\'s validity_years on creation', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $member = User::factory()->inBranch($this->branchA)->create();
    $fee = MembershipFee::factory()->create(['validity_years' => 2]);

    $paymentDate = now()->subMonths(2)->startOfDay();

    $payment = MembershipPayment::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => $paymentDate->toDateString(),
    ]);

    expect($payment->expiry_date->toDateString())
        ->toBe($paymentDate->copy()->addYears(2)->toDateString());
});
