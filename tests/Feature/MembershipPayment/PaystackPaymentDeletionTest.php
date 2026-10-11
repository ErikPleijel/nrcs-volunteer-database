<?php

/**
 * A membership payment made online through Paystack can never be deleted:
 * its income is already shared out in the Income Distribution report, and
 * deleting it would change a closed quarter. Manual payments are unchanged.
 */

use App\Models\MembershipPayment;
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

    $permissions = ['manage-admin-panel', 'add_payments', 'remove_payments', 'view_payments'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actor = User::factory()->create();
    $this->actor->assignRole('national_db_administrator');
});

function paymentWithChannel(string $channel): MembershipPayment
{
    return MembershipPayment::factory()->approved()->create([
        'payment_date' => now()->subYear()->toDateString(),
        'expiry_date' => now()->addYear()->toDateString(),
        'payment_channel' => $channel,
        'gateway_reference' => $channel === 'paystack' ? 'PSK-'.fake()->uuid() : null,
    ]);
}

test('a Paystack payment cannot be deleted', function () {
    $payment = paymentWithChannel('paystack');

    $this->actingAs($this->actor)
        ->delete(route('membership-payments.destroy', $payment))
        ->assertRedirect(route('membership-payments.show', $payment))
        ->assertSessionHas('error', 'This payment was made online through Paystack and cannot be deleted.');

    $row = DB::table('membership_payments')->where('id', $payment->id)->first();
    expect((bool) $row->is_deleted)->toBeFalse()
        ->and($row->removed_by_user_id)->toBeNull()
        ->and($row->removed_date)->toBeNull()
        ->and(DB::table('logs')->where('action', 'membership_payment_deleted')->exists())->toBeFalse();
});

test('the refusal is shown on the payment page', function () {
    $payment = paymentWithChannel('paystack');

    $this->actingAs($this->actor)
        ->followingRedirects()
        ->delete(route('membership-payments.destroy', $payment))
        ->assertOk()
        ->assertSee('This payment was made online through Paystack and cannot be deleted.');
});

test('the payment page hides the delete button for a Paystack payment', function () {
    $payment = paymentWithChannel('paystack');

    $this->actingAs($this->actor)
        ->get(route('membership-payments.show', $payment))
        ->assertOk()
        ->assertDontSee(route('membership-payments.destroy', $payment), false)
        ->assertDontSee('Delete payment')
        ->assertSee('Paid online through Paystack — this payment cannot be deleted.');
});

test('a manual payment still shows the delete button and can be deleted', function () {
    $payment = paymentWithChannel('manual');

    $this->actingAs($this->actor)
        ->get(route('membership-payments.show', $payment))
        ->assertOk()
        ->assertSee(route('membership-payments.destroy', $payment), false)
        ->assertSee('Delete payment');

    $this->actingAs($this->actor)
        ->delete(route('membership-payments.destroy', $payment))
        ->assertRedirect(route('membership-payments.show', $payment))
        ->assertSessionHas('deleted');

    expect((bool) DB::table('membership_payments')->where('id', $payment->id)->value('is_deleted'))->toBeTrue();
});
