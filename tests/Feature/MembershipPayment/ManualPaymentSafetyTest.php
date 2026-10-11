<?php

/**
 * Safety fixes before manual payments carry income shares:
 *   - branch and division are decided on the server (payer, organisation
 *     or unit), never taken from the form;
 *   - a personal payment can only be recorded for a payer inside the
 *     clerk's scope;
 *   - the reference fits its varchar(45) column;
 *   - a fee that payments (or an online checkout in progress) use cannot
 *     be deleted.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $permissions = ['manage-admin-panel', 'view_user', 'add_payments', 'view_payments'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    // As in PermissionsTableSeeder: the entry routes also need manage-admin-panel.
    Role::findOrCreate('branch_db_assistant', 'web')->syncPermissions(['manage-admin-panel', 'add_payments', 'view_payments', 'view_user']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $this->kano = Branch::create(['name' => 'Kano', 'code' => 'KAN']);
    $this->abia = Branch::create(['name' => 'Abia', 'code' => 'ABI']);
    $this->kanoDivision = Division::create(['name' => 'Kano Municipal', 'branch_id' => $this->kano->id]);
    $this->abiaDivision = Division::create(['name' => 'Aba North', 'branch_id' => $this->abia->id]);

    $this->fee = MembershipFee::factory()->create(['amount' => 5000, 'id_card_fee' => 2000, 'is_active' => true]);
});

function storeManualPayment(User $clerk, array $fields)
{
    return test()->actingAs($clerk)->post(route('membership-payments.store'), array_merge([
        'membership_fee_id' => test()->fee->id,
        'payment_date' => now()->toDateString(),
    ], $fields));
}

test('a personal payment takes the payer\'s branch and division, whatever the form says', function () {
    $payer = User::factory()->create(['branch_id' => $this->kano->id, 'division_id' => $this->kanoDivision->id]);

    storeManualPayment($this->admin, [
        'user_id' => $payer->id,
        'branch_id' => $this->abia->id,
        'division_id' => $this->abiaDivision->id,
    ])->assertSessionHasNoErrors();

    $payment = MembershipPayment::withAnyApprovalStatus()->sole();
    expect($payment->branch_id)->toBe($this->kano->id)
        ->and($payment->division_id)->toBe($this->kanoDivision->id);
});

test('a payer with a division but no branch gets the division\'s branch', function () {
    $payer = User::factory()->create(['branch_id' => null, 'division_id' => $this->kanoDivision->id]);

    storeManualPayment($this->admin, ['user_id' => $payer->id])->assertSessionHasNoErrors();

    expect(MembershipPayment::withAnyApprovalStatus()->sole()->branch_id)->toBe($this->kano->id);
});

test('an organisation payment takes the organisation\'s branch and no division', function () {
    $contact = User::factory()->create(['branch_id' => $this->abia->id, 'division_id' => $this->abiaDivision->id]);
    $organisation = Organisation::create(['name' => 'Sponsor Org', 'branch_id' => $this->kano->id]);
    $orgFee = MembershipFee::factory()->create(['for_organizations' => true, 'amount' => 20000, 'is_active' => true]);

    storeManualPayment($this->admin, [
        'user_id' => $contact->id,
        'organisation_id' => $organisation->id,
        'membership_fee_id' => $orgFee->id,
        'branch_id' => $this->abia->id,
        'division_id' => $this->abiaDivision->id,
    ])->assertSessionHasNoErrors();

    $payment = MembershipPayment::withAnyApprovalStatus()->sole();
    expect($payment->branch_id)->toBe($this->kano->id)
        ->and($payment->division_id)->toBeNull();
});

test('a branch clerk cannot record a personal payment for a member of another branch', function () {
    $clerk = User::factory()->create(['branch_id' => $this->kano->id]);
    $clerk->assignRole('branch_db_assistant');
    $outsider = User::factory()->create(['branch_id' => $this->abia->id, 'division_id' => $this->abiaDivision->id]);
    $insider = User::factory()->create(['branch_id' => $this->kano->id, 'division_id' => $this->kanoDivision->id]);

    storeManualPayment($clerk, ['user_id' => $outsider->id])->assertForbidden();
    expect(MembershipPayment::withAnyApprovalStatus()->count())->toBe(0);

    storeManualPayment($clerk, ['user_id' => $insider->id])->assertSessionHasNoErrors();
    expect(MembershipPayment::withAnyApprovalStatus()->sole()->user_id)->toBe($insider->id);
});

test('the reference may be at most 45 characters, the size of its column', function () {
    $payer = User::factory()->create(['branch_id' => $this->kano->id, 'division_id' => $this->kanoDivision->id]);

    storeManualPayment($this->admin, ['user_id' => $payer->id, 'reference' => str_repeat('x', 46)])
        ->assertSessionHasErrors('reference');
    expect(MembershipPayment::withAnyApprovalStatus()->count())->toBe(0);

    storeManualPayment($this->admin, ['user_id' => $payer->id, 'reference' => str_repeat('x', 45)])
        ->assertSessionHasNoErrors();
    expect(MembershipPayment::withAnyApprovalStatus()->sole()->reference)->toBe(str_repeat('x', 45));
});

test('the entry forms no longer send a branch or division', function () {
    $this->actingAs($this->admin)
        ->get(route('membership-payments.create'))
        ->assertOk()
        ->assertDontSee('name="branch_id"', false)
        ->assertDontSee('name="division_id"', false)
        ->assertSee('maxlength="45"', false);
});

/*
| Fee deletion
*/

function deleteFee(MembershipFee $fee)
{
    return test()->actingAs(test()->admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('membership-fees.destroy', $fee));
}

test('a fee used by a payment cannot be deleted, even a deleted or pending one', function (array $paymentState) {
    MembershipPayment::factory()->create(array_merge(['membership_fee_id' => $this->fee->id], $paymentState));

    deleteFee($this->fee)
        ->assertRedirect(route('membership-fees.index'))
        ->assertSessionHas('error', 'The fee "'.$this->fee->name.'" cannot be deleted: 1 payment use it. Deactivate it instead.');

    expect(MembershipFee::whereKey($this->fee->id)->exists())->toBeTrue()
        ->and(DB::table('logs')->where('action', 'membership_fee_deleted')->exists())->toBeFalse();
})->with([
    'approved' => [['approval_status' => 'approved']],
    'pending' => [[]],
    'soft-deleted' => [['approval_status' => 'approved', 'is_deleted' => true]],
]);

test('a fee with an online checkout in progress cannot be deleted', function () {
    PaymentTransaction::create([
        'user_id' => User::factory()->create()->id,
        'payable_type' => 'membership_payment',
        'reference' => 'PSK-in-progress',
        'amount' => 500_000,
        'status' => 'initiated',
        'meta' => ['payment_type' => 'membership', 'membership_fee_id' => $this->fee->id],
    ]);

    deleteFee($this->fee)->assertSessionHas('error', 'The fee "'.$this->fee->name.'" cannot be deleted: an online payment using it is still in progress. Deactivate it instead.');

    expect(MembershipFee::whereKey($this->fee->id)->exists())->toBeTrue();
});

test('an unused fee can still be deleted, and the refusal is shown on the fee list', function () {
    $unused = MembershipFee::factory()->create();

    deleteFee($unused)->assertSessionHas('success');
    expect(MembershipFee::whereKey($unused->id)->exists())->toBeFalse();

    MembershipPayment::factory()->create(['membership_fee_id' => $this->fee->id]);
    $this->actingAs($this->admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->followingRedirects()
        ->delete(route('membership-fees.destroy', $this->fee))
        ->assertSee('cannot be deleted');
});
