<?php

/**
 * Courses step 4: recording results (passed / failed / absent).
 * - only the organising side (Course::canRecordResults): national staff on any
 *   course; for a branch course the staff of that branch who can see it,
 *   assistants included; division staff only on their own division's course;
 *   not staff who only nominated people
 * - from the start date while the course is planned
 * - participants only (not facilitators), only entries of this course
 * - unchanged entries are left alone; one audit entry per save
 */

use App\Models\Branch;
use App\Models\Course;
use App\Models\CourseParticipant;
use App\Models\Division;
use App\Models\Log;
use App\Models\TrainingType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $managers = ['manage-admin-panel', 'view_user', 'view_courses', 'add_courses', 'edit_courses', 'manage_course_participants'];
    $helpers = ['manage-admin-panel', 'view_user', 'view_courses', 'manage_course_participants'];
    foreach ($managers as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    // Mirrors PermissionsTableSeeder.
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($managers);
    Role::findOrCreate('branch_db_administrator', 'web')->syncPermissions($managers);
    Role::findOrCreate('branch_db_assistant', 'web')->syncPermissions($helpers);
    Role::findOrCreate('division_db_assistant_operations', 'web')->syncPermissions($helpers);
    Role::findOrCreate('observer_national_level', 'web')->syncPermissions(['manage-admin-panel', 'view_user', 'view_courses']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branchA = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->divisionA1 = Division::create(['name' => 'Alpha One', 'branch_id' => $this->branchA->id]);
    $this->divisionA2 = Division::create(['name' => 'Alpha Two', 'branch_id' => $this->branchA->id]);

    $this->national = User::factory()->create();
    $this->national->assignRole('national_db_administrator');

    $this->branchAdmin = User::factory()->inBranch($this->branchA)->create();
    $this->branchAdmin->assignRole('branch_db_administrator');

    $this->branchAssistant = User::factory()->inBranch($this->branchA)->create();
    $this->branchAssistant->assignRole('branch_db_assistant');

    $this->divisionAssistant = User::factory()->inDivision($this->divisionA1)->create();
    $this->divisionAssistant->assignRole('division_db_assistant_operations');

    $this->divisionA2Assistant = User::factory()->inDivision($this->divisionA2)->create();
    $this->divisionA2Assistant->assignRole('division_db_assistant_operations');

    $this->observer = User::factory()->create();
    $this->observer->assignRole('observer_national_level');

    $this->type = TrainingType::factory()->create();
});

/** A started course in branch A with three participants and a facilitator. */
function resultCourse(array $attributes = []): Course
{
    $course = Course::create([
        'name' => 'Course '.fake()->unique()->lexify('????'),
        'training_type_id' => test()->type->id,
        'start_date' => today()->subDay(),
        'end_date' => today()->addDay(),
        'branch_id' => test()->branchA->id,
        ...$attributes,
    ]);

    foreach (['ann', 'ben', 'cat'] as $name) {
        test()->{$name} = $course->courseParticipants()->create([
            'user_id' => User::factory()->inDivision(test()->divisionA1)->create(['first_name' => ucfirst($name)])->id,
        ]);
    }
    test()->facilitator = $course->courseParticipants()->create([
        'user_id' => User::factory()->inDivision(test()->divisionA1)->create()->id,
        'role' => CourseParticipant::ROLE_FACILITATOR,
    ]);

    return $course;
}

function saveResults(User $actor, Course $course, array $results)
{
    return test()->actingAs($actor)
        ->from(route('courses.show', $course))
        ->put(route('courses.results.update', $course), ['results' => $results]);
}

/*
|--------------------------------------------------------------------------
| Saving
|--------------------------------------------------------------------------
*/

it('lets the organising branch record results and logs only the changes', function () {
    $course = resultCourse();

    saveResults($this->branchAdmin, $course, [
        $this->ann->id => 'passed',
        $this->ben->id => 'failed',
        $this->cat->id => '',
    ])->assertRedirect(route('courses.show', $course))
        ->assertSessionHas('success', '2 results saved.');

    $ann = $this->ann->fresh();
    expect($ann->result)->toBe('passed')
        ->and($ann->result_recorded_by_user_id)->toBe($this->branchAdmin->id)
        ->and($ann->result_recorded_at)->not->toBeNull()
        ->and($this->ben->fresh()->result)->toBe('failed')
        ->and($this->cat->fresh()->result)->toBeNull();

    $log = Log::where('action', 'course_results_recorded')->sole();
    expect($log->subject_id)->toBe($course->id)
        ->and($log->branch_id)->toBe($this->branchA->id)
        ->and($log->new_values)->toBe([(string) $this->ann->user_id => 'passed', (string) $this->ben->user_id => 'failed'])
        ->and($log->old_values)->toBe([(string) $this->ann->user_id => null, (string) $this->ben->user_id => null]);

    // Saving the same results again changes nothing and logs nothing.
    saveResults($this->branchAdmin, $course, [$this->ann->id => 'passed', $this->ben->id => 'failed'])
        ->assertSessionHas('success', 'No results changed.');
    expect(Log::where('action', 'course_results_recorded')->count())->toBe(1);
});

