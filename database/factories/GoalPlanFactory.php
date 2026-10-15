<?php

namespace Database\Factories;

use App\Modules\Family\Models\Family;
use App\Modules\Goals\Models\GoalPlan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A weekly cooperation plan starting today (in the family's timezone, like the API does), without scopes or members (add them in the test).
 *
 * @extends Factory<GoalPlan>
 */
class GoalPlanFactory extends Factory
{
    protected $model = GoalPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'name' => ucfirst(fake()->words(2, true)),
            'mode' => GoalPlan::MODE_COOPERATION,
            'period_type' => GoalPlan::PERIOD_WEEKLY,
            'target_points' => 50,
            'reward_text' => 'Pizza night!',
            // Late in the evening west of UTC, the UTC date is already tomorrow: a plan starting
            // "tomorrow" in the family's timezone has no current period yet.
            'starts_on' => fn (array $attributes) => CarbonImmutable::now(
                Family::query()->whereKey($attributes['family_id'])->value('timezone') ?? 'UTC'
            )->toDateString(),
            'ends_on' => null,
            'is_active' => true,
        ];
    }

    public function competition(): static
    {
        return $this->state(fn () => ['mode' => GoalPlan::MODE_COMPETITION]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
