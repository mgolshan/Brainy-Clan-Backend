<?php

namespace App\Modules\Notifications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member's effective notification preferences (times HH:MM in the family timezone).
 *
 * @property array{types: array<string, bool>, quiet_from: string|null, quiet_to: string|null, reminder_time: string} $resource
 */
class NotificationPreferencesResource extends JsonResource
{
    /**
     * @return array{types: array{goal_reached: bool, period_closed: bool, family_activity: bool, daily_reminder: bool, family_chat: bool, family_chat_report: bool}, quiet_from: string|null, quiet_to: string|null, reminder_time: string}
     */
    public function toArray(Request $request): array
    {
        $types = $this->resource['types'];

        return [
            'types' => [
                'goal_reached' => (bool) ($types['goal_reached'] ?? true),
                'period_closed' => (bool) ($types['period_closed'] ?? true),
                'family_activity' => (bool) ($types['family_activity'] ?? true),
                'daily_reminder' => (bool) ($types['daily_reminder'] ?? false),
                'family_chat' => (bool) ($types['family_chat'] ?? true),
                'family_chat_report' => (bool) ($types['family_chat_report'] ?? true),
            ],
            'quiet_from' => $this->resource['quiet_from'],
            'quiet_to' => $this->resource['quiet_to'],
            'reminder_time' => $this->resource['reminder_time'],
        ];
    }
}
