<?php

/**
 * Courses step 2: list, create, edit, cancel.
 * - who sees which courses (Course::visibleTo): national all; branch own branch
 *   + national courses; division own branch minus other divisions' courses
 * - only national and branch staff with add_courses create; branch staff
 *   always for their own branch; a division must belong to the branch
 * - only the organising branch (or national) edits and cancels; closed and
 *   cancelled courses are read-only
 * - audit log: course_created, course_updated (changed fields only), course_cancelled
 */

use App\Models\Branch;
use App\Models\Course;
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

    $managers = ['manage-admin-panel', 'view_courses', 'add_courses', 'edit_courses', 'manage_course_participants'];
    $helpers = ['manage-admin-panel', 'view_courses', 'manage_course_participants'];
    foreach ($managers as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    // Mirrors PermissionsTableSeeder.
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($managers);
    Role::findOrCreate('branch_db_administrator', 'web')->syncPermissions($managers);
    Role::findOrCreate('branch_db_assistant', 'web')->syncPermissions($helpers);
    Role::findOrCreate('division_db_assistant_operations', 'web')->syncPermissions($helpers);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branchA = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->branchB = Branch::create(['name' => 'Beta Branch', 'code' => 'BET']);
    $this->divisionA1 = Division::create(['name' => 'Alpha One', 'branch_id' => $this->branchA->id]);
    $this->divisionA2 = Division::create(['name' => 'Alpha Two', 'branch_id' => $this->branchA->id]);
    $this->divisionB1 = Division::create(['name' => 'Beta One', 'branch_id' => $this->branchB->id]);

    $this->national = User::factory()->create();
    $this->national->assignRole('national_db_administrator');

    $this->branchAdmin = User::factory()->inBranch($this->branchA)->create();
    $this->branchAdmin->assignRole('branch_db_administrator');

    $this->branchAssistant = User::factory()->inBranch($this->branchA)->create();
    $this->branchAssistant->assignRole('branch_db_assistant');

    $this->divisionAssistant = User::factory()->inDivision($this->divisionA1)->create();
    $this->divisionAssistant->assignRole('division_db_assistant_operations');

    $this->type = TrainingType::factory()->create(['name' => 'First Aid for volunteers']);
    $this->inactiveType = TrainingType::factory()->create(['name' => 'Old Course', 'is_active' => false]);
});

function makeCourse(array $attributes = []): Course
{
    return Course::create([
        'name' => 'Course '.fake()->unique()->lexify('????'),
        'training_type_id' => test()->type->id,
        'start_date' => today()->addDays(10),
        'end_date' => today()->addDays(12),
        'branch_id' => test()->branchA->id,
        ...$attributes,
    ]);
}

function coursePayload(array $overrides = []): array
{
    return [
        'name' => 'First Aid, Ikeja',
        'training_type_id' => test()->type->id,
        'start_date' => today()->addDays(20)->toDateString(),
        'end_date' => today()->addDays(22)->toDateString(),
        'place' => 'Branch office',
        'external_facilitators' => 'IFRC trainer',
        ...$overrides,
    ];
}

function listedCourseIds($response): array
{
    return $response->viewData('courses')->pluck('id')->sort()->values()->all();
}

/*
|--------------------------------------------------------------------------
| Who sees which courses
|--------------------------------------------------------------------------
*/

it('shows each level the courses of its own area plus national courses', function () {
    $national = makeCourse(['branch_id' => null]);
    $branchA = makeCourse();
    $divisionA1 = makeCourse(['division_id' => $this->divisionA1->id]);
    $divisionA2 = makeCourse(['division_id' => $this->divisionA2->id]);
    $branchB = makeCourse(['branch_id' => $this->branchB->id]);

    $sorted = fn (Course ...$courses) => collect($courses)->pluck('id')->sort()->values()->all();

    expect(listedCourseIds($this->actingAs($this->national)->get(route('courses.index'))))
        ->toBe($sorted($national, $branchA, $divisionA1, $divisionA2, $branchB))
        ->and(listedCourseIds($this->actingAs($this->branchAdmin)->get(route('courses.index'))))
        ->toBe($sorted($national, $branchA, $divisionA1, $divisionA2))
        ->and(listedCourseIds($this->actingAs($this->divisionAssistant)->get(route('courses.index'))))
        ->toBe($sorted($national, $branchA, $divisionA1));

    $this->actingAs($this->branchAdmin)->get(route('courses.show', $branchB))->assertForbidden();
    $this->actingAs($this->branchAdmin)->get(route('courses.show', $national))->assertOk()->assertSee('National');
    $this->actingAs($this->divisionAssistant)->get(route('courses.show', $divisionA2))->assertForbidden();
    $this->actingAs($this->divisionAssistant)->get(route('courses.show', $divisionA1))->assertOk();
});

