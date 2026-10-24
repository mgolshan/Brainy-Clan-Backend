<?php

namespace App\Modules\Chat\Actions;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Family\Models\Family;
use App\Modules\Policy\PolicyResolver;
use Carbon\CarbonImmutable;

/**
 * Chat retention (hourly, `chat:prune`): deletes, for good, every message older than its family's
 * `chat.retention_days` (tier setting: free 7, Family plan 365). One delete per tier (in batches
 * of {@see self::BATCH}, so no long locks), using the (created_at) index and a family-id subquery
 * on the tier. After a downgrade the shorter period applies on the next run.
 */
class PruneChatMessages
{
    public const int BATCH = 5000;

    public function __construct(private readonly PolicyResolver $policy) {}

    /**
     * @return int messages deleted
     */
    public function handle(): int
    {
        $deleted = 0;

        foreach (Family::TIERS as $tier) {
            $cutoff = CarbonImmutable::now()->subDays($this->policy->chatRetentionDays($tier));

            do {
                $batch = ChatMessage::query()
                    ->where('created_at', '<', $cutoff)
                    ->whereIn('family_id', Family::query()->select('id')->where('tier', $tier))
                    ->limit(self::BATCH)
                    ->delete();
                $deleted += $batch;
            } while ($batch >= self::BATCH);
        }

        return $deleted;
    }
}