it('clears a result', function () {
    $course = resultCourse();
    saveResults($this->branchAdmin, $course, [$this->ann->id => 'absent']);

    saveResults($this->branchAdmin, $course, [$this->ann->id => ''])->assertSessionHas('success', '1 result saved.');

    $ann = $this->ann->fresh();
    expect($ann->result)->toBeNull()
        ->and($ann->result_recorded_by_user_id)->toBeNull()
        ->and($ann->result_recorded_at)->toBeNull();
});

it('ignores facilitators and entries of other courses, and rejects unknown results', function () {
    $course = resultCourse();
    $facilitator = $this->facilitator;
    $otherCourseEntry = resultCourse()->courseParticipants()->first();

    saveResults($this->branchAdmin, $course, [
        $facilitator->id => 'passed',
        $otherCourseEntry->id => 'passed',
    ])->assertSessionHas('success', 'No results changed.');

    expect($facilitator->fresh()->result)->toBeNull()
        ->and($otherCourseEntry->fresh()->result)->toBeNull();

    saveResults($this->branchAdmin, $course, [$this->ann->id => 'excellent'])->assertSessionHasErrors('results.'.$this->ann->id);
    expect($this->ann->fresh()->result)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Who may record
|--------------------------------------------------------------------------
*/

it('lets the organising branch\'s assistants and national staff record results', function (string $who) {
    $course = resultCourse();

    saveResults($this->{$who}, $course, [$this->ann->id => 'passed'])->assertSessionHas('success');

    expect($this->ann->fresh()->result_recorded_by_user_id)->toBe($this->{$who}->id);
})->with(['branchAssistant', 'national']);

it('lets division staff record results only on their own division\'s course', function () {
    $branchCourse = resultCourse();

    saveResults($this->divisionAssistant, $branchCourse, [$this->ann->id => 'passed'])->assertForbidden();
    expect($this->ann->fresh()->result)->toBeNull();

    $this->actingAs($this->divisionAssistant)->get(route('courses.show', $branchCourse))
        ->assertOk()
        ->assertDontSee('Save results');

    $divisionCourse = resultCourse(['division_id' => $this->divisionA1->id]);

    saveResults($this->divisionAssistant, $divisionCourse, [$this->ann->id => 'passed'])->assertSessionHas('success');
    expect($this->ann->fresh()->result_recorded_by_user_id)->toBe($this->divisionAssistant->id);
});

it('does not let staff who only nominated people record results on a national course', function () {
    $course = resultCourse(['branch_id' => null]);

    saveResults($this->branchAdmin, $course, [$this->ann->id => 'passed'])->assertForbidden();
    saveResults($this->divisionAssistant, $course, [$this->ann->id => 'passed'])->assertForbidden();

    $this->actingAs($this->branchAdmin)->get(route('courses.show', $course))
        ->assertOk()
        ->assertDontSee('Save results');

    saveResults($this->national, $course, [$this->ann->id => 'passed'])->assertSessionHas('success');
});

it('refuses staff who cannot see the course or lack the permission', function () {
    $course = resultCourse(['division_id' => $this->divisionA1->id]);

    saveResults($this->divisionA2Assistant, $course, [$this->ann->id => 'passed'])->assertForbidden();
    saveResults($this->observer, $course, [$this->ann->id => 'passed'])->assertForbidden();

    $this->actingAs($this->observer)->get(route('courses.show', $course))
        ->assertOk()
        ->assertDontSee('Save results');

    expect($this->ann->fresh()->result)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| When
|--------------------------------------------------------------------------
*/

it('opens results only from the start date', function () {
    $course = resultCourse(['start_date' => today()->addDay(), 'end_date' => today()->addDays(2)]);

    $this->actingAs($this->branchAdmin)->get(route('courses.show', $course))
        ->assertOk()
        ->assertSee('Results can be recorded from '.today()->addDay()->format('j M Y'))
        ->assertDontSee('Save results');

    saveResults($this->branchAdmin, $course, [$this->ann->id => 'passed'])
        ->assertSessionHas('error', 'Results can be recorded from the start date of the course.');
    expect($this->ann->fresh()->result)->toBeNull();

    $course->update(['start_date' => today()]);
    $this->actingAs($this->branchAdmin)->get(route('courses.show', $course))->assertSee('Save results');
    saveResults($this->branchAdmin, $course, [$this->ann->id => 'passed'])->assertSessionHas('success');
});

it('does not change results on a closed or cancelled course', function (string $status) {
    $course = resultCourse(['status' => $status]);
    $this->ann->update(['result' => 'passed']);

    saveResults($this->branchAdmin, $course, [$this->ann->id => 'failed'])->assertForbidden();

    $this->actingAs($this->branchAdmin)->get(route('courses.show', $course))
        ->assertOk()
        ->assertDontSee('Save results')
        ->assertSee('Passed');

    expect($this->ann->fresh()->result)->toBe('passed');
})->with([Course::STATUS_CLOSED, Course::STATUS_CANCELLED]);

it('shows the result counts and a select per participant to the organisers', function () {
    $course = resultCourse();
    saveResults($this->branchAdmin, $course, [$this->ann->id => 'passed', $this->ben->id => 'absent']);

    $this->actingAs($this->branchAdmin)->get(route('courses.show', $course))
        ->assertOk()
        ->assertSee('Passed 1 · Failed 0 ·')
        ->assertSee('Absent 1 · Not recorded 1')
        ->assertSee('name="results['.$this->cat->id.']"', false)
        ->assertDontSee('name="results['.$this->facilitator->id.']"', false);
});
