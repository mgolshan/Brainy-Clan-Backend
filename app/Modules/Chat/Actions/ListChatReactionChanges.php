<?php

namespace App\Modules\Chat\Actions;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Support\ChatReactions;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * `GET /family/chat/reactions?since=<cursor>` (docs/API.md "Family chat" → "Reactions"): the
 * messages of the member's family whose reactions changed after the cursor (sets, replacements,
 * removals, deleted messages with `reactions: []`, members who left), oldest change first, at most
 * {@see ChatReactions::PAGE_SIZE}. Messages past retention are never listed.
 *
 * The new cursor is the (changed_at, id) of the last row of a full page; otherwise "now minus
 * {@see ChatReactions::SAFETY_SECONDS}" (never behind the given cursor), so a change whose
 * transaction committed a little late is still picked up. A message may then be listed twice,
 * which is harmless: rows are full snapshots. Without `since`: no rows and that same "now" cursor
 * (the app takes it with the first page of messages). Two queries for any page.
 */
class ListChatReactionChanges
{
    public function __construct(private readonly CountUnreadChatMessages $unread) {}

    /**
     * @return array{messages: Collection<int, ChatMessage>, cursor: string}
     */
    public function handle(FamilyMember $member, ?string $since): array
    {
        if ($since === null) {
            return ['messages' => new Collection, 'cursor' => ChatReactions::safeCursor()];
        }

        $position = ChatReactions::decodeCursor($since);

        if ($position === null) {
            throw ApiException::validation('since', 'The cursor is malformed.');
        }

        [$changedAt, $id] = $position;

        $messages = ChatMessage::query()
            ->where('family_id', $member->family_id)
            ->whereNotNull('reactions_changed_at')
            ->where(fn (Builder $query) => $query
                ->where('reactions_changed_at', '>', $changedAt)
                ->orWhere(fn (Builder $query) => $query->where('reactions_changed_at', $changedAt)->where('id', '>', $id)))
            ->where('created_at', '>=', $this->unread->retentionCutoff($member))
            ->orderBy('reactions_changed_at')
            ->orderBy('id')
            ->limit(ChatReactions::PAGE_SIZE)
            ->with(ChatReactions::eagerLoad())
            ->get(['id', 'family_id', 'deleted_at', 'reactions_changed_at']);

        $last = $messages->last();
        $cursor = $last !== null && $messages->count() >= ChatReactions::PAGE_SIZE
            ? ChatReactions::cursorOf($last)
            : ChatReactions::maxCursor($since, ChatReactions::safeCursor());

        return ['messages' => $messages, 'cursor' => $cursor];
    }
}
