<?php

/**
 * Who may open a membership payment's (and a donation's) page. Regression
 * (sandbox, 2026-10-12): branch staff got 403 on payments in their own
 * branch — the page checked the PAYER's branch (UserPolicy) instead of the
 * payment's own, so an organisation payment whose contact lives elsewhere,
 * a unit payment whose team leader lives elsewhere, or a personal payment
 * whose payer later moved branch, could be listed but not opened.
 */

use App\Models\Donation;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\RedCrossUnit;
use App\Models\User;
use Database\Factories\MembershipFeeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const BRANCH_STAFF_ROLES = ['branch_db_administrator', 'branch_secretary', 'branch_db_assistant'];

beforeEach(function () {
    $this->withoutVite();

    DB::table('branches')->insert([
        ['id' => 848, 'name' => 'FCT', 'code' => 'FCT', 'is_active' => true],
        ['id' => 858, 'name' => 'Lagos', 'code' => 'LAG', 'is_active' => true],
    ]);
    DB::table('divisions')->insert([
        ['id' => 3069, 'name' => 'Abuja Municipal', 'branch_id' => 848, 'is_active' => true],
        ['id' => 3582, 'name' => 'Oshodi-Isolo', 'branch_id' => 858, 'is_active' => true],
    ]);

    $permissions = ['manage-admin-panel', 'view_payments', 'view_donations', 'view_user', 'view_organisation', 'view_red_cross_unit'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    foreach ([...BRANCH_STAFF_ROLES, 'division_db_assistant_finance', 'national_db_administrator'] as $role) {
        Role::findOrCreate($role, 'web')->syncPermissions($permissions);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->staff = fn (string $role, array $attributes = ['branch_id' => 848, 'division_id' => 3069]) => tap(
        User::factory()->create($attributes),
        fn (User $user) => $user->assignRole($role)
    );

    // Payers who live in Lagos, paying for things in FCT / Abuja Municipal.
    $lagosPerson = fn () => User::factory()->create(['branch_id' => 858, 'division_id' => 3582]);
    $fctPerson = User::factory()->create(['branch_id' => 848, 'division_id' => 3069]);
    $organisation = Organisation::create(['name' => 'Acme', 'branch_id' => 848, 'division_id' => 3069]);
    $unit = RedCrossUnit::create(['name' => 'Alpha Unit', 'division_id' => 3069, 'is_active' => true]);

    $payment = fn (User $payer, array $attributes = []) => MembershipPayment::factory()->approved()->create(array_merge([
        'user_id' => $payer->id, 'branch_id' => 848, 'division_id' => 3069,
        'membership_fee_id' => MembershipFeeFactory::new()->create()->id,
    ], $attributes));

    $this->ownBranch = [
        'personal' => $payment($fctPerson),
        'personal, payer moved to another branch' => $payment($lagosPerson()),
        'unit' => $payment($lagosPerson(), ['red_cross_unit_id' => $unit->id]),
        'organisation' => $payment($lagosPerson(), ['organisation_id' => $organisation->id]),
    ];
    $this->otherBranch = $payment($lagosPerson(), ['branch_id' => 858, 'division_id' => 3582]);

    $donation = fn (User $donor, array $attributes = []) => Donation::factory()->approved()->create(array_merge([
        'user_id' => $donor->id, 'branch_id' => 848, 'division_id' => 3069,
    ], $attributes));
    $this->ownBranchDonations = [
        'personal' => $donation($fctPerson),
        'organisation' => $donation($lagosPerson(), ['organisation_id' => $organisation->id]),
    ];
    $this->otherBranchDonation = $donation($lagosPerson(), ['branch_id' => 858, 'division_id' => 3582]);
});

test('branch staff can open every payment in their own branch, from the list and by URL', function (string $role) {
    $user = ($this->staff)($role);

    $list = $this->actingAs($user)->get(route('membership-payments.index'))->assertOk();

    foreach ($this->ownBranch as $kind => $payment) {
        $url = route('membership-payments.show', $payment);
        $list->assertSee($url, false);
        expect($this->actingAs($user)->get($url)->status())->toBe(200, "{$role}: {$kind} payment");
    }
})->with(BRANCH_STAFF_ROLES);

test('branch staff get 403 on another branch\'s payment', function (string $role) {
    $this->actingAs(($this->staff)($role))
        ->get(route('membership-payments.show', $this->otherBranch))
        ->assertForbidden();
})->with(BRANCH_STAFF_ROLES);

test('division staff open their division\'s personal and unit payments, never organisation payments', function () {
    $user = ($this->staff)('division_db_assistant_finance');

    foreach (['personal', 'personal, payer moved to another branch', 'unit'] as $kind) {
        expect($this->actingAs($user)->get(route('membership-payments.show', $this->ownBranch[$kind]))->status())
            ->toBe(200, $kind);
    }
    $this->actingAs($user)->get(route('membership-payments.show', $this->ownBranch['organisation']))->assertForbidden();
    $this->actingAs($user)->get(route('membership-payments.show', $this->otherBranch))->assertForbidden();
});

test('national staff open any payment', function () {
    $user = ($this->staff)('national_db_administrator', []);

    foreach ([...array_values($this->ownBranch), $this->otherBranch] as $payment) {
        $this->actingAs($user)->get(route('membership-payments.show', $payment))->assertOk();
    }
});

test('branch staff open own-branch donations from the list and by URL, not another branch\'s', function (string $role) {
    $user = ($this->staff)($role);

    $list = $this->actingAs($user)->get(route('donations.index'))->assertOk();
    foreach ($this->ownBranchDonations as $kind => $donation) {
        $url = route('donations.show', $donation);
        $list->assertSee($url, false);
        expect($this->actingAs($user)->get($url)->status())->toBe(200, "{$role}: {$kind} donation");
    }

    $this->actingAs($user)->get(route('donations.show', $this->otherBranchDonation))->assertForbidden();
})->with(BRANCH_STAFF_ROLES);

test('division staff open their division\'s personal donations, never organisation donations', function () {
    $user = ($this->staff)('division_db_assistant_finance');

    $this->actingAs($user)->get(route('donations.show', $this->ownBranchDonations['personal']))->assertOk();
    $this->actingAs($user)->get(route('donations.show', $this->ownBranchDonations['organisation']))->assertForbidden();
    $this->actingAs($user)->get(route('donations.show', $this->otherBranchDonation))->assertForbidden();
});

test('delete follows the same rule: own-branch payments pass the access check, another branch\'s get 403', function () {
    Role::findOrCreate('branch_db_administrator', 'web')->givePermissionTo(Permission::findOrCreate('remove_payments', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user = ($this->staff)('branch_db_administrator');

    // May still be refused for other reasons (e.g. a finished quarter), but never with 403.
    expect($this->actingAs($user)->delete(route('membership-payments.destroy', $this->ownBranch['organisation']))->status())
        ->not->toBe(403);
    $this->actingAs($user)->delete(route('membership-payments.destroy', $this->otherBranch))->assertForbidden();
});

test('the payment page links to the organisation and the unit when the viewer may open them', function () {
    $organisationPayment = $this->ownBranch['organisation'];
    $unitPayment = $this->ownBranch['unit'];
    $user = ($this->staff)('branch_db_administrator');

    $this->actingAs($user)->get(route('membership-payments.show', $organisationPayment))
        ->assertOk()
        ->assertSee('href="'.route('organisations.show', $organisationPayment->organisation_id).'"', false)
        ->assertSee('Acme');

    $this->actingAs($user)->get(route('membership-payments.show', $unitPayment))
        ->assertOk()
        ->assertSee('href="'.route('red-cross-units.show', $unitPayment->red_cross_unit_id).'"', false)
        ->assertSee('Alpha Unit');

    // Division staff can open their division's unit too.
    $this->actingAs(($this->staff)('division_db_assistant_finance'))->get(route('membership-payments.show', $unitPayment))
        ->assertOk()
        ->assertSee('href="'.route('red-cross-units.show', $unitPayment->red_cross_unit_id).'"', false);
});

test('without the permission, or with the organisation out of scope, the name is plain text', function () {
    $organisationPayment = $this->ownBranch['organisation'];
    $unitPayment = $this->ownBranch['unit'];

    // No view_organisation / view_red_cross_unit.
    Role::findOrCreate('branch_db_assistant', 'web')->revokePermissionTo(['view_organisation', 'view_red_cross_unit']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $assistant = ($this->staff)('branch_db_assistant');

    $this->actingAs($assistant)->get(route('membership-payments.show', $organisationPayment))
        ->assertOk()
        ->assertSee('Acme')
        ->assertDontSee(route('organisations.show', $organisationPayment->organisation_id), false);
    $this->actingAs($assistant)->get(route('membership-payments.show', $unitPayment))
        ->assertOk()
        ->assertSee('Alpha Unit')
        ->assertDontSee(route('red-cross-units.show', $unitPayment->red_cross_unit_id), false);

    // The organisation has since moved to Lagos: the FCT payment stays visible, the organisation page is not.
    Organisation::whereKey($organisationPayment->organisation_id)->update(['branch_id' => 858, 'division_id' => 3582]);
    $this->actingAs(($this->staff)('branch_db_administrator'))->get(route('membership-payments.show', $organisationPayment))
        ->assertOk()
        ->assertSee('Acme')
        ->assertDontSee(route('organisations.show', $organisationPayment->organisation_id), false);
});
