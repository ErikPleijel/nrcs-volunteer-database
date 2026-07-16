<?php

namespace Database\Factories;

use App\Models\TrainingType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrainingType>
 *
 * NOTE: TrainingType imports HasFactory but does not use the trait in its
 * class body, so TrainingType::factory() does NOT resolve. Instantiate this
 * factory directly: TrainingTypeFactory::new()->create().
 */
class TrainingTypeFactory extends Factory
{
    protected $model = TrainingType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Column is varchar(100).
            'name' => 'Training '.fake()->unique()->lexify('????????'),
            'is_active' => true,
            'validity_years_limit' => null,
            'certificate_hq_only' => true,
            'is_first_aid' => false,
        ];
    }

    /**
     * A first-aid type with an expiry limit — the combination exercised by
     * Training::afterApproved()'s first-aid freshness recalculation.
     */
    public function firstAid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_first_aid' => true,
            'validity_years_limit' => 3,
        ]);
    }
}
