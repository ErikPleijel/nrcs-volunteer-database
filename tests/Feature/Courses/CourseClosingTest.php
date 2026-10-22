<?php

/**
 * Courses step 5: closing a course and "Approve all from this course".
 * - close from the end date (last day included), only when every participant
 *   has a result, only by the organisers (edit_courses + isManageableBy)
 * - one PENDING training record per passed participant: course start date,
 *   duration counting both first and last day, validity from the type, the
 *   participant's own branch/division, submitted by the person who closed it
 * - once only; failed/absent get no record
 * - approve all: approvers of the organising branch (also for participants
 *   from other branches) or national; not the person who closed the course
 * - the certificate of a 3-day course reads "from <start> to <end>"
 */

use App\Models\Branch;
use App\Models\Course;
use App\Models\CourseParticipant;
use App\Models\Division;
use App\Models\Log;
use App\Models\Training;
use App\Models\TrainingType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $managers = ['manage-admin-panel', 'view_user', 'view_courses', 'add_courses', 'edit_courses', 'manage_course_participants', 'view_trainings'];
    $all = [...$managers, 'approve_training', 'view_certificates', 'print_certificates'];
    foreach ($all as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    // Mirrors PermissionsTableSeeder for the parts used here.
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($all);
    Role::findOrCreate('national_db_assistant', 'web')->syncPermissions($managers);
    Role::findOrCreate('branch_secretary', 'web')->syncPermissions([...$managers, 'approve_training']);
    Role::findOrCreate('branch_db_administrator', 'web')->syncPermissions([...$managers, 'approve_training']);
    Role::findOrCreate('branch_db_assistant', 'web')->syncPermissions(['manage-admin-panel', 'view_courses', 'manage_course_participants']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branchA = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->branchB = Branch::create(['name' => 'Beta Branch', 'code' => 'BET']);
    $this->divisionA1 = Division::create(['name' => 'Alpha One', 'branch_id' => $this->branchA->id]);
    $this->divisionB1 = Division::create(['name' => 'Beta One', 'branch_id' => $this->branchB->id]);

    $this->national = User::factory()->create();
    $this->national->assignRole('national_db_administrator');
    $this->nationalAssistant = User::factory()->create();
    $this->nationalAssistant->assignRole('national_db_assistant');

    $this->closer = User::factory()->inBranch($this->branchA)->create();
    $this->closer->assignRole('branch_db_administrator');
    $this->secretaryA = User::factory()->inBranch($this->branchA)->create();
    $this->secretaryA->assignRole('branch_secretary');
    $this->secretaryB = User::factory()->inBranch($this->branchB)->create();
    $this->secretaryB->assignRole('branch_secretary');
    $this->assistantA = User::factory()->inBranch($this->branchA)->create();
    $this->assistantA->assignRole('branch_db_assistant');

    $this->type = TrainingType::factory()->create(['name' => 'Standard First Aid', 'validity_years_limit' => 2]);
});

/**
 * A branch-A course from 1 to 3 Dec 2025 with Ann (A, passed), Ben (B, passed),
 * Cat (A, failed), Dan (A, absent) and a facilitator.
 */
function closableCourse(array $attributes = []): Course
{
    $course = Course::create([
        'name' => 'First Aid course Alpha',
        'training_type_id' => test()->type->id,
        'start_date' => '2025-12-01',
        'end_date' => '2025-12-03',
        'branch_id' => test()->branchA->id,
        ...$attributes,
    ]);

    $people = [
        'ann' => [test()->divisionA1, 'passed'],
        'ben' => [test()->divisionB1, 'passed'],
        'cat' => [test()->divisionA1, 'failed'],
        'dan' => [test()->divisionA1, 'absent'],
    ];
    foreach ($people as $name => [$division, $result]) {
        test()->{$name} = User::factory()->inDivision($division)->create(['first_name' => ucfirst($name), 'last_name' => 'Tester', 'lifecycle_status' => 'active']);
        $course->courseParticipants()->create(['user_id' => test()->{$name}->id, 'result' => $result]);
    }
    $course->courseParticipants()->create([
        'user_id' => User::factory()->inDivision(test()->divisionA1)->create()->id,
        'role' => CourseParticipant::ROLE_FACILITATOR,
    ]);

    return $course;
}

function closeCourse(User $actor, Course $course)
{
    return test()->actingAs($actor)->post(route('courses.close', $course));
}

