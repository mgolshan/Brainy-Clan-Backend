<?php

namespace Database\Factories;

use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['iPhone', 'Pixel', 'iPad', 'Chrome']),
            'platform' => fake()->randomElement(Device::PLATFORMS),
            'app_version' => '1.0.0',
            'last_seen_at' => now(),
        ];
    }
}
