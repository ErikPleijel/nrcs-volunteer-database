<?php

/**
 * Feature tests for the public QR verification page (IdCardController::verifyId()),
 * covering the same member/volunteer distinction added to the printed card:
 * "Member"/"Volunteer" labelling and the category (fee name vs Red Cross
 * unit) swap.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\RedCrossUnit;
use App\Models\User;
use Database\Factories\MembershipFeeFactory;
use Database\Factories\MembershipPaymentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);
});

test('verify page shows Member status and the fee name for a member', function () {
    $fee = MembershipFeeFactory::new()->create(['name' => 'Ordinary Member', 'is_volunteer_fee' => false]);

    $member = User::factory()->create([
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);

    MembershipPaymentFactory::new()->approved()->create([
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subMonth(),
        'expiry_date' => now()->addYear(),
    ]);

    $html = $this->get(route('id.verify', $member->id_check_token))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('Member ID-Card Verification')
        ->toContain('Membership Status')
        ->toContain('Membership Type')
        ->toContain('Ordinary Member')
        ->not->toContain('Volunteer Status')
        ->not->toContain('ACTIVE VOLUNTEER');
});

test('verify page shows Volunteer status and the Red Cross unit name for a volunteer', function () {
    $unit = RedCrossUnit::create(['name' => 'Holy Mary RC Unit', 'division_id' => $this->division->id]);

    $volunteer = User::factory()->create([
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
        'red_cross_unit_id' => $unit->id,
    ]);

    $html = $this->get(route('id.verify', $volunteer->id_check_token))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('Volunteer ID-Card Verification')
        ->toContain('Volunteer Status')
        ->toContain('ACTIVE VOLUNTEER')
        ->toContain('Red Cross Unit')
        ->toContain('Holy Mary RC Unit')
        ->not->toContain('Membership Status')
        ->not->toContain('Membership Type');
});
