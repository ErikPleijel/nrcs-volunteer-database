<?php

/**
 * users/edit on a self-managed account (the person has logged in or
 * registered themselves): staff can change login details (email, phones,
 * password — all audited; an email or password change also emails the
 * person) and organisational fields,
 * but not personal details. A national DB administrator may still correct
 * name, gender and birth year, with a reason in the Audit Log.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\Log as AuditLog;
use App\Models\User;
use App\Notifications\AccountDetailsChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    foreach (['national_db_administrator', 'branch_db_administrator'] as $role) {
        Role::findOrCreate($role, 'web')->syncPermissions(['manage-admin-panel', 'edit_user']);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);

    $this->national = User::factory()->create();
    $this->national->assignRole('national_db_administrator');

    $this->branchAdmin = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);
    $this->branchAdmin->assignRole('branch_db_administrator');

    $this->person = User::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Okafor',
        'email' => 'ada@example.com',
        'telephone1' => '08031234567',
        'gender' => 'female',
        'birth_year' => 1990,
        'occupation' => 'Nurse',
        'residential_address' => '1 Old Road',
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
        'can_contribute_volunteering' => true,
        'self_managed_at' => now()->subMonth(),
    ]);
});

/** What the locked form submits: no personal fields except, for a national admin, the correctable ones. */
function selfManagedPayload(User $user, array $overrides = []): array
{
    return array_merge([
        'email' => $user->email,
        'telephone1' => $user->telephone1,
        'branch_id' => $user->branch_id,
        'division_id' => $user->division_id,
        'contribution_type' => 'volunteering',
    ], $overrides);
}

function nationalPayload(User $user, array $overrides = []): array
{
    return selfManagedPayload($user, array_merge([
        'first_name' => $user->first_name,
        'middle_name' => $user->middle_name,
        'last_name' => $user->last_name,
        'gender' => $user->gender,
        'birth_year' => $user->birth_year,
    ], $overrides));
}

test('the first own login marks the account self-managed', function () {
    $user = User::factory()->create(['email' => 'login@example.com', 'self_managed_at' => null]);
    expect($user->isSelfManaged())->toBeFalse();

    $this->post('/login', ['login' => 'login@example.com', 'password' => 'password']);

    expect($user->fresh()->isSelfManaged())->toBeTrue();
});

test('a later login keeps the first self-managed date', function () {
    $first = now()->subYear()->startOfSecond();
    $user = User::factory()->create(['email' => 'login@example.com', 'self_managed_at' => $first]);

    $this->post('/login', ['login' => 'login@example.com', 'password' => 'password']);

    expect($user->fresh()->self_managed_at->equalTo($first))->toBeTrue();
});

test('a not yet self-managed account stays fully editable', function () {
    $this->person->forceFill(['self_managed_at' => null])->save();

    $this->actingAs($this->branchAdmin)
        ->put(route('users.update', $this->person), nationalPayload($this->person, [
            'first_name' => 'Adaeze',
            'occupation' => 'Doctor',
        ]))
        ->assertSessionHasNoErrors();

    $this->person->refresh();
    expect($this->person->first_name)->toBe('Adaeze')
        ->and($this->person->occupation)->toBe('Doctor')
        ->and(AuditLog::where('action', 'personal_details_corrected')->exists())->toBeFalse();
});

test('the edit page shows locked fields as text, with the notice', function () {
    $this->actingAs($this->branchAdmin)
        ->get(route('users.edit', $this->person))
        ->assertOk()
        ->assertSee('This person manages their own details in My Profile.')
        ->assertDontSee('name="first_name"', false)
        ->assertDontSee('name="residential_address"', false)
        ->assertDontSee('name="correction_reason"', false)
        ->assertSee('name="email"', false)
        ->assertSee('name="telephone1"', false);
});

test('a national admin sees name, gender and birth year inputs and the reason field', function () {
    $this->actingAs($this->national)
        ->get(route('users.edit', $this->person))
        ->assertOk()
        ->assertSee('name="first_name"', false)
        ->assertSee('name="gender"', false)
        ->assertSee('name="birth_year"', false)
        ->assertSee('name="correction_reason"', false)
        ->assertDontSee('name="residential_address"', false)
        ->assertDontSee('name="national_id_number"', false);
});

test('a branch admin cannot change locked personal fields', function (string $field, mixed $value) {
    $before = $this->person->$field;

    $this->actingAs($this->branchAdmin)
        ->put(route('users.update', $this->person), selfManagedPayload($this->person, [$field => $value]))
        ->assertSessionHasErrors($field);

    expect($this->person->fresh()->$field)->toBe($before);
})->with([
    ['first_name', 'Changed'],
    ['last_name', 'Changed'],
    ['birth_year', 1980],
    ['gender', 'male'],
    ['title', 'Dr.'],
    ['occupation', 'Teacher'],
    ['residential_address', '2 New Road'],
    ['national_id_number', '12345678901'],
    ['personal_info', 'something'],
]);

