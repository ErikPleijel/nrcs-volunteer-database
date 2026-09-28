<?php

/**
 * National ID number (NIN): normalization, format, required-for-adults on
 * the two registration forms, optional-but-validated on the two edit
 * forms, uniqueness via the keyed national_id_number_hash column (the NIN
 * column itself is encrypted), the archived-account message, the DB
 * unique-index fallback, the migration's collision stop, and that neither
 * the hash nor the decrypted NIN leaks into JSON.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\RedCrossUnit;
use App\Models\TaskForce;
use App\Models\User;
use App\Rules\NationalIdNumberRule;
use App\Support\NationalIdNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const ADULT_BIRTH_YEAR = 1990;

function ninMinorBirthYear(): int
{
    return now()->year - 15;
}

function publicRegistrationPayload(Branch $branch, Division $division, array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Ada',
        'last_name' => 'Okafor',
        'email' => 'ada'.uniqid().'@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'telephone1' => '08031112222',
        'branch_id' => $branch->id,
        'division_id' => $division->id,
        'birth_year' => ADULT_BIRTH_YEAR,
        'gender' => 'female',
        'contribution_type' => 'member',
        'coc_commitment_1' => '1',
        'coc_commitment_2' => '1',
        'coc_commitment_3' => '1',
        'coc_commitment_4' => '1',
        // Bot traps: empty honeypot, form rendered well over 3 seconds ago.
        'website' => '',
        'form_rendered_at' => now()->timestamp - 60,
    ], $overrides);
}

function adminStorePayload(Branch $branch, Division $division, array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Musa',
        'last_name' => 'Bello',
        'gender' => 'male',
        'birth_year' => ADULT_BIRTH_YEAR,
        'telephone1' => '08044445555',
        'branch_id' => $branch->id,
        'division_id' => $division->id,
        'contribution_type' => 'member',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'admin_consent_confirmed' => '1',
        'admin_consent_form' => '1',
    ], $overrides);
}

function adminUpdatePayload(User $user, array $overrides = []): array
{
    return array_merge([
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'gender' => 'male',
        'birth_year' => ADULT_BIRTH_YEAR,
        'branch_id' => $user->branch_id,
        'division_id' => $user->division_id,
        'contribution_type' => 'member',
    ], $overrides);
}

function profileUpdatePayload(User $user, array $overrides = []): array
{
    return array_merge([
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'gender' => 'female',
        'birth_year' => ADULT_BIRTH_YEAR,
        'telephone1' => '08031112222',
        'branch_id' => $user->branch_id,
        'division_id' => $user->division_id,
        'contribution_type' => 'member',
    ], $overrides);
}

function ninError(): ?string
{
    return session('errors')?->get('national_id_number')[0] ?? null;
}

beforeEach(function () {
    $this->withoutVite();

    foreach (['manage-admin-panel', 'add_user', 'edit_user', 'edit_task_force'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions([
        'manage-admin-panel', 'add_user', 'edit_user', 'edit_task_force',
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);

    $this->admin = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);
    $this->admin->assignRole('national_db_administrator');
});

/*
|--------------------------------------------------------------------------
| Normalization and format
|--------------------------------------------------------------------------
*/

test('NationalIdNumber normalizes typed separators away', function (string $input, string $expected) {
    expect(NationalIdNumber::normalize($input))->toBe($expected);
})->with([
    ['12345678901', '12345678901'],
    ['123 456 789 01', '12345678901'],
    ['123-456-789-01', '12345678901'],
    ['123.456.789.01', '12345678901'],
    ["  123\u{00A0}456\u{2009}78901 ", '12345678901'],
    ["123\u{2013}456\u{2014}78901", '12345678901'],
    ["123\t456\n78901", '12345678901'],
]);

test('NationalIdNumber accepts exactly 11 ASCII digits only', function (string $input, bool $valid) {
    expect(NationalIdNumber::isValid(NationalIdNumber::normalize($input)))->toBe($valid);
})->with([
    ['12345678901', true],
    ['123 456 789 01', true],
    ['1234567890', false],      // 10 digits
    ['123456789012', false],    // 12 digits
    ['1234567890A', false],     // letter
    ['12345/678901', false],    // other punctuation isn't stripped
    ['١٢٣٤٥٦٧٨٩٠١', false],     // Arabic-Indic digits
    ['', false],
]);

test('the hash is keyed: it changes with NIN_HASH_KEY and is never a plain SHA-256', function () {
    $hash = NationalIdNumber::hash('12345678901');

    expect($hash)->toHaveLength(64)
        ->not->toBe(hash('sha256', '12345678901'));

    config(['app.nin_hash_key' => 'a-different-key']);
    expect(NationalIdNumber::hash('12345678901'))->not->toBe($hash);
});

