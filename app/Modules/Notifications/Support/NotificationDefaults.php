<?php

namespace App\Modules\Notifications\Support;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Policy\PolicyResolver;

/**
 * Default notification preferences for a member without a stored row (docs/API.md, B8).
 *
 * Quiet hours: children (PolicyResolver `is_child` age band) 19:30–07:30, everyone else
 * 21:30–07:00. All types on except `daily_reminder`; reminder at 17:00. Times are in the
 * family timezone.
 */
class NotificationDefaults
{
    public const array TYPES = ['goal_reached', 'period_closed', 'family_activity', 'daily_reminder'];

    public const array TYPE_DEFAULTS = [
        'goal_reached' => true,
        'period_closed' => true,
        'family_activity' => true,
        'daily_reminder' => false,
    ];

    public const array CHILD_QUIET_HOURS = ['19:30', '07:30'];

    public const array ADULT_QUIET_HOURS = ['21:30', '07:00'];

    public const string REMINDER_TIME = '17:00';

    public function __construct(private readonly PolicyResolver $policy) {}

    /**
     * @return array{types: array<string, bool>, quiet_from: string|null, quiet_to: string|null, reminder_time: string}
     */
    public function for(FamilyMember $member): array
    {
        [$from, $to] = $this->policy->isChild($member) ? self::CHILD_QUIET_HOURS : self::ADULT_QUIET_HOURS;

        return [
            'types' => self::TYPE_DEFAULTS,
            'quiet_from' => $from,
            'quiet_to' => $to,
            'reminder_time' => self::REMINDER_TIME,
        ];
    }
}
