<?php

namespace App\Modules\Billing\Support;

/**
 * Money helpers for the commission ledger: amounts are integers in the currency's minor unit.
 */
final class Money
{
    /** ISO 4217 currencies without a minor unit (what stores and Paddle use). */
    private const array ZERO_DECIMAL = ['BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

    public static function decimals(string $currency): int
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true) ? 0 : 2;
    }

    /**
     * A major-unit amount (RevenueCat `price_in_purchased_currency`, e.g. 49.99) in minor units.
     */
    public static function toMinor(float|int|string $amount, string $currency): int
    {
        return (int) round((float) $amount * (10 ** self::decimals($currency)));
    }

    /**
     * Minor units → a decimal string ("49.99", "-12.50").
     */
    public static function format(int $minor, string $currency): string
    {
        $decimals = self::decimals($currency);

        return number_format($minor / (10 ** $decimals), $decimals, '.', '');
    }

    /**
     * `$minor × $rate`, rounded half away from zero (so a refund mirrors its sale exactly).
     */
    public static function share(int $minor, float|string $rate): int
    {
        return (int) round($minor * (float) $rate, 0, PHP_ROUND_HALF_UP);
    }
}
