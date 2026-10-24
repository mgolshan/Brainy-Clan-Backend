<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Notifications\Actions\SendMemberNotification;
use App\Modules\Notifications\Support\PushLocale;
use App\Modules\Play\Events\SessionCompleted;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Queued: tells the other active family members that a member finished a session
 * (`family_activity`, docs/API.md B8). At most one per finishing member per hour (an atomic
 * `Cache::add` key), never to the finisher. Texts in each recipient's language ({@see PushLocale}):
 * "Sam finished a challenge!" / "Sam earned 10 points for the family." (no comparison or "your turn"
 * nudge, PLAN.md Mission). Push `data`: `{ "type": "family_activity", "member_id": …,
 * "session_id": … }`. Two queries: the family's members and their preferences.
 */
class NotifyFamilyActivity implements ShouldQueue
{
    public const int THROTTLE_SECONDS = 3600;

    /** @var array<string, array{title: string, body: string, point: string, points: string}> */
    public const array TEXTS = [
        'en' => [
            'title' => ':name finished a challenge!',
            'body' => ':name earned :points for the family.',
            'point' => '1 point',
            'points' => ':count points',
        ],
        'fr' => [
            'title' => ':name a terminé un défi !',
            'body' => ':name a gagné :points pour la famille.',
            'point' => '1 point',
            'points' => ':count points',
        ],
    ];

    public bool $afterCommit = true;

    public function __construct(private readonly SendMemberNotification $send) {}

    public function handle(SessionCompleted $event): void
    {
        $members = FamilyMember::query()
            ->with(['user', 'family'])
            ->where('family_id', $event->familyId)
            ->where('status', FamilyMember::STATUS_ACTIVE)
            ->get();

        $finisher = $members->firstWhere('id', $event->memberId);
        $recipients = $members->reject(fn (FamilyMember $member) => $member->id === $event->memberId)->values();

        if ($finisher === null || $recipients->isEmpty()) {
            return;
        }

        if (! Cache::add(self::throttleKey($event->memberId), true, self::THROTTLE_SECONDS)) {
            return;
        }

        foreach (PushLocale::group($recipients) as $locale => $group) {
            $texts = self::TEXTS[$locale];
            $points = $event->points === 1 ? $texts['point'] : strtr($texts['points'], [':count' => $event->points]);

            $this->send->handle(
                $group,
                'family_activity',
                strtr($texts['title'], [':name' => $finisher->nickname]),
                strtr($texts['body'], [':name' => $finisher->nickname, ':points' => $points]),
                ['member_id' => $finisher->id, 'session_id' => $event->sessionId],
                CarbonImmutable::now(),
            );
        }
    }

    public static function throttleKey(int $memberId): string
    {
        return "notifications:family_activity:{$memberId}";
    }
}
