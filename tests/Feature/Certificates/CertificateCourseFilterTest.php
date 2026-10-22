<?php

/**
 * Courses step 6: the "Course" filter on the certificates page.
 * - training certificates (competence and attendance) narrow to one course's records
 * - the area scope still applies: branch staff see their own branch's participants
 * - the dropdown lists closed courses the viewer can see that issued approved records
 * - a closed course's page links to its certificates
 */

use App\Models\Branch;
use App\Models\Course;
use App\Models\Division;
use App\Models\Training;
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

    $permissions = ['manage-admin-panel', 'print_certificates', 'view_certificates', 'view_courses', 'view_user'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    foreach (['national_db_administrator', 'branch_db_administrator'] as $role) {
        Role::findOrCreate($role, 'web')->syncPermissions($permissions);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branchA = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->branchB = Branch::create(['name' => 'Beta Branch', 'code' => 'BET']);
    $this->divisionA1 = Division::create(['name' => 'Alpha One', 'branch_id' => $this->branchA->id]);
    $this->divisionB1 = Division::create(['name' => 'Beta One', 'branch_id' => $this->branchB->id]);

    $this->national = User::factory()->create();
    $this->national->assignRole('national_db_administrator');
    $this->branchAdmin = User::factory()->inBranch($this->branchA)->create();
    $this->branchAdmin->assignRole('branch_db_administrator');

    $this->type = TrainingType::factory()->create(['name' => 'Standard First Aid', 'certificate_hq_only' => false]);

    $this->course = filterCourse('Alpha First Aid course');
    $this->recordA = courseRecord($this->course, $this->divisionA1);
    $this->recordB = courseRecord($this->course, $this->divisionB1);
    // Same training type, not from the course.
    $this->otherRecord = courseRecord(null, $this->divisionA1);
});

function filterCourse(string $name, array $attributes = []): Course
{
    return Course::create([
        'name' => $name,
        'training_type_id' => test()->type->id,
        'start_date' => '2025-12-01',
        'end_date' => '2025-12-03',
        'branch_id' => test()->branchA->id,
        'status' => Course::STATUS_CLOSED,
        'closed_at' => now(),
        ...$attributes,
    ]);
}

function courseRecord(?Course $course, Division $division, bool $approved = true): Training
{
    $factory = $approved ? Training::factory()->approved() : Training::factory();

    return $factory->create([
        'user_id' => User::factory()->inDivision($division)->create()->id,
        'training_type_id' => test()->type->id,
        'course_id' => $course?->id,
        'training_date' => '2025-12-01',
        'duration' => 3,
        'branch_id' => $division->branch_id,
        'division_id' => $division->id,
    ]);
}

function listedRecordIds($response): array
{
    return $response->viewData('records')->pluck('id')->sort()->values()->all();
}

it('narrows training certificates to one course', function (string $type) {
    $response = $this->actingAs($this->national)
        ->get(route('certificates.index', ['certificate_type' => $type, 'course_id' => $this->course->id]))
        ->assertOk();

    expect(listedRecordIds($response))->toBe([$this->recordA->id, $this->recordB->id]);

    expect(listedRecordIds($this->actingAs($this->national)->get(route('certificates.index', ['certificate_type' => $type]))))
        ->toBe([$this->recordA->id, $this->recordB->id, $this->otherRecord->id]);
})->with(['training_competence', 'training_attendance']);

it('keeps the area scope: branch staff see their own branch\'s participants', function () {
    $response = $this->actingAs($this->branchAdmin)
        ->get(route('certificates.index', ['certificate_type' => 'training_competence', 'course_id' => $this->course->id]));

    expect(listedRecordIds($response))->toBe([$this->recordA->id]);
});

it('lists closed courses the viewer can see that issued approved records', function () {
    $pendingOnly = filterCourse('Pending only course');
    courseRecord($pendingOnly, $this->divisionA1, approved: false);
    $planned = filterCourse('Planned course', ['status' => Course::STATUS_PLANNED, 'closed_at' => null]);
    $otherBranch = filterCourse('Beta course', ['branch_id' => $this->branchB->id]);
    courseRecord($otherBranch, $this->divisionB1);
    $national = filterCourse('National course', ['branch_id' => null]);
    courseRecord($national, $this->divisionA1);

    $listed = fn (User $viewer) => $this->actingAs($viewer)
        ->get(route('certificates.index', ['certificate_type' => 'training_competence']))
        ->viewData('courses')->pluck('name')->sort()->values()->all();

    expect($listed($this->branchAdmin))->toBe(['Alpha First Aid course', 'National course'])
        ->and($listed($this->national))->toBe(['Alpha First Aid course', 'Beta course', 'National course']);

    $this->actingAs($this->branchAdmin)
        ->get(route('certificates.index', ['certificate_type' => 'training_competence']))
        ->assertSee('Alpha First Aid course (1 – 3 Dec 2025)');
});

it('ignores the course filter for other certificate types', function () {
    $this->actingAs($this->national)
        ->get(route('certificates.index', ['certificate_type' => 'volunteering', 'course_id' => $this->course->id]))
        ->assertOk();
});

it('links a closed course to its certificates once records are approved', function () {
    $link = route('certificates.index', ['certificate_type' => 'training_competence', 'course_id' => $this->course->id]);

    $this->actingAs($this->branchAdmin)->get(route('courses.show', $this->course))
        ->assertOk()
        ->assertSee($link);

    $pendingOnly = filterCourse('Pending only course');
    courseRecord($pendingOnly, $this->divisionA1, approved: false);

    $this->actingAs($this->branchAdmin)->get(route('courses.show', $pendingOnly))
        ->assertOk()
        ->assertDontSee('course_id='.$pendingOnly->id);
});