test('hashing without NIN_HASH_KEY fails loudly', function () {
    config(['app.nin_hash_key' => '']);

    NationalIdNumber::hash('12345678901');
})->throws(RuntimeException::class, 'NIN_HASH_KEY is not set');

/*
|--------------------------------------------------------------------------
| Model: stored normalized, hash kept in step
|--------------------------------------------------------------------------
*/

test('saving a NIN stores it normalized and sets the hash; clearing it clears both', function () {
    $user = User::factory()->create(['national_id_number' => '123 456-789.01']);

    expect($user->fresh()->national_id_number)->toBe('12345678901')
        ->and($user->fresh()->national_id_number_hash)->toBe(NationalIdNumber::hash('12345678901'));

    $user->update(['national_id_number' => '']);

    expect($user->fresh()->national_id_number)->toBeNull()
        ->and($user->fresh()->national_id_number_hash)->toBeNull();
});

test('the hash column never appears in a User model JSON serialization', function () {
    $user = User::factory()->withNationalId()->create();

    expect($user->fresh()->national_id_number_hash)->not->toBeNull()
        ->and($user->fresh()->toArray())->not->toHaveKey('national_id_number_hash')
        ->and($user->fresh()->toJson())->not->toContain('national_id_number_hash')
        ->and(User::whereKey($user->id)->get()->toJson())->not->toContain('national_id_number_hash');
});

/*
|--------------------------------------------------------------------------
| Public registration
|--------------------------------------------------------------------------
*/

test('public registration requires a NIN for an adult', function () {
    $this->from(route('register'))
        ->post(route('register'), publicRegistrationPayload($this->branch, $this->division))
        ->assertRedirect(route('register'));

    expect(ninError())->toBe(NationalIdNumberRule::REQUIRED_MESSAGE);
    $this->assertGuest();
});

test('public registration succeeds for an adult with a NIN, typed with separators', function () {
    $this->post(route('register'), publicRegistrationPayload($this->branch, $this->division, [
        'email' => 'ada@example.com',
        'national_id_number' => '123 456 789-01',
    ]))->assertRedirect(route('registration.success'));

    $user = User::where('email', 'ada@example.com')->firstOrFail();
    expect($user->national_id_number)->toBe('12345678901');
});

test('public registration succeeds without a NIN for a minor', function () {
    $this->post(route('register'), publicRegistrationPayload($this->branch, $this->division, [
        'email' => 'minor@example.com',
        'birth_year' => ninMinorBirthYear(),
    ]))->assertRedirect(route('registration.success'));

    expect(User::where('email', 'minor@example.com')->value('national_id_number_hash'))->toBeNull();
});

test('a minor who does enter a NIN still has it format-checked', function () {
    $this->from(route('register'))->post(route('register'), publicRegistrationPayload($this->branch, $this->division, [
        'birth_year' => ninMinorBirthYear(),
        'national_id_number' => '12345',
    ]));

    expect(ninError())->toBe(NationalIdNumberRule::FORMAT_MESSAGE);
});

test('public registration rejects a badly formatted NIN', function () {
    $this->from(route('register'))->post(route('register'), publicRegistrationPayload($this->branch, $this->division, [
        'national_id_number' => '1234567890A',
    ]));

    expect(ninError())->toBe(NationalIdNumberRule::FORMAT_MESSAGE);
});

test('public registration rejects a NIN already on another account, however it is typed', function () {
    User::factory()->create(['national_id_number' => '12345678901', 'lifecycle_status' => 'active']);

    $this->from(route('register'))->post(route('register'), publicRegistrationPayload($this->branch, $this->division, [
        'national_id_number' => '123-456-789 01',
    ]));

    expect(ninError())->toBe(NationalIdNumberRule::TAKEN_MESSAGE);
});

test('public registration explains when the NIN belongs to an archived account', function () {
    User::factory()->create(['national_id_number' => '12345678901', 'lifecycle_status' => 'archived']);

    $this->from(route('register'))->post(route('register'), publicRegistrationPayload($this->branch, $this->division, [
        'national_id_number' => '12345678901',
    ]));

    expect(ninError())->toBe(NationalIdNumberRule::ARCHIVED_MESSAGE);
});

/*
|--------------------------------------------------------------------------
| Admin "Register new person" (users.store)
|--------------------------------------------------------------------------
*/

