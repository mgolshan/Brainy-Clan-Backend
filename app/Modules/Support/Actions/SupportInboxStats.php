<?php

namespace App\Modules\Support\Actions;

use App\Modules\Support\Models\SupportMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Counts for the admin dashboard card and the inbox navigation badge: new (unread), unanswered
 * (new + open) and when the oldest unanswered message arrived. One aggregate query on the
 * (status, created_at) index; not cached, so the numbers are exact right after a message arrives
 * or a status changes.
 */
class SupportInboxStats
{
    /**
     * @return array{new: int, unanswered: int, oldest_unanswered_at: CarbonImmutable|null}
     */
    public function handle(): array
    {
        $row = DB::table('support_messages')
            ->whereIn('status', [SupportMessage::STATUS_NEW, SupportMessage::STATUS_OPEN])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS new_count', [SupportMessage::STATUS_NEW])
            ->selectRaw('COUNT(*) AS unanswered')
            ->selectRaw('MIN(created_at) AS oldest')
            ->first();

        return [
            'new' => (int) ($row->new_count ?? 0),
            'unanswered' => (int) ($row->unanswered ?? 0),
            'oldest_unanswered_at' => isset($row->oldest) ? CarbonImmutable::parse((string) $row->oldest) : null,
        ];
    }

    /**
     * New messages only (the navigation badge). One COUNT query.
     */
    public function newCount(): int
    {
        return SupportMessage::query()->where('status', SupportMessage::STATUS_NEW)->count();
    }
}
