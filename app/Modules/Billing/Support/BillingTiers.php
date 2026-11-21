<?php

namespace App\Modules\Billing\Support;

use App\Modules\Family\Models\Family;

/**
 * The RevenueCat entitlement ↔ plan tier map (`services.revenuecat.entitlements`, docs/API.md
 * "Plans and tiers"): one entitlement per paid tier, defaults `family`, `big_clan`, `classroom`.
 * Subscription rows of both providers store the entitlement id of their tier.
 */
final class BillingTiers
{
    /**
     * Entitlement id → tier, for the paid tiers that have one configured.
     *
     * @return array<string, string>
     */
    public static function entitlementMap(): array
    {
        $map = [];

        foreach (Family::PAID_TIERS as $tier) {
            $entitlement = self::entitlementFor($tier);

            if ($entitlement !== '' && ! isset($map[$entitlement])) {
                $map[$entitlement] = $tier;
            }
        }

        return $map;
    }

    /**
     * The entitlement id of a paid tier ('' when not configured).
     */
    public static function entitlementFor(string $tier): string
    {
        $value = config("services.revenuecat.entitlements.{$tier}");

        return is_string($value) ? trim($value) : '';
    }

    /**
     * The tier an entitlement id unlocks, or null for an entitlement that isn't one of ours.
     */
    public static function tierForEntitlement(?string $entitlement): ?string
    {
        return $entitlement !== null ? (self::entitlementMap()[$entitlement] ?? null) : null;
    }

    /**
     * The highest-ranked tier among the given entitlement ids, with its entitlement; null when
     * none of them is one of ours.
     *
     * @param  iterable<mixed>  $entitlements
     * @return array{tier: string, entitlement: string}|null
     */
    public static function bestOf(iterable $entitlements): ?array
    {
        $best = null;

        foreach ($entitlements as $entitlement) {
            $tier = is_string($entitlement) ? self::tierForEntitlement($entitlement) : null;

            if ($tier !== null && ($best === null || Family::tierRank($tier) > Family::tierRank($best['tier']))) {
                $best = ['tier' => $tier, 'entitlement' => (string) $entitlement];
            }
        }

        return $best;
    }
}
