<?php

namespace App\Modules\Chat\Actions;

use App\Modules\Chat\Events\MessagePosted;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Support\ChatText;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * `POST /family/chat/messages`: stores a plain-text message in the member's family. The text is
 * normalized by {@see ChatText} (again here, so every caller gets the same rules) and must be
 * 1–1000 characters afterwards (422). `client_id` makes retries idempotent: an existing message
 * with the same (family, client_id) is returned instead (`created` false → 200), including when two
 * retries race (unique index). `MessagePosted` (ids only) triggers the `family_chat` push.
 */
class PostChatMessage
{
    /**
     * @return array{message: ChatMessage, created: bool}
     */
    public function handle(FamilyMember $member, string $body, string $clientId): array
    {
        $clientId = strtolower($clientId);
        $existing = $this->find($member, $clientId);

        if ($existing !== null) {
            return ['message' => $existing, 'created' => false];
        }

        $text = ChatText::normalize($body);

        if ($text === null || ! ChatText::isValidLength($text)) {
            throw ApiException::validation('body', 'The message must be between 1 and '.ChatText::MAX_LENGTH.' characters.');
        }

        try {
            $message = ChatMessage::query()->create([
                'family_id' => $member->family_id,
                'family_member_id' => $member->id,
                'body' => $text,
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

        MessagePosted::dispatch($message->id, $member->family_id, $member->id);

        return ['message' => $message, 'created' => true];
    }

    private function find(FamilyMember $member, string $clientId): ?ChatMessage
    {
        return ChatMessage::query()
            ->where('family_id', $member->family_id)
            ->where('client_id', $clientId)
            ->with(['author' => fn ($query) => $query->select(['id', 'family_id', 'user_id', 'nickname', 'status'])->with('user:id,avatar')])
            ->first();
    }
}
