<?php

/**
 * Courses step 1: tables, models and permissions.
 * - courses / course_participants relations and the derived "ongoing" status
 * - one row per person per course, in course_participants and in trainings
 *   (the guard that makes "close course" issue records only once)
 * - the permission migration grants the course permissions to the right roles
 */

use App\Models\Branch;
use App\Models\Course;
use App\Models\CourseParticipant;
use App\Models\Division;
use App\Models\Training;
use App\Models\TrainingType;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);
    $this->type = TrainingType::factory()->create();

    $this->makeCourse = fn (array $attributes = []) => Course::create([
        'name' => 'First Aid course',
        'training_type_id' => $this->type->id,
        'start_date' => today()->addDays(10),
        'end_date' => today()->addDays(12),
        'place' => 'Branch office',
        'branch_id' => $this->branch->id,
        ...$attributes,
    ]);
});

it('creates a planned course with participants and facilitators', function () {
    $course = ($this->makeCourse)();
    $participant = User::factory()->create();
    $facilitator = User::factory()->create();

    $course->courseParticipants()->create(['user_id' => $participant->id]);
    $course->courseParticipants()->create(['user_id' => $facilitator->id, 'role' => CourseParticipant::ROLE_FACILITATOR]);

    $course->refresh();

    expect($course->status)->toBe(Course::STATUS_PLANNED)
        ->and($course->isNational())->toBeFalse()
        ->and($course->trainingType->is($this->type))->toBeTrue()
        ->and($course->participants->pluck('id')->all())->toBe([$participant->id])
        ->and($course->facilitators->pluck('id')->all())->toBe([$facilitator->id])
        ->and($participant->courseParticipations->first()->course->is($course))->toBeTrue()
        ->and($this->type->courses->pluck('id')->all())->toBe([$course->id]);
});

it('works out "ongoing" and "upcoming" from the dates', function () {
    $future = ($this->makeCourse)();
    $ongoing = ($this->makeCourse)(['start_date' => today()->subDay(), 'end_date' => today()->addDay()]);
    $past = ($this->makeCourse)(['start_date' => today()->subDays(5), 'end_date' => today()->subDays(3)]);
    $cancelled = ($this->makeCourse)(['status' => Course::STATUS_CANCELLED]);
    $national = ($this->makeCourse)(['branch_id' => null]);

    expect($future->isOngoing())->toBeFalse()
        ->and($ongoing->isOngoing())->toBeTrue()
        ->and($past->isOngoing())->toBeFalse()
        ->and($national->isNational())->toBeTrue()
        ->and(Course::upcoming()->pluck('id')->sort()->values()->all())
        ->toBe([$future->id, $ongoing->id, $national->id]);

    expect($cancelled->isCancelled())->toBeTrue();
});

it('puts a person on a course only once', function () {
    $course = ($this->makeCourse)();
    $user = User::factory()->create();

    $course->courseParticipants()->create(['user_id' => $user->id]);

    expect(fn () => $course->courseParticipants()->create([
        'user_id' => $user->id,
        'role' => CourseParticipant::ROLE_FACILITATOR,
    ]))->toThrow(QueryException::class);
});

it('allows one training record per person per course, and any number without a course', function () {
    $course = ($this->makeCourse)();
    $user = User::factory()->create();

    $attributes = [
        'user_id' => $user->id,
        'training_type_id' => $this->type->id,
        'division_id' => $this->division->id,
    ];

    Training::factory()->create($attributes);
    Training::factory()->create($attributes);
    $fromCourse = Training::factory()->create([...$attributes, 'course_id' => $course->id]);

    expect($fromCourse->course->is($course))->toBeTrue()
        ->and(fn () => Training::factory()->create([...$attributes, 'course_id' => $course->id]))
        ->toThrow(QueryException::class);
});

it('grants the course permissions to the right roles', function () {
    $roles = [
        'national_db_administrator', 'national_db_assistant', 'branch_secretary', 'branch_db_administrator',
        'branch_db_assistant', 'division_db_assistant_operations', 'division_db_assistant_finance',
        'observer_national_level', 'super-admin',
    ];
    foreach ($roles as $role) {
        Role::findOrCreate($role, 'web');
    }

    (require database_path('migrations/2026_10_23_100003_add_course_permissions.php'))->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $held = fn (string $role) => Role::findByName($role, 'web')->permissions->pluck('name')->sort()->values()->all();

    $all = ['add_courses', 'edit_courses', 'manage_course_participants', 'view_courses'];
    $participantsOnly = ['manage_course_participants', 'view_courses'];

    foreach (['national_db_administrator', 'national_db_assistant', 'branch_secretary', 'branch_db_administrator'] as $role) {
        expect($held($role))->toBe($all);
    }
    foreach (['branch_db_assistant', 'division_db_assistant_operations', 'division_db_assistant_finance'] as $role) {
        expect($held($role))->toBe($participantsOnly);
    }
    expect($held('observer_national_level'))->toBe(['view_courses'])
        ->and($held('super-admin'))->toBe(['view_courses']);
});
