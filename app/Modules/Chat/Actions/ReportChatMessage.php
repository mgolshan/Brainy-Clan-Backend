<?php

namespace App\Modules\Chat\Actions;

use App\Modules\Chat\Events\MessageReported;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Models\ChatMessageReport;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * `POST /family/chat/messages/{id}/report` (docs/API.md "Family chat", Apple guideline 1.2): a
 * member reports another member's message to the family's admins — never to staff. Another
 * family's id or a message past retention → 404; the member's own message → 422. Idempotent per
 * (message, reporter): reporting again (or a racing retry, unique index) is a no-op. A message
 * already deleted needs no report (no-op too). Only a new report fires {@see MessageReported}
 * (ids only), which pushes `family_chat_report` to the admins.
 */
class ReportChatMessage
{
    public function __construct(private readonly CountUnreadChatMessages $unread) {}

    public function handle(FamilyMember $member, int $messageId): void
    {
        $message = ChatMessage::query()
            ->where('family_id', $member->family_id)
            ->whereKey($messageId)
            ->where('created_at', '>=', $this->unread->retentionCutoff($member))
            ->first(['id', 'family_id', 'family_member_id', 'deleted_at']);

        if ($message === null) {
            throw ApiException::notFound();
        }

        if ($message->family_member_id === $member->id) {
            throw ApiException::validation('message', 'You can not report your own message.');
        }

        if ($message->isDeleted()) {
            return;
        }

        try {
            $report = ChatMessageReport::query()->firstOrCreate([
                'chat_message_id' => $message->id,
                'reporter_member_id' => $member->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            return;
        }

        if ($report->wasRecentlyCreated) {
            MessageReported::dispatch($message->id, $member->family_id, $member->id);
        }
    }
}
