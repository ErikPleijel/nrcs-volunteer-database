<?php

/**
 * Anonymous volunteer statistics on the public certificate verification
 * page: figures per unit/branch (active + dormant users only), the
 * 10-member privacy threshold, the RCU → branch fallback, no block on
 * failure pages, and the 1-hour cache.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\RedCrossUnit;
use App\Models\User;
use App\Services\VerificationStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $this->branch = Branch::create(['name' => 'Abia', 'code' => 'ABI']);
    $this->division = Division::create(['name' => 'Aba North', 'branch_id' => $this->branch->id]);
    $this->unit = RedCrossUnit::create(['name' => 'Unit Stats', 'division_id' => $this->division->id, 'is_active' => true]);
    $this->rcuFee = MembershipFee::factory()->forRedCrossUnits()->create(['amount' => 20000]);
    $this->year = now()->year;
});

/** $count volunteers in the given unit (or none) of test()->branch. */
function statsVolunteers(int $count, ?RedCrossUnit $unit, array $attrs = []): void
{
    User::factory()->count($count)->create(array_merge([
        'branch_id' => test()->branch->id,
        'division_id' => test()->division->id,
        'red_cross_unit_id' => $unit?->id,
        'lifecycle_status' => 'active',
        'gender' => 'female',
        'birth_year' => test()->year - 30,
        'last_first_aid_at' => null,
    ], $attrs));
}

/**
 * 12 counted volunteers in $unit: 7 men / 5 women; valid ages 20..38 (avg
 * 29.0) plus two implausible birth years; 4 first-aid trained; mixed
 * active/dormant. Plus people who must NOT be counted.
 */
function seedKnownUnit(RedCrossUnit $unit): void
{
    $year = test()->year;
    $ages = [20, 22, 24, 26, 28, 30, 32, 34, 36, 38];

    foreach ($ages as $i => $age) {
        statsVolunteers(1, $unit, [
            'gender' => $i < 6 ? 'male' : 'female',
            'birth_year' => $year - $age,
            'lifecycle_status' => $i % 2 ? 'dormant' : 'active',
            'last_first_aid_at' => $i < 4 ? now()->subYears($i + 1)->toDateString() : null,
        ]);
    }

    // Implausible birth years: counted, but not in the average age.
    statsVolunteers(1, $unit, ['gender' => 'male', 'birth_year' => $year - 2]);
    statsVolunteers(1, $unit, ['gender' => 'female', 'birth_year' => 1900]);

    // Not counted: pending engagement, archived, super admin.
    statsVolunteers(3, $unit, ['lifecycle_status' => 'pending_engagement', 'gender' => 'male']);
    statsVolunteers(2, $unit, ['lifecycle_status' => 'archived', 'gender' => 'male']);
    statsVolunteers(1, $unit, ['is_super_admin' => true, 'gender' => 'male']);
}

function rcuCertificateUrl(RedCrossUnit $unit): string
{
    $payment = MembershipPayment::factory()->approved()->create([
        'user_id' => User::factory()->create(['lifecycle_status' => 'archived'])->id,
        'red_cross_unit_id' => $unit->id,
        'membership_fee_id' => test()->rcuFee->id,
        'payment_date' => now()->subMonth()->toDateString(),
        'expiry_date' => now()->addMonths(11)->toDateString(),
    ]);

    return URL::signedRoute('certificates.verify', [
        'rcu' => $unit->fresh()->id_check_token,
        'type' => 'rcu_membership',
        'payment' => $payment->id,
    ]);
}

/*
|--------------------------------------------------------------------------
| Service figures
|--------------------------------------------------------------------------
*/

test('unit figures count active + dormant volunteers and exclude implausible ages from the average', function () {
    seedKnownUnit($this->unit);

    $stats = app(VerificationStatsService::class)->forUnit($this->unit);

    expect($stats)->toMatchArray([
        'scope' => 'unit',
        'scope_label' => 'Unit Stats',
        'suppressed' => false,
        'total' => 12,
        'male' => 7,
        'female' => 5,
        'gender_unknown' => 0,
        'avg_age' => 29.0,
        'age_unknown' => 0,
        'first_aid' => 4,
    ]);
});

test('branch figures include branch volunteers outside the unit and still exclude other lifecycle states', function () {
    seedKnownUnit($this->unit);
    statsVolunteers(3, null, ['gender' => 'female', 'birth_year' => $this->year - 29, 'last_first_aid_at' => now()->toDateString()]);

    // Another branch's volunteers don't count.
    $other = Branch::create(['name' => 'Bauchi', 'code' => 'BAU']);
    User::factory()->count(4)->create(['branch_id' => $other->id, 'lifecycle_status' => 'active', 'gender' => 'male', 'birth_year' => $this->year - 50]);

    $stats = app(VerificationStatsService::class)->forBranch($this->branch);

    expect($stats)->toMatchArray([
        'scope' => 'branch',
        'scope_label' => 'Abia',
        'suppressed' => false,
        'total' => 15,
        'male' => 7,
        'female' => 8,
        'avg_age' => 29.0, // (290 + 3 × 29) / 13
        'first_aid' => 7,
    ]);
});

