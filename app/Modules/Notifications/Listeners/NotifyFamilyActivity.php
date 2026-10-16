<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Notifications\Actions\SendMemberNotification;
use App\Modules\Play\Events\SessionCompleted;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Queued: tells the other active family members that a member finished a session
 * (`family_activity`, docs/API.md B8). At most one per finishing member per hour (an atomic
 * `Cache::add` key), never to the finisher. Push `data`: `{ "type": "family_activity",
 * "member_id": …, "session_id": … }`. Two queries: the family's members and their preferences.
 */
class NotifyFamilyActivity implements ShouldQueue
{
    public const int THROTTLE_SECONDS = 3600;

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

        $points = $event->points === 1 ? '1 point' : "{$event->points} points";

        $this->send->handle(
            $recipients,
            'family_activity',
            "{$finisher->nickname} finished a challenge!",
            "{$finisher->nickname} scored {$points}. Your turn?",
            ['member_id' => $finisher->id, 'session_id' => $event->sessionId],
            CarbonImmutable::now(),
        );
    }

    public static function throttleKey(int $memberId): string
    {
        return "notifications:family_activity:{$memberId}";
    }
}
