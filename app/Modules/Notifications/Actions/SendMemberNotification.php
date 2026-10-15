<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Notifications\Jobs\DeliverPushNotification;
use Carbon\CarbonImmutable;

/**
 * Sends a push notification of one type to members (docs/API.md, B8): skips members who turned the
 * type off and delays members inside their quiet hours (family timezone) until the quiet hours end.
 * Members with the same send time share one {@see DeliverPushNotification} job; deliveries older than
 * 12 h are dropped by the job. Members need `user` and `family` loaded; no per-member queries.
 */
class SendMemberNotification
{
    public function __construct(private readonly GetNotificationPreferences $preferences) {}

    /**
     * @param  iterable<FamilyMember>  $members
     * @param  array<string, mixed>  $data  push `data` (`type` is added)
     */
    public function handle(iterable $members, string $type, string $title, string $body, array $data = [], ?CarbonImmutable $createdAt = null): void
    {
        $now = CarbonImmutable::now();
        $createdAt ??= $now;
        $members = collect($members)->filter(fn (FamilyMember $member) => $member->status === FamilyMember::STATUS_ACTIVE)->unique('id');
        $preferences = $this->preferences->forMembers($members);

        /** @var array<int, list<int>> $bySendTime unix timestamp (0 = now) => member ids */
        $bySendTime = [];

        foreach ($members as $member) {
            $prefs = $preferences[$member->id];

            if (! ($prefs['types'][$type] ?? false)) {
                continue;
            }

            $quietEnd = self::quietHoursEnd($prefs['quiet_from'], $prefs['quiet_to'], $now->setTimezone($member->family->timezone));
            $bySendTime[$quietEnd?->getTimestamp() ?? 0][] = $member->id;
        }

        foreach ($bySendTime as $sendAt => $memberIds) {
            $job = new DeliverPushNotification($memberIds, $title, $body, ['type' => $type, ...$data], $createdAt);

            dispatch($sendAt === 0 ? $job : $job->delay(CarbonImmutable::createFromTimestamp($sendAt)));
        }
    }

    /**
     * When the quiet hours around `$localNow` end, or null when it is not quiet hours. Windows may
     * span midnight (`21:30`–`07:00`).
     */
    public static function quietHoursEnd(?string $from, ?string $to, CarbonImmutable $localNow): ?CarbonImmutable
    {
        if ($from === null || $to === null || $from === $to) {
            return null;
        }

        $time = $localNow->format('H:i');
        $quiet = $from < $to ? ($time >= $from && $time < $to) : ($time >= $from || $time < $to);

        if (! $quiet) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $to));
        $end = $localNow->setTime($hour, $minute);

        return $end->lte($localNow) ? $end->addDay() : $end;
    }
}
