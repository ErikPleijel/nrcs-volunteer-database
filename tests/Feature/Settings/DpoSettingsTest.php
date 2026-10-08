<?php

/**
 * Data Protection Officer contact details: four institutional settings
 * (dpo.name, dpo.address, dpo.email, dpo.phone) in the 'Data protection'
 * group, seeded empty by a data migration and edited on the Settings page
 * by the National DB administrator. Not a role — see Decisions.md 2026-10-08.
 */

use App\Models\Log as AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const DPO_KEYS = ['dpo.name', 'dpo.address', 'dpo.email', 'dpo.phone'];

beforeEach(function () {
    Cache::flush();

    Permission::findOrCreate('change_settings', 'web');
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['change_settings']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->national = User::factory()->create();
    $this->national->assignRole('national_db_administrator');
});

function saveDpoSettings(array $values)
{
    return test()->actingAs(test()->national)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.settings.update'), ['settings' => $values]);
}

test('the migration installs the four DPO settings, empty, in the Data protection group', function () {
    foreach (DPO_KEYS as $key) {
        $setting = Setting::where('key', $key)->first();

        expect($setting)->not->toBeNull()
            ->and($setting->type)->toBe('string')
            ->and($setting->group)->toBe('Data protection')
            ->and($setting->value)->toBe('');
    }

    expect(Setting::where('key', 'dpo.name')->value('label'))->toBe('DPO name')
        ->and(Setting::where('key', 'dpo.address')->value('label'))->toBe('DPO postal address')
        ->and(Setting::where('key', 'dpo.email')->value('label'))->toBe('DPO email')
        ->and(Setting::where('key', 'dpo.phone')->value('label'))->toBe('DPO phone');
});

test('the National DB admin can update the DPO settings, audited and with the cache cleared', function () {
    // Prime the cache with the empty value first.
    expect(Setting::get('dpo.name'))->toBe('');

    saveDpoSettings([
        'dpo.name' => 'Data Protection Officer, NRCS National Headquarters',
        'dpo.address' => "Plot 589 T.O.S Benson Crescent\nUtako, Abuja",
        'dpo.email' => 'dpo@redcrossnigeria.org',
        'dpo.phone' => '+234 803 000 0000',
    ])->assertRedirect(route('admin.settings.index'));

    expect(Setting::get('dpo.name'))->toBe('Data Protection Officer, NRCS National Headquarters')
        ->and(Setting::get('dpo.address'))->toBe("Plot 589 T.O.S Benson Crescent\nUtako, Abuja")
        ->and(Setting::get('dpo.email'))->toBe('dpo@redcrossnigeria.org')
        ->and(Setting::get('dpo.phone'))->toBe('+234 803 000 0000');

    $log = AuditLog::where('action', 'setting_changed')
        ->where('description', 'like', 'Setting "dpo.email"%')
        ->sole();
    expect($log->old_values)->toBe(['dpo.email' => ''])
        ->and($log->new_values)->toBe(['dpo.email' => 'dpo@redcrossnigeria.org']);
});

test('the settings page shows the Data protection group with a textarea for the address', function () {
    $this->withoutVite();

    $this->actingAs($this->national)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertSee('Data protection')
        ->assertSee('<textarea name="settings[dpo.address]"', false)
        ->assertSee('<input type="text" name="settings[dpo.email]"', false);
});

test('an invalid DPO email is rejected and nothing is saved', function () {
    saveDpoSettings([
        'dpo.name' => 'Ada Officer',
        'dpo.email' => 'not-an-email',
    ])->assertSessionHasErrors();

    expect(Setting::where('key', 'dpo.email')->value('value'))->toBe('')
        ->and(Setting::where('key', 'dpo.name')->value('value'))->toBe('')
        ->and(AuditLog::where('action', 'setting_changed')->exists())->toBeFalse();
});

test('saving the page with the DPO settings still empty writes no audit entries', function () {
    saveDpoSettings(['dpo.name' => '', 'dpo.address' => '', 'dpo.email' => '', 'dpo.phone' => ''])
        ->assertRedirect(route('admin.settings.index'));

    expect(AuditLog::where('action', 'setting_changed')->exists())->toBeFalse();
});

test('an empty DPO email is accepted', function () {
    Setting::where('key', 'dpo.email')->first()->update(['value' => 'old@redcrossnigeria.org']);

    saveDpoSettings(['dpo.email' => ''])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.settings.index'));

    expect(Setting::where('key', 'dpo.email')->value('value'))->toBe('');
});