test('resubmitting a locked field unchanged is not an error', function () {
    $this->actingAs($this->branchAdmin)
        ->put(route('users.update', $this->person), selfManagedPayload($this->person, [
            'first_name' => 'Ada',
            'residential_address' => ' 1 Old Road ',
        ]))
        ->assertSessionHasNoErrors();
});

test('saving the locked form keeps the personal details, title included', function () {
    $this->person->forceFill(['title' => 'Mrs'])->save();

    $this->actingAs($this->branchAdmin)
        ->put(route('users.update', $this->person), selfManagedPayload($this->person))
        ->assertSessionHasNoErrors();

    $this->person->refresh();
    expect($this->person->title)->toBe('Mrs')
        ->and($this->person->first_name)->toBe('Ada')
        ->and($this->person->birth_year)->toBe(1990)
        ->and($this->person->occupation)->toBe('Nurse');
});

test('a national admin cannot change other personal fields either', function () {
    $this->actingAs($this->national)
        ->put(route('users.update', $this->person), nationalPayload($this->person, ['occupation' => 'Teacher']))
        ->assertSessionHasErrors('occupation');

    expect($this->person->fresh()->occupation)->toBe('Nurse');
});

test('a national admin correction needs a reason', function () {
    $this->actingAs($this->national)
        ->put(route('users.update', $this->person), nationalPayload($this->person, ['last_name' => 'Okafor-Eze']))
        ->assertSessionHasErrors('correction_reason');

    expect($this->person->fresh()->last_name)->toBe('Okafor');
});

test('a national admin correction with a reason is saved and logged', function () {
    $this->actingAs($this->national)
        ->put(route('users.update', $this->person), nationalPayload($this->person, [
            'last_name' => 'Okafor-Eze',
            'birth_year' => 1991,
            'gender' => 'male',
            'correction_reason' => 'Matches her NIN slip',
        ]))
        ->assertSessionHasNoErrors();

    $this->person->refresh();
    expect($this->person->last_name)->toBe('Okafor-Eze')
        ->and($this->person->birth_year)->toBe(1991)
        ->and($this->person->gender)->toBe('male');

    $log = AuditLog::where('action', 'personal_details_corrected')->where('subject_id', $this->person->id)->sole();
    expect($log->user_id)->toBe($this->national->id)
        ->and($log->old_values)->toBe(['last_name' => 'Okafor', 'gender' => 'female', 'birth_year' => 1990])
        ->and($log->new_values)->toMatchArray(['last_name' => 'Okafor-Eze', 'reason' => 'Matches her NIN slip'])
        ->and($log->description)->toContain('Matches her NIN slip');
});

test('a national admin save without corrections needs no reason', function () {
    $this->actingAs($this->national)
        ->put(route('users.update', $this->person), nationalPayload($this->person))
        ->assertSessionHasNoErrors();

    expect(AuditLog::where('action', 'personal_details_corrected')->exists())->toBeFalse();
});

test('staff can change email and phone; both are logged and the old address is told of the email only', function () {
    Notification::fake();

    $this->actingAs($this->branchAdmin)
        ->put(route('users.update', $this->person), selfManagedPayload($this->person, [
            'email' => 'ada.new@example.com',
            'telephone1' => '08039999999',
        ]))
        ->assertSessionHasNoErrors();

    $this->person->refresh();
    expect($this->person->email)->toBe('ada.new@example.com')
        ->and($this->person->telephone1)->toBe('08039999999');

    $log = AuditLog::where('action', 'user_contact_changed')->where('subject_id', $this->person->id)->sole();
    expect($log->old_values)->toBe(['email' => 'ada@example.com', 'telephone1' => '08031234567'])
        ->and($log->new_values)->toBe(['email' => 'ada.new@example.com', 'telephone1' => '08039999999']);

    Notification::assertSentTo($this->person, AccountDetailsChanged::class,
        fn (AccountDetailsChanged $n) => $n->to === 'ada@example.com'
            && $n->changes === ['Email address changed to ada.new@example.com']);
});

test('an email added where there was none notifies the new address', function () {
    Notification::fake();
    $this->person->forceFill(['email' => null])->save();

    $this->actingAs($this->branchAdmin)
        ->put(route('users.update', $this->person), selfManagedPayload($this->person, ['email' => 'ada.new@example.com']))
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($this->person, AccountDetailsChanged::class,
        fn (AccountDetailsChanged $n) => $n->to === 'ada.new@example.com');
});

test('the success message names the masked address the notice went to', function () {
    Notification::fake();

    $this->actingAs($this->branchAdmin)
        ->put(route('users.update', $this->person), selfManagedPayload($this->person, ['email' => 'ada.new@example.com']))
        ->assertSessionHas('success', 'User updated successfully. A notice was sent to a***@example.com.');
});

