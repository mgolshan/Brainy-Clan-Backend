<?php

namespace App\Modules\Support\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Support\Models\SupportMessage;
use Illuminate\Database\Eloquent\Collection;

/**
 * `GET /support/messages`: the caller's own app messages, newest first, at most
 * {@see self::LIMIT}. One query.
 */
class ListSupportMessages
{
    public const int LIMIT = 50;

    /**
     * @return Collection<int, SupportMessage>
     */
    public function handle(User $user): Collection
    {
        return SupportMessage::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();
    }
}
