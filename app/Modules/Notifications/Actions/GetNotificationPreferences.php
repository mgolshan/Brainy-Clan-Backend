<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\Support\NotificationDefaults;

/**
 * A member's effective notification preferences: the stored row merged over the defaults.
 */
class GetNotificationPreferences
{
    public function __construct(private readonly NotificationDefaults $defaults) {}

    /**
     * @return array{types: array<string, bool>, quiet_from: string|null, quiet_to: string|null, reminder_time: string}
     */
    public function handle(FamilyMember $member): array
    {
        $member->loadMissing(['user', 'family']);
        $defaults = $this->defaults->for($member);
        $stored = NotificationPreference::query()->where('family_member_id', $member->id)->first();

        return $this->merge($defaults, $stored);
    }

    /**
     * Effective preferences for many members with one query (members need `user` and `family` loaded).
     *
     * @param  iterable<FamilyMember>  $members
     * @return array<int, array{types: array<string, bool>, quiet_from: string|null, quiet_to: string|null, reminder_time: string}>
     */
    public function forMembers(iterable $members): array
    {
        $members = collect($members);
        $stored = NotificationPreference::query()->whereIn('family_member_id', $members->pluck('id'))->get()->keyBy('family_member_id');

        return $members->mapWithKeys(fn (FamilyMember $member) => [
            $member->id => $this->merge($this->defaults->for($member), $stored->get($member->id)),
        ])->all();
    }

    /**
     * @param  array{types: array<string, bool>, quiet_from: string|null, quiet_to: string|null, reminder_time: string}  $defaults
     * @return array{types: array<string, bool>, quiet_from: string|null, quiet_to: string|null, reminder_time: string}
     */
    private function merge(array $defaults, ?NotificationPreference $stored): array
    {
        if ($stored === null) {
            return $defaults;
        }

        return [
            'types' => array_merge($defaults['types'], array_intersect_key($stored->types, $defaults['types'])),
            'quiet_from' => self::hhmm($stored->quiet_from),
            'quiet_to' => self::hhmm($stored->quiet_to),
            'reminder_time' => self::hhmm($stored->reminder_time) ?? $defaults['reminder_time'],
        ];
    }

    private static function hhmm(?string $time): ?string
    {
        return $time !== null ? substr($time, 0, 5) : null;
    }
}
