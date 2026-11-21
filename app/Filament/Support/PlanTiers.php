<?php

namespace App\Filament\Support;

use App\Modules\Family\Models\Family;

/**
 * Plan tier options, labels and badge colours for the admin (tables, filters, forms, widgets).
 */
final class PlanTiers
{
    /**
     * @return array<string, string> tier => label, cheapest first
     */
    public static function options(): array
    {
        return array_combine(Family::TIERS, array_map(fn (string $tier): string => Family::tierLabel($tier), Family::TIERS));
    }

    public static function label(?string $tier): string
    {
        return $tier !== null ? Family::tierLabel($tier) : '—';
    }

    public static function color(?string $tier): string
    {
        return match ($tier) {
            Family::TIER_FAMILY => 'success',
            Family::TIER_BIG_CLAN => 'info',
            Family::TIER_CLASSROOM => 'warning',
            default => 'gray',
        };
    }
}
