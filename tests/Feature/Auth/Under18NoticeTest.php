<?php

/**
 * Registration and users/create: the birth year dropdown has an under-18
 * notice (x-under-18-notice), worked out from the current year, and the
 * contribution preference defaults to "Volunteering".
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function under18Notice(?int $oldYear, string $audience = 'self'): string
{
    session()->put('_old_input', $oldYear ? ['birth_year' => $oldYear] : []);
    request()->setLaravelSession(session()->driver());

    return Blade::render('<x-under-18-notice for="birth_year" :audience="$audience" />', ['audience' => $audience]);
}

test('the notice is hidden for adults and shown for under-18s, from the current year', function () {
    $boundary = (int) date('Y') - 18;

    $adult = under18Notice($boundary - 1);
    $maybe = under18Notice($boundary);
    $child = under18Notice($boundary + 1);

    expect($adult)->toContain('data-boundary-year="'.$boundary.'"')
        ->toMatch('/data-age-notice data-boundary-year="\d+"\s*class="[^"]*\bhidden\b/');

    expect($maybe)->not->toMatch('/data-age-notice data-boundary-year="\d+"\s*class="[^"]*\bhidden\b/')
        ->toMatch('/data-age-notice-text="maybe" class="">\s*If you have not had your 18th birthday yet/');

    expect($child)->toMatch('/data-age-notice-text="under" class="">\s*You are under 18\. Please ask your parent or guardian/');
});

test('staff see staff wording', function () {
    expect(under18Notice((int) date('Y') - 10, 'staff'))
        ->toContain('This person is under 18. Check that their parent or guardian agrees to them joining.');
});

test('the registration form has the notice and defaults to volunteering', function () {
    $html = $this->get(route('register'))->assertOk()->getContent();

    expect($html)->toContain('data-age-notice')
        ->toMatch('/id="contribution_volunteering"[^>]*checked/')
        ->not->toMatch('/id="contribution_member"[^>]*checked/');
});

test('users/create has the staff notice and defaults to volunteering', function () {
    foreach (['manage-admin-panel', 'add_user', 'view_user'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['manage-admin-panel', 'add_user', 'view_user']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $admin = User::factory()->create();
    $admin->assignRole('national_db_administrator');

    $html = $this->actingAs($admin)->get(route('users.create'))->assertOk()->getContent();

    expect($html)->toContain('This person may be under 18.')
        ->toMatch('/id="contribution_volunteering"[^>]*checked/s')
        ->not->toMatch('/id="contribution_member"[^>]*checked/s');
});

test('both message texts are always on the page, so the box can never show up empty', function () {
    // Regression: the JS read the texts' key via dataset, which fails for "data-under-18-text"
    // (the key becomes "under-18Text"), so every text was hidden and the blue box was empty.
    $html = under18Notice(null);

    expect($html)->toContain('data-age-notice-text="under"')
        ->toContain('You are under 18. Please ask your parent or guardian if it is OK for you to join.')
        ->toContain('data-age-notice-text="maybe"')
        ->toContain('If you have not had your 18th birthday yet')
        ->toContain("getAttribute('data-age-notice-text')")
        ->not->toContain('dataset');

    // The registration page itself carries the texts too.
    $this->get(route('register'))->assertOk()
        ->assertSee('You are under 18. Please ask your parent or guardian', false)
        ->assertSee('If you have not had your 18th birthday yet', false);
});

test('after a failed submit the selected under-18 year shows its text straight away', function () {
    $year = (int) date('Y') - 12;

    $html = $this->withSession(['_old_input' => ['birth_year' => $year]])
        ->get(route('register'))->assertOk()->getContent();

    expect($html)->toMatch('/data-age-notice data-boundary-year="\d+"\s*class="(?![^"]*\bhidden\b)[^"]*"/')
        ->toMatch('/data-age-notice-text="under" class="">\s*You are under 18/')
        ->toMatch('/<option value="'.$year.'" selected>/');
});
