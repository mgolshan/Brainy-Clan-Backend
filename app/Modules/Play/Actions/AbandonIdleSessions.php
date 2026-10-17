<?php

namespace App\Modules\Play\Actions;

use App\Modules\Play\Models\PlaySession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Scheduled (hourly): abandons unfinished sessions (`active`, `failed`, `redemption`) whose last
 * activity — the last answer's `answered_at`, else `started_at` — is older than 24 hours, so
 * resumable games don't pile up. Works in batches: one id query and one update per batch.
 */
class AbandonIdleSessions
{
    public const int IDLE_HOURS = 24;

    private const int BATCH = 500;

    /**
     * @return int number of abandoned sessions
     */
    public function handle(?CarbonImmutable $now = null): int
    {
        $cutoff = ($now ?? CarbonImmutable::now())->subHours(self::IDLE_HOURS);
        $abandoned = 0;

        do {
            $ids = $this->idleSessions($cutoff)->orderBy('id')->limit(self::BATCH)->pluck('id');

            if ($ids->isNotEmpty()) {
                // Re-check the conditions so a session answered meanwhile is left alone.
                $abandoned += $this->idleSessions($cutoff)->whereIn('id', $ids->all())->update([
                    'status' => PlaySession::STATUS_ABANDONED,
                    'updated_at' => now(),
                ]);
            }
        } while ($ids->count() === self::BATCH);

        return $abandoned;
    }

    /**
     * Last activity before the cutoff: started before it and no answer since (answers always
     * come after the start).
     *
     * @return Builder<PlaySession>
     */
    private function idleSessions(CarbonImmutable $cutoff): Builder
    {
        return PlaySession::query()
            ->whereIn('status', PlaySession::UNFINISHED_STATUSES)
            ->where('started_at', '<', $cutoff)
            ->whereNotExists(fn (QueryBuilder $q) => $q->selectRaw('1')
                ->from('session_items')
                ->whereColumn('session_items.play_session_id', 'play_sessions.id')
                ->where('session_items.answered_at', '>=', $cutoff));
    }
}
