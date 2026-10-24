<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Chat\Events\MessagePosted;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Notifications\Actions\SendMemberNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Queued: tells the other active family members with chat on that someone wrote in the family
 * chat (`family_chat`, docs/API.md "Family chat"). The push **never contains the message text** (it
 * travels through Expo/Apple/Google): title "Brainy Clan", body "Sam sent a message" in the
 * recipient's language, data `{ type: "family_chat", message_id }`. At most one per recipient per
 * {@see self::THROTTLE_SECONDS} (atomic `Cache::add`); preferences and quiet hours via
 * {@see SendMemberNotification}. Two queries: the family's members and their preferences.
 */
class NotifyFamilyChat implements ShouldQueue
{
    public const int THROTTLE_SECONDS = 120;

    public const string TITLE = 'Brainy Clan';

    /** @var array<string, string> */
    public const array BODY = [
        'en' => ':name sent a message',
        'fr' => ':name a envoyé un message',
    ];

    public bool $afterCommit = true;

    public function __construct(private readonly SendMemberNotification $send) {}

    public function handle(MessagePosted $event): void
    {
        $members = FamilyMember::query()
            ->with(['user', 'family'])
            ->where('family_id', $event->familyId)
            ->where('status', FamilyMember::STATUS_ACTIVE)
            ->get();

        $author = $members->firstWhere('id', $event->memberId);

        if ($author === null) {
            return;
        }

        $recipients = $members
            ->reject(fn (FamilyMember $member) => $member->id === $event->memberId || ! $member->chat_enabled)
            ->filter(fn (FamilyMember $member) => Cache::add(self::throttleKey($member->id), true, self::THROTTLE_SECONDS));

        foreach ($recipients->groupBy(fn (FamilyMember $member) => array_key_exists($member->user->locale, self::BODY) ? $member->user->locale : 'en') as $locale => $group) {
            $this->send->handle(
                $group,
                'family_chat',
                self::TITLE,
                str_replace(':name', $author->nickname, self::BODY[$locale]),
                ['message_id' => $event->messageId],
                CarbonImmutable::now(),
            );
        }
    }

    public static function throttleKey(int $memberId): string
    {
        return "notifications:family_chat:{$memberId}";
    }
}
