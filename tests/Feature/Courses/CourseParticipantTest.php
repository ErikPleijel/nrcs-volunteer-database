<?php

/**
 * Courses step 3: adding and removing participants and facilitators.
 * - organisers (national, or the organising branch) add anyone from any branch;
 *   other staff with manage_course_participants only people from their own
 *   branch or division (Course::canHandlePerson / addablePeopleFor)
 * - the person search is a JSON endpoint (courses.people.search): at least 2
 *   characters, up to 20 results, name, branch and division only
 * - archived, anonymized and System Administrator accounts are never offered;
 *   a person is on a course once
 * - everyone who sees the course sees all its people: name, branch and
 *   division only; the name links to the person's page only when allowed
 * - people can only be changed on a planned course
 * - audit log: course_participant_added / _removed, with the person's area
 */

use App\Models\Branch;
use App\Models\Course;
use App\Models\CourseParticipant;
use App\Models\Division;
use App\Models\Log;
use App\Models\TrainingType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
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
    Role::findOrCreate('division_db_assistant_operations', 'web')->syncPermissions($helpers);
    Role::findOrCreate('observer_national_level', 'web')->syncPermissions(['manage-admin-panel', 'view_user', 'view_courses']);
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

    $this->divisionAssistant = User::factory()->inDivision($this->divisionA1)->create();
    $this->divisionAssistant->assignRole('division_db_assistant_operations');

    $this->observer = User::factory()->create();
    $this->observer->assignRole('observer_national_level');

    $this->type = TrainingType::factory()->create();

    // Searchable people: all share the first name "Zebulon".
    $this->personA1 = User::factory()->inDivision($this->divisionA1)->create([
        'first_name' => 'Zebulon', 'last_name' => 'Aone', 'lifecycle_status' => 'active',
        'telephone1' => '08031112222', 'email' => 'aone@example.org',
    ]);
    $this->personA2 = User::factory()->inDivision($this->divisionA2)->create(['first_name' => 'Zebulon', 'last_name' => 'Atwo', 'lifecycle_status' => 'active']);
    $this->personB1 = User::factory()->inDivision($this->divisionB1)->create(['first_name' => 'Zebulon', 'last_name' => 'Bone', 'lifecycle_status' => 'dormant']);
});

function participantCourse(array $attributes = []): Course
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

function searchPeople(User $actor, Course $course, string $term)
{
    return test()->actingAs($actor)->getJson(route('courses.people.search', [$course, 'q' => $term]));
}

/** Last names of the people the search offers. */
function foundNames(User $actor, Course $course, string $term = 'Zebulon'): array
{
    $ids = searchPeople($actor, $course, $term)->assertOk()->json('results.*.id');

    return User::whereIn('id', $ids)->pluck('last_name')->sort()->values()->all();
}

function addPerson(User $actor, Course $course, User $person, string $role = 'participant')
{
    return test()->actingAs($actor)
        ->from(route('courses.show', $course))
        ->post(route('courses.participants.store', $course), ['user_id' => $person->id, 'role' => $role]);
}

/*
|--------------------------------------------------------------------------
| Who can be found and added
|--------------------------------------------------------------------------
*/

it('lets the organising branch find and add people from any branch', function () {
    $course = participantCourse();

    expect(foundNames($this->branchAdmin, $course))
        ->toBe(['Aone', 'Atwo', 'Bone']);

    addPerson($this->branchAdmin, $course, $this->personB1)
        ->assertRedirect(route('courses.show', $course))
        ->assertSessionHas('success');
    addPerson($this->branchAdmin, $course, $this->personA2, 'facilitator')->assertSessionHas('success');

    expect($course->participants->pluck('id')->all())->toBe([$this->personB1->id])
        ->and($course->facilitators->pluck('id')->all())->toBe([$this->personA2->id])
        ->and(CourseParticipant::where('user_id', $this->personB1->id)->sole()->added_by_user_id)->toBe($this->branchAdmin->id);

    $log = Log::where('action', 'course_participant_added')->where('subject_id', $course->id)->orderBy('id')->first();
    expect($log->branch_id)->toBe($this->branchB->id)
        ->and($log->division_id)->toBe($this->divisionB1->id)
        ->and($log->new_values)->toBe(['user_id' => $this->personB1->id, 'role' => 'participant']);

    // Someone already on the course is not offered again.
    expect(foundNames($this->branchAdmin, $course))
        ->toBe(['Aone']);
});

