<?php

/**
 * RCU report demographics tab: Volunteer vs Volunteer & Member split
 * (User::contributor_type). Every counted user belongs to a unit, so
 * Member never applies and the two columns sum to Total Volunteers.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    Permission::findOrCreate('view_reports', 'web');
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['view_reports']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->viewer = User::factory()->create();
    $this->viewer->assignRole('national_db_administrator');

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);
    $this->unit = RedCrossUnit::create(['name' => 'Unit Report', 'division_id' => $this->division->id, 'is_active' => true]);
});

function reportFee(User $user, array $overrides = []): void
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

test('the demographics tab splits each unit into Volunteers only and Volunteers & Members', function () {
    $member = fn (array $attrs = []) => User::factory()->create(array_merge([
        'red_cross_unit_id' => $this->unit->id,
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
        'lifecycle_status' => 'active',
    ], $attrs));

    $member();
    $member(['lifecycle_status' => 'dormant']);
    reportFee($member());                                                   // V+M
    reportFee($member(['lifecycle_status' => 'dormant']));                  // V+M
    reportFee($member(), ['expired' => true]);                              // lapsed: V
    reportFee($member(), ['organisation_id' => Organisation::create(['name' => 'Org'])->id]); // not personal: V
    reportFee($member(['lifecycle_status' => 'pending_engagement']));       // not counted

    $response = $this->actingAs($this->viewer)
        ->get(route('reports.red-cross-units.index', ['branch_id' => $this->branch->id, 'division_id' => $this->division->id]))
        ->assertOk()
        ->assertSee('Volunteers &amp;<br>Members', false);

    $row = collect($response->viewData('demographicsData'))->firstWhere('id', $this->unit->id);

    expect($row)->toMatchArray([
        'total_volunteers' => 6,
        'volunteer_only' => 4,
        'volunteer_member' => 2,
    ]);
});
