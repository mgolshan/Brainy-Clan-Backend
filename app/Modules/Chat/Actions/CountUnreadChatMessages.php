<?php

namespace App\Modules\Chat\Actions;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Policy\PolicyResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Unread family chat messages for a member: messages after their read marker, written by someone
 * else, not deleted and inside the family's retention window. One count query on (family_id, id).
 */
class CountUnreadChatMessages
{
    public function __construct(private readonly PolicyResolver $policy) {}

    public function handle(FamilyMember $member): int
    {
        return ChatMessage::query()
            ->where('family_id', $member->family_id)
            ->where('id', '>', $member->chat_last_read_id ?? 0)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $this->retentionCutoff($member))
            ->where(fn (Builder $query) => $query->whereNull('family_member_id')->orWhere('family_member_id', '!=', $member->id))
            ->count();
    }

    /**
     * Messages older than this are past retention (the hourly job deletes them; until then they are hidden).
     */
    public function retentionCutoff(FamilyMember $member): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays($this->policy->chatRetentionDays($member->family->tier));
    }
}