test('admin registration requires a NIN for an adult', function () {
    $this->actingAs($this->admin)->from(route('users.create'))
        ->post(route('users.store'), adminStorePayload($this->branch, $this->division));

    expect(ninError())->toBe(NationalIdNumberRule::REQUIRED_MESSAGE);
    expect(User::where('first_name', 'Musa')->exists())->toBeFalse();
});

test('admin registration succeeds for an adult with a valid NIN', function () {
    $this->actingAs($this->admin)
        ->post(route('users.store'), adminStorePayload($this->branch, $this->division, [
            'national_id_number' => '98765432109',
        ]))->assertSessionHasNoErrors();

    expect(User::where('first_name', 'Musa')->value('national_id_number_hash'))
        ->toBe(NationalIdNumber::hash('98765432109'));
});

test('admin registration succeeds without a NIN for a minor', function () {
    $this->actingAs($this->admin)
        ->post(route('users.store'), adminStorePayload($this->branch, $this->division, [
            'birth_year' => ninMinorBirthYear(),
        ]))->assertSessionHasNoErrors();

    expect(User::where('first_name', 'Musa')->exists())->toBeTrue();
});

test('admin registration rejects a NIN already on another account, with the plain message even if archived', function () {
    User::factory()->create(['national_id_number' => '98765432109', 'lifecycle_status' => 'archived']);

    $this->actingAs($this->admin)->from(route('users.create'))
        ->post(route('users.store'), adminStorePayload($this->branch, $this->division, [
            'national_id_number' => '98765432109',
        ]));

    expect(ninError())->toBe(NationalIdNumberRule::TAKEN_MESSAGE);
});

/*
|--------------------------------------------------------------------------
| Admin edit (users.update) — optional, validated when given
|--------------------------------------------------------------------------
*/

test('users/edit saves with no NIN at all', function () {
    $target = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);

    $this->actingAs($this->admin)
        ->put(route('users.update', $target), adminUpdatePayload($target, ['last_name' => 'Renamed']))
        ->assertSessionHasNoErrors();

    expect($target->fresh()->last_name)->toBe('Renamed');
});

test('users/edit lets a user keep their own unchanged NIN', function () {
    $target = User::factory()->create([
        'branch_id' => $this->branch->id, 'division_id' => $this->division->id,
        'national_id_number' => '11122233344',
    ]);

    $this->actingAs($this->admin)
        ->put(route('users.update', $target), adminUpdatePayload($target, [
            'national_id_number' => '111 222 333 44',
            'last_name' => 'Renamed',
        ]))->assertSessionHasNoErrors();

    expect($target->fresh()->last_name)->toBe('Renamed');
});

test('users/edit rejects another account\'s NIN and a badly formatted one', function () {
    User::factory()->create(['national_id_number' => '11122233344']);
    $target = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);

    $this->actingAs($this->admin)->from(route('users.edit', $target))
        ->put(route('users.update', $target), adminUpdatePayload($target, ['national_id_number' => '11122233344']));
    expect(ninError())->toBe(NationalIdNumberRule::TAKEN_MESSAGE);

    $this->actingAs($this->admin)->from(route('users.edit', $target))
        ->put(route('users.update', $target), adminUpdatePayload($target, ['national_id_number' => '111222']));
    expect(ninError())->toBe(NationalIdNumberRule::FORMAT_MESSAGE);

    expect($target->fresh()->national_id_number)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Self-service profile edit (profile.update) — optional, validated when given
|--------------------------------------------------------------------------
*/

test('profile/edit saves with no NIN', function () {
    $self = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);

    $this->actingAs($self)
        ->put(route('profile.update'), profileUpdatePayload($self, ['last_name' => 'Renamed']))
        ->assertRedirect(route('profile.show'));

    expect($self->fresh()->last_name)->toBe('Renamed');
});

test('profile/edit lets a user keep their own unchanged NIN', function () {
    $self = User::factory()->create([
        'branch_id' => $this->branch->id, 'division_id' => $this->division->id,
        'national_id_number' => '55566677788',
    ]);

    $this->actingAs($self)
        ->put(route('profile.update'), profileUpdatePayload($self, [
            'national_id_number' => '55566677788',
            'last_name' => 'Renamed',
        ]))->assertRedirect(route('profile.show'));

    expect($self->fresh()->last_name)->toBe('Renamed');
});

