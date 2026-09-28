<?php

/**
 * Phone-collision disambiguation: when a phone number matches 2+ live,
 * email-less accounts, POST /login hands off to /login/phone, which narrows
 * the colliding accounts by DB number (optional), then name, then birth
 * year, then a picker, before asking for the password.
 */

use App\Models\User;
use App\Support\DbNumber;
use App\Support\LoginThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const COLLIDING_PHONE = '08012345678';

function collidingUser(array $attributes): User
{
    return User::factory()->create(array_merge([
        'email' => null,
        'telephone1' => COLLIDING_PHONE,
        'lifecycle_status' => 'active',
    ], $attributes));
}

function startPhoneFlow()
{
    return test()->post('/login', ['login' => COLLIDING_PHONE, 'password' => 'password'])
        ->assertRedirect(route('login.phone'));
}

function phoneStep(array $data)
{
    return test()->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.random_int(1, 250)])
        ->from(route('login.phone'))
        ->post(route('login.phone'), $data);
}

function phoneFlowError(): ?string
{
    return session('errors')?->get('phone_login')[0] ?? null;
}

beforeEach(function () {
    config([
        'auth.login_throttle.max_attempts' => 5,
        'auth.login_throttle.decay_minutes' => 15,
    ]);

    $this->ada = collidingUser(['first_name' => 'Ada', 'last_name' => 'Okafor', 'birth_year' => 1990]);
    $this->musa = collidingUser(['first_name' => 'Musa', 'last_name' => 'Bello', 'birth_year' => 1985]);
});

test('a phone collision starts the flow at the DB-number step without logging anyone in', function () {
    startPhoneFlow();
    $this->assertGuest();

    $this->get(route('login.phone'))
        ->assertOk()
        ->assertSee('If you have your DB number')
        ->assertSee("I don't know it", false)
        ->assertSee('ending in <strong>5678</strong>', false);
});

test('the flow page without a started flow sends the user back to login', function () {
    $this->get(route('login.phone'))->assertRedirect(route('login'));
    $this->post(route('login.phone'), ['db_number' => 'DB-1'])->assertRedirect(route('login'));
});

test('a DB number matching a colliding account goes straight to the password step', function () {
    startPhoneFlow();

    phoneStep(['db_number' => 'DB-'.$this->musa->id, 'action' => 'continue'])
        ->assertRedirect(route('login.phone'));

    $this->get(route('login.phone'))->assertSee('Enter your password');

    phoneStep(['password' => 'password'])->assertRedirect('/profile');
    $this->assertAuthenticatedAs($this->musa);
});

test('a DB number not among the colliding accounts is a generic failure, not a fall-through', function () {
    // Exists, but on a different phone number — must not be matched or revealed.
    $outsider = User::factory()->create(['email' => null, 'telephone1' => '08099999999']);

    startPhoneFlow();

    phoneStep(['db_number' => 'DB-'.$outsider->id, 'action' => 'continue'])
        ->assertRedirect(route('login.phone'));

    expect(phoneFlowError())->toBe(trans('auth.failed'));
    expect(RateLimiter::attempts(LoginThrottle::phoneKey('8012345678')))->toBe(1);

    // Still on the DB-number step, not moved on to the name step.
    $this->get(route('login.phone'))
        ->assertSee('If you have your DB number')
        ->assertDontSee('First name');
    $this->assertGuest();
});

test('skipping the DB number moves on to the name step', function () {
    startPhoneFlow();

    phoneStep(['db_number' => '', 'action' => 'skip'])->assertRedirect(route('login.phone'));

    $this->get(route('login.phone'))->assertSee('First name')->assertSee('Last name');
    expect(RateLimiter::attempts(LoginThrottle::phoneKey('8012345678')))->toBe(0);
});

test('continuing with a blank DB number is treated as a skip', function () {
    startPhoneFlow();

    phoneStep(['db_number' => '   ', 'action' => 'continue']);

    $this->get(route('login.phone'))->assertSee('First name');
});

test('a name that narrows to one account goes to the password step, in either order and any case', function () {
    startPhoneFlow();
    phoneStep(['action' => 'skip']);

    phoneStep(['first_name' => '  OKAFOR ', 'last_name' => 'ada'])->assertRedirect(route('login.phone'));

    $this->get(route('login.phone'))->assertSee('Enter your password');

    phoneStep(['password' => 'password'])->assertRedirect('/profile');
    $this->assertAuthenticatedAs($this->ada);
});

test('a completely wrong name is a generic failure and stays on the name step', function () {
    startPhoneFlow();
    phoneStep(['action' => 'skip']);

    phoneStep(['first_name' => 'Chidi', 'last_name' => 'Eze']);

    expect(phoneFlowError())->toBe(trans('auth.failed'));
    $this->get(route('login.phone'))->assertSee('First name');
    $this->assertGuest();
});

test('a partly right name (first name only) does not match', function () {
    startPhoneFlow();
    phoneStep(['action' => 'skip']);

    phoneStep(['first_name' => 'Ada', 'last_name' => 'Bello']);

    expect(phoneFlowError())->toBe(trans('auth.failed'));
});

test('a missing name field is a validation error and is not counted', function () {
    startPhoneFlow();
    phoneStep(['action' => 'skip']);

    phoneStep(['first_name' => 'Ada'])->assertSessionHasErrors('last_name');

    expect(RateLimiter::attempts(LoginThrottle::phoneKey('8012345678')))->toBe(0);
});

