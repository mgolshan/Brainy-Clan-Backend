<?php

namespace App\Modules\Play\Actions;

use App\Modules\Content\Models\ContentItem;
use App\Modules\Play\Models\MemberItemHistory;
use App\Modules\Play\Models\PlaySession;
use App\Modules\Play\Models\SessionItem;
use Illuminate\Support\Collection;

/**
 * Appends items to a session and counts them as "seen" in the member's item history.
 * `topUp()` makes sure enough unanswered regular items remain to reach the target even if
 * every remaining mistake is used: (target − points) + (cap − mistakes).
 */
class AddSessionItems
{
    public function __construct(private readonly PickContentItems $pick) {}

    /**
     * @return Collection<int, SessionItem>
     */
    public function handle(PlaySession $session, int $count, bool $redemption = false): Collection
    {
        $member = $session->member;
        $existingIds = SessionItem::query()->where('play_session_id', $session->id)->whereNotNull('content_item_id')->pluck('content_item_id')->map(fn ($id) => (int) $id)->all();
        $hasItems = $existingIds !== [];

        $contentItems = $this->pick->handle($member, $session->category_id, $count, $existingIds, easier: $redemption, allowRepeats: $hasItems);

        if ($redemption && $contentItems->count() < $count) {
            // Small categories: allow easy repeats rather than an impossible round.
            $contentItems = $contentItems->merge($this->pick->handle($member, $session->category_id, $count - $contentItems->count(), [], easier: true));
        }

        $position = (int) SessionItem::query()->where('play_session_id', $session->id)->max('position');
        $added = new Collection;

        foreach ($contentItems as $contentItem) {
            /** @var ContentItem $contentItem */
            $added->push(SessionItem::query()->create([
                'play_session_id' => $session->id,
                'content_item_id' => $contentItem->id,
                'position' => ++$position,
                'is_redemption' => $redemption,
            ]));

            $history = MemberItemHistory::query()->firstOrNew(['member_id' => $member->id, 'content_item_id' => $contentItem->id]);
            $history->times_seen = ($history->times_seen ?? 0) + 1;
            $history->last_seen_at = now();
            $history->save();
        }

        return $added;
    }

    public function topUp(PlaySession $session): void
    {
        $needed = ($session->target_points - $session->points) + ($session->blunder_cap - $session->mistakes);
        $unanswered = SessionItem::query()
            ->where('play_session_id', $session->id)
            ->where('is_redemption', false)
            ->whereNull('answered_at')
            ->count();

        if ($needed > $unanswered) {
            $this->handle($session, $needed - $unanswered);
        }
    }
}