test('a group of 9 is suppressed without any figures; a group of 10 is shown', function () {
    statsVolunteers(9, $this->unit);

    $nine = app(VerificationStatsService::class)->forUnit($this->unit);
    expect($nine)->toBe(['scope' => 'unit', 'scope_label' => 'Unit Stats', 'suppressed' => true]);

    statsVolunteers(1, $this->unit);
    cache()->flush();

    $ten = app(VerificationStatsService::class)->forUnit($this->unit);
    expect($ten['suppressed'])->toBeFalse()
        ->and($ten['total'])->toBe(10);
});

test('the aggregate query runs once per scope within the cache hour', function () {
    statsVolunteers(10, $this->unit);
    $service = app(VerificationStatsService::class);

    DB::enableQueryLog();
    $first = $service->forUnit($this->unit);
    $second = $service->forUnit($this->unit);
    $service->forBranch($this->branch);
    $service->forBranch($this->branch);
    DB::disableQueryLog();

    $aggregates = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'COUNT(*) AS total'));

    expect($aggregates)->toHaveCount(2) // one unit, one branch
        ->and($second)->toBe($first);
});

/*
|--------------------------------------------------------------------------
| Verification page
|--------------------------------------------------------------------------
*/

test('an RCU certificate for a unit of 10+ shows the unit\'s own figures', function () {
    seedKnownUnit($this->unit);

    $response = $this->get(rcuCertificateUrl($this->unit))
        ->assertOk()
        ->assertSee('Certificate Verified')
        ->assertSeeInOrder(['About this Red Cross Unit', '12', 'Volunteers', '7 / 5', 'Men / Women', '29.0', 'Average age', '4', 'Have had first aid training']);

    expect($response->viewData('stats')['scope'])->toBe('unit');
});

test('an RCU certificate for a small unit falls back to its branch, which is not itself suppressed', function () {
    statsVolunteers(3, $this->unit);
    statsVolunteers(12, null, ['gender' => 'male']);

    $response = $this->get(rcuCertificateUrl($this->unit))
        ->assertOk()
        ->assertSee('About Abia branch')
        ->assertDontSee('About this Red Cross Unit')
        ->assertDontSee('Statistics are shown for groups');

    expect($response->viewData('stats'))->toMatchArray(['scope' => 'branch', 'suppressed' => false, 'total' => 15]);
});

test('with no branch to fall back to, a small unit shows the threshold note and no figures', function () {
    $unit = RedCrossUnit::create(['name' => 'Unit Orphan', 'is_active' => true]);
    User::factory()->count(3)->create(['red_cross_unit_id' => $unit->id, 'lifecycle_status' => 'active', 'gender' => 'male', 'birth_year' => $this->year - 40]);

    $this->get(rcuCertificateUrl($unit))
        ->assertOk()
        ->assertSee('About this Red Cross Unit')
        ->assertSee('Statistics are shown for groups of 10 or more members.')
        ->assertDontSee('Average age');
});

test('a personal certificate shows the holder\'s branch figures', function () {
    statsVolunteers(10, null, ['gender' => 'male', 'birth_year' => $this->year - 40]);
    $holder = User::factory()->create(['branch_id' => $this->branch->id, 'lifecycle_status' => 'active', 'gender' => 'female', 'birth_year' => $this->year - 20]);

    $this->get(URL::signedRoute('certificates.verify', ['u' => $holder->id_check_token, 'type' => 'membership']))
        ->assertOk()
        ->assertSee('Certificate Verified')
        ->assertSeeInOrder(['About Abia branch', '11', 'Volunteers', '10 / 1', '38.2', 'Average age']);
});

test('neither failure page shows a stats block', function () {
    seedKnownUnit($this->unit);

    $pages = [
        URL::signedRoute('certificates.verify', ['rcu' => str_repeat('x', 32), 'type' => 'rcu_membership', 'payment' => 1]),
        URL::signedRoute('certificates.verify', ['u' => str_repeat('y', 32), 'type' => 'membership']),
        rcuCertificateUrl($this->unit).'tampered',
    ];

    foreach ($pages as $url) {
        $this->get($url)
            ->assertSee('Verification Failed')
            ->assertDontSee('class="stats-section"', false)
            ->assertDontSee('Volunteers');
    }
});

/*
|--------------------------------------------------------------------------
| Volunteer / Volunteer & Member / Member split
|--------------------------------------------------------------------------
*/

