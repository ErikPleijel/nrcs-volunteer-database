<?php

/**
 * Regression guard: activities.update, donations.update, trainings.update,
 * and membership-payments.update were intentionally removed, along with
 * their controller methods. Approvable::resetApprovalOnEdit() (and
 * Training::afterDemoted()) are now unreachable dead code as a result — see
 * the deleted PART 2 sections of ActivityApprovalTest, DonationApprovalTest,
 * MembershipPaymentApprovalTest, and TrainingApprovalTest, and the deleted
 * "update()" section of OverlapProtectionTest.
 *
 * If one of these routes is ever reintroduced, this test fails — that's the
 * point: it forces a conscious decision about whether resetApprovalOnEdit()
 * (and, for Training, afterDemoted()) should be reconnected too, rather than
 * silently reviving a path nothing calls.
 */

use Illuminate\Support\Facades\Route;

test('activities.update no longer exists', function () {
    expect(Route::has('activities.update'))->toBeFalse();
});

test('donations.update no longer exists', function () {
    expect(Route::has('donations.update'))->toBeFalse();
});

test('trainings.update no longer exists', function () {
    expect(Route::has('trainings.update'))->toBeFalse();
});

test('membership-payments.update no longer exists', function () {
    expect(Route::has('membership-payments.update'))->toBeFalse();
});