function courseRecords(Course $course)
{
    return Training::withAnyApprovalStatus()->where('course_id', $course->id)->orderBy('user_id')->get();
}

/*
|--------------------------------------------------------------------------
| Closing
|--------------------------------------------------------------------------
*/

it('creates one pending training record per passed participant', function () {
    $course = closableCourse();

    closeCourse($this->closer, $course)
        ->assertRedirect(route('courses.show', $course))
        ->assertSessionHas('success', 'Course closed. 2 training record(s) were created and now wait for approval.');

    $records = courseRecords($course);
    expect($records->pluck('user_id')->all())->toBe([$this->ann->id, $this->ben->id]);

    $ben = $records->firstWhere('user_id', $this->ben->id);
    expect($ben->approval_status)->toBe(Training::PENDING)
        ->and($ben->training_type_id)->toBe($this->type->id)
        ->and($ben->training_date->toDateString())->toBe('2025-12-01')
        ->and($ben->duration)->toBe(3)
        ->and($ben->valid_years)->toBe(2)
        ->and($ben->branch_id)->toBe($this->branchB->id)
        ->and($ben->division_id)->toBe($this->divisionB1->id)
        ->and($ben->submitted_by_user_id)->toBe($this->closer->id)
        ->and($ben->submission_name)->toBe($this->closer->full_name)
        ->and($ben->reference)->toBe('First Aid course Alpha')
        ->and($ben->is_deleted)->toBeFalse();

    $course->refresh();
    expect($course->status)->toBe(Course::STATUS_CLOSED)
        ->and($course->closed_by_user_id)->toBe($this->closer->id)
        ->and($course->closed_at)->not->toBeNull()
        ->and(CourseParticipant::where('user_id', $this->ben->id)->sole()->training_id)->toBe($ben->id)
        ->and(CourseParticipant::where('user_id', $this->cat->id)->sole()->training_id)->toBeNull();

    $log = Log::where('action', 'course_closed')->sole();
    expect($log->new_values)->toBe([
        'status' => 'closed', 'passed' => 2, 'failed' => 1, 'absent' => 1, 'training_records_created' => 2,
    ])->and($log->branch_id)->toBe($this->branchA->id);

    // The records are pending, so they don't count yet.
    expect(Training::where('course_id', $course->id)->count())->toBe(0);
});

it('closes only once', function () {
    $course = closableCourse();

    closeCourse($this->closer, $course)->assertSessionHas('success');
    closeCourse($this->closer, $course)->assertSessionHas('error', 'Only a planned course can be closed.');
    closeCourse($this->national, $course)->assertSessionHas('error');

    expect(courseRecords($course))->toHaveCount(2)
        ->and(Log::where('action', 'course_closed')->count())->toBe(1);
});

it('closes from the end date, the last day included', function () {
    Carbon::setTestNow('2025-12-02 10:00');
    $course = closableCourse();

    closeCourse($this->closer, $course)->assertSessionHas('error', 'The course can be closed from its end date, 3 Dec 2025.');
    $this->actingAs($this->closer)->get(route('courses.show', $course))
        ->assertSee('The course can be closed from its end date')
        ->assertDontSee('Close course</button>', false);

    Carbon::setTestNow('2025-12-03 08:00');
    closeCourse($this->closer, $course)->assertSessionHas('success');

    Carbon::setTestNow();
});

it('needs a result for every participant', function () {
    $course = closableCourse();
    CourseParticipant::where('user_id', $this->cat->id)->update(['result' => null]);
    CourseParticipant::where('user_id', $this->dan->id)->update(['result' => null]);

    closeCourse($this->closer, $course)->assertSessionHas('error', '2 participants have no result yet.');

    expect($course->fresh()->status)->toBe(Course::STATUS_PLANNED)
        ->and(courseRecords($course))->toHaveCount(0);
});

it('closes a course where no one passed without creating records', function () {
    $course = closableCourse();
    $course->courseParticipants()->where('result', 'passed')->update(['result' => 'failed']);

    closeCourse($this->closer, $course)
        ->assertSessionHas('success', 'Course closed. No one passed, so no training records were created.');

    expect($course->fresh()->isClosed())->toBeTrue()
        ->and(courseRecords($course))->toHaveCount(0);
});

