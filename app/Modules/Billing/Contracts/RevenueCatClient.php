<?php

namespace App\Modules\Billing\Contracts;

use RuntimeException;

/**
 * RevenueCat REST API v1 (server side, secret API key). Faked in tests.
 */
interface RevenueCatClient
{
    /**
     * Whether an API key is configured; without one the server relies on webhooks only.
     */
    public function isConfigured(): bool;

    /**
     * `GET /subscribers/{app_user_id}` → the `subscriber` object (`entitlements`, `subscriptions`, …).
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException when the request fails
     */
    public function subscriber(string $appUserId): array;
}
