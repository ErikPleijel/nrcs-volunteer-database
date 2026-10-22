<?php

/**
 * users/edit writes one `user_details_changed` Audit Log entry per save for
 * the unit assignment, email/SMS opt-outs and contribution type: old and new
 * values of only the fields that changed, with the unit's name.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\Log as AuditLog;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['manage-admin-panel', 'edit_user']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);
    $this->unitA = RedCrossUnit::create(['name' => 'Unit A', 'division_id' => $this->division->id, 'is_active' => true]);
    $this->unitB = RedCrossUnit::create(['name' => 'Unit B', 'division_id' => $this->division->id, 'is_active' => true]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $this->person = User::factory()->create([
        'email' => 'ada@example.com',
        'telephone1' => '08031234567',
        'gender' => 'female',
        'birth_year' => 1990,
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
        'red_cross_unit_id' => $this->unitA->id,
        'can_contribute_volunteering' => true,
        'can_contribute_member' => false,
        'email_opt_out' => false,
        'sms_opt_out' => false,
        'self_managed_at' => null,
    ]);
});

/** The form as it stands, with $overrides. */
function detailsPayload(User $user, array $overrides = []): array
{
    return array_merge([
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'email' => $user->email,
        'telephone1' => $user->telephone1,
        'gender' => $user->gender,
        'birth_year' => $user->birth_year,
        'branch_id' => $user->branch_id,
        'division_id' => $user->division_id,
        'red_cross_unit_id' => $user->red_cross_unit_id,
        'contribution_type' => 'volunteering',
    ], $overrides);
}

function detailsLog(User $user): AuditLog
{
    return AuditLog::where('action', 'user_details_changed')->where('subject_id', $user->id)->sole();
}

test('a unit change is logged with ids and names', function () {
    $this->actingAs($this->admin)
        ->put(route('users.update', $this->person), detailsPayload($this->person, ['red_cross_unit_id' => $this->unitB->id]))
        ->assertSessionHasNoErrors();

    $log = detailsLog($this->person);
    expect($log->old_values)->toBe(['red_cross_unit_id' => $this->unitA->id, 'red_cross_unit' => 'Unit A'])
        ->and($log->new_values)->toBe(['red_cross_unit_id' => $this->unitB->id, 'red_cross_unit' => 'Unit B'])
        ->and($log->description)->toContain('red_cross_unit');
});

test('removing the unit is logged with an empty new value', function () {
    $this->actingAs($this->admin)
        ->put(route('users.update', $this->person), detailsPayload($this->person, ['red_cross_unit_id' => null]))
        ->assertSessionHasNoErrors();

    expect(detailsLog($this->person)->new_values)->toBe(['red_cross_unit_id' => null, 'red_cross_unit' => null]);
});

test('opt-outs and contribution type changed in one save give one entry', function () {
    $this->actingAs($this->admin)
        ->put(route('users.update', $this->person), detailsPayload($this->person, [
            'email_opt_out' => '1',
            'sms_opt_out' => '1',
            'contribution_type' => 'member',
        ]))
        ->assertSessionHasNoErrors();

    $log = detailsLog($this->person);
    expect($log->old_values)->toBe(['email_opt_out' => false, 'sms_opt_out' => false, 'contribution_type' => 'volunteering'])
        ->and($log->new_values)->toBe(['email_opt_out' => true, 'sms_opt_out' => true, 'contribution_type' => 'member']);
});

test('only the field that changed is in the entry', function () {
    $this->person->forceFill(['sms_opt_out' => true, 'sms_opt_out_at' => now()])->save();

    $this->actingAs($this->admin)
        ->put(route('users.update', $this->person), detailsPayload($this->person, ['sms_opt_out' => '0']))
        ->assertSessionHasNoErrors();

    $log = detailsLog($this->person);
    expect($log->old_values)->toBe(['sms_opt_out' => true])
        ->and($log->new_values)->toBe(['sms_opt_out' => false]);
});

test('a person who was both volunteer and member shows that as the old type', function () {
    $this->person->forceFill(['can_contribute_member' => true])->save();

    $this->actingAs($this->admin)
        ->put(route('users.update', $this->person), detailsPayload($this->person))
        ->assertSessionHasNoErrors();

    expect(detailsLog($this->person)->old_values)->toBe(['contribution_type' => 'volunteering and member']);
});

test('no entry when none of these fields changed', function () {
    $this->actingAs($this->admin)
        ->put(route('users.update', $this->person), detailsPayload($this->person, ['last_name' => 'Changed']))
        ->assertSessionHasNoErrors();

    expect(AuditLog::where('action', 'user_details_changed')->exists())->toBeFalse();
});
