<?php

/**
 * The one-time legacy import (migrate:membership-payments) can wipe the
 * table with --clear. Once Paystack payments exist it must refuse, because
 * they are money already received and shared out.
 */

use App\Models\MembershipPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('--clear is refused while Paystack payments exist, and nothing is deleted', function () {
    MembershipPayment::factory()->approved()->create(['payment_channel' => 'paystack', 'gateway_reference' => 'PSK-keep-me']);
    MembershipPayment::factory()->approved()->create(['payment_channel' => 'manual']);

    $this->artisan('migrate:membership-payments', ['--clear' => true])
        ->expectsOutput('Refusing --clear: membership_payments contains Paystack payments, which must never be deleted.')
        ->assertFailed();

    expect(MembershipPayment::withAnyApprovalStatus()->count())->toBe(2);
});
