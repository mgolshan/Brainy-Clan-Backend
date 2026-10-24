<?php

namespace App\Modules\Billing\Contracts;

use RuntimeException;

/**
 * Paddle Billing API (server side, API key). Faked in tests with `Http::fake()`.
 */
interface PaddleClient
{
    /**
     * Whether an API key is configured.
     */
    public function isConfigured(): bool;

    /**
     * `POST /transactions` → the new transaction's id and its `checkout.url` (null when Paddle has
     * no default payment link).
     *
     * @param  array<string, mixed>  $body
     * @return array{id: string, checkout_url: string|null}
     *
     * @throws RuntimeException when the request fails
     */
    public function createTransaction(array $body): array;

    /**
     * `POST /customers/{customer_id}/portal-sessions` → the portal overview URL (temporary; never stored).
     *
     * @param  list<string>  $subscriptionIds
     *
     * @throws RuntimeException when the request fails
     */
    public function customerPortalUrl(string $customerId, array $subscriptionIds = []): string;

    /**
     * `GET /subscriptions/{subscription_id}` → the subscription entity (`data`).
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException when the request fails
     */
    public function subscription(string $subscriptionId): array;
}
