<?php

/**
 * Income Distribution Tab 2, step 2: the manual payments go-live date
 * (setting fee_distribution.manual_go_live_date, edited on the Fee
 * distribution page) and membership_payments.accounting_date.
 */

use App\Models\Log as AuditLog;
use App\Models\MembershipPayment;
use App\Models\Setting;
use App\Models\User;
use App\Services\FeeDistributionSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    foreach (['change_settings', 'manage-admin-panel'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['change_settings', 'manage-admin-panel']);
    Role::findOrCreate('branch_db_administrator', 'web')->syncPermissions(['manage-admin-panel']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->national = User::factory()->create();
    $this->national->assignRole('national_db_administrator');
});

/** The page's own form: the current percentages plus $extra. */
function saveDistributionPage(array $extra, ?User $user = null)
{
    $percentages = FeeDistributionSettings::percentages();

    return test()->actingAs($user ?? test()->national)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.settings.fee-distribution.update'), array_merge(['percentages' => $percentages], $extra));
}

test('the migration adds accounting_date with an index and an empty go-live setting', function () {
    expect(Schema::hasColumn('membership_payments', 'accounting_date'))->toBeTrue();

    $indexColumns = collect(DB::select("SHOW INDEX FROM membership_payments WHERE Key_name = 'membership_payments_channel_accounting_date_index'"))
        ->sortBy('Seq_in_index')->pluck('Column_name')->all();
    expect($indexColumns)->toBe(['payment_channel', 'accounting_date']);

    $setting = Setting::where('key', 'fee_distribution.manual_go_live_date')->sole();
    expect($setting->value)->toBe('')
        ->and($setting->group)->toBe('fee_distribution')
        ->and($setting->type)->toBe('date');
});

test('while the go-live date is empty, there is no go-live date', function () {
    expect(FeeDistributionSettings::manualGoLiveDate())->toBeNull();
});

test('accounting_date is stored and read as a date, and is empty by default', function () {
    $payment = MembershipPayment::factory()->approved()->create();
    expect($payment->fresh()->accounting_date)->toBeNull();

    $payment->update(['accounting_date' => '2026-07-15']);
    expect($payment->fresh()->accounting_date->toDateString())->toBe('2026-07-15');
});

test('the national DB administrator can set, audit and clear the go-live date', function () {
    saveDistributionPage(['manual_go_live_date' => '2026-11-01'])
        ->assertRedirect(route('admin.settings.fee-distribution.index'))
        ->assertSessionHasNoErrors();

    expect(FeeDistributionSettings::manualGoLiveDate()?->toDateString())->toBe('2026-11-01')
        ->and(FeeDistributionSettings::manualGoLiveDate()->format('H:i:s'))->toBe('00:00:00');

    $log = AuditLog::where('action', 'setting_changed')->sole();
    expect($log->old_values)->toBe(['fee_distribution.manual_go_live_date' => ''])
        ->and($log->new_values)->toBe(['fee_distribution.manual_go_live_date' => '2026-11-01']);

    saveDistributionPage(['manual_go_live_date' => ''])->assertSessionHasNoErrors();

    expect(FeeDistributionSettings::manualGoLiveDate())->toBeNull()
        ->and(Setting::where('key', 'fee_distribution.manual_go_live_date')->value('value'))->toBe('');
});

test('the page shows the go-live field with its current value', function () {
    $this->withoutVite();
    Setting::where('key', 'fee_distribution.manual_go_live_date')->update(['value' => '2026-11-01']);
    Cache::forget('setting.fee_distribution.manual_go_live_date');

    $this->actingAs($this->national)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.settings.fee-distribution.index'))
        ->assertOk()
        ->assertSee('name="manual_go_live_date"', false)
        ->assertSee('value="2026-11-01"', false)
        ->assertSee('Leave empty until manual payments go live.');
});

test('an invalid date is refused and nothing is saved', function (string $value) {
    saveDistributionPage(['manual_go_live_date' => $value])->assertSessionHasErrors('manual_go_live_date');

    expect(Setting::where('key', 'fee_distribution.manual_go_live_date')->value('value'))->toBe('')
        ->and(AuditLog::where('action', 'setting_changed')->exists())->toBeFalse();
})->with([
    'not a date' => ['soon'],
    'wrong format' => ['01/11/2026'],
    'impossible' => ['2026-02-30'],
]);

test('saving without the field leaves the go-live date unchanged; saving it unchanged writes no audit entry', function () {
    saveDistributionPage(['manual_go_live_date' => '2026-11-01']);
    AuditLog::query()->delete();

    saveDistributionPage([])->assertSessionHasNoErrors();
    saveDistributionPage(['manual_go_live_date' => '2026-11-01'])->assertSessionHasNoErrors();

    expect(FeeDistributionSettings::manualGoLiveDate()?->toDateString())->toBe('2026-11-01')
        ->and(AuditLog::where('action', 'setting_changed')->exists())->toBeFalse();
});

test('only change_settings can set it, and the generic settings form neither shows nor saves it', function () {
    $branchAdmin = User::factory()->create();
    $branchAdmin->assignRole('branch_db_administrator');

    saveDistributionPage(['manual_go_live_date' => '2026-11-01'], $branchAdmin)->assertForbidden();

    $this->withoutVite();
    $this->actingAs($this->national)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertDontSee('fee_distribution.manual_go_live_date');

    $this->actingAs($this->national)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.settings.update'), ['settings' => ['fee_distribution.manual_go_live_date' => '2026-11-01']]);

    expect(FeeDistributionSettings::manualGoLiveDate())->toBeNull();
});
