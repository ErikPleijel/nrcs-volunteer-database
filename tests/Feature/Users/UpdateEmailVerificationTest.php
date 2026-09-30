<?php

/**
 * UserController::update() clears email_verified_at only when the email
 * address changes (including to empty) — never for a password change or an
 * edit that leaves the address alone — and sends a verification email to a
 * new non-empty address, without a mail failure breaking the save.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    foreach (['manage-admin-panel', 'edit_user'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions([
        'manage-admin-panel', 'edit_user',
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);

    $this->user = User::factory()->create([
        'email' => 'member@example.com',
        'email_verified_at' => '2026-01-15 10:00:00',
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
    ]);
});

function verificationUpdatePayload(User $user, array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => $user->email,
        'gender' => 'male',
        'birth_year' => 1990,
        'branch_id' => $user->branch_id,
        'division_id' => $user->division_id,
        'contribution_type' => 'volunteering',
    ], $overrides);
}

test('a password change keeps email_verified_at', function () {
    $this->actingAs($this->admin)
        ->put(route('users.update', $this->user), verificationUpdatePayload($this->user, [
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ]))
        ->assertSessionHasNoErrors();

    $this->user->refresh();
    expect(Hash::check('NewSecret123', $this->user->password))->toBeTrue()
        ->and($this->user->email_verified_at?->toDateTimeString())->toBe('2026-01-15 10:00:00');
});

test('an edit that keeps the same email keeps email_verified_at', function () {
    $this->actingAs($this->admin)
        ->put(route('users.update', $this->user), verificationUpdatePayload($this->user, ['first_name' => 'Renamed']))
        ->assertSessionHasNoErrors();

    expect($this->user->fresh()->email_verified_at?->toDateTimeString())->toBe('2026-01-15 10:00:00');
});

test('an email change clears email_verified_at', function () {
    $this->actingAs($this->admin)
        ->put(route('users.update', $this->user), verificationUpdatePayload($this->user, ['email' => 'new@example.com']))
        ->assertSessionHasNoErrors();

    $this->user->refresh();
    expect($this->user->email)->toBe('new@example.com')
        ->and($this->user->email_verified_at)->toBeNull();
});

test('emptying the email clears email_verified_at, leaving an email-less account', function () {
    $this->actingAs($this->admin)
        ->put(route('users.update', $this->user), verificationUpdatePayload($this->user, ['email' => '']))
        ->assertSessionHasNoErrors();

    $this->user->refresh();
    expect($this->user->email)->toBeNull()
        ->and($this->user->email_verified_at)->toBeNull();
});

test('an email change sends the verification email to the new address', function () {
    Notification::fake();

    $this->actingAs($this->admin)
        ->put(route('users.update', $this->user), verificationUpdatePayload($this->user, ['email' => 'new@example.com']))
        ->assertSessionHasNoErrors();

    Notification::assertSentTo(
        $this->user->fresh(),
        VerifyEmailNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->email === 'new@example.com'
    );
});

test('a password-only change sends no verification email', function () {
    Notification::fake();

    $this->actingAs($this->admin)
        ->put(route('users.update', $this->user), verificationUpdatePayload($this->user, [
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ]))
        ->assertSessionHasNoErrors();

    Notification::assertNothingSentTo($this->user->fresh());
});

test('emptying the email sends no verification email', function () {
    Notification::fake();

    $this->actingAs($this->admin)
        ->put(route('users.update', $this->user), verificationUpdatePayload($this->user, ['email' => '']))
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

test('a failing verification email does not break the update', function () {
    app(ChannelManager::class)->extend('sendgrid', fn () => new class {
        public function send($notifiable, $notification): void
        {
            throw new RuntimeException('no mail provider');
        }
    });

    $this->actingAs($this->admin)
        ->put(route('users.update', $this->user), verificationUpdatePayload($this->user, ['email' => 'new@example.com']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('users.show', $this->user));

    expect($this->user->fresh()->email)->toBe('new@example.com');
});
