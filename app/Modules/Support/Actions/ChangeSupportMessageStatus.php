<?php

namespace App\Modules\Support\Actions;

use App\Modules\Support\Models\SupportMessage;
use InvalidArgumentException;

/**
 * Status changes from the Filament "Support inbox": `open` (re-open, or a `new` message that a
 * staff member viewed — {@see self::markSeen()}) and `closed` (`closed_at`; a closed message is
 * deleted 1 year later by `support:prune`). Every change moves `last_activity_at`.
 */
class ChangeSupportMessageStatus
{
    public function handle(SupportMessage $message, string $status): SupportMessage
    {
        if (! in_array($status, [SupportMessage::STATUS_OPEN, SupportMessage::STATUS_CLOSED], true)) {
            throw new InvalidArgumentException("Unsupported status {$status}.");
        }

        $message->forceFill([
            'status' => $status,
            'closed_at' => $status === SupportMessage::STATUS_CLOSED ? now() : null,
            'last_activity_at' => now(),
        ])->save();

        return $message;
    }

    /**
     * Viewing a `new` message in the inbox marks it `open` (no longer counted as unread).
     * Returns whether it changed.
     */
    public function markSeen(SupportMessage $message): bool
    {
        if ($message->status !== SupportMessage::STATUS_NEW) {
            return false;
        }

        $message->forceFill(['status' => SupportMessage::STATUS_OPEN, 'last_activity_at' => now()])->save();

        return true;
    }
}
