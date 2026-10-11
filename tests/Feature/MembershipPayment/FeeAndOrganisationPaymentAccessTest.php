<?php

/**
 * Step 1b access fixes:
 *   - changing membership fees (create, edit, activate/deactivate, delete)
 *     needs change_settings; the admin panel alone may only view them;
 *   - organisation payments need add_payments, and the organisation must
 *     be in the clerk's scope (national: any; branch: own branch; division
 *     clerks: none), on the form and when storing.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    foreach (['manage-admin-panel', 'change_settings', 'add_payments', 'view_payments', 'view_user'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    // As in PermissionsTableSeeder, reduced to what these routes check.
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['manage-admin-panel', 'change_settings', 'add_payments', 'view_payments', 'view_user']);
    Role::findOrCreate('branch_db_administrator', 'web')->syncPermissions(['manage-admin-panel', 'add_payments', 'view_payments', 'view_user']);
    Role::findOrCreate('branch_db_assistant', 'web')->syncPermissions(['manage-admin-panel', 'add_payments', 'view_payments', 'view_user']);
    Role::findOrCreate('division_db_assistant_finance', 'web')->syncPermissions(['manage-admin-panel', 'add_payments', 'view_payments', 'view_user']);
    Role::findOrCreate('division_db_assistant_operations', 'web')->syncPermissions(['manage-admin-panel', 'view_user']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->kano = Branch::create(['name' => 'Kano', 'code' => 'KAN']);
    $this->abia = Branch::create(['name' => 'Abia', 'code' => 'ABI']);
    $this->kanoDivision = Division::create(['name' => 'Kano Municipal', 'branch_id' => $this->kano->id]);

    $this->national = User::factory()->create();
    $this->national->assignRole('national_db_administrator');
    $this->branchAdmin = User::factory()->create(['branch_id' => $this->kano->id]);
    $this->branchAdmin->assignRole('branch_db_administrator');

    $this->fee = MembershipFee::factory()->create(['name' => 'Gold', 'amount' => 10000, 'is_active' => true]);
});

function asConfirmed(User $user)
{
    return test()->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
}

/*
|--------------------------------------------------------------------------
| Membership fees
|--------------------------------------------------------------------------
*/

function feeFields(array $overrides = []): array
{
    return array_merge([
        'name' => 'Platinum',
        'amount' => 25000,
        'id_card_fee' => 2000,
        'validity_years' => 1,
        'is_active' => 1,
    ], $overrides);
}

test('a national DB administrator can open the fee forms', function () {
    asConfirmed($this->national)->get(route('membership-fees.create'))->assertOk()->assertSee('name="amount"', false);
    asConfirmed($this->national)->get(route('membership-fees.edit', $this->fee))->assertOk();
    asConfirmed($this->national)->get(route('membership-fees.index'))
        ->assertOk()
        ->assertSee(route('membership-fees.create'), false)
        ->assertSee(route('membership-fees.edit', $this->fee), false);
});

test('a national DB administrator can still add and delete fees', function () {
    asConfirmed($this->national)->post(route('membership-fees.store'), feeFields())->assertSessionHasNoErrors();
    expect(MembershipFee::where('name', 'Platinum')->exists())->toBeTrue();

    asConfirmed($this->national)->delete(route('membership-fees.destroy', MembershipFee::where('name', 'Platinum')->first()))
        ->assertSessionHas('success');
    expect(MembershipFee::where('name', 'Platinum')->exists())->toBeFalse();
});

test('a branch DB administrator can view fees but not open the forms or see their buttons', function () {
    asConfirmed($this->branchAdmin)->get(route('membership-fees.index'))
        ->assertOk()
        ->assertSee('Gold')
        ->assertDontSee(route('membership-fees.create'), false)
        ->assertDontSee(route('membership-fees.edit', $this->fee), false);
    asConfirmed($this->branchAdmin)->get(route('membership-fees.show', $this->fee))->assertOk();

    asConfirmed($this->branchAdmin)->get(route('membership-fees.create'))->assertForbidden();
    asConfirmed($this->branchAdmin)->get(route('membership-fees.edit', $this->fee))->assertForbidden();
});

