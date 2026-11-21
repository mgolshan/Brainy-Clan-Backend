<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\Family;
use App\Modules\Policy\PolicyResolver;

/**
 * `PATCH /family` (admin): name, timezone, the chat's profanity filter (every plan) and the chat
 * mode (docs/API.md "Chat safety"). Changing the chat mode needs `policy.chat.modes_allowed`
 * (Classroom), else 403 `plan_required`; sending `normal` without it is accepted as a no-op (it is
 * already the effective mode) and keeps the stored mode for a later upgrade.
 */
class UpdateFamily
{
    public function __construct(private readonly PolicyResolver $policy) {}

    /**
     * @param  array{name?: string, timezone?: string, chat_filter?: bool, chat_mode?: string}  $data
     */
    public function handle(Family $family, array $data): Family
    {
        $changes = array_intersect_key($data, array_flip(['name', 'timezone', 'chat_filter']));

        if (array_key_exists('chat_mode', $data)) {
            if ($this->policy->chatModesAllowed($family->tier)) {
                $changes['chat_mode'] = $data['chat_mode'];
            } elseif ($data['chat_mode'] !== Family::CHAT_MODE_NORMAL) {
                throw ApiException::planRequired('Chat modes need the Classroom plan.');
            }
        }

        $family->fill($changes)->save();

        return $family;
    }
}
