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

test('approving a pending_engagement user\'s training promotes them to active', function () {
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $member = User::factory()->create(['lifecycle_status' => 'pending_engagement']);

    $training = Training::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'training_date' => now()->subWeek()->toDateString(),
    ]);

    $training->approve($approver);

    expect(Training::withAnyApprovalStatus()->find($training->id)->approval_status)->toBe(Training::APPROVED)
        ->and($member->refresh()->lifecycle_status)->toBe('active');
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

test('a pending_engagement user with no RC unit or membership is re-demoted to dormant when the promoting record is old', function () {
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    // No red_cross_unit_id, no assigned_rcu_date, no membership payment: this
    // member classifies as policy type 'neither', governed purely by
    // last_activity_at against the default 12-month dormant_after_months.
    $member = User::factory()->create(['lifecycle_status' => 'pending_engagement']);

    $training = Training::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        // 18 months ago — beyond the default 12-month inactivity threshold.
        'training_date' => now()->subMonths(18)->toDateString(),
    ]);

    $training->approve($approver);

    // The lift-then-recompute sequence promotes to 'active' first, then
    // recalculateLifecycle() immediately walks it back to 'dormant' in the same
    // request, because the only qualifying record is too old to satisfy policy.
    $member->refresh();
    expect($member->lifecyclePolicyType())->toBe('neither')
        ->and($member->lifecycle_status)->toBe('dormant');
});

test('an earlier non-promoting donation does not block a later training from promoting the member', function () {
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

    // The training approval promotes the member normally — unaffected by the
    // earlier donation, which correctly left them pending_engagement.
    expect($member->refresh()->lifecycle_status)->toBe('active');
});

test('a pending_engagement user with no RC unit or membership is promoted to active when the promoting record is recent', function () {
    // Happy-path complement to the old-date re-demotion test above: identical
    // setup, recent date instead of old.
    $submitter = User::factory()->create();
    $approver = User::factory()->create();
    $member = User::factory()->create(['lifecycle_status' => 'pending_engagement']);

    $training = Training::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'training_date' => now()->subWeek()->toDateString(),
    ]);

    $training->approve($approver);

    $member->refresh();
    expect($member->lifecyclePolicyType())->toBe('neither')
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
