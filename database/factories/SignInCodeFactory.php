<?php

namespace Database\Factories;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\SignInCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SignInCode>
 */
class SignInCodeFactory extends Factory
{
    protected $model = SignInCode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => FamilyMember::factory(),
            'code_hash' => SignInCode::hashCode(fake()->unique()->regexify('[A-HJ-NP-Z2-9]{8}')),
            'expires_at' => now()->addMinutes(15),
        ];
    }

    /**
     * A code with a known plain value (tests type it in).
     */
    public function withCode(string $code): static
    {
        return $this->state(fn () => ['code_hash' => SignInCode::hashCode($code)]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }

    public function used(): static
    {
        return $this->state(fn () => ['used_at' => now()->subMinutes(2)]);
    }
}