it('lists upcoming, past and cancelled courses separately', function () {
    $upcoming = makeCourse();
    $ongoing = makeCourse(['start_date' => today()->subDay(), 'end_date' => today()->addDay()]);
    $endedOpen = makeCourse(['start_date' => today()->subDays(9), 'end_date' => today()->subDays(8)]);
    $closed = makeCourse([
        'start_date' => today()->subDays(30), 'end_date' => today()->subDays(29),
        'status' => Course::STATUS_CLOSED, 'closed_at' => now(),
    ]);
    $cancelled = makeCourse(['status' => Course::STATUS_CANCELLED, 'cancelled_at' => now()]);

    $index = fn (array $query = []) => listedCourseIds($this->actingAs($this->national)->get(route('courses.index', $query)));
    $sorted = fn (Course ...$courses) => collect($courses)->pluck('id')->sort()->values()->all();

    expect($index())->toBe($sorted($upcoming, $ongoing))
        ->and($index(['status' => 'past']))->toBe($sorted($endedOpen, $closed))
        ->and($index(['status' => 'cancelled']))->toBe($sorted($cancelled));

    $this->actingAs($this->national)->get(route('courses.index'))
        ->assertSee('Ongoing')
        ->assertSee('Planned');
    $this->actingAs($this->national)->get(route('courses.index', ['status' => 'past']))
        ->assertSee('Ended, not closed')
        ->assertSee('Closed');
});

it('filters national courses for a national admin', function () {
    $national = makeCourse(['branch_id' => null]);
    makeCourse();

    expect(listedCourseIds($this->actingAs($this->national)->get(route('courses.index', ['branch_id' => 'national']))))
        ->toBe([$national->id]);
});

/*
|--------------------------------------------------------------------------
| Create
|--------------------------------------------------------------------------
*/

it('lets a branch admin create a course, always for their own branch', function () {
    $this->actingAs($this->branchAdmin)
        ->get(route('courses.create'))
        ->assertOk()
        ->assertSee('Alpha One')
        ->assertDontSee('Beta One');

    $response = $this->actingAs($this->branchAdmin)->post(route('courses.store'), coursePayload([
        'branch_id' => $this->branchB->id, // ignored: branch staff organise for their own branch
        'division_id' => $this->divisionA2->id,
    ]));

    $course = Course::sole();
    $response->assertRedirect(route('courses.show', $course));

    expect($course->branch_id)->toBe($this->branchA->id)
        ->and($course->division_id)->toBe($this->divisionA2->id)
        ->and($course->status)->toBe(Course::STATUS_PLANNED)
        ->and($course->created_by_user_id)->toBe($this->branchAdmin->id)
        ->and($course->external_facilitators)->toBe('IFRC trainer');

    $log = Log::where('action', 'course_created')->sole();
    expect($log->subject_id)->toBe($course->id)
        ->and($log->branch_id)->toBe($this->branchA->id)
        ->and($log->division_id)->toBe($this->divisionA2->id)
        ->and($log->new_values['name'])->toBe('First Aid, Ikeja')
        ->and($log->new_values['start_date'])->toBe(today()->addDays(20)->toDateString());
});

it('lets a national admin create a national course or one for any branch', function () {
    $this->actingAs($this->national)->post(route('courses.store'), coursePayload(['name' => 'National ToT']))
        ->assertSessionHasNoErrors();
    $this->actingAs($this->national)->post(route('courses.store'), coursePayload([
        'name' => 'Beta course',
        'branch_id' => $this->branchB->id,
        'division_id' => $this->divisionB1->id,
    ]))->assertSessionHasNoErrors();

    expect(Course::where('name', 'National ToT')->sole()->branch_id)->toBeNull()
        ->and(Course::where('name', 'Beta course')->sole()->division_id)->toBe($this->divisionB1->id);
});

it('rejects a division outside the organising branch, a national course with a division, and bad dates', function () {
    $this->actingAs($this->branchAdmin)
        ->post(route('courses.store'), coursePayload(['division_id' => $this->divisionB1->id]))
        ->assertSessionHasErrors('division_id');

    $this->actingAs($this->national)
        ->post(route('courses.store'), coursePayload(['division_id' => $this->divisionA1->id]))
        ->assertSessionHasErrors('division_id');

    $this->actingAs($this->branchAdmin)
        ->post(route('courses.store'), coursePayload([
            'start_date' => today()->addDays(5)->toDateString(),
            'end_date' => today()->addDays(4)->toDateString(),
        ]))
        ->assertSessionHasErrors('end_date');

    $this->actingAs($this->branchAdmin)
        ->post(route('courses.store'), coursePayload(['training_type_id' => $this->inactiveType->id]))
        ->assertSessionHasErrors('training_type_id');

    expect(Course::count())->toBe(0);
});

