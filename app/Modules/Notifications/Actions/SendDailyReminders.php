<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Play\Models\PlaySession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * `daily_reminder` (docs/API.md B8), run every 15 minutes: members whose `reminder_time` falls in the
 * current 15-minute window of their family's local time, who have the type on and have not started
 * a session today (family timezone). Members are processed in chunks with a constant number of
 * queries per chunk (members, preferences, today's sessions); sending goes through
 * {@see SendMemberNotification} (quiet hours). A member is reminded at most once per local day.
 */
class SendDailyReminders
{
    public const int WINDOW_MINUTES = 15;

    public const int CHUNK = 500;

    public function __construct(
        private readonly GetNotificationPreferences $preferences,
        private readonly SendMemberNotification $send,
    ) {}

    /**
     * @return int how many members were reminded
     */
    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $reminded = 0;

        // `daily_reminder` is off by default (NotificationDefaults), so only members who saved
        // preferences can have it on.
        FamilyMember::query()
            ->with(['user', 'family'])
            ->where('status', FamilyMember::STATUS_ACTIVE)
            ->whereIn('id', NotificationPreference::query()->select('family_member_id'))
            ->chunkById(self::CHUNK, function (Collection $members) use ($now, &$reminded): void {
                /** @var Collection<int, FamilyMember> $members */
                $due = $this->dueMembers($members, $now);

                if ($due->isEmpty()) {
                    return;
                }

                $due = $this->withoutPlayedToday($due, $now)->filter(
                    fn (FamilyMember $member) => Cache::add(self::sentKey($member, $now), true, now()->addDays(2)),
                );

                if ($due->isEmpty()) {
                    return;
                }

                $this->send->handle($due, 'daily_reminder', 'Time for a challenge!', 'A quick round keeps your brain busy. Ready?', [], $now);
                $reminded += $due->count();
            });

        return $reminded;
    }

    /**
     * Members with the type on whose reminder time is in the current local 15-minute window.
     *
     * @param  Collection<int, FamilyMember>  $members
     * @return Collection<int, FamilyMember>
     */
    private function dueMembers(Collection $members, CarbonImmutable $now): Collection
    {
        $preferences = $this->preferences->forMembers($members);

        return $members->filter(function (FamilyMember $member) use ($preferences, $now): bool {
            $prefs = $preferences[$member->id];

            if (! ($prefs['types']['daily_reminder'] ?? false)) {
                return false;
            }

            [$from, $to] = self::window($now->setTimezone($member->family->timezone));

            return $prefs['reminder_time'] >= $from && $prefs['reminder_time'] < $to;
        })->values();
    }

    /**
     * Drops members who started a session since their family's local midnight (one query).
     *
     * @param  Collection<int, FamilyMember>  $members
     * @return Collection<int, FamilyMember>
     */
    private function withoutPlayedToday(Collection $members, CarbonImmutable $now): Collection
    {
        $startOfDay = fn (FamilyMember $member) => $now->setTimezone($member->family->timezone)->startOfDay()->utc();
        $earliest = $members->map($startOfDay)->min();

        $lastStarted = PlaySession::query()
            ->whereIn('member_id', $members->pluck('id'))
            ->where('started_at', '>=', $earliest)
            ->groupBy('member_id')
            ->selectRaw('member_id, max(started_at) as last_started_at')
            ->pluck('last_started_at', 'member_id');

        return $members->reject(function (FamilyMember $member) use ($lastStarted, $startOfDay): bool {
            $last = $lastStarted->get($member->id);

            return $last !== null && CarbonImmutable::parse((string) $last, 'UTC')->gte($startOfDay($member));
        })->values();
    }

    /**
     * The 15-minute window around a local time as `HH:MM` bounds, e.g. 17:07 → [17:00, 17:15).
     * The last window of the day ends at `24:00`.
     *
     * @return array{0: string, 1: string}
     */
    public static function window(CarbonImmutable $local): array
    {
        $start = intdiv($local->hour * 60 + $local->minute, self::WINDOW_MINUTES) * self::WINDOW_MINUTES;
        $end = $start + self::WINDOW_MINUTES;

        return [sprintf('%02d:%02d', intdiv($start, 60), $start % 60), sprintf('%02d:%02d', intdiv($end, 60), $end % 60)];
    }

    private static function sentKey(FamilyMember $member, CarbonImmutable $now): string
    {
        return "notifications:daily_reminder:{$member->id}:".$now->setTimezone($member->family->timezone)->toDateString();
    }
}
