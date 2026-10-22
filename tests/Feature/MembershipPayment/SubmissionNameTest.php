<?php

/**
 * A staff-entered membership payment records who entered it in submission_name
 * (the staff member's full name), like the Paystack flow does for the payer.
 * Before 2026-10-22 it read a non-existent `name` attribute and saved null.
 */

use App\Models\MembershipPayment;
use App\Models\User;
use Database\Factories\MembershipFeeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a staff-entered membership payment stores the staff member\'s full name', function () {
    $this->withoutVite();
    foreach (['manage-admin-panel', 'add_payments', 'view_payments'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions([
        'manage-admin-panel', 'add_payments', 'view_payments',
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $actor = User::factory()->create(['first_name' => 'Amina', 'last_name' => 'Bello']);
    $actor->assignRole('national_db_administrator');
    $member = User::factory()->create();
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);

    $this->actingAs($actor)->post(route('membership-payments.store'), [
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $payment = MembershipPayment::withAnyApprovalStatus()->sole();
    expect($payment->submission_name)->toBe('Amina Bello')
        ->and($payment->submitted_by_user_id)->toBe($actor->id);
});
