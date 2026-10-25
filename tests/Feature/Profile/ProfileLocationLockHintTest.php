<?php

/**
 * My Profile / Edit: when Branch and Division are locked (Red Cross unit
 * member or a branch/division database role), a layer over each disabled
 * dropdown catches clicks and taps and makes the "Why can't I change my
 * Branch or Division?" button stand out (Alpine branchDivisionHelpNudge).
 * Users whose fields are enabled get none of it.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $branch->id]);
});

function lockedHintMarkup(): array
{
    return [
        'x-data="branchDivisionHelpNudge()"',
        'data-location-locked-overlay @click="nudgeHelp()"',
        'id="branchDivisionHelpRevealBtn" x-ref="helpButton"',
        'id="branchDivisionHelp" x-ref="helpText"',
    ];
}

it('puts a click layer over the locked fields for a Red Cross unit member', function () {
    $unit = RedCrossUnit::create(['name' => 'Unit One', 'division_id' => $this->division->id, 'is_active' => true]);
    $user = User::factory()->inDivision($this->division)->create(['red_cross_unit_id' => $unit->id]);

    $response = $this->actingAs($user)->get(route('profile.edit'))->assertOk();

    foreach (lockedHintMarkup() as $markup) {
        $response->assertSee($markup, false);
    }
    expect(substr_count($response->getContent(), 'data-location-locked-overlay'))->toBe(2);
    $response->assertSee("Why can't I change my Branch or Division?", false);
});

it('puts a click layer over the locked fields for someone with a division role', function () {
    Role::findOrCreate('division_db_assistant_operations', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->inDivision($this->division)->create();
    $user->assignRole('division_db_assistant_operations');

    $response = $this->actingAs($user)->get(route('profile.edit'))->assertOk();

    foreach (lockedHintMarkup() as $markup) {
        $response->assertSee($markup, false);
    }
});

it('adds nothing when the fields can be changed', function () {
    $user = User::factory()->inDivision($this->division)->create();

    $response = $this->actingAs($user)->get(route('profile.edit'))->assertOk();

    foreach (lockedHintMarkup() as $markup) {
        $response->assertDontSee($markup, false);
    }
    $response->assertDontSee('id="branchDivisionHelpRevealBtn"', false);
});
