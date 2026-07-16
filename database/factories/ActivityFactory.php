<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Division;
use App\Models\RedCrossUnit;
use App\Models\TaskForce;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Activity>
 *
 * Defaults produce a PENDING volunteering activity (approval_status left to
 * the DB default). division_id is the table's only NOT NULL business column
 * with no default — reuses an existing Division row when the testing DB has
 * one, falling back to a plain placeholder row (not a factory — divisions are
 * fixed reference data, not fabricated LGA data). The polymorphic assignable
 * pair stays null unless set together via forRedCrossUnit()/forTaskForce().
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'submitted_by_user_id' => User::factory(),
            'activity_type_id' => null,
            'date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'hours' => fake()->numberBetween(1, 8),
            'division_id' => Division::first()?->id ?? Division::query()->create(['name' => 'Test Division'])->id,
            'branch_id' => null,
            'is_deleted' => false,
            'submitted_at' => now(),
            'assignable_id' => null,
            'assignable_type' => null,
        ];
    }

    /**
     * create() leaves DB defaults (approval_status = 'pending') unset on the
     * in-memory model — refresh so tests can read them immediately.
     */
    public function configure(): static
    {
        return $this->afterCreating(fn (Activity $activity) => $activity->refresh());
    }

    /**
     * Assign to a Red Cross unit — sets the polymorphic pair together via the
     * model's setAssignable() helper, never one column without the other.
     */
    public function forRedCrossUnit(RedCrossUnit $unit): static
    {
        return $this->afterMaking(fn (Activity $activity) => $activity->setAssignable($unit));
    }

    /**
     * Assign to a task force — same paired-columns guarantee as above.
     */
    public function forTaskForce(TaskForce $taskForce): static
    {
        return $this->afterMaking(fn (Activity $activity) => $activity->setAssignable($taskForce));
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => Activity::APPROVED,
            'decided_by_user_id' => User::factory(),
            'decided_at' => now(),
        ]);
    }
}
