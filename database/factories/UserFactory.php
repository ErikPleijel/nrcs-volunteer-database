<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 *
 * Defaults produce an ACTIVE, policy-accepted user so role-holding test users
 * are not redirected by the RequiresPolicyAcceptance middleware. The model's
 * creating hook generates id_check_token; the 'hashed' cast hashes the
 * password — neither is set here. The encrypted attributes
 * (national_id_number, personal_info) are only set via withNationalId().
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
            'remember_token' => Str::random(10),
            // Schema default is 'pending_engagement'; most tests need an active user.
            'lifecycle_status' => 'active',
            'policy_accepted_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Explicitly mark the data-handling policy as accepted (already the
     * default; use policyNotAccepted() to exercise the middleware redirect).
     */
    public function policyAccepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'policy_accepted_at' => now(),
        ]);
    }

    /**
     * A role-holding user in this state is redirected to the policy page by
     * RequiresPolicyAcceptance on every request.
     */
    public function policyNotAccepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'policy_accepted_at' => null,
        ]);
    }

    /**
     * Populate the encrypted attributes. Kept out of definition() because they
     * are not needed by most tests and must always flow through Eloquent.
     */
    public function withNationalId(): static
    {
        return $this->state(fn (array $attributes) => [
            'national_id_number' => fake()->numerify('###########'),
            'personal_info' => fake()->sentence(),
        ]);
    }

    /**
     * Scope the user to an existing branch (branch_id carries a real FK).
     */
    public function inBranch(Branch $branch): static
    {
        return $this->state(fn (array $attributes) => [
            'branch_id' => $branch->id,
        ]);
    }

    /**
     * Scope the user to an existing division (division_id carries a real FK).
     * branch_id is set from the division to match the model's saving hook,
     * which syncs branch from division whenever both are present.
     */
    public function inDivision(Division $division): static
    {
        return $this->state(fn (array $attributes) => [
            'division_id' => $division->id,
            'branch_id' => $division->branch_id,
        ]);
    }
}
