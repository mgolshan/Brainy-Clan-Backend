<?php

namespace Database\Factories;

use App\Modules\Billing\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = (string) Str::uuid();

        return [
            'provider' => WebhookEvent::PROVIDER_REVENUECAT,
            'event_id' => $id,
            'type' => 'TEST',
            'family_id' => null,
            'payload' => ['api_version' => '1.0', 'event' => ['id' => $id, 'type' => 'TEST']],
            'outcome' => WebhookEvent::OUTCOME_IGNORED,
            'processed_at' => now(),
        ];
    }
}