it('lets branch staff add only their own branch\'s people to a national course', function () {
    $course = participantCourse(['branch_id' => null]);

    expect(foundNames($this->branchAdmin, $course))
        ->toBe(['Aone', 'Atwo']);

    addPerson($this->branchAdmin, $course, $this->personB1)->assertSessionHas('error', 'This person cannot be added to the course.');
    addPerson($this->branchAdmin, $course, $this->personA1)->assertSessionHas('success');

    expect($course->courseParticipants()->pluck('user_id')->all())->toBe([$this->personA1->id]);

    // The national admin organises it and may add anyone.
    addPerson($this->national, $course, $this->personB1)->assertSessionHas('success');
});

it('lets a division assistant add and remove only their own division\'s people', function () {
    $course = participantCourse();

    expect(foundNames($this->divisionAssistant, $course))
        ->toBe(['Aone']);

    addPerson($this->divisionAssistant, $course, $this->personA2)->assertSessionHas('error');
    addPerson($this->divisionAssistant, $course, $this->personA1)->assertSessionHas('success');

    $own = CourseParticipant::where('user_id', $this->personA1->id)->sole();
    $other = $course->courseParticipants()->create(['user_id' => $this->personA2->id]);

    $this->actingAs($this->divisionAssistant)
        ->delete(route('courses.participants.destroy', [$course, $other]))
        ->assertForbidden();

    $this->actingAs($this->divisionAssistant)
        ->from(route('courses.show', $course))
        ->delete(route('courses.participants.destroy', [$course, $own]))
        ->assertSessionHas('success');

    expect($course->courseParticipants()->pluck('user_id')->all())->toBe([$this->personA2->id]);

    $log = Log::where('action', 'course_participant_removed')->sole();
    expect($log->old_values)->toBe(['user_id' => $this->personA1->id, 'role' => 'participant'])
        ->and($log->division_id)->toBe($this->divisionA1->id);
});

it('never offers archived, anonymized or System Administrator accounts', function () {
    $course = participantCourse();
    $archived = User::factory()->inDivision($this->divisionA1)->create(['first_name' => 'Zebulon', 'last_name' => 'Archived', 'lifecycle_status' => 'archived']);
    $anonymized = User::factory()->inDivision($this->divisionA1)->create(['first_name' => 'Zebulon', 'last_name' => 'Anon', 'lifecycle_status' => 'active', 'anonymized_at' => now()]);
    $pending = User::factory()->inDivision($this->divisionA1)->create(['first_name' => 'Zebulon', 'last_name' => 'Pending', 'lifecycle_status' => 'pending_engagement']);
    $superAdmin = User::factory()->inDivision($this->divisionA1)->create(['first_name' => 'Zebulon', 'last_name' => 'Super', 'lifecycle_status' => 'active', 'is_super_admin' => true]);

    expect(foundNames($this->national, $course))
        ->toBe(['Aone', 'Atwo', 'Bone', 'Pending']);

    foreach ([$archived, $anonymized, $superAdmin] as $person) {
        addPerson($this->national, $course, $person)->assertSessionHas('error');
    }
    expect($course->courseParticipants()->count())->toBe(0);
});

