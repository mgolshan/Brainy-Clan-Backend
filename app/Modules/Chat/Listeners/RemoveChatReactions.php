<?php

namespace App\Modules\Chat\Listeners;

use App\Modules\Chat\Actions\RemoveMemberChatReactions;
use App\Modules\Family\Events\MemberRemoved;

/**
 * Synchronous (inside the removal transaction): a member who left keeps no reactions.
 */
class RemoveChatReactions
{
    public function __construct(private readonly RemoveMemberChatReactions $remove) {}

    public function handle(MemberRemoved $event): void
    {
        $this->remove->handle([$event->memberId]);
    }
}
