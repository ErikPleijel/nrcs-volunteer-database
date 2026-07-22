<?php

/**
 * Feature tests for the four-eyes approval workflow on the Donations module.
 */

use App\Models\Branch;
use App\Models\Donation;
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

/** Re-fetch a donation bypassing the ApprovedScope global scope. */
function freshDonation(int $id): ?Donation
{
    return Donation::withAnyApprovalStatus()->find($id);
}

beforeEach(function () {
    // The admin layout loads assets via @vite; no build manifest exists in CI/test.
    $this->withoutVite();

    $permissions = [
        'manage-admin-panel',
        'approve_donations',
        'view_donations',
        'add_donations',
        'edit_donations',
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
        'view_donations',
        'add_donations',
        'edit_donations',
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

test('an approver can approve a donation submitted by someone else', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $member = User::factory()->inBranch($this->branchA)->create();

    $donation = Donation::factory()->create([
        'user_id' => $member->id,
        'entered_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
        'amount' => 500,
    ]);
    expect($donation->approval_status)->toBe(Donation::PENDING);

    $response = $this->actingAs($approver)
        ->from(route('donations.approvals'))
        ->post(route('donations.approve', $donation->id));

    $response->assertRedirect(route('donations.approvals'));
    $response->assertSessionHas('success');

    $fresh = freshDonation($donation->id);
    expect($fresh->approval_status)->toBe(Donation::APPROVED)
        ->and($fresh->decided_by_user_id)->toBe($approver->id)
        ->and($fresh->decided_at)->not->toBeNull();
});

test('the submitter cannot approve their own donation at the model level (guardNotSelf)', function () {
    $secretary = User::factory()->inBranch($this->branchA)->create();
    $secretary->assignRole('branch_secretary');
    $member = User::factory()->inBranch($this->branchA)->create();

    // Submitted by the secretary themself — even a fully-permissioned approver
    // must be refused on their own submission.
    $donation = Donation::factory()->create([
        'user_id' => $member->id,
        'entered_by_user_id' => $secretary->id,
        'branch_id' => $this->branchA->id,
        'amount' => 500,
    ]);

    expect(fn () => $donation->approve($secretary))
        ->toThrow(RuntimeException::class, 'same user who submitted');

    $fresh = freshDonation($donation->id);
    expect($fresh->approval_status)->toBe(Donation::PENDING)
        ->and($fresh->decided_by_user_id)->toBeNull();
});

test('a user without approve_donations is blocked by route middleware even when not the submitter', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_secretary');
    $assistant = User::factory()->inBranch($this->branchA)->create();
    $assistant->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();

    $donation = Donation::factory()->create([
        'user_id' => $member->id,
        'entered_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
        'amount' => 500,
    ]);

    $this->actingAs($assistant)
        ->post(route('donations.approve', $donation->id))
        ->assertForbidden();

    expect(freshDonation($donation->id)->approval_status)->toBe(Donation::PENDING);
});

test('an approver outside the record branch is rejected by authorizeApprovalScope', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();
    $outsideApprover = User::factory()->inBranch($this->branchB)->create();
    $outsideApprover->assignRole('branch_secretary');

    $donation = Donation::factory()->create([
        'user_id' => $member->id,
        'entered_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
        'amount' => 500,
    ]);

    $this->actingAs($outsideApprover)
        ->post(route('donations.approve', $donation->id))
        ->assertForbidden();

    expect(freshDonation($donation->id)->approval_status)->toBe(Donation::PENDING);
});

test('bulk approve processes eligible donations and skips self-submitted ones without erroring', function () {
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();

    $eligible = Donation::factory()->create([
        'user_id' => $member->id,
        'entered_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
        'amount' => 500,
    ]);
    $ownSubmission = Donation::factory()->create([
        'user_id' => $member->id,
        'entered_by_user_id' => $approver->id,
        'branch_id' => $this->branchA->id,
        'amount' => 500,
    ]);

    $response = $this->actingAs($approver)
        ->from(route('donations.approvals'))
        ->post(route('donations.bulk-approve'), [
            'ids' => [$eligible->id, $ownSubmission->id],
        ]);

    $response->assertRedirect(route('donations.approvals'));
    $response->assertSessionHas('bulk_result');

    $bulk = session('bulk_result');
    expect($bulk['approved'])->toBe(1)
        ->and($bulk['skipped'])->toHaveCount(1)
        ->and($bulk['skipped'][0]['id'])->toBe($ownSubmission->id);

    expect(freshDonation($eligible->id)->approval_status)->toBe(Donation::APPROVED)
        ->and(freshDonation($ownSubmission->id)->approval_status)->toBe(Donation::PENDING);
});