test('a name still matching 2+ accounts asks for the birth year, which can narrow to one', function () {
    $twin = collidingUser(['first_name' => 'Ada', 'last_name' => 'Okafor', 'birth_year' => 2001]);

    startPhoneFlow();
    phoneStep(['action' => 'skip']);
    phoneStep(['first_name' => 'Ada', 'last_name' => 'Okafor']);

    $this->get(route('login.phone'))->assertSee('What year were you born?');

    phoneStep(['birth_year' => '2001']);
    $this->get(route('login.phone'))->assertSee('Enter your password');

    phoneStep(['password' => 'password'])->assertRedirect('/profile');
    $this->assertAuthenticatedAs($twin);
});

test('a wrong birth year is a generic failure', function () {
    collidingUser(['first_name' => 'Ada', 'last_name' => 'Okafor', 'birth_year' => 2001]);

    startPhoneFlow();
    phoneStep(['action' => 'skip']);
    phoneStep(['first_name' => 'Ada', 'last_name' => 'Okafor']);
    phoneStep(['birth_year' => '1970']);

    expect(phoneFlowError())->toBe(trans('auth.failed'));
    $this->get(route('login.phone'))->assertSee('What year were you born?');
});

test('still 2+ after name and birth year shows a picker of first name + DB number only', function () {
    $twinA = collidingUser(['first_name' => 'Ada', 'last_name' => 'Okafor', 'birth_year' => 2001]);
    $twinB = collidingUser(['first_name' => 'Ada', 'last_name' => 'Okafor', 'birth_year' => 2001]);

    startPhoneFlow();
    phoneStep(['action' => 'skip']);
    phoneStep(['first_name' => 'Ada', 'last_name' => 'Okafor']);
    phoneStep(['birth_year' => '2001']);

    $page = $this->get(route('login.phone'))
        ->assertSee('Choose yours')
        ->assertSee('DB-'.$twinA->formatUserIdForDisplay())
        ->assertSee('DB-'.$twinB->formatUserIdForDisplay())
        ->assertDontSee('Okafor')
        ->assertDontSee('2001');

    // Ada (1990) was excluded by the birth year and must not be offered.
    $page->assertDontSee('DB-'.$this->ada->formatUserIdForDisplay());

    phoneStep(['account' => $twinB->id]);
    $this->get(route('login.phone'))->assertSee('Enter your password');

    phoneStep(['password' => 'password'])->assertRedirect('/profile');
    $this->assertAuthenticatedAs($twinB);
});

test('same-name accounts with no distinguishing birth year skip straight to the picker', function () {
    collidingUser(['first_name' => 'Ada', 'last_name' => 'Okafor', 'birth_year' => null]);

    startPhoneFlow();
    phoneStep(['action' => 'skip']);
    phoneStep(['first_name' => 'Ada', 'last_name' => 'Okafor']);

    $this->get(route('login.phone'))->assertSee('Choose yours')->assertDontSee('What year were you born?');
});

test('picking an account outside the remaining candidates is a generic failure', function () {
    $twin = collidingUser(['first_name' => 'Ada', 'last_name' => 'Okafor', 'birth_year' => 1990]);

    startPhoneFlow();
    phoneStep(['action' => 'skip']);
    phoneStep(['first_name' => 'Ada', 'last_name' => 'Okafor']);

    phoneStep(['account' => $this->musa->id]);

    expect(phoneFlowError())->toBe(trans('auth.failed'));
    $this->get(route('login.phone'))->assertSee('Choose yours');
});

test('a wrong password at the end of the flow is a generic failure and counted', function () {
    startPhoneFlow();
    phoneStep(['db_number' => (string) $this->ada->id]);

    phoneStep(['password' => 'wrong-password']);

    expect(phoneFlowError())->toBe(trans('auth.failed'));
    expect(RateLimiter::attempts(LoginThrottle::phoneKey('8012345678')))->toBe(1);
    $this->assertGuest();
});

test('wrong answers across the flow lock the phone number out, including a fresh start', function () {
    startPhoneFlow();

    phoneStep(['db_number' => 'DB-999999999']);
    phoneStep(['db_number' => 'not a number']);
    phoneStep(['action' => 'skip']); // not a wrong answer — not counted
    phoneStep(['first_name' => 'Wrong', 'last_name' => 'Person']);
    phoneStep(['first_name' => 'Still', 'last_name' => 'Wrong']);
    expect(phoneFlowError())->toBe(trans('auth.failed'));

    // 5th wrong answer: flow abandoned, back to the login form with the lockout.
    phoneStep(['first_name' => 'Nobody', 'last_name' => 'Here'])->assertRedirect(route('login'));
    expect(session('errors')->get('login')[0])->toContain('Too many failed sign-in attempts');

    // Starting over with the same number is refused before the flow begins.
    $this->from('/login')->post('/login', ['login' => '+234 801 234 5678', 'password' => 'password'])
        ->assertRedirect('/login');
    expect(session('errors')->get('login')[0])->toContain('Too many failed sign-in attempts');
    $this->assertGuest();
});

test('the flow expires after 15 minutes', function () {
    startPhoneFlow();

    $this->travel(16)->minutes();

    $this->get(route('login.phone'))->assertRedirect(route('login'));
});

test('DbNumber::parse accepts the card and profile formats', function (string $input, ?int $expected) {
    expect(DbNumber::parse($input))->toBe($expected);
})->with([
    ['DB-123456', 123456],
    ['DB 123 456', 123456],
    ['db123456', 123456],
    ["DB-123\u{2009}456", 123456],
    ["DB-123\u{00A0}456", 123456],
    ['  DB-123456  ', 123456],
    ['123456', 123456],
    ['DB-123456-LAG-IKEJA', 123456],
    ['DB-123 456-LAG-IKEJA', 123456],
    ['DB-123456/LAG/IKEJA/NO-UNIT', 123456],
    ['DB-', null],
    ['DB-0', null],
    ['abc', null],
    ['', null],
    ['DB-12a456', null],
    ['٣٤٥', null],
]);
