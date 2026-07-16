<?php

namespace Database\Factories;

use App\Models\Donation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Donation>
 *
 * Defaults produce a PENDING cash donation (approval_status is left to the DB
 * default). Donations record their submitter in entered_by_user_id, not
 * submitted_by_user_id. Donation uses SoftDeletes on removed_date — leave it
 * null or the record is born trashed.
 */
class DonationFactory extends Factory
{
    protected $model = Donation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'entered_by_user_id' => User::factory(),
            'in_kind_donation' => false,
            // Business rule: cash donations always carry donation_item 'Naira'.
            'donation_item' => 'Naira',
            // Integer column — no decimals.
            'amount' => fake()->numberBetween(100, 50000),
            'date_donation' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            // Nullable with NO default; scopeActive() requires false, not null.
            'is_deleted' => false,
            'anonymous' => false,
        ];
    }

    /**
     * create() leaves DB defaults (approval_status = 'pending') unset on the
     * in-memory model — refresh so tests can read them immediately.
     */
    public function configure(): static
    {
        return $this->afterCreating(fn (Donation $donation) => $donation->refresh());
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => Donation::APPROVED,
            'decided_by_user_id' => User::factory(),
            'decided_at' => now(),
        ]);
    }
}
