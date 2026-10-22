<?php

/**
 * Courses step 7: a course's people as a campaign audience.
 * - UserFilterService: course_id (+ course_role, course_result) and the new
 *   archived_filter "not_archived" (pending engagement included)
 * - the sender's area still applies (branch campaigns reach their own branch)
 * - UserFilterDescriber labels
 * - the course page's message buttons start the wizard with these filters,
 *   and the recipient builder reaches exactly those people
 */

use App\Campaigns\Recipients\CampaignRecipientBuilder;
use App\Models\Branch;
use App\Models\Course;
use App\Models\CourseParticipant;
use App\Models\Division;
use App\Models\MessagingCampaign;
use App\Models\TrainingType;
use App\Models\User;
use App\Services\UserFilterService;
use App\Support\Filters\UserFilterDescriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $permissions = ['manage-admin-panel', 'view_user', 'view_courses', 'campaign_request_create'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    Role::findOrCreate('branch_db_administrator', 'web')->syncPermissions($permissions);
    Role::findOrCreate('division_db_assistant_operations', 'web')->syncPermissions(['manage-admin-panel', 'view_user', 'view_courses']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branchA = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->branchB = Branch::create(['name' => 'Beta Branch', 'code' => 'BET']);
    $this->divisionA1 = Division::create(['name' => 'Alpha One', 'branch_id' => $this->branchA->id]);
    $this->divisionB1 = Division::create(['name' => 'Beta One', 'branch_id' => $this->branchB->id]);

    $this->national = User::factory()->create();
    $this->national->assignRole('national_db_administrator');
    $this->branchAdmin = User::factory()->inBranch($this->branchA)->create();
    $this->branchAdmin->assignRole('branch_db_administrator');
    $this->divisionAssistant = User::factory()->inDivision($this->divisionA1)->create();
    $this->divisionAssistant->assignRole('division_db_assistant_operations');

    $this->course = Course::create([
        'name' => 'Alpha First Aid course',
        'training_type_id' => TrainingType::factory()->create()->id,
        'start_date' => today()->subDays(5),
        'end_date' => today()->subDays(3),
        'branch_id' => $this->branchA->id,
    ]);

    $person = fn (Division $division, string $status = 'active') => User::factory()->inDivision($division)->create([
        'lifecycle_status' => $status,
        'email' => fake()->unique()->safeEmail(),
    ]);
    $this->passedA = $person($this->divisionA1);
    $this->failedB = $person($this->divisionB1);
    $this->pendingA = $person($this->divisionA1, 'pending_engagement');
    $this->archivedA = $person($this->divisionA1, 'archived');
    $this->facilitatorA = $person($this->divisionA1);
    $this->notOnCourse = $person($this->divisionA1);

    $on = fn (User $user, ?string $result, string $role = CourseParticipant::ROLE_PARTICIPANT) => $this->course->courseParticipants()
        ->create(['user_id' => $user->id, 'role' => $role, 'result' => $result]);
    $on($this->passedA, 'passed');
    $on($this->failedB, 'failed');
    $on($this->pendingA, 'absent');
    $on($this->archivedA, 'passed');
    $on($this->facilitatorA, null, CourseParticipant::ROLE_FACILITATOR);
});

function courseAudience(array $filters, string $level = 'national', ?int $scopedId = null): array
{
    return app(UserFilterService::class)
        ->apply(User::query(), ['course_id' => test()->course->id, 'archived_filter' => 'not_archived', ...$filters], $level, $scopedId)
        ->pluck('id')->sort()->values()->all();
}

function courseAudienceIds(User ...$users): array
{
    return collect($users)->pluck('id')->sort()->values()->all();
}

it('filters a course\'s people by role and result, without archived people', function () {
    expect(courseAudience([]))->toBe(courseAudienceIds($this->passedA, $this->failedB, $this->pendingA, $this->facilitatorA))
        ->and(courseAudience(['course_role' => 'participant']))->toBe(courseAudienceIds($this->passedA, $this->failedB, $this->pendingA))
        ->and(courseAudience(['course_role' => 'facilitator']))->toBe(courseAudienceIds($this->facilitatorA))
        ->and(courseAudience(['course_role' => 'participant', 'course_result' => 'passed']))->toBe(courseAudienceIds($this->passedA))
        // The default lifecycle filter (active and dormant) leaves out pending engagement.
        ->and(courseAudience(['archived_filter' => 'operational']))->toBe(courseAudienceIds($this->passedA, $this->failedB, $this->facilitatorA));
});

it('keeps the sender\'s area', function () {
    expect(courseAudience([], 'branch', $this->branchA->id))->toBe(courseAudienceIds($this->passedA, $this->pendingA, $this->facilitatorA))
        ->and(courseAudience([], 'branch', $this->branchB->id))->toBe(courseAudienceIds($this->failedB));
});

it('describes the course filters', function () {
    expect(UserFilterDescriber::labels([
        'course_id' => $this->course->id,
        'course_role' => 'participant',
        'course_result' => 'passed',
        'archived_filter' => 'not_archived',
    ]))->toContain('Course: Alpha First Aid course')
        ->toContain('Course role: Participants')
        ->toContain('Course result: Passed')
        ->toContain('Lifecycle: All except archived');

    expect(UserFilterDescriber::labels(['course_id' => $this->course->id]))
        ->toContain('Course role: Participants and facilitators');
});

it('starts the campaign wizard from the course page with the course\'s people', function () {
    $this->actingAs($this->national)->get(route('courses.show', $this->course))
        ->assertOk()
        ->assertSee('Send a message')
        ->assertSee('Everyone on the course')
        ->assertDontSee('Participants who passed');

    $this->actingAs($this->national)->post(route('campaigns.wizard.start'), [
        'filter_json' => [
            'course_id' => $this->course->id,
            'archived_filter' => 'not_archived',
            'audience_type' => 'both',
            'course_role' => 'participant',
        ],
    ])->assertRedirect();

    $campaign = MessagingCampaign::latest('id')->first();
    expect((int) $campaign->filter_json['course_id'])->toBe($this->course->id)
        ->and($campaign->lifecycle)->toBe('not_archived');

    $this->actingAs($this->national)->get(route('campaigns.wizard.step1', $campaign))
        ->assertOk()
        ->assertSee('Course: Alpha First Aid course');

    $this->actingAs($this->national)->get(route('campaigns.wizard.step2', $campaign))
        ->assertOk()
        ->assertViewHas('matchedTotal', 3);

    $audience = app(CampaignRecipientBuilder::class)->audience($campaign)->pluck('id')->sort()->values()->all();
    expect($audience)->toBe(courseAudienceIds($this->passedA, $this->failedB, $this->pendingA));
});

it('offers "Participants who passed" once the course is closed', function () {
    $this->course->update(['status' => Course::STATUS_CLOSED, 'closed_at' => now()]);

    $this->actingAs($this->branchAdmin)->get(route('courses.show', $this->course))
        ->assertSee('Participants who passed')
        ->assertSee('only people in your own branch are reached');
});

it('shows no message buttons without campaign_request_create or on a cancelled course', function () {
    $this->actingAs($this->divisionAssistant)->get(route('courses.show', $this->course))
        ->assertOk()
        ->assertDontSee('Send a message');

    $this->course->update(['status' => Course::STATUS_CANCELLED, 'cancelled_at' => now()]);
    $this->actingAs($this->national)->get(route('courses.show', $this->course))
        ->assertOk()
        ->assertDontSee('Send a message');
});
