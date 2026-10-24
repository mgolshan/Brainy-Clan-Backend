<?php

namespace App\Modules\Chat\Actions;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Database\Eloquent\Collection;

/**
 * `GET /family/chat/messages` (docs/API.md "Family chat"): one page of the member's family chat by
 * id cursor — newest first without a cursor or with `before`, oldest first with `after` (polling).
 * Only the member's own family; messages past retention are hidden. A fixed number of queries for
 * any page size: messages (with `reported` as an EXISTS subquery for admins), authors, their users
 * (avatars) and the unread count.
 */
class ListChatMessages
{
    public const int DEFAULT_LIMIT = 50;

    public const int MAX_LIMIT = 100;

    public function __construct(
        private readonly PolicyResolver $policy,
        private readonly CountUnreadChatMessages $unread,
    ) {}

    /**
     * @return array{messages: Collection<int, ChatMessage>, has_more: bool, retention_days: int, last_read_id: int|null, unread: int}
     */
    public function handle(FamilyMember $member, ?int $before = null, ?int $after = null, int $limit = self::DEFAULT_LIMIT): array
    {
        $limit = max(1, min(self::MAX_LIMIT, $limit));

        $messages = ChatMessage::query()
            ->where('family_id', $member->family_id)
            ->where('created_at', '>=', $this->unread->retentionCutoff($member))
            ->when($after !== null, fn ($query) => $query->where('id', '>', $after)->orderBy('id'))
            ->when($after === null, fn ($query) => $query->when($before !== null, fn ($query) => $query->where('id', '<', $before))->orderByDesc('id'))
            ->with(['author' => fn ($query) => $query->select(['id', 'family_id', 'user_id', 'nickname', 'status'])->with('user:id,avatar')])
            // `reported` only for admins: an EXISTS subquery in the same select (no extra query).
            ->when($member->isAdmin(), fn ($query) => $query->withExists('reports as reported'))
            ->limit($limit + 1)
            ->get();

        $hasMore = $messages->count() > $limit;

        return [
            'messages' => $messages->take($limit)->values(),
            'has_more' => $hasMore,
            'retention_days' => $this->policy->chatRetentionDays($member->family->tier),
            'last_read_id' => $member->chat_last_read_id,
            'unread' => $this->unread->handle($member),
        ];
    }
}