test('profile/edit rejects another account\'s NIN and a badly formatted one', function () {
    User::factory()->create(['national_id_number' => '55566677788']);
    $self = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);

    $this->actingAs($self)->from(route('profile.edit'))
        ->put(route('profile.update'), profileUpdatePayload($self, ['national_id_number' => '555 666 777 88']));
    expect(ninError())->toBe(NationalIdNumberRule::TAKEN_MESSAGE);

    $this->actingAs($self)->from(route('profile.edit'))
        ->put(route('profile.update'), profileUpdatePayload($self, ['national_id_number' => 'abc']));
    expect(ninError())->toBe(NationalIdNumberRule::FORMAT_MESSAGE);
});

/*
|--------------------------------------------------------------------------
| DB unique index: near-simultaneous duplicates
|--------------------------------------------------------------------------
*/

test('a duplicate that slips past validation hits the unique index and becomes the friendly error', function () {
    $target = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);
    $rival = User::factory()->create();

    // Simulate the race: validation has already passed when, just before
    // the save, another request stores the same NIN on $rival.
    User::saving(function (User $user) use ($target, $rival) {
        if ($user->id === $target->id && $user->isDirty('national_id_number_hash')) {
            DB::table('users')->where('id', $rival->id)
                ->update(['national_id_number_hash' => NationalIdNumber::hash('99988877766')]);
        }
    });

    $this->actingAs($this->admin)->from(route('users.edit', $target))
        ->put(route('users.update', $target), adminUpdatePayload($target, ['national_id_number' => '99988877766']))
        ->assertRedirect(route('users.edit', $target));

    expect(ninError())->toBe(NationalIdNumberRule::TAKEN_MESSAGE)
        ->and($target->fresh()->national_id_number_hash)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Migration: collision stop
|--------------------------------------------------------------------------
*/

test('the migration refuses to run when two accounts share a NIN, and changes nothing', function () {
    $migration = require database_path('migrations/2026_09_28_120000_add_national_id_number_hash_to_users_table.php');
    $migration->down();

    $a = User::factory()->create();
    $b = User::factory()->create();
    $c = User::factory()->create();
    // Written raw (encrypted, differently formatted) as legacy data would be.
    DB::table('users')->where('id', $a->id)->update(['national_id_number' => Crypt::encryptString('12345678901')]);
    DB::table('users')->where('id', $b->id)->update(['national_id_number' => Crypt::encryptString('123 456 789-01')]);
    DB::table('users')->where('id', $c->id)->update(['national_id_number' => Crypt::encryptString('10987654321')]);

    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, "DB-{$a->id}, DB-{$b->id}");

    expect(Schema::hasColumn('users', 'national_id_number_hash'))->toBeFalse();

    // Resolve the collision and it goes through, backfilling the survivors.
    DB::table('users')->where('id', $b->id)->update(['national_id_number' => null]);
    $migration->up();

    expect(DB::table('users')->where('id', $a->id)->value('national_id_number_hash'))->toBe(NationalIdNumber::hash('12345678901'))
        ->and(DB::table('users')->where('id', $c->id)->value('national_id_number_hash'))->toBe(NationalIdNumber::hash('10987654321'));
});

/*
|--------------------------------------------------------------------------
| TaskForce endpoints no longer leak the NIN
|--------------------------------------------------------------------------
*/

test('task-force member search and add-member return only id, name and DB reference', function () {
    $unit = RedCrossUnit::create(['name' => 'Unit A', 'division_id' => $this->division->id, 'is_active' => true]);
    $volunteer = User::factory()->withNationalId()->create([
        'first_name' => 'Zainab', 'last_name' => 'Searchable',
        'branch_id' => $this->branch->id, 'division_id' => $this->division->id,
        'red_cross_unit_id' => $unit->id, 'lifecycle_status' => 'active',
    ]);
    $taskForce = TaskForce::create(['name' => 'Flood Team', 'task_force_type_id' => 1, 'branch_id' => $this->branch->id]);

    $search = $this->actingAs($this->admin)
        ->getJson(route('task-forces.search-users', $taskForce).'?query=Searchable')
        ->assertOk();

    expect($search->json())->toHaveCount(1)
        ->and(array_keys($search->json()[0]))->toBe(['id', 'full_name', 'user_id_reference'])
        ->and($search->json()[0]['id'])->toBe($volunteer->id);

    $added = $this->actingAs($this->admin)
        ->postJson(route('task-forces.add-member', $taskForce), ['user_id' => $volunteer->id])
        ->assertOk();

    expect(array_keys($added->json('user')))->toBe(['id', 'full_name', 'user_id_reference']);

    foreach ([$search, $added] as $response) {
        expect($response->getContent())
            ->not->toContain($volunteer->national_id_number)
            ->not->toContain('national_id_number')
            ->not->toContain('personal_info');
    }
});
