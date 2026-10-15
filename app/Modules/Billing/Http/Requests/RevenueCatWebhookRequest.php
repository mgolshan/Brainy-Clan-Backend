<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Core\Exceptions\ApiException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A RevenueCat webhook: authorized by the shared secret in `Authorization: Bearer <secret>`.
 */
class RevenueCatWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $secret = (string) config('services.revenuecat.webhook_secret');

        return $secret !== '' && hash_equals($secret, (string) $this->bearerToken());
    }

    protected function failedAuthorization(): never
    {
        throw new ApiException('unauthenticated', 'Invalid webhook secret.', 401);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'array'],
            'event.id' => ['required', 'string', 'max:100'],
            'event.type' => ['required', 'string', 'max:40'],
        ];
    }
}
