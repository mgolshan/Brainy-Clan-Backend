<?php

namespace App\Modules\Billing\Support;

/**
 * Reads the Paddle part of `config/billing.php`.
 */
final class PaddleConfig
{
    public const array API_URLS = [
        'sandbox' => 'https://sandbox-api.paddle.com',
        'production' => 'https://api.paddle.com',
    ];

    public static function environment(): string
    {
        return config('billing.paddle.environment') === 'production' ? 'production' : 'sandbox';
    }

    public static function apiUrl(): string
    {
        return self::API_URLS[self::environment()];
    }

    public static function apiKey(): string
    {
        return self::string('billing.paddle.api_key');
    }

    public static function webhookSecret(): string
    {
        return self::string('billing.paddle.webhook_secret');
    }

    /**
     * Paddle price id of a plan (`monthly`, `semiannual`, `annual`), or '' when not configured.
     */
    public static function priceId(string $plan): string
    {
        return self::string("billing.paddle.price_ids.{$plan}");
    }

    /**
     * The plan (`annual`, …) a Paddle price id belongs to, or null.
     */
    public static function planForPrice(?string $priceId): ?string
    {
        if ($priceId === null || $priceId === '') {
            return null;
        }

        foreach (array_keys((array) config('billing.plans', [])) as $plan) {
            if (self::priceId((string) $plan) === $priceId) {
                return (string) $plan;
            }
        }

        return null;
    }

    /**
     * Web checkout can be offered: API key, webhook secret and a price id for every plan.
     */
    public static function webCheckoutAvailable(): bool
    {
        if (self::apiKey() === '' || self::webhookSecret() === '') {
            return false;
        }

        foreach (array_keys((array) config('billing.plans', [])) as $plan) {
            if (self::priceId((string) $plan) === '') {
                return false;
            }
        }

        return true;
    }

    public static function hostedCheckoutUrl(): string
    {
        return self::string('billing.paddle.hosted_checkout_url');
    }

    public static function clientToken(): string
    {
        return self::string('billing.paddle.client_token');
    }

    private static function string(string $key): string
    {
        $value = config($key);

        return is_string($value) ? trim($value) : '';
    }
}
