<?php

/**
 * Income Distribution Tab 2, step 5: an approved manual payment that counts
 * in a FINISHED quarter can no longer be deleted (its quarter's figures
 * must not change); one in the current quarter still can. The quarter is
 * decided by accounting_date, or — before shares are stored — the payment
 * date for a legacy import and the approval date otherwise.
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

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    // Current quarter: 2026 Q4.
    $this->travelTo(\Carbon\Carbon::parse('2026-10-11 10:00:00', 'Africa/Lagos'));
});

function manualPaymentCountingOn(array $attributes): MembershipPayment
{
    return MembershipPayment::factory()->approved()->create(array_merge([
        'payment_channel' => 'manual',
        'payment_date' => '2026-01-10',
        'expiry_date' => '2027-01-10',
        'decided_by_user_id' => User::factory()->create()->id,
        'decided_at' => '2026-10-02 09:00:00',
    ], $attributes));
}

function deletePayment(MembershipPayment $payment)
{
    return test()->actingAs(test()->admin)->delete(route('membership-payments.destroy', $payment));
}

function isDeleted(MembershipPayment $payment): bool
{
    return (bool) DB::table('membership_payments')->where('id', $payment->id)->value('is_deleted');
}

test('a payment counting in a finished quarter cannot be deleted', function () {
    $payment = manualPaymentCountingOn(['accounting_date' => '2026-09-30']);

    deletePayment($payment)
        ->assertRedirect(route('membership-payments.show', $payment))
        ->assertSessionHas('error', 'This payment counts in 2026 Q3, a finished quarter, so it can no longer be deleted.');

    expect(isDeleted($payment))->toBeFalse()
        ->and(DB::table('logs')->where('action', 'membership_payment_deleted')->exists())->toBeFalse();
});

test('a payment counting in the current quarter can still be deleted', function () {
    $payment = manualPaymentCountingOn(['accounting_date' => '2026-10-01']);

    deletePayment($payment)->assertSessionHas('deleted');

    expect(isDeleted($payment))->toBeTrue();
});

test('without shares yet, a legacy payment counts by its payment date, not its shifted decided_at', function () {
    $legacy = manualPaymentCountingOn(['decided_by_user_id' => null, 'decided_at' => '2016-07-13 23:00:00', 'payment_date' => '2016-07-14', 'expiry_date' => '2017-07-14']);

    deletePayment($legacy)->assertSessionHas('error', 'This payment counts in 2016 Q3, a finished quarter, so it can no longer be deleted.');
    expect(isDeleted($legacy))->toBeFalse();

    // Paid on the first day of the current quarter; the import's one-hour
    // shift puts decided_at in the previous quarter. The payment date wins.
    $boundary = manualPaymentCountingOn(['decided_by_user_id' => null, 'decided_at' => '2026-09-30 23:00:00', 'payment_date' => '2026-10-01', 'expiry_date' => '2027-10-01']);

    deletePayment($boundary)->assertSessionHas('deleted');
    expect(isDeleted($boundary))->toBeTrue();
});

test('without shares yet, a payment approved in the app counts by its approval date', function () {
    // Paid in Q3 but approved in Q4: counts in Q4, so still deletable.
    $lateEntry = manualPaymentCountingOn(['payment_date' => '2026-09-20', 'decided_at' => '2026-10-02 09:00:00']);
    deletePayment($lateEntry)->assertSessionHas('deleted');

    $approvedInQ3 = manualPaymentCountingOn(['payment_date' => '2026-09-20', 'decided_at' => '2026-09-21 09:00:00']);
    deletePayment($approvedInQ3)->assertSessionHas('error');
    expect(isDeleted($approvedInQ3))->toBeFalse();
});

test('the quarter turns at midnight Lagos time', function () {
    $payment = manualPaymentCountingOn(['accounting_date' => '2026-12-31']);

    $this->travelTo(\Carbon\Carbon::parse('2026-12-31 23:59:00', 'Africa/Lagos'));
    expect($payment->fresh()->isInFinishedIncomeQuarter())->toBeFalse();

    $this->travelTo(\Carbon\Carbon::parse('2027-01-01 00:01:00', 'Africa/Lagos'));
    expect($payment->fresh()->isInFinishedIncomeQuarter())->toBeTrue();
});

test('the payment page hides Delete for a finished quarter and says why', function () {
    $finished = manualPaymentCountingOn(['accounting_date' => '2026-09-30']);
    $current = manualPaymentCountingOn(['accounting_date' => '2026-10-01']);

    $this->actingAs($this->admin)->get(route('membership-payments.show', $finished))
        ->assertOk()
        ->assertDontSee(route('membership-payments.destroy', $finished), false)
        ->assertSee('Counts in 2026 Q3, a finished quarter — this payment can no longer be deleted.');

    $this->actingAs($this->admin)->get(route('membership-payments.show', $current))
        ->assertOk()
        ->assertSee(route('membership-payments.destroy', $current), false);
});

test('the refusal is shown on the payment page', function () {
    $payment = manualPaymentCountingOn(['accounting_date' => '2026-09-30']);

    $this->actingAs($this->admin)
        ->followingRedirects()
        ->delete(route('membership-payments.destroy', $payment))
        ->assertOk()
        ->assertSee('This payment counts in 2026 Q3, a finished quarter, so it can no longer be deleted.');
});
