<?php

/**
 * Fee distribution: five whole-percentage settings (group 'fee_distribution')
 * that split online membership income between HQ, branch and division,
 * edited on their own Settings page because each rule must add up to 100.
 */

use App\Models\Log as AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\FeeDistributionSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    foreach (['change_settings', 'manage-admin-panel', 'manage_roles_and_permissions'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')
        ->syncPermissions(['change_settings', 'manage-admin-panel', 'manage_roles_and_permissions']);
    // Can open edit-roles, but not Settings (matches PermissionsTableSeeder).
    Role::findOrCreate('branch_db_administrator', 'web')
        ->syncPermissions(['manage-admin-panel', 'manage_roles_and_permissions']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->national = User::factory()->create();
    $this->national->assignRole('national_db_administrator');
});

function feeDistributionPayload(array $standard = [25, 25, 50], array $branchLevel = [25, 75]): array
{
    return ['percentages' => [
        'standard' => ['hq' => $standard[0], 'branch' => $standard[1], 'division' => $standard[2]],
        'branch_level' => ['hq' => $branchLevel[0], 'branch' => $branchLevel[1]],
    ]];
}

function saveFeeDistribution(array $payload, ?User $user = null)
{
    return test()->actingAs($user ?? test()->national)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.settings.fee-distribution.update'), $payload);
}

test('the migration installs the five settings with the default split', function () {
    // The group also holds the manual payments go-live date (ManualGoLiveSettingTest).
    expect(Setting::where('group', 'fee_distribution')->where('type', 'int')->pluck('value', 'key')->all())->toEqual([
        'fee_distribution.standard.hq' => '25',
        'fee_distribution.standard.branch' => '25',
        'fee_distribution.standard.division' => '50',
        'fee_distribution.branch_level.hq' => '25',
        'fee_distribution.branch_level.branch' => '75',
    ]);

    expect(FeeDistributionSettings::percentages())->toBe(FeeDistributionSettings::DEFAULTS);
});

test('the National DB admin sees the page with the note about earlier payments', function () {
    $this->withoutVite();

    $this->actingAs($this->national)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.settings.fee-distribution.index'))
        ->assertOk()
        ->assertSee('Changes apply only to payments made after saving. Earlier payments keep the percentages that applied when they were paid.')
        ->assertSee('name="percentages[standard][division]"', false)
        ->assertSee('name="percentages[branch_level][branch]"', false);
});

test('the settings index links to the page', function () {
    $this->withoutVite();

    $this->actingAs($this->national)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee(route('admin.settings.fee-distribution.index'), false);
});

test('saving changes updates the values, clears the cache and audits only the changed keys', function () {
    // Prime the cache with the old values first.
    expect(FeeDistributionSettings::percentages()['standard']['hq'])->toBe(25);

    saveFeeDistribution(feeDistributionPayload([30, 20, 50], [25, 75]))
        ->assertRedirect(route('admin.settings.fee-distribution.index'))
        ->assertSessionHasNoErrors();

    expect(FeeDistributionSettings::percentages())->toBe([
        'standard' => ['hq' => 30, 'branch' => 20, 'division' => 50],
        'branch_level' => ['hq' => 25, 'branch' => 75],
    ]);

    $logs = AuditLog::where('action', 'setting_changed')->orderBy('id')->get();
    expect($logs)->toHaveCount(2)
        ->and($logs[0]->old_values)->toBe(['fee_distribution.standard.hq' => '25'])
        ->and($logs[0]->new_values)->toBe(['fee_distribution.standard.hq' => '30'])
        ->and($logs[1]->old_values)->toBe(['fee_distribution.standard.branch' => '25'])
        ->and($logs[1]->new_values)->toBe(['fee_distribution.standard.branch' => '20']);
});

test('saving unchanged values writes no audit entries', function () {
    saveFeeDistribution(feeDistributionPayload())->assertSessionHasNoErrors();

    expect(AuditLog::where('action', 'setting_changed')->exists())->toBeFalse();
});

test('a rule that does not add up to 100 is rejected and nothing is saved', function (array $standard, array $branchLevel, string $errorKey) {
    saveFeeDistribution(feeDistributionPayload($standard, $branchLevel))
        ->assertSessionHasErrors($errorKey);

    expect(FeeDistributionSettings::percentages())->toBe(FeeDistributionSettings::DEFAULTS)
        ->and(AuditLog::where('action', 'setting_changed')->exists())->toBeFalse();
})->with([
    'standard over 100' => [[30, 25, 50], [25, 75], 'percentages.standard'],
    'standard under 100' => [[20, 25, 50], [25, 75], 'percentages.standard'],
    'branch level under 100' => [[25, 25, 50], [25, 70], 'percentages.branch_level'],
]);

test('values must be whole numbers from 0 to 100', function (mixed $value) {
    saveFeeDistribution(feeDistributionPayload([25, 25, 50], [$value, 75]))
        ->assertSessionHasErrors('percentages.branch_level.hq');

    expect(FeeDistributionSettings::percentages())->toBe(FeeDistributionSettings::DEFAULTS);
})->with([
    'negative' => [-25],
    'over 100' => [101],
    'decimal' => [25.5],
    'text' => ['abc'],
    'missing' => [null],
]);

test('0 and 100 are allowed', function () {
    saveFeeDistribution(feeDistributionPayload([0, 0, 100], [100, 0]))->assertSessionHasNoErrors();

    expect(FeeDistributionSettings::percentages())->toBe([
        'standard' => ['hq' => 0, 'branch' => 0, 'division' => 100],
        'branch_level' => ['hq' => 100, 'branch' => 0],
    ]);
});

test('a user without change_settings cannot open or save the page', function () {
    $branchAdmin = User::factory()->create();
    $branchAdmin->assignRole('branch_db_administrator');

    $this->actingAs($branchAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.settings.fee-distribution.index'))
        ->assertForbidden();

    saveFeeDistribution(feeDistributionPayload([30, 20, 50]), $branchAdmin)->assertForbidden();

    expect(FeeDistributionSettings::percentages())->toBe(FeeDistributionSettings::DEFAULTS);
});

test('the page asks for the password again like the rest of Settings', function () {
    $this->actingAs($this->national)
        ->get(route('admin.settings.fee-distribution.index'))
        ->assertRedirect(route('password.confirm'));
});

test('the generic settings form neither shows nor saves the fee distribution keys', function () {
    $this->withoutVite();

    $this->actingAs($this->national)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertDontSee('fee_distribution.standard.hq');

    $this->actingAs($this->national)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.settings.update'), ['settings' => ['fee_distribution.standard.hq' => '90']])
        ->assertRedirect(route('admin.settings.index'));

    expect(Setting::where('key', 'fee_distribution.standard.hq')->value('value'))->toBe('25')
        ->and(AuditLog::where('action', 'setting_changed')->exists())->toBeFalse();
});
