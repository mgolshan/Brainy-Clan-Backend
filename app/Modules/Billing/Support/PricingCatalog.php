<?php

namespace App\Modules\Billing\Support;

/**
 * Display prices of the Family plan from `config/billing.php` (`GET /pricing`, the website). They
 * mirror the store / Paddle prices; the app shows the store prices from RevenueCat instead.
 */
final class PricingCatalog
{
    /**
     * @return list<string>
     */
    public static function currencies(): array
    {
        return array_map('strval', array_keys((array) config('billing.prices', [])));
    }

    /**
     * @return list<string>
     */
    public static function planIds(): array
    {
        return array_map('strval', array_keys((array) config('billing.plans', [])));
    }

    public static function defaultCurrency(): string
    {
        return (string) config('billing.default_currency', 'USD');
    }

    /**
     * @return array{currency: string, plans: list<array{id: string, price: string, per_month: string, saving_percent: int, trial_days: int, highlighted: bool}>}
     */
    public static function forCurrency(?string $currency = null): array
    {
        $currency = strtoupper($currency ?? self::defaultCurrency());
        /** @var array<string, string> $prices */
        $prices = (array) config("billing.prices.{$currency}", []);
        /** @var array<string, array{months: int, trial_days: int, highlighted: bool}> $plans */
        $plans = (array) config('billing.plans', []);
        $monthly = (float) ($prices['monthly'] ?? 0);
        $rows = [];

        foreach ($plans as $id => $plan) {
            if (! isset($prices[$id])) {
                continue;
            }

            $price = (float) $prices[$id];
            $months = max(1, (int) $plan['months']);
            $perMonth = $price / $months;

            $rows[] = [
                'id' => (string) $id,
                'price' => number_format($price, 2, '.', ''),
                'per_month' => number_format($perMonth, 2, '.', ''),
                'saving_percent' => $months > 1 && $monthly > 0 ? max(0, (int) round((1 - $perMonth / $monthly) * 100)) : 0,
                'trial_days' => (int) $plan['trial_days'],
                'highlighted' => (bool) $plan['highlighted'],
            ];
        }

        return ['currency' => $currency, 'plans' => $rows];
    }
}
