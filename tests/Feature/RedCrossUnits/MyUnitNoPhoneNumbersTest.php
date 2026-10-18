<?php

/**
 * /my-unit shows only the leaders' telephone numbers (NDPA legal review,
 * 2026-10-08; leaders' numbers restored 2026-10-18).
 *
 * Any unit member can open /my-unit, so member cards show names and DB
 * numbers only. The team leader and assistant team leader cards show their
 * phone number with a copy button, so members can reach them.
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

test('/my-unit shows the leaders\' phone numbers but not the members\'', function (string $viewer) {
    $response = $this->actingAs($this->{$viewer})
        ->get(route('red-cross-units.my-unit'))
        ->assertOk();

    $response->assertSee('Tess Leader')
        ->assertSee('Abe Assistant')
        ->assertSee('Mia Member')
        ->assertSee('08031110001')
        ->assertSee('08031110002')
        ->assertSee('Copy phone number');

    $response->assertDontSee('08031110003')
        ->assertDontSee('08031110004');
})->with(['an ordinary member' => 'viewer', 'the team leader' => 'leader']);
