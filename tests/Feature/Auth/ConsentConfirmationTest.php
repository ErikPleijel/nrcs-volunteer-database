<?php

/**
 * One-time Code of Conduct + NDPA consent confirmation (2026-10-08).
 *
 * EnsureConsentConfirmed sends every logged-in user without a
 * code_of_conduct_accepted_at to /consent/confirm. It runs before
 * RequiresPolicyAcceptance, so a role holder confirms consent first and
 * accepts the staff policy second.
 */

use App\Models\Log as AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('branch_db_administrator', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function allCommitments(): array
{
    return [
        'coc_commitment_1' => '1',
        'coc_commitment_2' => '1',
        'coc_commitment_3' => '1',
        'coc_commitment_4' => '1',
    ];
}

/**
 * GET $url and follow redirects by hand, at most $maxHops times, so a
 * redirect loop fails the test instead of hanging it. Returns the final
 * response and the list of URLs visited.
 */
function getFollowingRedirects(string $url, int $maxHops = 10): array
{
    $visited = [$url];
    $response = test()->get($url);

    while ($response->isRedirect() && count($visited) <= $maxHops) {
        $url = $response->headers->get('Location');
        $visited[] = $url;
        $response = test()->get($url);
    }

    return [$response, $visited];
}

test('a legacy-style user who logs in is sent to the confirmation page from any normal page', function () {
    $user = User::factory()->notConsented()->create(['email' => 'legacy@example.org']);

    $this->post('/login', ['login' => 'legacy@example.org', 'password' => 'password']);
    $this->assertAuthenticatedAs($user);

    $this->get(route('profile.show'))->assertRedirect(route('consent.confirm'));
    $this->get(route('red-cross-units.my-unit'))->assertRedirect(route('consent.confirm'));
});

test('the confirmation page shows the intro, the Code of Conduct and the four commitments', function () {
    $user = User::factory()->notConsented()->create();

    $this->actingAs($user)->get(route('consent.confirm'))
        ->assertOk()
        ->assertSee('Welcome to the new Nigerian Red Cross membership database.')
        ->assertSee('coc-scroll-container', false)
        ->assertSee('I have read the entire Code of Conduct.')
        ->assertSee('I agree to follow the Code of Conduct at all times.')
        ->assertSee('I understand that violations may result in disciplinary action.')
        ->assertSee('I consent to the Nigerian Red Cross Society collecting')
        ->assertSee('If you do not agree, please log out and contact your branch.');
});

test('submitting without all four boxes fails validation and records nothing', function () {
    $user = User::factory()->notConsented()->create();

    $this->actingAs($user)
        ->post(route('consent.confirm.store'), [...allCommitments(), 'coc_commitment_3' => null])
        ->assertSessionHasErrors('coc_commitment_3');

    $user->refresh();
    expect($user->code_of_conduct_accepted_at)->toBeNull()
        ->and($user->consent_obtained_at)->toBeNull()
        ->and(AuditLog::where('action', 'consent_confirmed')->exists())->toBeFalse();
});

test('submitting all four boxes records both timestamps, audits it and returns to the intended page', function () {
    $staff = User::factory()->create();
    $user = User::factory()->notConsented()->create([
        'consent_obtained_by_id' => $staff->id,
        'consent_notes' => 'admin-registered, consent attested via form checkboxes',
    ]);

    $this->actingAs($user)->get(route('red-cross-units.my-unit'))
        ->assertRedirect(route('consent.confirm'));

    $this->post(route('consent.confirm.store'), allCommitments())
        ->assertRedirect(route('red-cross-units.my-unit'));

    $user->refresh();
    expect($user->code_of_conduct_accepted_at)->not->toBeNull()
        ->and($user->consent_obtained_at)->not->toBeNull()
        ->and($user->consent_obtained_by_id)->toBe($user->id)
        ->and($user->consent_notes)->toBe('Confirmed by user at login');

    $log = AuditLog::where('action', 'consent_confirmed')->sole();
    expect($log->subject_id)->toBe($user->id)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->old_values['code_of_conduct_accepted_at'])->toBeNull()
        ->and($log->old_values['consent_obtained_by_id'])->toBe($staff->id)
        ->and($log->old_values['consent_notes'])->toBe('admin-registered, consent attested via form checkboxes')
        ->and($log->new_values['consent_notes'])->toBe('Confirmed by user at login');

    $this->get(route('red-cross-units.my-unit'))->assertOk();
});

test('a consented user is never redirected to the confirmation page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('profile.show'))->assertOk();
    $this->get(route('red-cross-units.my-unit'))->assertOk();

    // Opening the page directly after confirming just moves on.
    $this->get(route('consent.confirm'))->assertRedirect('/profile');
});

test('a role holder without consent or policy acceptance sees the consent page first, then the policy page', function () {
    $user = User::factory()->notConsented()->policyNotAccepted()->create();
    $user->assignRole('branch_db_administrator');

    $this->actingAs($user)->get(route('profile.show'))
        ->assertRedirect(route('consent.confirm'));
    $this->get(route('consent.confirm'))->assertOk();

    $this->post(route('consent.confirm.store'), allCommitments())
        ->assertRedirect(route('profile.show'));

    $this->get(route('profile.show'))->assertRedirect(route('policy.accept'));
    $this->get(route('policy.accept'))->assertOk();
});

test('a role holder with an unverified email and no policy acceptance does not loop', function () {
    $user = User::factory()->unverified()->policyNotAccepted()->create();
    $user->assignRole('branch_db_administrator');
    $this->actingAs($user);

    [$response, $visited] = getFollowingRedirects(route('profile.show'));

    expect($response->isRedirect())->toBeFalse('Still redirecting after: '.implode(' -> ', $visited));
    $response->assertOk();
    expect(end($visited))->toBe(route('verification.required'));
});

test('a user with an unverified email can still reach the confirmation page', function () {
    $user = User::factory()->notConsented()->unverified()->create();

    $this->actingAs($user)->get(route('consent.confirm'))->assertOk();
});

test('logout works while the user is gated', function () {
    $user = User::factory()->notConsented()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect();
    $this->assertGuest();
});

test('JSON requests get a 403 instead of a redirect', function () {
    $user = User::factory()->notConsented()->create();

    $this->actingAs($user)->getJson(route('profile.show'))
        ->assertForbidden()
        ->assertJson(['message' => 'Please confirm the Code of Conduct and consent before continuing.']);
});
