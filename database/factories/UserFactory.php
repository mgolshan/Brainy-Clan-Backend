<?php

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    /**
     * An adult with email and password by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'display_name' => fake()->firstName(),
            'avatar' => fake()->randomElement(User::AVATARS),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'birth_year' => now()->year - fake()->numberBetween(25, 50),
            'birth_month' => fake()->numberBetween(1, 12),
            'locale' => 'en',
            'is_staff' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * A child account: nickname + avatar only, no email or password.
     */
    public function child(int $age = 9): static
    {
        return $this->state(fn () => [
            'email' => null,
            'email_verified_at' => null,
            'password' => null,
            'birth_year' => now()->year - $age,
            'birth_month' => 1,
            'remember_token' => null,
        ]);
    }

    public function aged(int $age): static
    {
        return $this->state(fn () => ['birth_year' => now()->year - $age, 'birth_month' => 1]);
    }

    public function staff(): static
    {
        return $this->state(fn () => ['is_staff' => true]);
    }
}
