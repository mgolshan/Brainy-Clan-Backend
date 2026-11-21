<?php

namespace App\Modules\Billing\Support;

use App\Modules\Family\Models\Family;

/**
 * Reads the Paddle part of `config/billing.php`. Prices are configured per paid tier and billing
 * period (`billing.paddle.price_ids.{tier}.{plan}`); the pre-B12 `legacy_price_ids` still count as
 * the Family prices.
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
     * Paddle price id of a tier's billing period (`monthly`, `semiannual`, `annual`), or '' when
     * not configured.
     */
    public static function priceId(string $tier, string $plan): string
    {
        if (! Family::isPaidTier($tier)) {
            return '';
        }

        $priceId = self::string("billing.paddle.price_ids.{$tier}.{$plan}");

        if ($priceId === '' && $tier === Family::TIER_FAMILY) {
            return self::string("billing.paddle.legacy_price_ids.{$plan}");
        }

        return $priceId;
    }

    /**
     * The tier and billing period a Paddle price id belongs to, or null for an unknown price.
     *
     * @return array{tier: string, plan: string}|null
     */
    public static function tierAndPlanForPrice(?string $priceId): ?array
    {
        if ($priceId === null || $priceId === '') {
            return null;
        }

        foreach (Family::PAID_TIERS as $tier) {
            foreach (PricingCatalog::planIds() as $plan) {
                if (self::priceId($tier, $plan) === $priceId) {
                    return ['tier' => $tier, 'plan' => $plan];
                }
            }
        }

        return null;
    }

    /**
     * Web checkout can be offered for a tier: API key, webhook secret and a price id for each of
     * the tier's billing periods.
     */
    public static function webCheckoutAvailable(string $tier = Family::TIER_FAMILY): bool
    {
        if (self::apiKey() === '' || self::webhookSecret() === '' || ! Family::isPaidTier($tier)) {
            return false;
        }

        foreach (PricingCatalog::planIds() as $plan) {
            if (self::priceId($tier, $plan) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * The paid tiers web checkout is configured for, in rank order.
     *
     * @return list<string>
     */
    public static function availableTiers(): array
    {
        return array_values(array_filter(Family::PAID_TIERS, fn (string $tier): bool => self::webCheckoutAvailable($tier)));
    }

    public static function anyTierAvailable(): bool
    {
        return self::availableTiers() !== [];
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
