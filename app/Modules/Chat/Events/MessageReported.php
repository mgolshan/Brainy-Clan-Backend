<?php

namespace App\Modules\Chat\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A member reported a family chat message (first report by that member only). Ids only — never
 * the text (queued listeners are serialized into Redis/Horizon and failed-job rows).
 */
class MessageReported
{
    use Dispatchable;

    public function __construct(
        public readonly int $messageId,
        public readonly int $familyId,
        public readonly int $reporterMemberId,
    ) {}
}
