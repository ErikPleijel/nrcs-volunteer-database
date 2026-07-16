<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\Training;
use App\Models\TrainingType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Training>
 *
 * Defaults produce a PENDING training (approval_status left to the DB
 * default). division_id is the table's only NOT NULL business column with no
 * default — reuses an existing Division row when the testing DB has one,
 * falling back to a plain placeholder row (not a factory — divisions are
 * fixed reference data, not fabricated LGA data). valid_years is HQ-governed
 * (copied from the training type's validity_years_limit, never free-entered),
 * so it stays null until a type is attached via ofType()/firstAid().
 */
class TrainingFactory extends Factory
{
    protected $model = Training::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'submitted_by_user_id' => User::factory(),
            'training_type_id' => null,
            'training_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'duration' => fake()->numberBetween(1, 5),
            'valid_years' => null,
            'division_id' => Division::first()?->id ?? Division::query()->create(['name' => 'Test Division'])->id,
            'branch_id' => null,
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
        return $this->afterCreating(fn (Training $training) => $training->refresh());
    }

    /**
     * Attach an existing training type, deriving valid_years from its
     * validity_years_limit exactly as TrainingController@store does.
     */
    public function ofType(TrainingType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'training_type_id' => $type->id,
            'valid_years' => $type->validity_years_limit,
        ]);
    }

    /**
     * A first-aid training — needed by any test exercising
     * Training::afterApproved()'s recalculateLastFirstAidAt().
     */
    public function firstAid(): static
    {
        return $this->state(function (array $attributes) {
            $type = TrainingTypeFactory::new()->firstAid()->create();

            return [
                'training_type_id' => $type->id,
                'valid_years' => $type->validity_years_limit,
            ];
        });
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => Training::APPROVED,
            'decided_by_user_id' => User::factory(),
            'decided_at' => now(),
        ]);
    }
}
