<?php

namespace Database\Factories;

use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MembershipPayment>
 *
 * Defaults produce a PENDING payment (approval_status left to the DB default).
 * NOT NULL columns without defaults: user_id, payment_date, membership_fee_id,
 * submitted_by_user_id (this module's submitter column — required, unlike the
 * other three modules). expiry_date is derived from the fee's validity_years,
 * mirroring the app's own store logic.
 */
class MembershipPaymentFactory extends Factory
{
    protected $model = MembershipPayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'submitted_by_user_id' => User::factory(),
            'membership_fee_id' => MembershipFeeFactory::new(),
            'payment_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            // Attribute order matters: membership_fee_id and payment_date are
            // resolved before this closure runs.
            'expiry_date' => function (array $attributes) {
                $fee = MembershipFee::find($attributes['membership_fee_id']);

                return Carbon::parse($attributes['payment_date'])
                    ->addYears($fee?->validity_years ?? 1)
                    ->toDateString();
            },
            'is_deleted' => false,
            'submitted_at' => now(),
        ];
    }

    /**
     * create() leaves DB defaults (approval_status = 'pending') unset on the
     * in-memory model — refresh so tests can read them immediately.
     */
    public function configure(): static
    {
        return $this->afterCreating(fn (MembershipPayment $payment) => $payment->refresh());
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => MembershipPayment::APPROVED,
            'decided_by_user_id' => User::factory(),
            'decided_at' => now(),
        ]);
    }
}
