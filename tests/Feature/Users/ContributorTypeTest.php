<?php

/**
 * Volunteer / Volunteer & Member / Member classification (User::contributor_type
 * and its scopes), the shared pending_engagement -> active rule
 * (User::promoteFromPendingIfQualified()), MembershipPayment's expiry-aware
 * promotesFromPendingEngagement(), and the lifecycle:promote-qualified-pending
 * backfill.
 */

use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\RedCrossUnit;
use App\Models\User;
use Database\Factories\MembershipFeeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/** An approved personal payment for $user; pass 'expired' => true for a lapsed one. */
function contributorFee(User $user, array $overrides = []): MembershipPayment
{
    $expired = $overrides['expired'] ?? false;
    unset($overrides['expired']);

    return MembershipPayment::factory()->approved()->create(array_merge([
        'user_id' => $user->id,
        'membership_fee_id' => MembershipFeeFactory::new()->create(['is_volunteer_fee' => false])->id,
        'payment_date' => $expired ? now()->subMonths(18)->toDateString() : now()->subMonth()->toDateString(),
        'expiry_date' => $expired ? now()->subMonths(6)->toDateString() : now()->addMonths(11)->toDateString(),
    ], $overrides));
}

/** One user per classification case, keyed by case name => expected contributor_type. */
function contributorMatrix(): array
{
    $unit = RedCrossUnit::create(['name' => 'Unit A']);
    $org = Organisation::create(['name' => 'Test Org']);

    $vm = User::factory()->create(['red_cross_unit_id' => $unit->id]);
    contributorFee($vm);

    $v = User::factory()->create(['red_cross_unit_id' => $unit->id]);

    $m = User::factory()->create();
    contributorFee($m);

    $neither = User::factory()->create();

    $rcuExpired = User::factory()->create(['red_cross_unit_id' => $unit->id]);
    contributorFee($rcuExpired, ['expired' => true]);

    $rcuOrgFee = User::factory()->create(['red_cross_unit_id' => $unit->id]);
    contributorFee($rcuOrgFee, ['organisation_id' => $org->id]);

    $rcuRcuFee = User::factory()->create(['red_cross_unit_id' => $unit->id]);
    contributorFee($rcuRcuFee, ['red_cross_unit_id' => $unit->id]);

    // Volunteer-type fee, no RCU: counts as M (fee type doesn't matter).
    $volFeeNoRcu = User::factory()->create();
    contributorFee($volFeeNoRcu, [
        'membership_fee_id' => MembershipFeeFactory::new()->create(['is_volunteer_fee' => true])->id,
    ]);

    return [
        'rcu + current fee' => [$vm, User::CONTRIBUTOR_VOLUNTEER_MEMBER],
        'rcu only' => [$v, User::CONTRIBUTOR_VOLUNTEER],
        'current fee only' => [$m, User::CONTRIBUTOR_MEMBER],
        'neither' => [$neither, null],
        'rcu + expired fee' => [$rcuExpired, User::CONTRIBUTOR_VOLUNTEER],
        'rcu + organisational fee' => [$rcuOrgFee, User::CONTRIBUTOR_VOLUNTEER],
        'rcu + rcu-attributed fee' => [$rcuRcuFee, User::CONTRIBUTOR_VOLUNTEER],
        'volunteer-type fee, no rcu' => [$volFeeNoRcu, User::CONTRIBUTOR_MEMBER],
    ];
}

/*
|--------------------------------------------------------------------------
| Classification
|--------------------------------------------------------------------------
*/

test('contributor_type classifies every case correctly', function () {
    foreach (contributorMatrix() as $case => [$user, $expected]) {
        expect(User::find($user->id)->contributor_type)->toBe($expected, $case);
    }
});

test('scopeContributorType matches the accessor for a mixed set of users', function () {
    $matrix = contributorMatrix();

    foreach ([User::CONTRIBUTOR_VOLUNTEER, User::CONTRIBUTOR_VOLUNTEER_MEMBER, User::CONTRIBUTOR_MEMBER] as $type) {
        $expectedIds = collect($matrix)
            ->filter(fn ($row) => $row[1] === $type)
            ->map(fn ($row) => $row[0]->id)
            ->sort()->values()->all();

        $actualIds = User::query()
            ->whereIn('id', collect($matrix)->map(fn ($row) => $row[0]->id))
            ->contributorType($type)
            ->pluck('id')->sort()->values()->all();

        expect($actualIds)->toBe($expectedIds, $type);
    }
});

test('scopeContributorType rejects an unknown type', function () {
    User::query()->contributorType('nonsense');
})->throws(InvalidArgumentException::class);

test('withContributorFacts matches the lazy fallback without per-user queries', function () {
    $matrix = contributorMatrix();
    $ids = collect($matrix)->map(fn ($row) => $row[0]->id);

    $lazy = User::whereIn('id', $ids)->orderBy('id')->get()
        ->mapWithKeys(fn ($u) => [$u->id => [$u->hasCurrentPersonalFee(), $u->contributor_type]]);

    $users = User::whereIn('id', $ids)->orderBy('id')->withContributorFacts()->get();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $eager = $users->mapWithKeys(fn ($u) => [$u->id => [$u->hasCurrentPersonalFee(), $u->contributor_type]]);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($eager->all())->toBe($lazy->all())
        ->and($queries)->toBe(0);
});

