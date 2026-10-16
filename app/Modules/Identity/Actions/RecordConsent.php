<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Consent;
use App\Modules\Identity\Models\User;

/**
 * Appends one consent row. The IP is stored only as an HMAC (keyed with the app key), enough to
 * show that two consents came from the same network without keeping the address itself.
 */
class RecordConsent
{
    public function handle(
        User $user,
        string $type,
        string $version,
        ?string $ipAddress = null,
        ?int $subjectInviteId = null,
        ?int $subjectMemberId = null,
    ): Consent {
        return Consent::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'version' => $version,
            'subject_invite_id' => $subjectInviteId,
            'subject_member_id' => $subjectMemberId,
            'accepted_at' => now(),
            'ip_hash' => self::hashIp($ipAddress),
        ]);
    }

    public static function hashIp(?string $ipAddress): ?string
    {
        if ($ipAddress === null || $ipAddress === '') {
            return null;
        }

        return hash_hmac('sha256', $ipAddress, (string) config('app.key'));
    }
}
