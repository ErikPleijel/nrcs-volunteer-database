<?php

/**
 * Coverage for the parts of the lifecycle system not exercised by the
 * Approval*Test files or LifecyclePromotionTest:
 *  - PART 1: the ReconcileLifecycleStatus batch command (lifecycle:reconcile)
 *  - PART 2: the 'member' policy-type dormancy branch (membership-expiry based)
 *  - PART 3: the 'unassigned' (ghost) policy type
 *  - PART 4: month-boundary precision on the inactivity threshold
 *
 * Note: User::$fillable does NOT include last_activity_at (it's only ever
 * written internally via forceFill(), e.g. by recalculateLifecycle() and
 * markActive()) — every test here that needs to control it uses
 * $user->forceFill(['last_activity_at' => ...])->save(), never
 * User::factory()->create(['last_activity_at' => ...]), which would
 * silently be dropped by mass assignment.
 *
 * Note: MembershipPayment uses Approvable, whose ApprovedScope global scope
 * filters approval_status = 'approved' on every default query — including
 * the currentMembershipPayment() relation that both isUnassignedGhost() and
 * lifecyclePolicyType()/isDormantByPolicy()'s 'member' branch rely on.
 * MembershipPayment::factory() alone leaves a payment 'pending' (the DB
 * default), which is invisible to all three of those checks regardless of
 * its dates or fee — so every test here needing a payment that actually
 * counts uses ->approved().
 */

use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\User;
use Carbon\Carbon;
use Database\Factories\MembershipFeeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

