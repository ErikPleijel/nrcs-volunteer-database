<?php

/**
 * Code of Conduct: the public read-only page (/code-of-conduct), the
 * "Privacy & Code of Conduct" box on My Profile, and the shared scroll box
 * (policies/code-of-conduct-reader) on registration and the consent page.
 * The wording lives only in policies/code-of-conduct.blade.php.
 *
 * The scroll-to-unlock behaviour is Alpine (cocReader()) and is checked by
 * hand in the browser; these tests cover the markup.
 */

use App\Http\Middleware\EnsureConsentConfirmed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

/*
|--------------------------------------------------------------------------
| The read-only page
|--------------------------------------------------------------------------
*/

test('a guest can open the Code of Conduct, with the full text and the logo', function () {
    $this->get(route('code-of-conduct'))
        ->assertOk()
        ->assertSee('I. Introduction')
        ->assertSee('Rules of Conduct')
        ->assertSee('class="coc-title-logo"', false)
        ->assertSee('<div class="coc-wrap">', false);
});

test('the page is read-only: no form and no checkboxes', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('code-of-conduct'))
        ->assertOk()
        ->assertDontSee('<form method="POST" action="'.route('consent.confirm.store').'"', false)
        ->assertDontSee('type="checkbox"', false)
        ->assertDontSee('coc_commitment_1', false);
});

test('a user who still has to confirm consent can open the page without being redirected', function () {
    $this->actingAs(User::factory()->notConsented()->create())
        ->get(route('code-of-conduct'))
        ->assertOk();
});

test('a role holder who has not accepted the staff policy can open the page too', function () {
    Role::findOrCreate('branch_db_administrator', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->notConsented()->policyNotAccepted()->create();
    $user->assignRole('branch_db_administrator');

    $this->actingAs($user)->get(route('code-of-conduct'))->assertOk();
});

/*
|--------------------------------------------------------------------------
| My Profile
|--------------------------------------------------------------------------
*/

test('My Profile links to both the privacy policy and the Code of Conduct, with the acceptance date', function () {
    $user = User::factory()->create(['code_of_conduct_accepted_at' => Carbon::parse('2026-03-14 10:00')]);

    $this->actingAs($user)->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Privacy &amp; Code of Conduct', false)
        ->assertSee('href="'.route('privacy-policy').'"', false)
        ->assertSee('href="'.route('code-of-conduct').'"', false)
        ->assertSee('You accepted the Code of Conduct on 14 March 2026.');
});

test('My Profile leaves out the acceptance line when there is no date', function () {
    $this->withoutMiddleware(EnsureConsentConfirmed::class);

    $this->actingAs(User::factory()->notConsented()->create())
        ->get(route('profile.show'))
        ->assertOk()
        ->assertSee('href="'.route('code-of-conduct').'"', false)
        ->assertDontSee('You accepted the Code of Conduct on');
});

/*
|--------------------------------------------------------------------------
| The shared scroll box on registration and the consent page
|--------------------------------------------------------------------------
*/

dataset('coc reader pages', [
    'registration' => [fn () => test()->get(route('register'))],
    'consent page' => [fn () => test()->actingAs(User::factory()->notConsented()->create())->get(route('consent.confirm'))],
]);

test('the page uses the shared scroll box, embedded, with its heading, hint and locked-click message', function (Closure $visit) {
    $html = $visit()->assertOk()->getContent();

    expect($html)->toContain('x-data="cocReader()"')
        ->and($html)->toContain('function cocReader()')
        ->and($html)->toContain('Code of Conduct – please read')
        ->and($html)->toContain('Scroll down to read the full text ↓')
        ->and($html)->toContain('Please read the Code of Conduct first – scroll to the bottom of the text.')
        ->and($html)->toContain('id="coc-scroll-container"')
        ->and($html)->toContain('tabindex="0"')
        ->and($html)->toContain('aria-describedby="coc-scroll-hint"')
        ->and($html)->toContain('@click="explainLocked($event)"')
        ->and($html)->toContain('class="coc-wrap coc-wrap--embedded"')
        ->and($html)->not->toContain('class="coc-title-logo"')
        ->and(substr_count($html, 'disabled:pointer-events-none'))->toBe(4)
        // The component is defined once and the old inline versions are gone.
        ->and(substr_count($html, 'function cocReader()'))->toBe(1)
        ->and($html)->not->toContain('codeOfConductFlow')
        ->and($html)->not->toContain('consentConfirmFlow');
})->with('coc reader pages');

test('the register page asks for four commitments and the button has no fixed aria-disabled', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('confirm the four commitments')
        ->assertDontSee('three commitments')
        ->assertDontSee('aria-disabled="true"', false);
});

test('ticked boxes come back marked after a validation error on the consent page', function () {
    $user = User::factory()->notConsented()->create();

    $this->actingAs($user)
        ->from(route('consent.confirm'))
        ->followingRedirects()
        ->post(route('consent.confirm.store'), ['coc_commitment_1' => '1', 'coc_commitment_3' => '1'])
        ->assertOk()
        ->assertSee('Please tick all four boxes to continue.');

    $html = $this->withSession(['_old_input' => ['coc_commitment_1' => '1', 'coc_commitment_3' => '1']])
        ->get(route('consent.confirm'))
        ->getContent();

    expect(preg_match_all('/\sdata-old-checked\s/', $html))->toBe(2)
        ->and($html)->toMatch('/name="coc_commitment_1"\s+data-old-checked/')
        ->and($html)->toMatch('/name="coc_commitment_3"\s+data-old-checked/');
});