/** Approved personal (or, via overrides, attributed) payment; 'expired' => true for a lapsed one. */
function statsFee(User $user, array $overrides = []): void
{
    $expired = $overrides['expired'] ?? false;
    unset($overrides['expired']);

    MembershipPayment::factory()->approved()->create(array_merge([
        'user_id' => $user->id,
        'membership_fee_id' => MembershipFee::factory()->create(['is_volunteer_fee' => true])->id,
        'payment_date' => $expired ? now()->subMonths(18)->toDateString() : now()->subMonth()->toDateString(),
        'expiry_date' => $expired ? now()->subMonths(6)->toDateString() : now()->addMonths(11)->toDateString(),
    ], $overrides));
}

/** Mark the first $n counted users matching $where as holders of a fee described by $overrides. */
function giveStatsFees(int $n, callable $where, array $overrides = []): void
{
    User::query()->where('lifecycle_status', 'active')->where($where)->orderBy('id')->limit($n)->get()
        ->each(fn (User $u) => statsFee($u, $overrides));
}

test('unit figures split into Volunteer and Volunteer & Member, summing to the total', function () {
    seedKnownUnit($this->unit);
    $inUnit = fn ($q) => $q->where('red_cross_unit_id', $this->unit->id)->where('is_super_admin', false);
    giveStatsFees(3, $inUnit);

    // Neither makes a Volunteer & Member: a lapsed fee, or a fee paid for an organisation.
    $lapsed = User::where('red_cross_unit_id', $this->unit->id)->where('lifecycle_status', 'dormant')->first();
    statsFee($lapsed, ['expired' => true]);
    $orgPayer = User::where('red_cross_unit_id', $this->unit->id)->where('lifecycle_status', 'dormant')->skip(1)->first();
    statsFee($orgPayer, ['organisation_id' => \App\Models\Organisation::create(['name' => 'Org'])->id]);

    // Not counted at all: a pending user with a fee.
    statsFee(User::where('red_cross_unit_id', $this->unit->id)->where('lifecycle_status', 'pending_engagement')->first());

    $stats = app(VerificationStatsService::class)->forUnit($this->unit);

    expect($stats)->toMatchArray([
        'total' => 12,
        'volunteer_only' => 9,
        'volunteer_member' => 3,
        'member_only' => 0,
        'unclassified' => 0,
    ])->and($stats['volunteer_only'] + $stats['volunteer_member'] + $stats['member_only'] + $stats['unclassified'])->toBe($stats['total']);
});

test('branch figures add Members and an unclassified bucket, summing to the total', function () {
    seedKnownUnit($this->unit);
    giveStatsFees(2, fn ($q) => $q->where('red_cross_unit_id', $this->unit->id));

    statsVolunteers(4, null);
    $noUnit = fn ($q) => $q->whereNull('red_cross_unit_id')->where('branch_id', $this->branch->id);
    giveStatsFees(3, $noUnit);                        // 3 Members
    $lapsedMember = User::whereNull('red_cross_unit_id')->where('branch_id', $this->branch->id)
        ->whereDoesntHave('membershipPayments')->first();
    statsFee($lapsedMember, ['expired' => true]);     // no RCU, lapsed fee: unclassified

    $stats = app(VerificationStatsService::class)->forBranch($this->branch);

    expect($stats)->toMatchArray([
        'total' => 16,
        'volunteer_only' => 10,
        'volunteer_member' => 2,
        'member_only' => 3,
        'unclassified' => 1,
    ])->and($stats['volunteer_only'] + $stats['volunteer_member'] + $stats['member_only'] + $stats['unclassified'])->toBe($stats['total']);
});

test('a suppressed group exposes none of the split, even when its members pay fees', function () {
    statsVolunteers(9, $this->unit);
    giveStatsFees(4, fn ($q) => $q->where('red_cross_unit_id', $this->unit->id));

    expect(app(VerificationStatsService::class)->forUnit($this->unit))
        ->toBe(['scope' => 'unit', 'scope_label' => 'Unit Stats', 'suppressed' => true]);
});

test('the verification page shows the split under the four figures, and never for a suppressed group', function () {
    seedKnownUnit($this->unit);
    giveStatsFees(3, fn ($q) => $q->where('red_cross_unit_id', $this->unit->id));

    $this->get(rcuCertificateUrl($this->unit))
        ->assertOk()
        ->assertSeeInOrder(['Have had first aid training', 'Volunteers: 9', 'Volunteers &amp; Members: 3'], false)
        ->assertDontSee('Members: 0', false); // unit scope: no Members / Other

    $small = RedCrossUnit::create(['name' => 'Unit Orphan', 'is_active' => true]);
    User::factory()->count(3)->create(['red_cross_unit_id' => $small->id, 'lifecycle_status' => 'active']);

    $this->get(rcuCertificateUrl($small))
        ->assertOk()
        ->assertSee('Statistics are shown for groups of 10 or more members.')
        ->assertDontSee('class="stats-breakdown"', false);
});
