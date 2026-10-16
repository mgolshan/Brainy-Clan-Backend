<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\Device;
use App\Modules\Notifications\Actions\SendMemberNotification;
use App\Modules\Notifications\Contracts\ExpoPushSender;
use App\Modules\Notifications\Support\PushSendResult;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Pushes one notification to every device of the given members (preferences and quiet hours were
 * already checked by {@see SendMemberNotification}). Dropped when
 * older than {@see self::MAX_AGE_HOURS}. Tokens Expo reports as not registered are cleared.
 *
 * When Expo is temporarily unavailable (see {@see PushSendResult::$retryTokens})
 * a follow-up job is queued for just those tokens after {@see self::RETRY_DELAYS_SECONDS}; tokens that were
 * delivered are never sent twice. A retry only reaches devices of the same members that still hold the token
 * (signed out or moved tokens are skipped).
 */
class DeliverPushNotification implements ShouldQueue
{
    use Queueable;

    public const int MAX_AGE_HOURS = 12;

    /** Delay before attempt 2, 3, … (so at most three attempts in total). */
    public const array RETRY_DELAYS_SECONDS = [60, 300];

    /**
     * @param  list<int>  $memberIds
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $tokens  only these tokens (a retry); null = every device of the members
     */
    public function __construct(
        public readonly array $memberIds,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data,
        public readonly CarbonImmutable $createdAt,
        public readonly ?array $tokens = null,
        public readonly int $attempt = 1,
    ) {}

    public function handle(ExpoPushSender $sender): void
    {
        if ($this->createdAt->lt(CarbonImmutable::now()->subHours(self::MAX_AGE_HOURS)) || $this->memberIds === []
            || $this->tokens === []) {
            return;
        }

        $tokens = Device::query()
            ->whereIn('user_id', FamilyMember::query()->select('user_id')->whereIn('id', $this->memberIds))
            ->whereNotNull('push_token')
            ->when($this->tokens !== null, fn ($query) => $query->whereIn('push_token', $this->tokens))
            ->distinct()
            ->pluck('push_token')
            ->all();

        if ($tokens === []) {
            return;
        }

        $result = $sender->send(array_map(fn (string $token) => [
            'to' => $token,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
        ], array_values($tokens)));

        if ($result->invalidTokens !== []) {
            Device::query()->whereIn('push_token', $result->invalidTokens)->update(['push_token' => null]);
        }

        if ($result->retryTokens === []) {
            return;
        }

        $delay = self::RETRY_DELAYS_SECONDS[$this->attempt - 1] ?? null;

        if ($delay === null) {
            Log::warning('Push notification dropped after retries', [
                'type' => $this->data['type'] ?? null,
                'devices' => count($result->retryTokens),
                'attempts' => $this->attempt,
            ]);

            return;
        }

        dispatch((new self(
            $this->memberIds, $this->title, $this->body, $this->data, $this->createdAt,
            $result->retryTokens, $this->attempt + 1,
        ))->delay(CarbonImmutable::now()->addSeconds($delay)));
    }
}
