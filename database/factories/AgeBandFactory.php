<?php

namespace Database\Factories;

use App\Modules\Policy\Models\AgeBand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgeBand>
 */
class AgeBandFactory extends Factory
{
    protected $model = AgeBand::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('band_????'),
            'name' => 'Band',
            'min_age' => 18,
            'max_age' => null,
            'country_code' => null,
            'policy' => [
                'ui_theme' => 'modern',
                'is_child' => false,
                'ads_allowed' => true,
                'purchases_allowed' => true,
                'collect_email' => true,
                'redemption_allowed' => true,
                'difficulty' => ['min' => 1, 'max' => 10, 'default' => 5],
                'accessibility_defaults' => ['text_scale' => 1.0, 'reduced_motion' => false, 'calm_timers' => false, 'sound_effects' => false],
            ],
        ];
    }
}