it('does not let assistants create courses', function (string $who) {
    $user = $this->{$who};

    $this->actingAs($user)->get(route('courses.create'))->assertForbidden();
    $this->actingAs($user)->post(route('courses.store'), coursePayload())->assertForbidden();
    $this->actingAs($user)->get(route('courses.index'))->assertOk()->assertDontSee('New Course');

    expect(Course::count())->toBe(0);
})->with(['branchAssistant', 'divisionAssistant']);

/*
|--------------------------------------------------------------------------
| Edit and cancel
|--------------------------------------------------------------------------
*/

it('lets the organising branch edit, and logs only the changed fields', function () {
    $course = makeCourse(['name' => 'Before', 'place' => 'Hall']);

    $this->actingAs($this->branchAdmin)->get(route('courses.edit', $course))->assertOk()->assertSee('Before');

    $this->actingAs($this->branchAdmin)
        ->put(route('courses.update', $course), coursePayload([
            'name' => 'After',
            'start_date' => $course->start_date->toDateString(),
            'end_date' => $course->end_date->toDateString(),
            'place' => 'Hall',
            'external_facilitators' => null,
        ]))
        ->assertRedirect(route('courses.show', $course));

    $log = Log::where('action', 'course_updated')->sole();
    expect($course->fresh()->name)->toBe('After')
        ->and($log->old_values)->toBe(['name' => 'Before'])
        ->and($log->new_values)->toBe(['name' => 'After']);

    // Saving again without changes writes no entry.
    $this->actingAs($this->branchAdmin)
        ->put(route('courses.update', $course), coursePayload([
            'name' => 'After',
            'start_date' => $course->start_date->toDateString(),
            'end_date' => $course->end_date->toDateString(),
            'place' => 'Hall',
            'external_facilitators' => null,
        ]));
    expect(Log::where('action', 'course_updated')->count())->toBe(1);
});

it('keeps a course\'s inactive training type when it is edited', function () {
    $course = makeCourse(['training_type_id' => $this->inactiveType->id]);

    $this->actingAs($this->branchAdmin)
        ->put(route('courses.update', $course), coursePayload(['training_type_id' => $this->inactiveType->id]))
        ->assertSessionHasNoErrors();
});

it('does not let a branch admin edit or cancel another branch\'s course or a national course', function () {
    $courses = [makeCourse(['branch_id' => $this->branchB->id]), makeCourse(['branch_id' => null])];

    foreach ($courses as $course) {
        $this->actingAs($this->branchAdmin)->get(route('courses.edit', $course))->assertForbidden();
        $this->actingAs($this->branchAdmin)->put(route('courses.update', $course), coursePayload())->assertForbidden();
        $this->actingAs($this->branchAdmin)->post(route('courses.cancel', $course))->assertForbidden();
    }

    $this->actingAs($this->branchAdmin)->get(route('courses.show', $courses[1]))->assertOk()->assertDontSee('Cancel course');
    $this->actingAs($this->national)->get(route('courses.show', $courses[1]))->assertOk()->assertSee('Cancel course');
});

it('does not let assistants edit or cancel', function () {
    $course = makeCourse(['division_id' => $this->divisionA1->id]);

    $this->actingAs($this->divisionAssistant)->get(route('courses.edit', $course))->assertForbidden();
    $this->actingAs($this->branchAssistant)->post(route('courses.cancel', $course))->assertForbidden();
    $this->actingAs($this->branchAssistant)->get(route('courses.show', $course))->assertOk()->assertDontSee('Cancel course');
});

it('cancels a planned course once, after which it cannot be changed', function () {
    $course = makeCourse(['name' => 'To cancel']);

    $this->actingAs($this->branchAdmin)->post(route('courses.cancel', $course))
        ->assertRedirect(route('courses.show', $course));

    $course->refresh();
    expect($course->status)->toBe(Course::STATUS_CANCELLED)
        ->and($course->cancelled_by_user_id)->toBe($this->branchAdmin->id)
        ->and($course->cancelled_at)->not->toBeNull()
        ->and(Log::where('action', 'course_cancelled')->sole()->new_values)->toBe(['status' => 'cancelled']);

    $this->actingAs($this->branchAdmin)->get(route('courses.edit', $course))
        ->assertRedirect(route('courses.show', $course))
        ->assertSessionHas('error');
    $this->actingAs($this->branchAdmin)->put(route('courses.update', $course), coursePayload(['name' => 'Changed']))
        ->assertSessionHas('error');
    $this->actingAs($this->branchAdmin)->post(route('courses.cancel', $course))
        ->assertSessionHas('error');

    expect($course->fresh()->name)->toBe('To cancel')
        ->and(Log::where('action', 'course_cancelled')->count())->toBe(1);
});

it('shows Courses in the menu to everyone with view_courses', function () {
    $this->actingAs($this->divisionAssistant)
        ->get(route('courses.index'))
        ->assertOk()
        ->assertSee(route('courses.index'));
});
