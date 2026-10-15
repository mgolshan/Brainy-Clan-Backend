<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Notifications\Models\NotificationPreference;

/**
 * Updates a member's notification preferences (the member themself, or an admin of the same
 * family). Missing fields keep their current (or default) value.
 */
class UpdateNotificationPreferences
{
    public function __construct(private readonly GetNotificationPreferences $get) {}

    /**
     * @param  array{types?: array<string, bool>, quiet_from?: string|null, quiet_to?: string|null, reminder_time?: string}  $data
     * @return array{types: array<string, bool>, quiet_from: string|null, quiet_to: string|null, reminder_time: string}
     */
    public function handle(FamilyMember $actor, FamilyMember $member, array $data): array
    {
        self::authorize($actor, $member);

        $current = $this->get->handle($member);
        $next = [
            'types' => array_merge($current['types'], array_map(boolval(...), $data['types'] ?? [])),
            'quiet_from' => array_key_exists('quiet_from', $data) ? $data['quiet_from'] : $current['quiet_from'],
            'quiet_to' => array_key_exists('quiet_to', $data) ? $data['quiet_to'] : $current['quiet_to'],
            'reminder_time' => $data['reminder_time'] ?? $current['reminder_time'],
        ];

        if (($next['quiet_from'] === null) !== ($next['quiet_to'] === null)) {
            throw ApiException::validation('quiet_from', 'Set both quiet_from and quiet_to, or clear both.');
        }

        NotificationPreference::query()->updateOrCreate(['family_member_id' => $member->id], $next);

        return $next;
    }

    /**
     * Admins of the member's family, or the member themself.
     */
    public static function authorize(FamilyMember $actor, FamilyMember $member): void
    {
        $allowed = $actor->family_id === $member->family_id && ($actor->isAdmin() || $actor->id === $member->id);

        if (! $allowed) {
            throw ApiException::forbidden('Only family admins can manage the notification preferences of another member.');
        }
    }
}
