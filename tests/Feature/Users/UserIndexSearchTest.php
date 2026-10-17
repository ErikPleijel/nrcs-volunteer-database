<?php

/**
 * The users-list search box (UserFilterService + ParsedSearch): each kind
 * of term searches only its own fields — DB code (id), phone (telephone1/2,
 * exact after normalising), NIN (keyed hash), email, names — and the notice
 * shown when it finds nobody (UserSearchNotice): out of area, hidden by
 * filters, the neutral phone/NIN line, and the out-of-area rate limit.
 * Plus the masking/encryption of phone and NIN terms (SearchTerm).
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\User;
use App\Services\UserSearchNotice;
use App\Support\Filters\SearchTerm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $permissions = ['manage-admin-panel', 'view_user'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    foreach (['national_db_administrator', 'branch_db_administrator', 'division_db_assistant_operations'] as $role) {
        Role::findOrCreate($role, 'web')->syncPermissions($permissions);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->alpha = Branch::create(['name' => 'Alpha', 'code' => 'ALP']);
    $this->beta = Branch::create(['name' => 'Beta', 'code' => 'BET']);
    $this->alphaOne = Division::create(['name' => 'Alpha One', 'branch_id' => $this->alpha->id]);
    $this->alphaTwo = Division::create(['name' => 'Alpha Two', 'branch_id' => $this->alpha->id]);
    $this->betaOne = Division::create(['name' => 'Beta One', 'branch_id' => $this->beta->id]);

    $this->national = User::factory()->create();
    $this->national->assignRole('national_db_administrator');
    $this->branchAdmin = User::factory()->inBranch($this->alpha)->create();
    $this->branchAdmin->assignRole('branch_db_administrator');
    $this->divisionAdmin = User::factory()->inDivision($this->alphaOne)->create();
    $this->divisionAdmin->assignRole('division_db_assistant_operations');

    $this->target = User::factory()->inDivision($this->betaOne)->create([
        'first_name' => 'Zebedee', 'last_name' => 'Targetson', 'email' => 'zebedee@example.org',
        'telephone1' => '0803 123 4567', 'telephone2' => null,
    ]);
    $this->alphaTwoPerson = User::factory()->inDivision($this->alphaTwo)->create(['first_name' => 'Alphatwoperson']);

    // The DB code's digits inside a phone and an email: neither may match a DB code search.
    $this->phoneDecoy = User::factory()->create([
        'first_name' => 'Phonedecoy', 'telephone1' => '0809'.str_pad((string) $this->target->id, 7, '0', STR_PAD_LEFT),
    ]);
    $this->emailDecoy = User::factory()->create([
        'first_name' => 'Emaildecoy', 'email' => 'x'.$this->target->id.'@example.org',
    ]);
});

function searchUsers($test, User $viewer, string $search, array $extra = [])
{
    return $test->actingAs($viewer)->get(route('users.index', ['search' => $search] + $extra))->assertOk();
}

// ---------------------------------------------------------------- DB code

test('a DB code matches only that id, not phones or emails containing its digits', function () {
    searchUsers($this, $this->national, (string) $this->target->id)
        ->assertSee('Found 1 result')
        ->assertSee('Zebedee')
        ->assertDontSee('Phonedecoy')
        ->assertDontSee('Emaildecoy');
});

test('a DB code with a prefix or spaces finds the same person', function (string $form) {
    $id = (string) $this->target->id;
    $term = str_replace('ID', strlen($id) > 1 ? substr($id, 0, 1).' '.substr($id, 1) : $id, $form);

    searchUsers($this, $this->national, $term)
        ->assertSee('Found 1 result')
        ->assertSee('Zebedee');
})->with(['ID', 'DB-ID', 'DBID', 'db ID']);

// ---------------------------------------------------------------- Phone / NIN

test('a phone number in 10, 11 or 13-digit form matches the stored number', function (string $term) {
    searchUsers($this, $this->national, $term)
        ->assertSee('Found 1 result')
        ->assertSee('Zebedee');
})->with(['8031234567', '08031234567', '0803 123 4567', '+2348031234567', '234 803 123 4567']);

test('telephone2 and stored +234 / trailing-comma formats match too', function () {
    $other = User::factory()->create(['first_name' => 'Secondphone', 'telephone1' => null, 'telephone2' => '+234 (805) 555-0000,']);

    searchUsers($this, $this->national, '08055550000')
        ->assertSee('Found 1 result')
        ->assertSee('Secondphone');
});

test('part of a phone number no longer matches', function () {
    searchUsers($this, $this->national, '1234567')
        ->assertSee('Found 0 results')
        ->assertDontSee('Zebedee');
});

test('an 11-digit NIN matches by its hash, with spaces ignored', function () {
    $this->target->update(['national_id_number' => '12345678901']);

    searchUsers($this, $this->national, '123 4567 8901')
        ->assertSee('Found 1 result')
        ->assertSee('Zebedee');
});

// ---------------------------------------------------------------- Text

test('a term with @ searches email from the start of the address', function (string $term) {
    searchUsers($this, $this->national, $term)
        ->assertSee('Found 1 result')
        ->assertSee('Zebedee');
})->with(['zebedee@', 'Zebedee@Example.org', 'zebedee@example.org']);

test('a term with @ does not match inside an email', function (string $term) {
    searchUsers($this, $this->national, $term)
        ->assertSee('Found 0 results')
        ->assertDontSee('Zebedee')
        ->assertDontSee('Emaildecoy');
})->with(['@example.org', 'bedee@', '%@example.org', '_ebedee@']);

test('a term with letters searches names only, never email', function () {
    searchUsers($this, $this->national, 'zebe targ')->assertSee('Found 1 result')->assertSee('Zebedee');

    // 'example' and 'org' are in every test email but in nobody's name.
    searchUsers($this, $this->national, 'example')->assertSee('Found 0 results');
    searchUsers($this, $this->national, 'example.org')->assertSee('Found 0 results');
});

test('a middle name matches as a name part', function () {
    $this->target->update(['middle_name' => 'Quillon']);

    searchUsers($this, $this->national, 'zebedee quillon')->assertSee('Found 1 result')->assertSee('Zebedee');
});

test('digits never match an email, whatever their length', function (string $term) {
    User::factory()->create(['first_name' => 'Digitmail', 'email' => 'samuelpat1234567892468@example.org']);

    searchUsers($this, $this->national, $term)->assertDontSee('Digitmail');
})->with(['12345', '1234567', '123456789', '1234567892468', '92468']);

// ---------------------------------------------------------------- Notices

test('a number that is no DB code, phone or NIN says so', function () {
    searchUsers($this, $this->national, '123456789012')
        ->assertSee('Found 0 results')
        ->assertSee('This is not a DB code, phone number or NIN.');
});

test('a branch user searching a DB code in another branch sees its branch and division only', function () {
    searchUsers($this, $this->branchAdmin, (string) $this->target->id)
        ->assertSee('Found 0 results')
        ->assertSee("DB {$this->target->id} is in Beta Branch, Beta One Division.")
        ->assertDontSee('Zebedee')
        ->assertDontSee('Targetson');
});

test('a division user searching a DB code in another division of their branch sees where it is', function () {
    searchUsers($this, $this->divisionAdmin, (string) $this->alphaTwoPerson->id)
        ->assertSee("DB {$this->alphaTwoPerson->id} is in Alpha Branch, Alpha Two Division.")
        ->assertDontSee('Alphatwoperson');
});

test('a DB code in the viewer\'s area but filtered out is "hidden by your filters"', function (string $viewer) {
    $person = User::factory()->inDivision($this->alphaOne)->create(['first_name' => 'Archivedone', 'lifecycle_status' => 'archived']);

    searchUsers($this, $this->{$viewer}, (string) $person->id)
        ->assertSee('Found 0 results')
        ->assertSee("DB {$person->id} exists but is hidden by your filters.")
        ->assertDontSee('is in Alpha Branch');
})->with(['national', 'branchAdmin', 'divisionAdmin']);

test('a DB code nobody has gives no notice', function (string $viewer) {
    searchUsers($this, $this->{$viewer}, '999999')
        ->assertSee('Found 0 results')
        ->assertDontSee('data-search-notice', false);
})->with(['national', 'branchAdmin', 'divisionAdmin']);

test('national users never get the out-of-area message', function () {
    searchUsers($this, $this->national, (string) $this->target->id, ['branch_id' => $this->alpha->id])
        ->assertSee("DB {$this->target->id} exists but is hidden by your filters.")
        ->assertDontSee('is in Beta Branch');
});

test('branch and division users get the same neutral line for any unmatched phone or NIN', function (string $viewer, string $term) {
    searchUsers($this, $this->{$viewer}, $term)
        ->assertSee('Found 0 results')
        ->assertSee('No match in your area. The person may be registered elsewhere — contact the national office.')
        ->assertDontSee('Beta');
})->with(['branchAdmin', 'divisionAdmin'])->with([
    'existing phone elsewhere' => '08031234567',
    'unknown phone' => '+2348099999999',
    'unknown NIN' => '98765432109',
]);

test('national users get no neutral line for an unmatched phone', function () {
    searchUsers($this, $this->national, '08099999999')
        ->assertSee('Found 0 results')
        ->assertDontSee('No match in your area');
});

test('the out-of-area message counts against the hourly limit, then falls back to plain no results', function () {
    $key = UserSearchNotice::rateLimitKey($this->branchAdmin);
    RateLimiter::clear($key);

    searchUsers($this, $this->branchAdmin, (string) $this->target->id)->assertSee('is in Beta Branch');
    expect(RateLimiter::attempts($key))->toBe(1);

    for ($i = 1; $i < UserSearchNotice::OUT_OF_AREA_PER_HOUR; $i++) {
        RateLimiter::hit($key, 3600);
    }

    searchUsers($this, $this->branchAdmin, (string) $this->target->id)
        ->assertSee('Found 0 results')
        ->assertDontSee('is in Beta Branch')
        ->assertDontSee('data-search-notice', false);

    // Hidden-by-filters and the neutral line are not limited.
    searchUsers($this, $this->branchAdmin, '08031234567')->assertSee('No match in your area');

    RateLimiter::clear($key);
});

// ---------------------------------------------------------------- Privacy

test('phone and NIN terms are masked and stored encrypted; DB codes and names are not', function () {
    expect(SearchTerm::label('+2348031234567'))->toBe('Search: ●●●●●●●●567')
        ->and(SearchTerm::label('8031234567'))->toBe('Search: ●●●●●●●●567')
        ->and(SearchTerm::label('2246'))->toBe('Search: "2246"');

    $stored = SearchTerm::forStorage(['search' => '+2348031234567']);
    expect($stored)->not->toHaveKey('search')
        ->and(SearchTerm::from($stored))->toBe('+2348031234567')
        ->and(SearchTerm::forStorage(['search' => 'DB-2246']))->toBe(['search' => 'DB-2246']);
});

test('a phone or NIN in the viewer\'s area but filtered out is "hidden by your filters"', function (string $viewer, string $status, string $term) {
    User::factory()->inDivision($this->alphaOne)->create([
        'first_name' => 'Hiddenphone', 'lifecycle_status' => $status,
        'telephone1' => '08077770000', 'national_id_number' => '11122233344',
    ]);

    searchUsers($this, $this->{$viewer}, $term)
        ->assertSee('Found 0 results')
        ->assertSee('This person exists but is hidden by your filters.')
        ->assertDontSee('No match in your area')
        ->assertDontSee('Hiddenphone');
})->with(['national', 'branchAdmin', 'divisionAdmin'])
    ->with(['pending_engagement', 'archived'])
    ->with(['phone' => '+2348077770000', 'NIN' => '111 2223 3344']);

test('a hidden phone outside the viewer\'s area still gets the neutral line', function (string $viewer) {
    $this->target->update(['lifecycle_status' => 'archived']);

    searchUsers($this, $this->{$viewer}, '08031234567')
        ->assertSee('No match in your area')
        ->assertDontSee('hidden by your filters');
})->with(['branchAdmin', 'divisionAdmin']);
