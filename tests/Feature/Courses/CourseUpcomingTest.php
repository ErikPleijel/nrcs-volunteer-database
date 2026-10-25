<?php

/**
 * Courses step 8: upcoming courses on the dashboard and on My Profile.
 * - dashboard: planned courses that have not ended, in the viewer's own area
 *   (Course::visibleTo, not the dashboard's selected branch), outside the
 *   hourly cache; only for view_courses
 * - My Profile: the person's own upcoming courses, as participant or facilitator
 */

use App\Models\Branch;
use App\Models\Course;
use App\Models\CourseParticipant;
use App\Models\Division;
use App\Models\TrainingType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Cache::flush();

    foreach (['manage-admin-panel', 'view_reports', 'view_courses', 'view_user'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('branch_db_administrator', 'web')->syncPermissions(['manage-admin-panel', 'view_reports', 'view_courses', 'view_user']);
    // A role that sees reports but not courses.
    Role::findOrCreate('branch_db_assistant', 'web')->syncPermissions(['manage-admin-panel', 'view_reports', 'view_user']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branchA = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->branchB = Branch::create(['name' => 'Beta Branch', 'code' => 'BET']);
    $this->divisionA1 = Division::create(['name' => 'Alpha One', 'branch_id' => $this->branchA->id]);

    $this->branchAdmin = User::factory()->inBranch($this->branchA)->create();
    $this->branchAdmin->assignRole('branch_db_administrator');
    $this->noCourses = User::factory()->inBranch($this->branchA)->create();
    $this->noCourses->assignRole('branch_db_assistant');

    $this->type = TrainingType::factory()->create();
});

function upcomingCourse(string $name, array $attributes = []): Course
{
    return Course::create([
        'name' => $name,
        'training_type_id' => test()->type->id,
        'start_date' => today()->addDays(7),
        'end_date' => today()->addDays(8),
        'place' => 'Branch hall',
        'branch_id' => test()->branchA->id,
        ...$attributes,
    ]);
}

it('lists upcoming courses in the viewer\'s area on the dashboard', function () {
    $soon = upcomingCourse('Soon course');
    $ongoing = upcomingCourse('Ongoing course', ['start_date' => today()->subDay(), 'end_date' => today()->addDay()]);
    $national = upcomingCourse('National course', ['branch_id' => null]);
    upcomingCourse('Beta course', ['branch_id' => $this->branchB->id]);
    upcomingCourse('Past course', ['start_date' => today()->subDays(5), 'end_date' => today()->subDays(4)]);
    upcomingCourse('Cancelled course', ['status' => Course::STATUS_CANCELLED]);
    $soon->courseParticipants()->create(['user_id' => User::factory()->create()->id]);

    $response = $this->actingAs($this->branchAdmin)->get(route('reports.dashboard'))->assertOk();

    expect($response->viewData('upcomingCourses')->pluck('id')->all())->toBe([$ongoing->id, $soon->id, $national->id]);

    $response->assertSee('Upcoming Courses')
        ->assertSee('Soon course')
        ->assertSee('1 participant')
        ->assertSee('Ongoing')
        ->assertDontSee('Beta course')
        ->assertDontSee('Past course')
        ->assertDontSee('Cancelled course');
});

it('shows 10 upcoming courses and a "View all courses" link when there are more', function () {
    foreach (range(1, 10) as $i) {
        upcomingCourse("Course {$i}", ['start_date' => today()->addDays($i), 'end_date' => today()->addDays($i)]);
    }

    $link = 'href="'.route('courses.index', ['status' => 'upcoming']).'"';

    $response = $this->actingAs($this->branchAdmin)->get(route('reports.dashboard'))->assertOk();
    expect($response->viewData('upcomingCourses'))->toHaveCount(10);
    $response->assertDontSee('View all courses')->assertDontSee($link, false)->assertSee('All courses');

    upcomingCourse('Course 11', ['start_date' => today()->addDays(11), 'end_date' => today()->addDays(11)]);

    $response = $this->actingAs($this->branchAdmin)->get(route('reports.dashboard'))->assertOk();
    expect($response->viewData('upcomingCourses'))->toHaveCount(10);
    $response->assertSee('View all courses')
        ->assertSee($link, false)
        ->assertSee('Course 10')
        ->assertDontSee('Course 11');
});

it('is not cached: a new course shows on the next visit', function () {
    $this->actingAs($this->branchAdmin)->get(route('reports.dashboard'))->assertSee('No upcoming courses in your area.');

    upcomingCourse('Brand new course');

    $this->actingAs($this->branchAdmin)->get(route('reports.dashboard'))->assertSee('Brand new course');
});

it('shows no course section without view_courses', function () {
    upcomingCourse('Soon course');

    $this->actingAs($this->noCourses)->get(route('reports.dashboard'))
        ->assertOk()
        ->assertDontSee('Upcoming Courses')
        ->assertDontSee('Soon course');
});

it('shows a person their own upcoming courses on My Profile', function () {
    $person = User::factory()->inDivision($this->divisionA1)->create();
    $participating = upcomingCourse('Participating course');
    $facilitating = upcomingCourse('Facilitating course', ['branch_id' => null, 'start_date' => today()->addDays(20), 'end_date' => today()->addDays(21)]);
    $past = upcomingCourse('Past course', ['start_date' => today()->subDays(5), 'end_date' => today()->subDays(4)]);
    $cancelled = upcomingCourse('Cancelled course', ['status' => Course::STATUS_CANCELLED]);
    upcomingCourse('Someone else\'s course');

    $participating->courseParticipants()->create(['user_id' => $person->id]);
    $facilitating->courseParticipants()->create(['user_id' => $person->id, 'role' => CourseParticipant::ROLE_FACILITATOR]);
    $past->courseParticipants()->create(['user_id' => $person->id]);
    $cancelled->courseParticipants()->create(['user_id' => $person->id]);

    $response = $this->actingAs($person)->get(route('profile.show'))->assertOk();

    expect($response->viewData('upcomingCourses')->pluck('course_id')->all())->toBe([$participating->id, $facilitating->id]);

    $response->assertSee('YOUR UPCOMING COURSES')
        ->assertSeeInOrder(['Participating course', 'Participant', 'Facilitating course', 'Facilitator'])
        ->assertSee('Organised by Alpha Branch')
        ->assertSee('Organised by NRCS National Headquarters')
        ->assertDontSee('Past course')
        ->assertDontSee('Cancelled course')
        ->assertDontSee('Someone else');
});

it('hides the section on My Profile when there are no upcoming courses', function () {
    $person = User::factory()->inDivision($this->divisionA1)->create();

    $this->actingAs($person)->get(route('profile.show'))
        ->assertOk()
        ->assertDontSee('YOUR UPCOMING COURSES')
        ->assertDontSee('You are registered for')
        ->assertDontSee('href="#upcoming-courses"', false);
});

it('shows a notice per upcoming course under the seven principles on My Profile', function () {
    $person = User::factory()->inDivision($this->divisionA1)->create();
    $participating = upcomingCourse('First Aid Basic', [
        'start_date' => today()->addDays(3), 'end_date' => today()->addDays(5), 'place' => 'Ikeja branch office',
    ]);
    $facilitating = upcomingCourse('Disaster Response', ['start_date' => today()->addDays(20), 'end_date' => today()->addDays(20), 'place' => null]);
    $past = upcomingCourse('Past course', ['start_date' => today()->subDays(5), 'end_date' => today()->subDays(4)]);

    $participating->courseParticipants()->create(['user_id' => $person->id]);
    $facilitating->courseParticipants()->create(['user_id' => $person->id, 'role' => CourseParticipant::ROLE_FACILITATOR]);
    $past->courseParticipants()->create(['user_id' => $person->id]);

    $response = $this->actingAs($person)->get(route('profile.show'))->assertOk();

    $response->assertSeeInOrder([
        '<strong>Universality</strong>',
        'href="#upcoming-courses"',
        'You are registered for <strong>First Aid Basic</strong>,',
        e($participating->date_range).', at Ikeja branch office.',
        'You are registered for <strong>Disaster Response</strong> as facilitator,',
        e($facilitating->date_range).'.',
        'id="upcoming-courses"',
    ], false);

    $response->assertDontSee('You are registered for <strong>Past course</strong>', false);
    expect(substr_count($response->getContent(), 'You are registered for'))->toBe(2);
});
