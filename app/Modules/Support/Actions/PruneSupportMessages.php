<?php

namespace App\Modules\Support\Actions;

use App\Modules\Support\Models\SupportMessage;
use Carbon\CarbonImmutable;

/**
 * Support message retention (daily, `support:prune`; docs/API.md "Support messages"): deletes, for
 * good, closed messages {@see self::CLOSED_DAYS} days after closing and every message
 * {@see self::MAX_DAYS} days after its last activity, with their replies (FK cascade). Batches of
 * {@see self::BATCH}, so no long locks.
 */
class PruneSupportMessages
{
    public const int CLOSED_DAYS = 365;

    public const int MAX_DAYS = 730;

    public const int BATCH = 1000;

    /**
     * @return int messages deleted
     */
    public function handle(): int
    {
        $closedCutoff = CarbonImmutable::now()->subDays(self::CLOSED_DAYS);
        $activityCutoff = CarbonImmutable::now()->subDays(self::MAX_DAYS);
        $deleted = 0;

        do {
            $batch = SupportMessage::query()
                ->where(fn ($query) => $query
                    ->where(fn ($query) => $query->where('status', SupportMessage::STATUS_CLOSED)->where('closed_at', '<', $closedCutoff))
                    ->orWhere('last_activity_at', '<', $activityCutoff)
                    ->orWhere(fn ($query) => $query->whereNull('last_activity_at')->where('created_at', '<', $activityCutoff)))
                ->limit(self::BATCH)
                ->delete();
            $deleted += $batch;
        } while ($batch >= self::BATCH);

        return $deleted;
    }
}