it('puts a person on a course once', function () {
    $course = participantCourse();

    addPerson($this->branchAdmin, $course, $this->personA1)->assertSessionHas('success');
    addPerson($this->branchAdmin, $course, $this->personA1, 'facilitator')
        ->assertSessionHas('error', 'This person is already on the course.');

    expect($course->courseParticipants()->count())->toBe(1)
        ->and(Log::where('action', 'course_participant_added')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Who sees the people
|--------------------------------------------------------------------------
*/

it('shows everyone who sees the course all its people, with name, branch and division only', function () {
    $course = participantCourse(['branch_id' => null]);
    $course->courseParticipants()->create(['user_id' => $this->personA1->id]);
    $course->courseParticipants()->create(['user_id' => $this->personB1->id, 'role' => 'facilitator']);

    foreach ([$this->observer, $this->branchAdmin, $this->divisionAssistant] as $viewer) {
        $response = $this->actingAs($viewer)->get(route('courses.show', $course))->assertOk();

        $response->assertSee('Zebulon Aone')
            ->assertSee('Zebulon Bone')
            ->assertSee('Beta One')
            ->assertDontSee('08031112222')
            ->assertDontSee('aone@example.org');

        expect($response->viewData('participants')->pluck('user_id')->all())->toBe([$this->personA1->id])
            ->and($response->viewData('facilitators')->pluck('user_id')->all())->toBe([$this->personB1->id]);
    }

    // The name links to the person's page only when the viewer may open it.
    $this->actingAs($this->branchAdmin)->get(route('courses.show', $course))
        ->assertSee(route('users.show', $this->personA1->id))
        ->assertDontSee(route('users.show', $this->personB1->id));
});

it('gives staff without manage_course_participants no search and no changes', function () {
    $course = participantCourse();
    $entry = $course->courseParticipants()->create(['user_id' => $this->personA1->id]);

    $this->actingAs($this->observer)->get(route('courses.show', [$course, 'person_search' => 'Zebulon']))
        ->assertOk()
        ->assertDontSee('Add people')
        ->assertDontSee('Remove');

    searchPeople($this->observer, $course, 'Zebulon')->assertForbidden();

    addPerson($this->observer, $course, $this->personA2)->assertForbidden();
    $this->actingAs($this->observer)->delete(route('courses.participants.destroy', [$course, $entry]))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Course state and area
|--------------------------------------------------------------------------
*/

it('changes people only on a planned course', function (string $status) {
    $course = participantCourse(['status' => $status]);
    $entry = $course->courseParticipants()->create(['user_id' => $this->personA1->id]);

    $this->actingAs($this->branchAdmin)->get(route('courses.show', $course))
        ->assertOk()
        ->assertDontSee('Add people')
        ->assertDontSee('Remove');

    searchPeople($this->branchAdmin, $course, 'Zebulon')->assertForbidden();
    addPerson($this->branchAdmin, $course, $this->personA2)->assertForbidden();
    $this->actingAs($this->branchAdmin)->delete(route('courses.participants.destroy', [$course, $entry]))->assertForbidden();

    expect($course->courseParticipants()->count())->toBe(1);
})->with([Course::STATUS_CLOSED, Course::STATUS_CANCELLED]);

it('refuses a course outside the viewer\'s area and an entry of another course', function () {
    $otherBranchCourse = participantCourse(['branch_id' => $this->branchB->id]);
    $course = participantCourse();
    $entryElsewhere = participantCourse()->courseParticipants()->create(['user_id' => $this->personA1->id]);

    searchPeople($this->branchAdmin, $otherBranchCourse, 'Zebulon')->assertForbidden();
    addPerson($this->branchAdmin, $otherBranchCourse, $this->personA1)->assertForbidden();

    $this->actingAs($this->branchAdmin)
        ->delete(route('courses.participants.destroy', [$course, $entryElsewhere]))
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| The search endpoint
|--------------------------------------------------------------------------
*/

it('returns name, branch and division only', function () {
    $course = participantCourse();

    $response = searchPeople($this->branchAdmin, $course, 'Aone')->assertOk();

    expect($response->json('results'))->toBe([[
        'id' => $this->personA1->id,
        'name' => $this->personA1->full_name,
        'branch' => 'Alpha Branch',
        'division' => 'Alpha One',
    ]]);
    $response->assertDontSee('08031112222')->assertDontSee('aone@example.org');

    // A database ID finds the person too.
    expect(foundNames($this->branchAdmin, $course, 'DB-'.$this->personB1->id))->toBe(['Bone']);
});

it('needs at least 2 characters', function () {
    $course = participantCourse();

    foreach (['', ' ', 'Z', ' Z '] as $term) {
        searchPeople($this->national, $course, $term)->assertOk()->assertExactJson(['results' => []]);
    }

    expect(foundNames($this->national, $course, 'Zebul'))->toBe(['Aone', 'Atwo', 'Bone']);
});

it('returns at most 20 people', function () {
    $course = participantCourse();
    User::factory()->count(22)->inDivision($this->divisionA1)->create(['first_name' => 'Zebulon', 'lifecycle_status' => 'active']);

    expect(searchPeople($this->national, $course, 'Zebulon')->assertOk()->json('results'))->toHaveCount(20);
});

it('fills the search box again from the address after adding someone', function () {
    $course = participantCourse();

    $this->actingAs($this->branchAdmin)->get(route('courses.show', [$course, 'person_search' => 'Zebulon']))
        ->assertOk()
        ->assertSee(Js::from(route('courses.people.search', $course))->toHtml(), false)
        ->assertSee('coursePeopleSearch(', false)
        ->assertSee(Js::from('Zebulon')->toHtml(), false);
});
