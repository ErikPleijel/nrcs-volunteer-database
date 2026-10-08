<?php

/**
 * Public privacy policy page (/privacy-policy). The wording lives only in
 * policies/privacy-policy-text.blade.php (with a draft banner until Legal's
 * final text arrives); the Data Protection Officer box below it is the
 * public variant of <x-dpo-contact />. Exempt from both login gates.
 */

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Cache::flush();
});

test('a guest can open the privacy policy and sees the draft banner', function () {
    $this->get(route('privacy-policy'))
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('Draft — the final text will be provided by the NRCS Legal department.')
        ->assertSee('Last updated:')
        ->assertSee('How long we keep your data');
});

test('the page shows the DPO name and email when they are set', function () {
    Setting::where('key', 'dpo.name')->first()->update(['value' => 'Ada Officer']);
    Setting::where('key', 'dpo.email')->first()->update(['value' => 'dpo@redcrossnigeria.org']);

    $this->get(route('privacy-policy'))
        ->assertOk()
        ->assertSee('Data Protection Officer (DPO)')
        ->assertSee('Ada Officer')
        ->assertSee('href="mailto:dpo@redcrossnigeria.org"', false)
        ->assertDontSee('will be published here soon');
});

test('with no DPO set the page says details will follow, never the admin Settings prompt', function () {
    $this->get(route('privacy-policy'))
        ->assertOk()
        ->assertSee('Contact details for the Data Protection Officer will be published here soon.')
        ->assertDontSee('No Data Protection Officer is registered.')
        ->assertDontSee('Go to <a', false);
});

test('a settings admin also sees the public note, not the Settings prompt', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('change_settings', 'web'));

    $this->actingAs($admin)->get(route('privacy-policy'))
        ->assertOk()
        ->assertSee('will be published here soon')
        ->assertDontSee('Go to <a', false);
});

test('a user who still has to confirm consent can open the page without being redirected', function () {
    $user = User::factory()->notConsented()->create();

    $this->actingAs($user)->get(route('privacy-policy'))->assertOk();
});

test('a role holder who has not accepted the staff policy can open the page too', function () {
    Role::findOrCreate('branch_db_administrator', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->notConsented()->policyNotAccepted()->create();
    $user->assignRole('branch_db_administrator');

    $this->actingAs($user)->get(route('privacy-policy'))->assertOk();
});

/*
|--------------------------------------------------------------------------
| Links to the privacy policy
|--------------------------------------------------------------------------
*/

test('profile/show links to the privacy policy', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Your privacy')
        ->assertSee('href="'.route('privacy-policy').'"', false);
});

test('the register page links to the privacy policy from the consent checkbox, in a new tab', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('<a href="'.route('privacy-policy').'" target="_blank" rel="noopener"', false)
        ->assertSee('Data Protection Officer</a>.', false);
});

test('the consent page links to the privacy policy', function () {
    $this->actingAs(User::factory()->notConsented()->create())
        ->get(route('consent.confirm'))
        ->assertOk()
        ->assertSee('Read our Privacy Policy')
        ->assertSee('href="'.route('privacy-policy').'" target="_blank" rel="noopener"', false);
});