test('bulk approve never approves a donation for an archived member', function () {
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $archivedMember = User::factory()->inBranch($this->branchA)->create([
        'lifecycle_status' => 'archived',
    ]);

    $donation = Donation::factory()->create([
        'user_id' => $archivedMember->id,
        'entered_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
        'amount' => 500,
    ]);

    $this->actingAs($approver)
        ->from(route('donations.approvals'))
        ->post(route('donations.bulk-approve'), ['ids' => [$donation->id]]);

    $bulk = session('bulk_result');
    expect($bulk['approved'])->toBe(0)
        ->and($bulk['skipped'])->toHaveCount(1)
        ->and($bulk['skipped'][0]['reason'])->toContain('archived');

    expect(freshDonation($donation->id)->approval_status)->toBe(Donation::PENDING)
        ->and($archivedMember->refresh()->lifecycle_status)->toBe('archived');
});

test('eligibleForApproval never includes the viewer\'s own submissions', function () {
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();

    $othersDonation = Donation::factory()->create([
        'user_id' => $member->id,
        'entered_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
        'amount' => 500,
    ]);
    $ownDonation = Donation::factory()->create([
        'user_id' => $member->id,
        'entered_by_user_id' => $approver->id,
        'branch_id' => $this->branchA->id,
        'amount' => 500,
    ]);
    $otherBranchDonation = Donation::factory()->create([
        'user_id' => $member->id,
        'entered_by_user_id' => $submitter->id,
        'branch_id' => $this->branchB->id,
        'amount' => 500,
    ]);

    $eligibleIds = Donation::eligibleForApproval($approver)->pluck('id');

    expect($eligibleIds)->toContain($othersDonation->id)
        ->and($eligibleIds)->not->toContain($ownDonation->id)
        ->and($eligibleIds)->not->toContain($otherBranchDonation->id);

    // The approvals tab renders from the same scope: own submission absent.
    $this->actingAs($approver)
        ->get(route('donations.approvals'))
        ->assertOk()
        ->assertDontSee(route('donations.review', $ownDonation->id));
});

test('the submitter sees withdraw (not approve/reject) on the review page, and withdraw removes the record', function () {
    $submitter = User::factory()->inBranch($this->branchA)->create();
    $submitter->assignRole('branch_db_assistant');
    $member = User::factory()->inBranch($this->branchA)->create();

    $donation = Donation::factory()->create([
        'user_id' => $member->id,
        'entered_by_user_id' => $submitter->id,
        'branch_id' => $this->branchA->id,
        'amount' => 500,
    ]);

    $page = $this->actingAs($submitter)->get(route('donations.review', $donation->id));

    $page->assertOk()
        ->assertSee('Awaiting review')
        ->assertSee('Withdraw')
        ->assertDontSee(route('donations.approve', $donation->id))
        ->assertDontSee(route('donations.reject', $donation->id));

    $response = $this->actingAs($submitter)
        ->from(route('donations.review', $donation->id))
        ->post(route('donations.withdraw', $donation->id));

    $response->assertSessionHas('success');

    // Donation uses SoftDeletes (removed_date), so the guarded delete either
    // removes the row or trashes it — either way it must no longer be pending.
    expect(Donation::pendingApproval()->find($donation->id))->toBeNull();

    $raw = DB::table('donations')->where('id', $donation->id)->first();
    expect($raw === null || $raw->removed_date !== null)->toBeTrue();
});

test('a self-directed donation (beneficiary == submitter) is still approvable by a different user', function () {
    // isSelfDirected is a UI warning, not a four-eyes block: a clerk entering a
    // donation in their own name can still be approved by someone else.
    $clerk = User::factory()->inBranch($this->branchA)->create();
    $clerk->assignRole('branch_db_assistant');
    $approver = User::factory()->inBranch($this->branchA)->create();
    $approver->assignRole('branch_secretary');

    $donation = Donation::factory()->create([
        'user_id' => $clerk->id,
        'entered_by_user_id' => $clerk->id,
        'branch_id' => $this->branchA->id,
        'amount' => 500,
    ]);

    expect($donation->isSelfDirected)->toBeTrue();

    $this->actingAs($approver)
        ->from(route('donations.approvals'))
        ->post(route('donations.approve', $donation->id))
        ->assertSessionHas('success');

    $fresh = freshDonation($donation->id);
    expect($fresh->approval_status)->toBe(Donation::APPROVED)
        ->and($fresh->decided_by_user_id)->toBe($approver->id);
});
