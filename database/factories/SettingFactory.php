<?php

namespace Database\Factories;

use App\Modules\Policy\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'scope' => Setting::SCOPE_GLOBAL,
            'scope_id' => null,
            'value' => true,
        ];
    }
}
