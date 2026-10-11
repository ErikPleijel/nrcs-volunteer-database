<?php

/**
 * Income Distribution Tab 2: the manual payments go-live date
 * (MANUAL_GO_LIVE_DATE in .env, read through config/income.php) and
 * membership_payments.accounting_date.
 */

use App\Models\MembershipPayment;
use App\Models\Setting;
use App\Models\User;
use App\Services\FeeDistributionSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('accounting_date has an index, and the old go-live setting row is gone', function () {
    expect(Schema::hasColumn('membership_payments', 'accounting_date'))->toBeTrue();

    $indexColumns = collect(DB::select("SHOW INDEX FROM membership_payments WHERE Key_name = 'membership_payments_channel_accounting_date_index'"))
        ->sortBy('Seq_in_index')->pluck('Column_name')->all();
    expect($indexColumns)->toBe(['payment_channel', 'accounting_date']);

    expect(Setting::where('key', 'fee_distribution.manual_go_live_date')->exists())->toBeFalse();
});

test('accounting_date is stored and read as a date, and is empty by default', function () {
    $payment = MembershipPayment::factory()->approved()->create();
    expect($payment->fresh()->accounting_date)->toBeNull();

    $payment->update(['accounting_date' => '2026-07-15']);
    expect($payment->fresh()->accounting_date->toDateString())->toBe('2026-07-15');
});

test('while MANUAL_GO_LIVE_DATE is empty, there is no go-live date', function (?string $value) {
    config(['income.manual_go_live_date' => $value]);

    expect(FeeDistributionSettings::manualGoLiveDate())->toBeNull();
})->with([
    'not set' => [null],
    'empty' => [''],
    'spaces' => ['  '],
]);

test('the go-live date is the start of that day in Lagos time', function () {
    config(['income.manual_go_live_date' => '2026-11-01']);

    $date = FeeDistributionSettings::manualGoLiveDate();
    expect($date->toDateString())->toBe('2026-11-01')
        ->and($date->format('H:i:s'))->toBe('00:00:00')
        ->and($date->getTimezone()->getName())->toBe('Africa/Lagos')
        ->and($date->toIso8601String())->toBe('2026-11-01T00:00:00+01:00');
});

test('an invalid value counts as not set and is logged', function (string $value) {
    Log::spy();
    config(['income.manual_go_live_date' => $value]);

    expect(FeeDistributionSettings::manualGoLiveDate())->toBeNull();
    Log::shouldHaveReceived('warning')->once();
})->with([
    'not a date' => ['soon'],
    'wrong format' => ['01/11/2026'],
    'impossible' => ['2026-02-30'],
]);

test('the Fee distribution page no longer has a go-live field, and saving it does nothing', function () {
    $this->withoutVite();
    foreach (['change_settings', 'manage-admin-panel'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['change_settings', 'manage-admin-panel']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $national = User::factory()->create();
    $national->assignRole('national_db_administrator');

    $this->actingAs($national)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.settings.fee-distribution.index'))
        ->assertOk()
        ->assertDontSee('manual_go_live_date')
        ->assertDontSee('Manual payments (collected by branches)');

    $this->actingAs($national)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.settings.fee-distribution.update'), [
            'percentages' => FeeDistributionSettings::percentages(),
            'manual_go_live_date' => '2026-11-01',
        ])
        ->assertSessionHasNoErrors();

    expect(FeeDistributionSettings::manualGoLiveDate())->toBeNull()
        ->and(Setting::where('key', 'fee_distribution.manual_go_live_date')->exists())->toBeFalse();
});
