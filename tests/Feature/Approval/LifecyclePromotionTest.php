<?php

/**
 * Feature tests for the pending_engagement -> active promotion added to
 * Approvable::approve() (the third lift-block, alongside the existing
 * dormant and archived lifts).
 *
 * These are model-level tests (calling ->approve($approver) directly)
 * since the behaviour under test is Approvable's universal lifecycle work,
 * not the four-eyes/authorization layer already covered by the other four
 * Approval*Test files — no roles/permissions/branches are needed here.
 */

use App\Models\Activity;
use App\Models\Donation;
use App\Models\MembershipPayment;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('approving a pending_engagement user\'s training does NOT promote them to active', function () {
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $member = User::factory()->create(['lifecycle_status' => 'pending_engagement']);

    $training = Training::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'training_date' => now()->subWeek()->toDateString(),
    ]);

    $training->approve($approver);

    // Training::promotesFromPendingEngagement() returns false — the lift-block
    // never fires, and recalculateLifecycle() itself no-ops for a non-active
    // member (its early-return guard), so nothing here can touch lifecycle_status.
    expect(Training::withAnyApprovalStatus()->find($training->id)->approval_status)->toBe(Training::APPROVED)
        ->and($member->refresh()->lifecycle_status)->toBe('pending_engagement');
});

test('approving a pending_engagement user\'s activity promotes them to active', function () {
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $member = User::factory()->create(['lifecycle_status' => 'pending_engagement']);

    $activity = Activity::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'date' => now()->subWeek()->toDateString(),
    ]);

    $activity->approve($approver);

    expect(Activity::withAnyApprovalStatus()->find($activity->id)->approval_status)->toBe(Activity::APPROVED)
        ->and($member->refresh()->lifecycle_status)->toBe('active');
});

test('approving a pending_engagement user\'s membership payment promotes them to active', function () {
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $member = User::factory()->create(['lifecycle_status' => 'pending_engagement']);

    // Default factory dates (payment_date within the last 3 months, validity_years 1)
    // yield a currently-valid membership, so this member classifies as policy type
    // 'member' the moment they're lifted to active, and isDormantByPolicy() finds a
    // valid current payment — so the lift is not walked back.
    $payment = MembershipPayment::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
    ]);

    $payment->approve($approver);

    expect(MembershipPayment::withAnyApprovalStatus()->find($payment->id)->approval_status)->toBe(MembershipPayment::APPROVED)
        ->and($member->refresh()->lifecycle_status)->toBe('active');
});

test('approving a pending_engagement user\'s donation does NOT promote them to active', function () {
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $member = User::factory()->create(['lifecycle_status' => 'pending_engagement']);

    $donation = Donation::factory()->create([
        'user_id' => $member->id,
        'entered_by_user_id' => $submitter->id,
    ]);

    $donation->approve($approver);

    // Donation::promotesFromPendingEngagement() returns false — the lift-block
    // never fires, and recalculateLifecycle() itself no-ops for a non-active
    // member (its early-return guard), so nothing here can touch lifecycle_status.
    expect(Donation::withAnyApprovalStatus()->find($donation->id)->approval_status)->toBe(Donation::APPROVED)
        ->and($member->refresh()->lifecycle_status)->toBe('pending_engagement');
});

test('a pending_engagement user with no RC unit is not promoted by an already-expired membership payment', function () {
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    // No red_cross_unit_id: only a CURRENT (unexpired) personal payment is a
    // basis for promotion (MembershipPayment::promotesFromPendingEngagement()).
    // Previously this lifted to active and recalculateLifecycle() immediately
    // walked it back to dormant; now the lift never happens.
    $member = User::factory()->create(['lifecycle_status' => 'pending_engagement']);

    $payment = MembershipPayment::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        // Paid 18 months ago, expired 6 months ago — both beyond "current"
        // and beyond the 12-month inactivity threshold.
        'payment_date' => now()->subMonths(18)->toDateString(),
        'expiry_date' => now()->subMonths(6)->toDateString(),
    ]);

    expect($payment->promotesFromPendingEngagement())->toBeFalse();

    $payment->approve($approver);

    expect(MembershipPayment::withAnyApprovalStatus()->find($payment->id)->approval_status)->toBe(MembershipPayment::APPROVED)
        ->and($member->refresh()->lifecycle_status)->toBe('pending_engagement');
});

test('two earlier non-promoting approvals (donation, training) do not block a later membership payment from promoting the member', function () {
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $member = User::factory()->create(['lifecycle_status' => 'pending_engagement']);

    $donation = Donation::factory()->create([
        'user_id' => $member->id,
        'entered_by_user_id' => $submitter->id,
        'date_donation' => now()->subMonths(2)->toDateString(),
    ]);
    $donation->approve($approver);

    expect($member->refresh()->lifecycle_status)->toBe('pending_engagement');

    $training = Training::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'training_date' => now()->subWeek()->toDateString(),
    ]);
    $training->approve($approver);

    // Neither Donation nor Training promotes on its own (both override
    // promotesFromPendingEngagement() to return false) — the member is
    // still pending_engagement after both approvals.
    expect($member->refresh()->lifecycle_status)->toBe('pending_engagement');

    // Default factory dates (payment_date within the last 3 months, validity_years 1)
    // yield a currently-valid membership, so this approval promotes the member —
    // unaffected by the two earlier non-promoting approvals before it.
    $payment = MembershipPayment::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
    ]);
    $payment->approve($approver);

    expect($member->refresh()->lifecycle_status)->toBe('active');
});

test('a pending_engagement user with no RC unit is promoted to active and classified as a member when the membership payment is current', function () {
    // Happy-path complement to the expired-payment re-demotion test above:
    // identical setup, a current (unexpired) payment instead of an expired
    // one. Replaces the old training-based version of this test, since
    // Training no longer promotes at all.
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $member = User::factory()->create(['lifecycle_status' => 'pending_engagement']);

    // Default factory dates yield a currently-valid membership.
    $payment = MembershipPayment::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
    ]);

    $payment->approve($approver);

    $member->refresh();
    // Unlike the old training-based version of this test, a CURRENT
    // membership payment reclassifies the member from 'neither' to 'member'
    // (User::currentMembershipPayment() now finds it) — isDormantByPolicy()
    // for 'member' only checks for a current payment's existence, which is
    // satisfied, so the member stays active regardless of last_activity_at.
    expect($member->lifecyclePolicyType())->toBe('member')
        ->and($member->lifecycle_status)->toBe('active');
});

test('approving a record for an already-active member is unaffected by the new pending_engagement lift', function () {
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $member = User::factory()->create(['lifecycle_status' => 'active']);

    $activity = Activity::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'date' => now()->subWeek()->toDateString(),
    ]);

    $activity->approve($approver);

    // Member never was pending_engagement, so the new lift-block's condition is
    // false and never fires — behaviour is identical to before this change.
    expect($member->refresh()->lifecycle_status)->toBe('active');
});
