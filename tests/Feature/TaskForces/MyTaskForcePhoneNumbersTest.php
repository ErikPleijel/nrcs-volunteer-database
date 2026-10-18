<?php

/**
 * /my-task-force/{id} shows only the leaders' telephone numbers (2026-10-18).
 *
 * Every member of the task force can open the page, so member cards show
 * names and DB numbers only — for every viewer, the leaders included. The
 * team leader and assistant team leader show their number with a copy button.
 */

use App\Models\Branch;
use App\Models\TaskForce;
use App\Models\TaskForceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $type = TaskForceType::create(['name' => 'Phone Type', 'level' => 1]);
    $this->taskForce = TaskForce::create(['name' => 'Phone Task Force', 'task_force_type_id' => $type->id, 'branch_id' => $branch->id]);

    $inTaskForce = function (array $attributes) use ($branch) {
        $user = User::factory()->create(['branch_id' => $branch->id, ...$attributes]);
        $this->taskForce->users()->attach($user->id);

        return $user;
    };

    $this->leader = $inTaskForce(['first_name' => 'Tess', 'last_name' => 'Leader', 'telephone1' => '08031110001']);
    $this->assistant = $inTaskForce(['first_name' => 'Abe', 'last_name' => 'Assistant', 'telephone1' => '08031110002']);
    $this->member = $inTaskForce(['first_name' => 'Mia', 'last_name' => 'Member', 'telephone1' => '08031110003']);
    $this->viewer = $inTaskForce(['first_name' => 'Vic', 'last_name' => 'Viewer', 'telephone1' => '08031110004']);

    $this->taskForce->update([
        'team_leader_user_id' => $this->leader->id,
        'assist_team_leader_user_id' => $this->assistant->id,
    ]);
});

test('/my-task-force shows the leaders\' phone numbers but not the members\'', function (string $viewer) {
    $response = $this->actingAs($this->{$viewer})
        ->get(route('my-task-force.show', $this->taskForce))
        ->assertOk();

    $response->assertSee('Tess Leader')
        ->assertSee('Abe Assistant')
        ->assertSee('Mia Member')
        ->assertSee('08031110001')
        ->assertSee('08031110002')
        ->assertSee('Copy phone number');

    $response->assertDontSee('08031110003')
        ->assertDontSee('08031110004');
})->with(['an ordinary member' => 'viewer', 'the team leader' => 'leader', 'the assistant team leader' => 'assistant']);
