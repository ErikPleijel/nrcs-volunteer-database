<?php

/**
 * Public self-registration records the Code of Conduct acceptance and the
 * NDPA consent. code_of_conduct_accepted_at was missing from User::$fillable,
 * so User::create() silently dropped it (fixed 2026-10-08).
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha One', 'branch_id' => $this->branch->id]);
});

function registrationPayload(array $overrides = []): array
{
    return [
        'form_rendered_at' => now()->timestamp - 60,
        'first_name' => 'Rita',
        'last_name' => 'Registrant',
        'email' => 'rita@example.org',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
        'telephone1' => '08031234567',
        'branch_id' => test()->branch->id,
        'division_id' => test()->division->id,
        'birth_year' => 1990,
        'gender' => 'female',
        'contribution_type' => 'volunteering',
        'coc_commitment_1' => '1',
        'coc_commitment_2' => '1',
        'coc_commitment_3' => '1',
        'coc_commitment_4' => '1',
        ...$overrides,
    ];
}

test('public registration stores the Code of Conduct acceptance and the consent', function () {
    $this->post(route('register'), registrationPayload())
        ->assertRedirect(route('registration.success'));

    $user = User::where('email', 'rita@example.org')->firstOrFail();

    expect($user->code_of_conduct_accepted_at)->not->toBeNull()
        ->and($user->consent_obtained_at)->not->toBeNull()
        ->and($user->consent_obtained_by_id)->toBe($user->id);
});

test('registration without all four commitments is rejected', function () {
    $this->post(route('register'), registrationPayload(['coc_commitment_4' => null]))
        ->assertSessionHasErrors('coc_commitment_4');

    expect(User::where('email', 'rita@example.org')->exists())->toBeFalse();
});