test('isMember() ignores organisational and RCU-attributed payments', function () {
    foreach (contributorMatrix() as $case => [$user, $expected]) {
        $isMemberExpected = in_array($expected, [User::CONTRIBUTOR_MEMBER, User::CONTRIBUTOR_VOLUNTEER_MEMBER], true);
        expect(User::find($user->id)->isMember())->toBe($isMemberExpected, $case);
    }
});

/*
|--------------------------------------------------------------------------
| promoteFromPendingIfQualified()
|--------------------------------------------------------------------------
*/

test('promoteFromPendingIfQualified promotes a pending user with an RCU or a current fee, and nobody else', function () {
    $unit = RedCrossUnit::create(['name' => 'Unit A']);

    $withRcu = User::factory()->create(['lifecycle_status' => 'pending_engagement', 'red_cross_unit_id' => $unit->id]);
    $withFee = User::factory()->create(['lifecycle_status' => 'pending_engagement']);
    contributorFee($withFee);
    $expiredFee = User::factory()->create(['lifecycle_status' => 'pending_engagement']);
    contributorFee($expiredFee, ['expired' => true]);
    $neither = User::factory()->create(['lifecycle_status' => 'pending_engagement']);
    $dormantWithRcu = User::factory()->create(['lifecycle_status' => 'dormant', 'red_cross_unit_id' => $unit->id]);

    expect($withRcu->promoteFromPendingIfQualified())->toBeTrue()
        ->and($withFee->promoteFromPendingIfQualified())->toBeTrue()
        ->and($expiredFee->promoteFromPendingIfQualified())->toBeFalse()
        ->and($neither->promoteFromPendingIfQualified())->toBeFalse()
        ->and($dormantWithRcu->promoteFromPendingIfQualified())->toBeFalse()
        ->and($withRcu->fresh()->lifecycle_status)->toBe('active')
        ->and($withFee->fresh()->lifecycle_status)->toBe('active')
        ->and($expiredFee->fresh()->lifecycle_status)->toBe('pending_engagement')
        ->and($neither->fresh()->lifecycle_status)->toBe('pending_engagement')
        ->and($dormantWithRcu->fresh()->lifecycle_status)->toBe('dormant');
});

test('promoteFromPendingIfQualified only bumps last_activity_at when asked to', function () {
    $unit = RedCrossUnit::create(['name' => 'Unit A']);
    $quiet = User::factory()->create(['lifecycle_status' => 'pending_engagement', 'red_cross_unit_id' => $unit->id, 'last_activity_at' => null]);
    $touched = User::factory()->create(['lifecycle_status' => 'pending_engagement', 'red_cross_unit_id' => $unit->id, 'last_activity_at' => null]);

    $quiet->promoteFromPendingIfQualified();
    $touched->promoteFromPendingIfQualified(touchActivity: true);

    expect($quiet->fresh()->last_activity_at)->toBeNull()
        ->and($touched->fresh()->last_activity_at)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Payment approval
|--------------------------------------------------------------------------
*/

// The back-dated, already-expired case is covered in
// tests/Feature/Approval/LifecyclePromotionTest.php.
test('a personal payment expiring today still promotes', function () {
    $payment = MembershipPayment::factory()->make(['expiry_date' => today()->toDateString()]);

    expect($payment->promotesFromPendingEngagement())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| lifecycle:promote-qualified-pending
|--------------------------------------------------------------------------
*/

test('the backfill dry-run reports qualifying pending users without promoting them', function () {
    $unit = RedCrossUnit::create(['name' => 'Unit A']);
    $withRcu = User::factory()->create(['lifecycle_status' => 'pending_engagement', 'red_cross_unit_id' => $unit->id]);
    $withFee = User::factory()->create(['lifecycle_status' => 'pending_engagement']);
    contributorFee($withFee);
    $expiredFee = User::factory()->create(['lifecycle_status' => 'pending_engagement']);
    contributorFee($expiredFee, ['expired' => true]);

    $this->artisan('lifecycle:promote-qualified-pending')
        ->expectsOutputToContain('Would promote: 2')
        ->assertSuccessful();

    expect($withRcu->fresh()->lifecycle_status)->toBe('pending_engagement')
        ->and($withFee->fresh()->lifecycle_status)->toBe('pending_engagement')
        ->and($expiredFee->fresh()->lifecycle_status)->toBe('pending_engagement');
});

test('the backfill --apply promotes qualifying users and is idempotent', function () {
    $unit = RedCrossUnit::create(['name' => 'Unit A']);
    $withRcu = User::factory()->create(['lifecycle_status' => 'pending_engagement', 'red_cross_unit_id' => $unit->id]);
    $withFee = User::factory()->create(['lifecycle_status' => 'pending_engagement']);
    contributorFee($withFee);
    $neither = User::factory()->create(['lifecycle_status' => 'pending_engagement']);

    $this->artisan('lifecycle:promote-qualified-pending', ['--apply' => true])
        ->expectsOutputToContain('Promoted: 2')
        ->assertSuccessful();

    expect($withRcu->fresh()->lifecycle_status)->toBe('active')
        ->and($withFee->fresh()->lifecycle_status)->toBe('active')
        ->and($neither->fresh()->lifecycle_status)->toBe('pending_engagement');

    $this->artisan('lifecycle:promote-qualified-pending', ['--apply' => true])
        ->expectsOutputToContain('Promoted: 0')
        ->assertSuccessful();
});
