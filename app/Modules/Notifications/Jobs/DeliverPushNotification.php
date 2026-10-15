<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\Device;
use App\Modules\Notifications\Actions\SendMemberNotification;
use App\Modules\Notifications\Contracts\ExpoPushSender;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pushes one notification to every device of the given members (preferences and quiet hours were
 * already checked by {@see SendMemberNotification}). Dropped when
 * older than {@see self::MAX_AGE_HOURS}. Tokens Expo reports as not registered are cleared.
 */
class DeliverPushNotification implements ShouldQueue
{
    use Queueable;

    public const int MAX_AGE_HOURS = 12;

    /**
     * @param  list<int>  $memberIds
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly array $memberIds,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data,
        public readonly CarbonImmutable $createdAt,
    ) {}

    public function handle(ExpoPushSender $sender): void
    {
        if ($this->createdAt->lt(CarbonImmutable::now()->subHours(self::MAX_AGE_HOURS)) || $this->memberIds === []) {
            return;
        }

        $tokens = Device::query()
            ->whereIn('user_id', FamilyMember::query()->select('user_id')->whereIn('id', $this->memberIds))
            ->whereNotNull('push_token')
            ->distinct()
            ->pluck('push_token')
            ->all();

        if ($tokens === []) {
            return;
        }

        $invalid = $sender->send(array_map(fn (string $token) => [
            'to' => $token,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
        ], array_values($tokens)));

        if ($invalid !== []) {
            Device::query()->whereIn('push_token', $invalid)->update(['push_token' => null]);
        }
    }
}
