<?php

namespace App\Modules\Core\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The scheduler writes a timestamp every minute (`ops:heartbeat`); the deep health check reads
 * its age, so a stopped `scheduler` container is noticed within 3 minutes.
 */
class SchedulerHeartbeat
{
    public const string CACHE_KEY = 'ops:scheduler-heartbeat';

    public function beat(): void
    {
        Cache::forever(self::CACHE_KEY, now()->timestamp);
    }

    /**
     * Seconds since the last beat, or null when there was none.
     */
    public function ageSeconds(): ?int
    {
        $beat = Cache::get(self::CACHE_KEY);

        return is_numeric($beat) ? max(0, now()->timestamp - (int) $beat) : null;
    }
}
