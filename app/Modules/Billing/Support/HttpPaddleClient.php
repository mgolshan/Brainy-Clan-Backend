<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Contracts\PaddleClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Paddle Billing REST API over plain HTTP (no SDK): `Authorization: Bearer {PADDLE_API_KEY}`,
 * base URL https://sandbox-api.paddle.com or https://api.paddle.com ({@see PaddleConfig}).
 */
class HttpPaddleClient implements PaddleClient
{
    public function isConfigured(): bool
    {
        return PaddleConfig::apiKey() !== '';
    }

    public function createTransaction(array $body): array
    {
        $response = $this->send(fn (PendingRequest $request) => $request->post('/transactions', $body), 'create transaction');
        $id = $response->json('data.id');
        $checkoutUrl = $response->json('data.checkout.url');

        if (! is_string($id) || $id === '') {
            throw new RuntimeException('Paddle returned a transaction without an id.');
        }

        return ['id' => $id, 'checkout_url' => is_string($checkoutUrl) && $checkoutUrl !== '' ? $checkoutUrl : null];
    }

    public function customerPortalUrl(string $customerId, array $subscriptionIds = []): string
    {
        $body = $subscriptionIds !== [] ? ['subscription_ids' => $subscriptionIds] : (object) [];
        $response = $this->send(
            fn (PendingRequest $request) => $request->post('/customers/'.rawurlencode($customerId).'/portal-sessions', $body),
            'create portal session',
        );
        $url = $response->json('data.urls.general.overview');

        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Paddle returned a portal session without a URL.');
        }

        return $url;
    }

    public function subscription(string $subscriptionId): array
    {
        $response = $this->send(fn (PendingRequest $request) => $request->get('/subscriptions/'.rawurlencode($subscriptionId)), 'get subscription');
        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('Paddle returned no subscription.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call, string $what): Response
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('PADDLE_API_KEY is not configured.');
        }

        $request = Http::baseUrl(PaddleConfig::apiUrl())
            ->acceptJson()
            ->asJson()
            ->withToken(PaddleConfig::apiKey())
            ->timeout(10)
            ->retry(2, 300, fn ($exception) => $exception instanceof ConnectionException, throw: false);

        try {
            $response = $call($request);
        } catch (ConnectionException $exception) {
            throw new RuntimeException("Paddle could not be reached ({$what}): ".$exception->getMessage(), previous: $exception);
        }

        if ($response->failed()) {
            $code = $response->json('error.code');

            throw new RuntimeException("Paddle {$what} failed with HTTP {$response->status()}".(is_string($code) ? " ({$code})" : '').'.');
        }

        return $response;
    }
}
