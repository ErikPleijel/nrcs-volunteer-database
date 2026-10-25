<?php

/**
 * Server-side checks on the person certificate print routes (plain, branded,
 * branded portrait) and Mark as printed:
 * - every selected record must be in the viewer's own area (branch or division)
 * - competence certificates of an "HQ only" training type can only be printed
 *   by national roles; attendance certificates of such a type can be printed
 *   in the area as usual
 * - one disallowed ID refuses the whole request, nothing is printed or recorded
 * The index shows "Printed at HQ" only for HQ-only competence certificates.
 */

use App\Models\Branch;
use App\Models\CertificatePrint;
use App\Models\Division;
use App\Models\MembershipPayment;
use App\Models\Training;
use App\Models\TrainingType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const PRINT_ROUTES = [
    'certificates.bulk.print.plain',
    'certificates.bulk.print.branded',
    'certificates.bulk_print_branded_portrait',
];

const TRAINING_CERTIFICATES = ['training_competence', 'training_attendance'];

beforeEach(function () {
    $this->withoutVite();

    $permissions = ['manage-admin-panel', 'print_certificates', 'view_certificates'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    // Division roles do not print in production; one is given the permission
    // here only to cover the division-area rule.
    foreach (['national_db_administrator', 'branch_db_administrator', 'division_db_assistant_operations'] as $role) {
        Role::findOrCreate($role, 'web')->syncPermissions($permissions);
    }
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

    $this->divisionAdmin = User::factory()->inDivision($this->divisionA1)->create();
    $this->divisionAdmin->assignRole('division_db_assistant_operations');

    $this->branchType = TrainingType::factory()->create(['name' => 'Branch Course', 'certificate_hq_only' => false]);
    $this->hqType = TrainingType::factory()->create(['name' => 'HQ Course', 'certificate_hq_only' => true]);
});

function printableTraining(Division $division, TrainingType $type): Training
{
    $member = User::factory()->inDivision($division)->create();

    return Training::factory()->approved()->create([
        'user_id' => $member->id,
        'training_type_id' => $type->id,
        'branch_id' => $division->branch_id,
        'division_id' => $division->id,
        'training_date' => now()->subMonth()->toDateString(),
        'duration' => 2,
    ]);
}

function printRequest(string $type, array $ids): array
{
    return ['certificate_type' => $type, 'training_ids' => $ids];
}

/*
|--------------------------------------------------------------------------
| Area
|--------------------------------------------------------------------------
*/

it('lets a branch admin print training certificates from their own branch', function (string $routeName, string $type) {
    $training = printableTraining($this->divisionA2, $this->branchType);

    $this->actingAs($this->branchAdmin)
        ->post(route($routeName), printRequest($type, [$training->id]))
        ->assertOk()
        ->assertSee($training->user->full_name);
})->with(PRINT_ROUTES)->with(TRAINING_CERTIFICATES);

it('refuses a training certificate from another branch', function (string $routeName, string $type) {
    $training = printableTraining($this->divisionB1, $this->branchType);

    $this->actingAs($this->branchAdmin)
        ->post(route($routeName), printRequest($type, [$training->id]))
        ->assertForbidden();
})->with(PRINT_ROUTES)->with(TRAINING_CERTIFICATES);

it('refuses the whole selection when one record is outside the area', function (string $routeName) {
    $own = printableTraining($this->divisionA1, $this->branchType);
    $other = printableTraining($this->divisionB1, $this->branchType);

    $this->actingAs($this->branchAdmin)
        ->post(route($routeName), printRequest('training_competence', [$own->id, $other->id]))
        ->assertForbidden()
        ->assertDontSee($own->user->full_name);
})->with(PRINT_ROUTES);

it('limits a division-level viewer to their own division', function () {
    $own = printableTraining($this->divisionA1, $this->branchType);
    $sameBranch = printableTraining($this->divisionA2, $this->branchType);

    $this->actingAs($this->divisionAdmin)
        ->post(route('certificates.bulk.print.branded'), printRequest('training_competence', [$own->id]))
        ->assertOk();

    $this->actingAs($this->divisionAdmin)
        ->post(route('certificates.bulk.print.branded'), printRequest('training_competence', [$sameBranch->id]))
        ->assertForbidden();
});

it('refuses records that do not exist or are still pending', function () {
    $pending = printableTraining($this->divisionA1, $this->branchType);
    $pending->forceFill(['approval_status' => Training::PENDING])->save();

    $this->actingAs($this->national)
        ->post(route('certificates.bulk.print.branded'), printRequest('training_competence', [$pending->id]))
        ->assertForbidden();

    $this->actingAs($this->national)
        ->post(route('certificates.bulk.print.branded'), printRequest('training_competence', [999999]))
        ->assertForbidden();
});

it('refuses a membership certificate from another branch', function () {
    $own = MembershipPayment::factory()->approved()->create([
        'user_id' => User::factory()->inDivision($this->divisionA1)->create()->id,
        'branch_id' => $this->branchA->id,
        'division_id' => $this->divisionA1->id,
    ]);
    $other = MembershipPayment::factory()->approved()->create([
        'user_id' => User::factory()->inDivision($this->divisionB1)->create()->id,
        'branch_id' => $this->branchB->id,
        'division_id' => $this->divisionB1->id,
    ]);

    $this->actingAs($this->branchAdmin)
        ->post(route('certificates.bulk.print.branded'), printRequest('membership', [$own->id]))
        ->assertOk();

    $this->actingAs($this->branchAdmin)
        ->post(route('certificates.bulk.print.branded'), printRequest('membership', [$other->id]))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| HQ-only training types
|--------------------------------------------------------------------------
*/

it('refuses an HQ-only competence certificate to a branch admin', function (string $routeName) {
    $training = printableTraining($this->divisionA1, $this->hqType);

    $this->actingAs($this->branchAdmin)
        ->post(route($routeName), printRequest('training_competence', [$training->id]))
        ->assertForbidden();
})->with(PRINT_ROUTES);

it('lets a branch admin print an attendance certificate of an HQ-only type in their area', function (string $routeName) {
    $training = printableTraining($this->divisionA1, $this->hqType);

    $this->actingAs($this->branchAdmin)
        ->post(route($routeName), printRequest('training_attendance', [$training->id]))
        ->assertOk()
        ->assertSee($training->user->full_name);

    // The area rule still applies.
    $other = printableTraining($this->divisionB1, $this->hqType);
    $this->actingAs($this->branchAdmin)
        ->post(route($routeName), printRequest('training_attendance', [$other->id]))
        ->assertForbidden();
})->with(PRINT_ROUTES);

it('lets a national admin print an HQ-only training certificate', function (string $routeName, string $type) {
    $training = printableTraining($this->divisionB1, $this->hqType);

    $this->actingAs($this->national)
        ->post(route($routeName), printRequest($type, [$training->id]))
        ->assertOk()
        ->assertSee($training->user->full_name);
})->with(PRINT_ROUTES)->with(TRAINING_CERTIFICATES);

/*
|--------------------------------------------------------------------------
| Mark as printed
|--------------------------------------------------------------------------
*/

it('records prints only for allowed training certificates', function (string $type) {
    $own = printableTraining($this->divisionA1, $this->branchType);
    $other = printableTraining($this->divisionB1, $this->branchType);

    $this->actingAs($this->branchAdmin)
        ->postJson(route('certificates.mark-as-printed'), printRequest($type, [$own->id, $other->id]))
        ->assertForbidden();

    expect(CertificatePrint::count())->toBe(0);

    $this->actingAs($this->branchAdmin)
        ->postJson(route('certificates.mark-as-printed'), printRequest($type, [$own->id]))
        ->assertOk();

    expect(CertificatePrint::pluck('training_id')->all())->toBe([$own->id])
        ->and(CertificatePrint::first()->certificate_type)->toBe($type);
})->with(TRAINING_CERTIFICATES);

it('records HQ-only prints for branch admins only on attendance certificates', function () {
    $hqOnly = printableTraining($this->divisionA1, $this->hqType);

    $this->actingAs($this->branchAdmin)
        ->postJson(route('certificates.mark-as-printed'), printRequest('training_competence', [$hqOnly->id]))
        ->assertForbidden();

    expect(CertificatePrint::count())->toBe(0);

    $this->actingAs($this->branchAdmin)
        ->postJson(route('certificates.mark-as-printed'), printRequest('training_attendance', [$hqOnly->id]))
        ->assertOk();

    expect(CertificatePrint::pluck('certificate_type')->all())->toBe(['training_attendance']);
});

it('records volunteering and donation prints only for people in the area', function (string $type) {
    $own = User::factory()->inDivision($this->divisionA1)->create();
    $other = User::factory()->inDivision($this->divisionB1)->create();

    $this->actingAs($this->branchAdmin)
        ->postJson(route('certificates.mark-as-printed'), printRequest($type, [$own->id, $other->id]))
        ->assertForbidden();

    $this->actingAs($this->branchAdmin)
        ->postJson(route('certificates.mark-as-printed'), printRequest($type, [999999]))
        ->assertForbidden();

    expect(CertificatePrint::count())->toBe(0);

    $this->actingAs($this->branchAdmin)
        ->postJson(route('certificates.mark-as-printed'), printRequest($type, [$own->id]))
        ->assertOk();

    expect(CertificatePrint::pluck('user_id')->all())->toBe([$own->id]);
})->with(['volunteering', 'donation']);

/*
|--------------------------------------------------------------------------
| Index
|--------------------------------------------------------------------------
*/

it('shows HQ-only competence certificates as "Printed at HQ" without a checkbox to branch admins', function () {
    $training = printableTraining($this->divisionA1, $this->hqType);

    $checkbox = 'name="training_ids[]"'."\n".'                                               value="'.$training->id.'"';

    $this->actingAs($this->branchAdmin)
        ->get(route('certificates.index', ['certificate_type' => 'training_competence']))
        ->assertOk()
        ->assertSee('Printed<br>at HQ', false)
        ->assertDontSee($checkbox, false);

    $this->actingAs($this->national)
        ->get(route('certificates.index', ['certificate_type' => 'training_competence']))
        ->assertOk()
        ->assertDontSee('Printed<br>at HQ', false)
        ->assertSee($checkbox, false);
});

it('gives branch admins a checkbox for attendance certificates of an HQ-only type', function () {
    $training = printableTraining($this->divisionA1, $this->hqType);

    $checkbox = 'name="training_ids[]"'."\n".'                                               value="'.$training->id.'"';

    $this->actingAs($this->branchAdmin)
        ->get(route('certificates.index', ['certificate_type' => 'training_attendance']))
        ->assertOk()
        ->assertDontSee('Printed<br>at HQ', false)
        ->assertSee($checkbox, false);
});
