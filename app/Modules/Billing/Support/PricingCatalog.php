<?php

namespace App\Modules\Billing\Support;

use App\Modules\Family\Models\Family;
use App\Modules\Policy\PolicyResolver;

/**
 * Display prices of the paid plans from `config/billing.php` (`GET /pricing`, the website), per
 * tier and billing period. They mirror the store / Paddle prices; the app shows the store prices
 * from RevenueCat instead.
 *
 * @phpstan-type PlanRow array{id: string, price: string, per_month: string, saving_percent: int, trial_days: int, highlighted: bool}
 * @phpstan-type TierRow array{id: string, members: int, min_owner_age: int|null, highlighted: bool, available: bool, plans: list<PlanRow>}
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
     * Billing periods: `annual`, `semiannual`, `monthly`.
     *
     * @return list<string>
     */
    public static function planIds(): array
    {
        return array_map('strval', array_keys((array) config('billing.plans', [])));
    }

    /**
     * Paid tiers with prices, in rank order (`family`, `big_clan`, `classroom`).
     *
     * @return list<string>
     */
    public static function tierIds(): array
    {
        $configured = array_map('strval', array_keys((array) config('billing.tiers', [])));

        return array_values(array_filter(Family::PAID_TIERS, fn (string $tier): bool => in_array($tier, $configured, true)));
    }

    public static function defaultCurrency(): string
    {
        return (string) config('billing.default_currency', 'USD');
    }

    /**
     * @return array{currency: string, tiers: list<TierRow>}
     */
    public static function forCurrency(?string $currency = null): array
    {
        $currency = strtoupper($currency ?? self::defaultCurrency());
        $policy = app(PolicyResolver::class);
        $rows = [];

        foreach (self::tierIds() as $tier) {
            $rows[] = [
                'id' => $tier,
                'members' => $policy->maxMembersFor($tier),
                'min_owner_age' => $policy->minOwnerAgeFor($tier),
                'highlighted' => (bool) config("billing.tiers.{$tier}.highlighted", false),
                'available' => PaddleConfig::webCheckoutAvailable($tier),
                'plans' => self::plansFor($tier, $currency),
            ];
        }

        return ['currency' => $currency, 'tiers' => $rows];
    }

    /**
     * One tier's billing periods in a currency; [] when the tier has no prices there.
     *
     * @return list<PlanRow>
     */
    public static function plansFor(string $tier, string $currency): array
    {
        /** @var array<string, string> $prices */
        $prices = (array) config("billing.prices.{$currency}.{$tier}", []);
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

        return $rows;
    }
}
