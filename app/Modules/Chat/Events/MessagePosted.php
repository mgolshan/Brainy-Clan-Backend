<?php

namespace App\Modules\Chat\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A new family chat message was posted. Carries ids only — never the text, because queued
 * listeners are serialized into Redis/Horizon (visible to staff) and failed-job rows.
 */
class MessagePosted
{
    use Dispatchable;

    public function __construct(
        public readonly int $messageId,
        public readonly int $familyId,
        public readonly int $memberId,
    ) {}
}
