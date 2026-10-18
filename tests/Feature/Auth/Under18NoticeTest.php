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
        ->toMatch('/data-under-18-notice[^>]*class="[^"]*\bhidden\b/');

    expect($maybe)->not->toMatch('/data-under-18-notice[^>]*class="[^"]*\bhidden\b/')
        ->toMatch('/data-under-18-text="maybe" class="">\s*If you have not had your 18th birthday yet/');

    expect($child)->toMatch('/data-under-18-text="under" class="">\s*You are under 18\. Please ask your parent or guardian/');
});

test('staff see staff wording', function () {
    expect(under18Notice((int) date('Y') - 10, 'staff'))
        ->toContain('This person is under 18. Check that their parent or guardian agrees to them joining.');
});

test('the registration form has the notice and defaults to volunteering', function () {
    $html = $this->get(route('register'))->assertOk()->getContent();

    expect($html)->toContain('data-under-18-notice')
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
