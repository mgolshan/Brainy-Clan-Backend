<?php

namespace App\Modules\Core\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Formats timestamps the way the API contract wants them: ISO 8601 in UTC,
 * without fractions (`2026-10-10T14:00:00Z`).
 */
final class Iso
{
    public static function format(?DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return Carbon::instance($date)->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
