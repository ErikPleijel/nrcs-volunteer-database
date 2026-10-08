<?php

/**
 * /my-unit shows no telephone numbers (NDPA legal review, 2026-10-08).
 *
 * Any unit member can open /my-unit, so the team leader, assistant team
 * leader and member cards show names and DB numbers only — no phone
 * numbers and no copy-phone buttons.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $division = Division::create(['name' => 'Alpha One', 'branch_id' => $branch->id]);

    $this->unit = RedCrossUnit::create(['name' => 'Phone Unit', 'division_id' => $division->id, 'is_active' => true]);

    $inUnit = fn (array $attributes) => User::factory()->create([
        'branch_id' => $branch->id,
        'division_id' => $division->id,
        'red_cross_unit_id' => $this->unit->id,
        ...$attributes,
    ]);

    $this->leader = $inUnit(['first_name' => 'Tess', 'last_name' => 'Leader', 'telephone1' => '08031110001']);
    $this->assistant = $inUnit(['first_name' => 'Abe', 'last_name' => 'Assistant', 'telephone1' => '08031110002']);
    $this->member = $inUnit(['first_name' => 'Mia', 'last_name' => 'Member', 'telephone1' => '08031110003']);
    $this->viewer = $inUnit(['first_name' => 'Vic', 'last_name' => 'Viewer', 'telephone1' => '08031110004']);

    $this->unit->update([
        'team_leader_user_id' => $this->leader->id,
        'assistant_team_leader_user_id' => $this->assistant->id,
    ]);
});

test('/my-unit shows the leaders and members but none of their phone numbers', function () {
    $response = $this->actingAs($this->viewer)
        ->get(route('red-cross-units.my-unit'))
        ->assertOk();

    $response->assertSee('Tess Leader')
        ->assertSee('Abe Assistant')
        ->assertSee('Mia Member');

    foreach (['08031110001', '08031110002', '08031110003', '08031110004'] as $phone) {
        $response->assertDontSee($phone);
    }

    $response->assertDontSee('copy-phone-btn')
        ->assertDontSee('Copy phone number');
});
