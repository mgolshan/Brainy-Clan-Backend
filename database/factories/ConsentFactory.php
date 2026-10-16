<?php

namespace Database\Factories;

use App\Modules\Identity\Models\Consent;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consent>
 */
class ConsentFactory extends Factory
{
    protected $model = Consent::class;

    /**
     * The current terms, accepted just now.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => Consent::TERMS,
            'version' => (string) config('legal.documents.terms.version'),
            'accepted_at' => now(),
            'ip_hash' => hash('sha256', fake()->ipv4()),
        ];
    }

    public function privacy(): static
    {
        return $this->state(fn () => ['type' => Consent::PRIVACY, 'version' => (string) config('legal.documents.privacy.version')]);
    }

    public function parental(): static
    {
        return $this->state(fn () => ['type' => Consent::PARENTAL, 'version' => (string) config('legal.documents.privacy.version')]);
    }
}
