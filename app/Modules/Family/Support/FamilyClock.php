<?php

namespace App\Modules\Family\Support;

use App\Modules\Family\Models\Family;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Time windows in a family's timezone ("today" ends at the family's midnight; weeks start
 * Monday). Returned bounds are UTC, start inclusive, end exclusive.
 */
final class FamilyClock
{
    public static function now(Family $family): CarbonImmutable
    {
        return CarbonImmutable::now($family->timezone);
    }

    public static function today(Family $family): CarbonImmutable
    {
        return self::now($family)->startOfDay();
    }

    /**
     * @param  'today'|'day'|'daily'|'week'|'weekly'|'month'|'monthly'  $period
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} [start, end) in UTC
     */
    public static function window(Family $family, string $period, ?CarbonImmutable $at = null): array
    {
        $local = ($at ?? CarbonImmutable::now())->setTimezone($family->timezone);

        [$start, $end] = match ($period) {
            'today', 'day', 'daily' => [$local->startOfDay(), $local->startOfDay()->addDay()],
            'week', 'weekly' => [$local->startOfWeek(CarbonImmutable::MONDAY), $local->startOfWeek(CarbonImmutable::MONDAY)->addWeek()],
            'month', 'monthly' => [$local->startOfMonth(), $local->startOfMonth()->addMonth()],
            default => throw new InvalidArgumentException("Unknown period [{$period}]."),
        };

        return [$start->utc(), $end->utc()];
    }
}