test('the success message says nothing extra when no notice was sent', function () {
    Notification::fake();

    $this->actingAs($this->branchAdmin)
        ->put(route('users.update', $this->person), selfManagedPayload($this->person, ['contribution_type' => 'member']))
        ->assertSessionHas('success', 'User updated successfully.');
});

test('staff see the audit notes next to the login and correction fields', function () {
    $this->actingAs($this->branchAdmin)
        ->get(route('users.edit', $this->person))
        ->assertSee('Changes here are recorded in the Audit Log, and the person will be notified by email.')
        ->assertSee('Changes here are recorded in the Audit Log.')
        ->assertDontSee('Corrections need a reason and are recorded in the Audit Log.');

    $this->actingAs($this->national)
        ->get(route('users.edit', $this->person))
        ->assertSee('Corrections need a reason and are recorded in the Audit Log.');
});

test('without an email address the note only mentions the Audit Log', function () {
    $this->person->forceFill(['email' => null])->save();

    $this->actingAs($this->branchAdmin)
        ->get(route('users.edit', $this->person))
        ->assertSee('Changes here are recorded in the Audit Log.')
        ->assertDontSee('the person will be notified by email');
});

test('no audit notes on a not yet self-managed account', function () {
    $this->person->forceFill(['self_managed_at' => null])->save();

    $this->actingAs($this->national)
        ->get(route('users.edit', $this->person))
        ->assertDontSee('Changes here are recorded in the Audit Log')
        ->assertDontSee('Corrections need a reason');
});

test('a phone change alone sends no notice but is logged', function () {
    Notification::fake();

    $this->actingAs($this->branchAdmin)
        ->put(route('users.update', $this->person), selfManagedPayload($this->person, ['telephone1' => '08039999999']))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'User updated successfully.');

    Notification::assertNotSentTo($this->person, AccountDetailsChanged::class);

    $log = AuditLog::where('action', 'user_contact_changed')->where('subject_id', $this->person->id)->sole();
    expect($log->old_values)->toBe(['telephone1' => '08031234567'])
        ->and($log->new_values)->toBe(['telephone1' => '08039999999']);
});

test('a password set by staff is logged without the value and the person is told', function () {
    Notification::fake();

    $this->actingAs($this->branchAdmin)
        ->put(route('users.update', $this->person), selfManagedPayload($this->person, [
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ]))
        ->assertSessionHasNoErrors();

    $log = AuditLog::where('action', 'user_password_set_by_staff')->where('subject_id', $this->person->id)->sole();
    expect($log->new_values)->toBeNull();

    Notification::assertSentTo($this->person, AccountDetailsChanged::class,
        fn (AccountDetailsChanged $n) => $n->to === 'ada@example.com'
            && $n->changes === ['A new password was set']);
});

test('no notice for a not yet self-managed account, but the change is logged', function () {
    Notification::fake();
    $this->person->forceFill(['self_managed_at' => null])->save();

    $this->actingAs($this->branchAdmin)
        ->put(route('users.update', $this->person), nationalPayload($this->person, ['telephone1' => '08039999999']))
        ->assertSessionHasNoErrors();

    Notification::assertNotSentTo($this->person, AccountDetailsChanged::class);
    expect(AuditLog::where('action', 'user_contact_changed')->where('subject_id', $this->person->id)->exists())->toBeTrue();
});

test('no notice when nothing login-related changed', function () {
    Notification::fake();

    $this->actingAs($this->branchAdmin)
        ->put(route('users.update', $this->person), selfManagedPayload($this->person))
        ->assertSessionHasNoErrors();

    Notification::assertNotSentTo($this->person, AccountDetailsChanged::class);
    expect(AuditLog::where('action', 'user_contact_changed')->exists())->toBeFalse();
});

test('organisational fields stay editable on a self-managed account', function () {
    $this->actingAs($this->branchAdmin)
        ->put(route('users.update', $this->person), selfManagedPayload($this->person, [
            'contribution_type' => 'member',
            'red_cross_id_number' => 'ALP0456',
        ]))
        ->assertSessionHasNoErrors();

    $this->person->refresh();
    expect((bool) $this->person->can_contribute_member)->toBeTrue()
        ->and($this->person->red_cross_id_number)->toBe('ALP0456');
});

test('an admin editing their own self-managed account gets the same lock', function () {
    $this->branchAdmin->forceFill(['self_managed_at' => now()])->save();

    $this->actingAs($this->branchAdmin)
        ->put(route('users.update', $this->branchAdmin), selfManagedPayload($this->branchAdmin, ['first_name' => 'Me']))
        ->assertSessionHasErrors('first_name');
});
