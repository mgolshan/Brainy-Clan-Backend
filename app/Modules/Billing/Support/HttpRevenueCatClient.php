<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Contracts\RevenueCatClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * RevenueCat REST API v1 over HTTP: `GET {services.revenuecat.api_url}/subscribers/{app_user_id}`
 * with `Authorization: Bearer {REVENUECAT_API_KEY}` (secret key, server side only).
 */
class HttpRevenueCatClient implements RevenueCatClient
{
    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    public function subscriber(string $appUserId): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('REVENUECAT_API_KEY is not configured.');
        }

        $url = rtrim((string) config('services.revenuecat.api_url'), '/').'/subscribers/'.rawurlencode($appUserId);
        try {
            $response = Http::acceptJson()->withToken($this->apiKey())->timeout(10)->retry(2, 300, throw: false)->get($url);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('RevenueCat could not be reached: '.$exception->getMessage(), previous: $exception);
        }

        if ($response->failed() || ! is_array($response->json('subscriber'))) {
            throw new RuntimeException("RevenueCat subscriber request failed with HTTP {$response->status()}.");
        }

        /** @var array<string, mixed> $subscriber */
        $subscriber = $response->json('subscriber');

        return $subscriber;
    }

    private function apiKey(): string
    {
        $key = config('services.revenuecat.api_key');

        return is_string($key) ? $key : '';
    }
}