it('lets only the organisers close a course', function () {
    $course = closableCourse();
    $national = closableCourse(['branch_id' => null, 'name' => 'National course']);

    closeCourse($this->assistantA, $course)->assertForbidden();
    closeCourse($this->secretaryB, $course)->assertForbidden();
    closeCourse($this->closer, $national)->assertForbidden();

    expect($course->fresh()->isPlanned())->toBeTrue();

    closeCourse($this->nationalAssistant, $national)->assertSessionHas('success');
});

/*
|--------------------------------------------------------------------------
| Approve all from this course
|--------------------------------------------------------------------------
*/

it('lets another approver of the organising branch approve all records, also from other branches', function () {
    $course = closableCourse();
    closeCourse($this->closer, $course);

    $this->actingAs($this->secretaryA)->get(route('courses.show', $course))
        ->assertSee('Training records waiting for approval (2)')
        ->assertSee('Approve all from this course');

    $this->actingAs($this->secretaryA)->post(route('courses.approve-records', $course))
        ->assertRedirect(route('courses.show', $course))
        ->assertSessionHas('success', '2 training records approved.');

    $records = courseRecords($course);
    expect($records->pluck('approval_status')->unique()->all())->toBe([Training::APPROVED])
        ->and($records->pluck('decided_by_user_id')->unique()->all())->toBe([$this->secretaryA->id])
        ->and(Training::where('course_id', $course->id)->count())->toBe(2);
});

it('does not let the person who closed the course approve its records', function () {
    $course = closableCourse();
    closeCourse($this->closer, $course);

    $this->actingAs($this->closer)->get(route('courses.show', $course))
        ->assertSee('You closed this course, so another approver must approve its training records.')
        ->assertDontSee('Approve all from this course</button>', false);

    $this->actingAs($this->closer)->post(route('courses.approve-records', $course))
        ->assertSessionHas('error');

    expect(courseRecords($course)->pluck('approval_status')->unique()->all())->toBe([Training::PENDING]);
});

it('does not let approvers of a participant\'s branch use approve all on another branch\'s course', function () {
    $course = closableCourse();
    closeCourse($this->closer, $course);

    $this->actingAs($this->secretaryB)->post(route('courses.approve-records', $course))->assertForbidden();
    $this->actingAs($this->assistantA)->post(route('courses.approve-records', $course))->assertForbidden();

    // Their own branch's record is still in their normal approvals.
    $ben = courseRecords($course)->firstWhere('user_id', $this->ben->id);
    $this->actingAs($this->secretaryB)
        ->from(route('trainings.approvals'))
        ->post(route('trainings.approve', $ben->id))
        ->assertSessionHas('success');
    expect($ben->fresh()->approval_status)->toBe(Training::APPROVED);
});

it('lets only national approvers approve all on a national course', function () {
    $course = closableCourse(['branch_id' => null]);
    closeCourse($this->nationalAssistant, $course);

    $this->actingAs($this->secretaryA)->post(route('courses.approve-records', $course))->assertForbidden();
    $this->actingAs($this->national)->post(route('courses.approve-records', $course))->assertSessionHas('success', '2 training records approved.');
});

it('skips an archived member', function () {
    $course = closableCourse();
    closeCourse($this->closer, $course);
    $this->ben->update(['lifecycle_status' => 'archived']);

    $this->actingAs($this->secretaryA)->post(route('courses.approve-records', $course))
        ->assertSessionHas('success', '1 training record approved.')
        ->assertSessionHas('skipped', ['Ben Tester (archived: approve on its own review page)']);

    expect(courseRecords($course)->firstWhere('user_id', $this->ben->id)->approval_status)->toBe(Training::PENDING);
});

/*
|--------------------------------------------------------------------------
| Certificate
|--------------------------------------------------------------------------
*/

it('prints "from <start> to <end>" on the certificate of a 3-day course', function (string $type) {
    $course = closableCourse();
    closeCourse($this->closer, $course);
    $this->actingAs($this->secretaryA)->post(route('courses.approve-records', $course));

    $ann = courseRecords($course)->firstWhere('user_id', $this->ann->id);

    $this->actingAs($this->national)
        ->post(route('certificates.bulk.print.branded'), ['certificate_type' => $type, 'training_ids' => [$ann->id]])
        ->assertOk()
        ->assertSee('from December 1, 2025 to December 3, 2025.');
})->with(['training_competence', 'training_attendance']);
