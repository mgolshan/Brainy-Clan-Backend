<?php

namespace App\Modules\Chat\Actions;

use App\Modules\Chat\Events\MessagePosted;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Support\ChatText;
use App\Modules\Chat\Support\ProfanityFilter;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * `POST /family/chat/messages`: stores a plain-text message in the member's family. The text is
 * normalized by {@see ChatText} (again here, so every caller gets the same rules) and must be
 * 1–1000 characters afterwards (422). `client_id` makes retries idempotent: an existing message
 * with the same (family, client_id) is returned instead (`created` false → 200), including when two
 * retries race (unique index). `MessagePosted` (ids only) triggers the `family_chat` push.
 *
 * Chat safety (B12): with the family's `chat_filter` on, blocked words are masked by
 * {@see ProfanityFilter} before storing (the original text is never stored; `filtered` records
 * whether masking changed the body). In `announcements` mode only family admins post (403
 * `chat_read_only`). The member's `family` must be loaded ({@see ResolveChatMember} does).
 */
class PostChatMessage
{
    public function __construct(
        private readonly PolicyResolver $policy,
        private readonly ProfanityFilter $filter,
    ) {}

    /**
     * @return array{message: ChatMessage, created: bool}
     */
    public function handle(FamilyMember $member, string $body, string $clientId): array
    {
        $family = $member->family;

        if (! $member->isAdmin() && $this->policy->effectiveChatMode($family) === Family::CHAT_MODE_ANNOUNCEMENTS) {
            throw ApiException::chatReadOnly();
        }

        $clientId = strtolower($clientId);
        $existing = $this->find($member, $clientId);

        if ($existing !== null) {
            return ['message' => $existing, 'created' => false];
        }

        $text = ChatText::normalize($body);

        if ($text === null || ! ChatText::isValidLength($text)) {
            throw ApiException::validation('body', 'The message must be between 1 and '.ChatText::MAX_LENGTH.' characters.');
        }

        $filtered = false;

        if ($family->chat_filter) {
            ['text' => $text, 'filtered' => $filtered] = $this->filter->mask($text);
        }

        try {
            $message = ChatMessage::query()->create([
                'family_id' => $member->family_id,
                'family_member_id' => $member->id,
                'body' => $text,
                'filtered' => $filtered,
                'client_id' => $clientId,
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = $this->find($member, $clientId);

            if ($existing === null) {
                throw ApiException::validation('client_id', 'This client_id could not be used.');
            }

            return ['message' => $existing, 'created' => false];
        }

        $message->setRelation('author', $member->loadMissing('user'));
        $message->setRelation('reactions', new Collection);

        MessagePosted::dispatch($message->id, $member->family_id, $member->id);

        return ['message' => $message, 'created' => true];
    }

    private function find(FamilyMember $member, string $clientId): ?ChatMessage
    {
        return ChatMessage::viewedBy($member)
            ->where('client_id', $clientId)
            ->first();
    }
}