afterEach(function () {
    // PART 4 pins Carbon::setTestNow(); this is a new pattern for this suite,
    // so unconditionally reset it after every test in this file to guarantee
    // it never leaks into other tests, even if a test fails before its own
    // cleanup would run.
    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| PART 1 — ReconcileLifecycleStatus command behaviour
|--------------------------------------------------------------------------
*/

test('dry-run (no --apply) makes zero writes, not even a partial one', function () {
    // 'neither' policy type (no RC unit, no membership): policy correctly says
    // dormant (18 months of inactivity, past the default 12-month threshold).
    $user = User::factory()->create(['lifecycle_status' => 'active']);
    $user->forceFill(['last_activity_at' => now()->subMonths(18)])->save();

    $beforeUpdatedAt = $user->fresh()->updated_at;

    $this->artisan('lifecycle:reconcile')->assertExitCode(0);

    $fresh = $user->fresh();
    expect($fresh->lifecycle_status)->toBe('active')
        ->and($fresh->updated_at->eq($beforeUpdatedAt))->toBeTrue();
});

test('--apply writes active to dormant for a user whose policy says dormant, and leaves an already-correct user untouched', function () {
    $dormantCandidate = User::factory()->create(['lifecycle_status' => 'active']);
    $dormantCandidate->forceFill(['last_activity_at' => now()->subMonths(18)])->save();

    $alreadyCorrect = User::factory()->create(['lifecycle_status' => 'active']);
    $alreadyCorrect->forceFill(['last_activity_at' => now()->subDays(5)])->save();
    $alreadyCorrectUpdatedAt = $alreadyCorrect->fresh()->updated_at;

    $this->artisan('lifecycle:reconcile', ['--apply' => true])->assertExitCode(0);

    expect($dormantCandidate->fresh()->lifecycle_status)->toBe('dormant');

    // target === current for this user, so the command's own `continue` skips
    // it entirely — confirm it's truly untouched, not just "still active".
    $freshCorrect = $alreadyCorrect->fresh();
    expect($freshCorrect->lifecycle_status)->toBe('active')
        ->and($freshCorrect->updated_at->eq($alreadyCorrectUpdatedAt))->toBeTrue();
});

test('--apply writes dormant to active for a member policy-type user whose membership is now current', function () {
    $user = User::factory()->create(['lifecycle_status' => 'dormant']);
    $fee = MembershipFeeFactory::new()->create();
    MembershipPayment::factory()->approved()->create([
        'user_id' => $user->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subMonth()->toDateString(),
        'expiry_date' => now()->addMonths(6)->toDateString(),
    ]);

    $this->artisan('lifecycle:reconcile', ['--apply' => true])->assertExitCode(0);

    expect($user->fresh()->lifecycle_status)->toBe('active');
});

test('organisation-linked users are never touched, even when their policy would otherwise flag a change', function () {
    $orgUser = User::factory()->create(['lifecycle_status' => 'active']);
    $orgUser->forceFill(['last_activity_at' => now()->subMonths(18)])->save();

    $organisation = Organisation::create(['name' => 'Test Org']);
    $orgUser->organisations()->attach($organisation->id);

    $this->artisan('lifecycle:reconcile', ['--apply' => true])->assertExitCode(0);

    // whereDoesntHave('organisations') excludes this user from the scan entirely.
    expect($orgUser->fresh()->lifecycle_status)->toBe('active');
});

test('pending_engagement and archived users are never scanned by this command', function () {
    $pending = User::factory()->create(['lifecycle_status' => 'pending_engagement']);
    $pending->forceFill(['last_activity_at' => now()->subMonths(18)])->save();

    $archived = User::factory()->create(['lifecycle_status' => 'archived']);
    $archived->forceFill(['last_activity_at' => now()->subMonths(18)])->save();

    $this->artisan('lifecycle:reconcile', ['--apply' => true])->assertExitCode(0);

    // whereIn(['active', 'dormant']) excludes both, regardless of how
    // dormant-looking their activity history is.
    expect($pending->fresh()->lifecycle_status)->toBe('pending_engagement')
        ->and($archived->fresh()->lifecycle_status)->toBe('archived');
});

/*
|--------------------------------------------------------------------------
| PART 2 — 'member' policy-type dormancy branch
|--------------------------------------------------------------------------
*/

test('a member policy-type user with a current personal payment is not dormant regardless of last_activity_at', function () {
    // No RC unit, no last_activity_at at all (null) — the strongest possible
    // demonstration that the member branch never even looks at inactivity.
    $user = User::factory()->create(['lifecycle_status' => 'active']);
    $fee = MembershipFeeFactory::new()->create();
    MembershipPayment::factory()->approved()->create([
        'user_id' => $user->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subMonth()->toDateString(),
        'expiry_date' => now()->addYear()->toDateString(),
    ]);

    $fresh = $user->fresh();
    expect($fresh->last_activity_at)->toBeNull()
        ->and($fresh->lifecyclePolicyType())->toBe('member')
        ->and($fresh->isDormantByPolicy())->toBeFalse();
});

test('a member policy-type user becomes dormant-by-policy once their only payment expires', function () {
    // IMPORTANT finding: this does NOT happen via a distinct "member, but
    // expired" branch. currentMembershipPayment() itself filters
    // expiry_date >= today(), so once the payment expires,
    // lifecyclePolicyType() no longer returns 'member' at all — it falls
    // through to 'neither' (no RC unit, no assigned_rcu_date) — and dormancy
    // is then decided by the *inactivity* branch, not membership. So
    // last_activity_at must ALSO be old here for isDormantByPolicy() to be
    // true; a recently-active user whose membership merely expired would NOT
    // be flagged dormant until their own activity ages past the threshold too.
    $user = User::factory()->create(['lifecycle_status' => 'active']);
    $fee = MembershipFeeFactory::new()->create();
    MembershipPayment::factory()->approved()->create([
        'user_id' => $user->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subYear()->subMonth()->toDateString(),
        'expiry_date' => now()->subDay()->toDateString(),
    ]);
    $user->forceFill(['last_activity_at' => now()->subYear()->subMonth()])->save();

    $fresh = $user->fresh();
    expect($fresh->lifecyclePolicyType())->toBe('neither')
        ->and($fresh->isDormantByPolicy())->toBeTrue();
});

test('an organisational payment does not count toward member classification — falls through to neither', function () {
    $user = User::factory()->create(['lifecycle_status' => 'active']);
    $organisation = Organisation::create(['name' => 'Payer Org']);
    $fee = MembershipFeeFactory::new()->create();
    MembershipPayment::factory()->approved()->create([
        'user_id' => $user->id,
        'organisation_id' => $organisation->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subMonth()->toDateString(),
        'expiry_date' => now()->addYear()->toDateString(),
    ]);

    expect($user->fresh()->lifecyclePolicyType())->toBe('neither');

    // Governed by inactivity instead of the (organisational, non-qualifying)
    // "valid" payment: old activity flips them dormant despite the payment
    // itself still being unexpired.
    $user->forceFill(['last_activity_at' => now()->subMonths(18)])->save();
    expect($user->fresh()->isDormantByPolicy())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| PART 3 — 'unassigned' (ghost) policy type
|--------------------------------------------------------------------------
*/

test('a genuine ghost (left unit, only a volunteer-fee payment) classifies as unassigned', function () {
    $user = User::factory()->create([
        'assigned_rcu_date' => now()->subYear()->toDateString(),
        'red_cross_unit_id' => null,
    ]);
    // Explicitly override is_volunteer_fee — MembershipFeeFactory defaults to
    // false, which would disqualify the ghost condition (a non-volunteer
    // personal payment counts as genuine membership evidence).
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => true]);
    MembershipPayment::factory()->approved()->create([
        'user_id' => $user->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subMonth()->toDateString(),
        'expiry_date' => now()->addYear()->toDateString(),
    ]);

    expect($user->fresh()->lifecyclePolicyType())->toBe('unassigned');
});

test('a user who never had an RC unit (no assigned_rcu_date) does not classify as unassigned', function () {
    $user = User::factory()->create([
        'assigned_rcu_date' => null,
        'red_cross_unit_id' => null,
    ]);

    // isUnassignedGhost()'s explicit is_null(assigned_rcu_date) guard means a
    // person who was simply never assigned a unit falls to 'neither', not
    // 'unassigned' — even though they also have no membership.
    expect($user->fresh()->lifecyclePolicyType())->toBe('neither');
});

test('a former-unit user with a genuine non-volunteer-fee personal payment classifies as member, not unassigned', function () {
    $user = User::factory()->create([
        'assigned_rcu_date' => now()->subYear()->toDateString(),
        'red_cross_unit_id' => null,
    ]);
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);
    MembershipPayment::factory()->approved()->create([
        'user_id' => $user->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subMonth()->toDateString(),
        'expiry_date' => now()->addYear()->toDateString(),
    ]);

    expect($user->fresh()->lifecyclePolicyType())->toBe('member');
});

/*
|--------------------------------------------------------------------------
| PART 4 — month-boundary precision
|--------------------------------------------------------------------------
*/

test('the inactivity threshold is a precise boundary, not a loose approximation', function () {
    $pinnedNow = Carbon::create(2026, 6, 15, 12, 0, 0);
    Carbon::setTestNow($pinnedNow);

    // 'neither' policy type: no RC unit, no membership.
    $user = User::factory()->create(['lifecycle_status' => 'active']);
    $threshold = $pinnedNow->copy()->subMonths(12);

    // 12 months minus one day before "now" — one day more recent than the
    // threshold — not yet dormant.
    $user->forceFill(['last_activity_at' => $threshold->copy()->addDay()])->save();
    expect($user->fresh()->isDormantByPolicy())->toBeFalse();

    // 12 months and one day before "now" — one day past the threshold — dormant.
    $user->forceFill(['last_activity_at' => $threshold->copy()->subDay()])->save();
    expect($user->fresh()->isDormantByPolicy())->toBeTrue();
});
