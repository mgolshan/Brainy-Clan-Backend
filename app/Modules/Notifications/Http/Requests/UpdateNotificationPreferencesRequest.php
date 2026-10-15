<?php

namespace App\Modules\Notifications\Http\Requests;

use App\Modules\Notifications\Support\NotificationDefaults;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'types' => ['sometimes', 'array:'.implode(',', NotificationDefaults::TYPES)],
            /** Start of quiet hours (HH:MM, family timezone); null together with `quiet_to` turns quiet hours off. */
            'quiet_from' => ['sometimes', 'nullable', 'date_format:H:i'],
            /** End of quiet hours (HH:MM, family timezone). */
            'quiet_to' => ['sometimes', 'nullable', 'date_format:H:i'],
            /** Daily reminder time (HH:MM, family timezone). */
            'reminder_time' => ['sometimes', 'date_format:H:i'],
        ];

        foreach (NotificationDefaults::TYPES as $type) {
            $rules["types.{$type}"] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}
