<?php

/**
 * Feature tests for Approvable::markApprovedViaGateway() — the auto-approval
 * path used by the Paystack webhook when a payment is verified, in place of
 * the human-decider approve(). Mirrors tests/Feature/Approval/
 * LifecyclePromotionTest.php's model-level style: these call the trait
 * method directly, not through a controller/route.
 */

use App\Models\Donation;
use App\Models\MembershipPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('markApprovedViaGateway approves a pending record without any decider', function () {
    $member = User::factory()->create();

    $donation = Donation::factory()->create([
        'user_id' => $member->id,
        'entered_by_user_id' => $member->id,
    ]);
    expect($donation->approval_status)->toBe(Donation::PENDING);

    $result = $donation->markApprovedViaGateway();

    expect($result)->toBeTrue();

    $fresh = Donation::withAnyApprovalStatus()->find($donation->id);
    expect($fresh->approval_status)->toBe(Donation::APPROVED)
        ->and($fresh->decided_by_user_id)->toBeNull()
        ->and($fresh->decided_at)->not->toBeNull();
});

test('markApprovedViaGateway is a no-op and returns false for an already-approved record', function () {
    $member = User::factory()->create();

    $payment = MembershipPayment::factory()->approved()->create([
        'user_id' => $member->id,
    ]);
    $originalDecidedBy = $payment->decided_by_user_id;

    $result = $payment->markApprovedViaGateway();

    expect($result)->toBeFalse();

    $fresh = MembershipPayment::withAnyApprovalStatus()->find($payment->id);
    expect($fresh->approval_status)->toBe(MembershipPayment::APPROVED)
        ->and($fresh->decided_by_user_id)->toBe($originalDecidedBy);
});

test('markApprovedViaGateway is a no-op and returns false for a rejected record', function () {
    $member = User::factory()->create();
    $approver = User::factory()->create();

    $donation = Donation::factory()->create([
        'user_id' => $member->id,
        'entered_by_user_id' => $member->id,
    ]);
    $donation->reject($approver, 'Not enough documentation');

    $result = $donation->markApprovedViaGateway();

    expect($result)->toBeFalse();
    expect(Donation::withAnyApprovalStatus()->find($donation->id)->approval_status)->toBe(Donation::REJECTED);
});

test('markApprovedViaGateway silently lifts a dormant member to active', function () {
    $member = User::factory()->create(['lifecycle_status' => 'dormant']);

    // Current (unexpired) payment so the lift isn't immediately walked back by
    // recalculateLifecycle()'s isDormantByPolicy() check — mirrors the same
    // reasoning used throughout LifecyclePromotionTest.
    $payment = MembershipPayment::factory()->create(['user_id' => $member->id]);

    $payment->markApprovedViaGateway();

    expect($member->refresh()->lifecycle_status)->toBe('active');
});

test('markApprovedViaGateway promotes a pending_engagement member for a module that opts in (MembershipPayment)', function () {
    $member = User::factory()->create(['lifecycle_status' => 'pending_engagement']);

    $payment = MembershipPayment::factory()->create(['user_id' => $member->id]);

    $payment->markApprovedViaGateway();

    expect($member->refresh()->lifecycle_status)->toBe('active');
});

test('markApprovedViaGateway does NOT promote a pending_engagement member for a module that opts out (Donation)', function () {
    $member = User::factory()->create(['lifecycle_status' => 'pending_engagement']);

    $donation = Donation::factory()->create([
        'user_id' => $member->id,
        'entered_by_user_id' => $member->id,
    ]);

    $donation->markApprovedViaGateway();

    // Donation::promotesFromPendingEngagement() returns false, and
    // recalculateLifecycle() itself no-ops for a non-active member.
    expect($member->refresh()->lifecycle_status)->toBe('pending_engagement');
});
