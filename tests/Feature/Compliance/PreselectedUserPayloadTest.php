<?php

/**
 * The four "create" pages (activity, donation, membership payment, training)
 * embed the preselected member as `const preselectedUser = {...}` for the
 * page JavaScript. Only the fields selectUser() reads may be embedded — never
 * the full model, whose serialisation includes the decrypted NIN and
 * personal_info.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $permissions = ['manage-admin-panel', 'add_volunteering', 'add_donations', 'add_payments', 'add_trainings'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Admin']);
    $this->admin->assignRole('national_db_administrator');

    $branch = Branch::create(['name' => 'Kano', 'code' => 'KAN']);
    $division = Division::create(['name' => 'Nassarawa', 'branch_id' => $branch->id]);

    $this->member = User::factory()->create([
        'first_name' => 'Zainab',
        'middle_name' => 'Halima',
        'last_name' => 'Preselectova',
        'national_id_number' => '73918264051',
        'personal_info' => 'Private note about the member',
        'branch_id' => $branch->id,
        'division_id' => $division->id,
    ]);
});

/**
 * The decoded `const preselectedUser = ...;` object from the page source.
 */
function preselectedUserPayload(string $html): ?array
{
    expect(preg_match('/const preselectedUser = (.*);/', $html, $m))->toBe(1);

    return json_decode($m[1], true);
}

dataset('create pages', [
    'activities' => ['activities.create', ['id', 'first_name', 'middle_name', 'last_name', 'branch_id', 'division_id', 'branch', 'division', 'red_cross_unit', 'task_forces']],
    'donations' => ['donations.create', ['id', 'first_name', 'middle_name', 'last_name', 'branch_id', 'division_id', 'branch', 'division']],
    'membership payments' => ['membership-payments.create', ['id', 'first_name', 'middle_name', 'last_name', 'branch_id', 'division_id', 'branch', 'division', 'red_cross_unit_id', 'rcu_name', 'in_active_unit', 'left_unit']],
    'trainings' => ['trainings.create', ['id', 'first_name', 'middle_name', 'last_name', 'branch_id', 'division_id', 'branch', 'division', 'red_cross_unit']],
]);

test('the page source holds the preselected member\'s name but not their NIN or personal info', function (string $route, array $expectedKeys) {
    $response = $this->actingAs($this->admin)->get(route($route, $this->member));

    $response->assertOk()
        ->assertDontSee('73918264051')
        ->assertDontSee('Private note about the member')
        ->assertSee('Zainab')
        ->assertSee('Preselectova');

    $payload = preselectedUserPayload($response->getContent());

    expect(array_keys($payload))->toEqualCanonicalizing($expectedKeys)
        ->and($payload['id'])->toBe($this->member->id)
        ->and($payload['first_name'])->toBe('Zainab')
        ->and($payload['middle_name'])->toBe('Halima')
        ->and($payload['last_name'])->toBe('Preselectova')
        ->and($payload['branch'])->toMatchArray(['name' => 'Kano', 'code' => 'KAN'])
        ->and($payload['division'])->toMatchArray(['name' => 'Nassarawa']);
})->with('create pages');

test('without a preselected member the payload is null', function (string $route) {
    $response = $this->actingAs($this->admin)->get(route($route));

    $response->assertOk();
    expect(preselectedUserPayload($response->getContent()))->toBeNull();
})->with('create pages');