test('a branch DB administrator cannot add, change, deactivate or delete a fee', function () {
    // A migration seeds the RCU annual fee, so count from what is there.
    $feesBefore = MembershipFee::count();

    asConfirmed($this->branchAdmin)->post(route('membership-fees.store'), feeFields())->assertForbidden();
    asConfirmed($this->branchAdmin)->put(route('membership-fees.update', $this->fee), feeFields(['name' => 'Gold', 'amount' => 1]))->assertForbidden();
    asConfirmed($this->branchAdmin)->put(route('membership-fees.update', $this->fee), feeFields(['name' => 'Gold', 'amount' => 10000, 'is_active' => 0]))->assertForbidden();
    asConfirmed($this->branchAdmin)->delete(route('membership-fees.destroy', $this->fee))->assertForbidden();

    expect(MembershipFee::count())->toBe($feesBefore);
    $fee = $this->fee->fresh();
    expect((float) $fee->amount)->toBe(10000.0)
        ->and((bool) $fee->is_active)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Organisation payments
|--------------------------------------------------------------------------
*/

function clerkWith(string $role, array $attributes): User
{
    $clerk = User::factory()->create($attributes);
    $clerk->assignRole($role);

    return $clerk;
}

function storeOrganisationPayment(User $clerk, Organisation $organisation)
{
    $contact = User::factory()->create(['branch_id' => $organisation->branch_id]);
    $fee = MembershipFee::factory()->create(['for_organizations' => true, 'amount' => 20000, 'is_active' => true]);

    return test()->actingAs($clerk)->post(route('membership-payments.store'), [
        'user_id' => $contact->id,
        'organisation_id' => $organisation->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
    ]);
}

test('a branch clerk can record a payment for an organisation in their own branch', function () {
    $clerk = clerkWith('branch_db_assistant', ['branch_id' => $this->kano->id]);
    $own = Organisation::create(['name' => 'Kano Org', 'branch_id' => $this->kano->id]);

    $this->actingAs($clerk)->get(route('organisations.payments.create', $own))->assertOk();
    storeOrganisationPayment($clerk, $own)->assertSessionHasNoErrors();

    expect(MembershipPayment::withAnyApprovalStatus()->sole()->organisation_id)->toBe($own->id);
});

test('a branch clerk cannot open or store a payment for another branch\'s organisation', function () {
    $clerk = clerkWith('branch_db_assistant', ['branch_id' => $this->kano->id]);
    $other = Organisation::create(['name' => 'Abia Org', 'branch_id' => $this->abia->id]);

    $this->actingAs($clerk)->get(route('organisations.payments.create', $other))->assertForbidden();
    storeOrganisationPayment($clerk, $other)->assertForbidden();

    expect(MembershipPayment::withAnyApprovalStatus()->count())->toBe(0);
});

test('a division clerk cannot record organisation payments, not even in their own branch', function () {
    $clerk = clerkWith('division_db_assistant_finance', ['branch_id' => $this->kano->id, 'division_id' => $this->kanoDivision->id]);
    $own = Organisation::create(['name' => 'Kano Org', 'branch_id' => $this->kano->id]);

    $this->actingAs($clerk)->get(route('organisations.payments.create', $own))->assertForbidden();
    storeOrganisationPayment($clerk, $own)->assertForbidden();
    expect(MembershipPayment::withAnyApprovalStatus()->count())->toBe(0);

    $this->actingAs($clerk)->get(route('organisations.show', $own))
        ->assertOk()
        ->assertDontSee(route('organisations.payments.create', $own), false);
});

test('national staff may record payments for any organisation', function () {
    $other = Organisation::create(['name' => 'Abia Org', 'branch_id' => $this->abia->id]);

    $this->actingAs($this->national)->get(route('organisations.payments.create', $other))->assertOk();
    storeOrganisationPayment($this->national, $other)->assertSessionHasNoErrors();
});

test('the organisation payment form needs add_payments', function () {
    $noPayments = clerkWith('division_db_assistant_operations', ['branch_id' => $this->kano->id, 'division_id' => $this->kanoDivision->id]);
    $own = Organisation::create(['name' => 'Kano Org', 'branch_id' => $this->kano->id]);

    $this->actingAs($noPayments)->get(route('organisations.payments.create', $own))->assertForbidden();
});

test('the organisation page shows Add Payment only to clerks who may use it', function () {
    $clerk = clerkWith('branch_db_assistant', ['branch_id' => $this->kano->id]);
    $own = Organisation::create(['name' => 'Kano Org', 'branch_id' => $this->kano->id]);
    $other = Organisation::create(['name' => 'Abia Org', 'branch_id' => $this->abia->id]);

    $this->actingAs($clerk)->get(route('organisations.show', $own))
        ->assertOk()
        ->assertSee(route('organisations.payments.create', $own), false);

    $this->actingAs($clerk)->get(route('organisations.show', $other))
        ->assertOk()
        ->assertDontSee(route('organisations.payments.create', $other), false);
});
