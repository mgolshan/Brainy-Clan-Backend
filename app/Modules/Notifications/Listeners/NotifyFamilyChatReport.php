<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Chat\Events\MessageReported;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Notifications\Actions\SendMemberNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued: tells the family's admins (active, chat on, not the reporter) that a member reported a
 * chat message (`family_chat_report`, docs/API.md "Family chat", Apple guideline 1.2). Never staff,
 * and the push **never contains the message text**: title "Brainy Clan", body "Sam reported a
 * message in the family chat" in the recipient's language, data `{ type: "family_chat_report",
 * message_id }`. Preferences and quiet hours via {@see SendMemberNotification}. Two queries: the
 * family's admins (+ the reporter) and their preferences.
 */
class NotifyFamilyChatReport implements ShouldQueue
{
    public const string TITLE = 'Brainy Clan';

    /** @var array<string, string> */
    public const array BODY = [
        'en' => ':name reported a message in the family chat',
        'fr' => ':name a signalé un message dans la discussion familiale',
    ];

    public bool $afterCommit = true;

    public function __construct(private readonly SendMemberNotification $send) {}

    public function handle(MessageReported $event): void
    {
        $members = FamilyMember::query()
            ->with(['user', 'family'])
            ->where('family_id', $event->familyId)
            ->where('status', FamilyMember::STATUS_ACTIVE)
            ->where(fn ($query) => $query->where('role', FamilyMember::ROLE_ADMIN)->orWhere('id', $event->reporterMemberId))
            ->get();

        $reporter = $members->firstWhere('id', $event->reporterMemberId);

        if ($reporter === null) {
            return;
        }

        $recipients = $members->filter(fn (FamilyMember $member) => $member->id !== $reporter->id && $member->isAdmin() && $member->chat_enabled);

        foreach ($recipients->groupBy(fn (FamilyMember $member) => array_key_exists($member->user->locale, self::BODY) ? $member->user->locale : 'en') as $locale => $group) {
            $this->send->handle(
                $group,
                'family_chat_report',
                self::TITLE,
                str_replace(':name', $reporter->nickname, self::BODY[$locale]),
                ['message_id' => $event->messageId],
                CarbonImmutable::now(),
            );
        }
    }
}
