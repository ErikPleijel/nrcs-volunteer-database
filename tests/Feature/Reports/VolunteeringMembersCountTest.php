<?php

/**
 * The "including N volunteering members" line on the welcome page and
 * dashboard Volunteer cards (the Volunteer & Member subset, via
 * User::contributorType()), and the "Supporting Member" label for a pure
 * member on the membership status badge.
 */

use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\RedCrossUnit;
use App\Models\User;
use App\View\Components\UserMembershipStatusBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->unit = RedCrossUnit::create(['name' => 'Unit Count', 'is_active' => true]);
});

/** Approved personal fee for $user; 'expired' => true for a lapsed one. */
function countFee(User $user, bool $expired = false): void
{
    MembershipPayment::factory()->approved()->create([
        'user_id' => $user->id,
        'membership_fee_id' => MembershipFee::factory()->create(['is_volunteer_fee' => true])->id,
        'payment_date' => $expired ? now()->subMonths(18)->toDateString() : now()->subMonth()->toDateString(),
        'expiry_date' => $expired ? now()->subMonths(6)->toDateString() : now()->addMonths(11)->toDateString(),
    ]);
}

/** 2 Volunteer & Members among 4 unit volunteers, plus people who must not count. */
function seedVolunteeringMembers(RedCrossUnit $unit): void
{
    $inUnit = fn (array $attrs = []) => User::factory()->create(array_merge(['red_cross_unit_id' => $unit->id], $attrs));

    countFee($inUnit());
    countFee($inUnit(['lifecycle_status' => 'dormant']));
    $inUnit();                                          // Volunteer, never paid
    countFee($inUnit(), expired: true);                 // Volunteer, lapsed fee
    countFee($inUnit(['lifecycle_status' => 'archived'])); // archived: not counted
    countFee(User::factory()->create());                // Supporting Member (no unit): not counted
}

test('the welcome Volunteer card shows the volunteering-members count, cached', function () {
    seedVolunteeringMembers($this->unit);

    $this->get(route('welcome'))
        ->assertOk()
        ->assertSeeInOrder(['Volunteers', 'Active volunteers', 'including 2 volunteering members']);

    expect(Cache::get('welcome:volunteering-members-count'))->toBe(2);

    // Served from the cache until it expires.
    countFee(User::factory()->create(['red_cross_unit_id' => $this->unit->id]));
    $this->get(route('welcome'))->assertSee('including 2 volunteering members');
});

test('the dashboard Volunteer card shows the volunteering-members count under the main number', function () {
    Permission::findOrCreate('view_reports', 'web');
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['view_reports']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin = User::factory()->create();
    $admin->assignRole('national_db_administrator');

    seedVolunteeringMembers($this->unit);

    $response = $this->actingAs($admin)->get(route('reports.dashboard'))->assertOk();

    // 4 counted volunteers (active + dormant, in an active unit), 2 of them also members.
    expect($response->viewData('dashboardData'))->toMatchArray([
        'volunteersCount' => 4,
        'volunteeringMembersCount' => 2,
    ]);
    $response->assertSeeInOrder(['Volunteers', '4', 'including 2 volunteering members']);
});

test('a pure member\'s membership badge reads Supporting Member', function () {
    $user = User::factory()->create();
    countFee($user);

    $badge = new UserMembershipStatusBadge($user->fresh());

    expect($badge->type)->toBe('active')
        ->and($badge->line1)->toBe('Supporting Member')
        ->and($badge->styles)->toBe('bg-blue-100 text-blue-800');
});
