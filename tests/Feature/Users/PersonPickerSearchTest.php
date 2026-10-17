<?php

/**
 * The person pickers outside the users list — role assignment
 * (users.search-for-roles) and task force members
 * (task-forces.search-users) — follow the same search rules
 * (UserFilterService::applySearch()): digits are a DB code / phone / NIN and
 * never match an email, text without '@' searches names only, and '@'
 * matches email from the start of the address.
 */

use App\Models\Branch;
use App\Models\RedCrossUnit;
use App\Models\TaskForce;
use App\Models\TaskForceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $permissions = ['manage-admin-panel', 'manage_roles_and_permissions', 'edit_task_force'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    // Volunteers, so the task force search (User::volunteers()) can see them too.
    $unit = RedCrossUnit::create(['name' => 'Picker Unit', 'is_active' => true]);
    $this->named = User::factory()->create([
        'first_name' => 'Zebedee', 'last_name' => 'Pickerson', 'email' => 'zebedee@example.org',
        'red_cross_unit_id' => $unit->id,
    ]);
    $this->digitsInEmail = User::factory()->create([
        'first_name' => 'Digitmail', 'last_name' => 'Person', 'email' => 'samuelpat1234567892468@gmail.com',
        'red_cross_unit_id' => $unit->id,
    ]);

    $type = TaskForceType::create(['name' => 'Picker Type', 'level' => 1]);
    $branch = Branch::create(['name' => 'Picker', 'code' => 'PCK']);
    $this->taskForce = TaskForce::create(['name' => 'Picker Task Force', 'task_force_type_id' => $type->id, 'branch_id' => $branch->id]);
});

/**
 * The ids a picker returns for $search.
 */
function pickerIds($test, string $picker, string $search): array
{
    $request = $test->actingAs($test->admin)->withSession(['auth.password_confirmed_at' => time()]);

    $response = $picker === 'roles'
        ? $request->getJson(route('users.search-for-roles', ['search' => $search]))
        : $request->getJson(route('task-forces.search-users', ['task_force' => $test->taskForce, 'query' => $search]));

    return collect($response->assertOk()->json())->pluck('id')->all();
}

dataset('pickers', ['roles', 'task force']);

test('digits never match an email', function (string $picker) {
    expect(pickerIds($this, $picker, '12345'))->toBe([])
        ->and(pickerIds($this, $picker, '92468'))->toBe([]);
})->with('pickers');

test('a DB code finds exactly that person', function (string $picker) {
    expect(pickerIds($this, $picker, (string) $this->digitsInEmail->id))->toBe([$this->digitsInEmail->id])
        ->and(pickerIds($this, $picker, 'DB-'.$this->named->id))->toBe([$this->named->id]);
})->with('pickers');

test('text without @ searches names only, never email', function (string $picker) {
    expect(pickerIds($this, $picker, 'zebedee pick'))->toBe([$this->named->id])
        ->and(pickerIds($this, $picker, 'samuelpat'))->toBe([])
        ->and(pickerIds($this, $picker, 'gmail'))->toBe([]);
})->with('pickers');

test('@ matches email from the start only', function (string $picker) {
    expect(pickerIds($this, $picker, 'samuelpat@'))->toBe([])
        ->and(pickerIds($this, $picker, 'samuelpat1234567892468@'))->toBe([$this->digitsInEmail->id])
        ->and(pickerIds($this, $picker, 'zebedee@example.org'))->toBe([$this->named->id])
        ->and(pickerIds($this, $picker, '@gmail.com'))->toBe([])
        ->and(pickerIds($this, $picker, '@example.org'))->toBe([]);
})->with('pickers');
