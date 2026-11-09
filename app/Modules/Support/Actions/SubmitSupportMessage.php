<?php

namespace App\Modules\Support\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use App\Modules\Support\Mail\SupportMessageReceived;
use App\Modules\Support\Models\SupportMessage;
use App\Modules\Support\Support\SupportText;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * `POST /support/messages` (docs/API.md "Support messages"): a family admin writes to the support
 * team from the app. The text is normalized by {@see SupportText} (again here, so every caller gets
 * the same rules) and must be 10–5000 code points afterwards (422).
 *
 * `client_id` makes retries idempotent: an existing message of the same user with that id is
 * returned (`created` false → 200) — also when two retries race (unique index) — and does not
 * count against the limits. Limits: {@see self::PER_HOUR} new messages per hour and
 * {@see self::PER_DAY} per day per user (429 `rate_limited` with `Retry-After`).
 *
 * A new message is mailed (queued) to the support inbox with Reply-To = the admin's email.
 */
class SubmitSupportMessage
{
    public const int PER_HOUR = 5;

    public const int PER_DAY = 20;

    /**
     * @param  array{topic: string, message: string, client_id: string, app_version?: string|null, platform?: string|null, device?: string|null}  $input
     * @return array{message: SupportMessage, created: bool}
     */
    public function handle(User $user, FamilyMember $admin, array $input): array
    {
        $clientId = strtolower($input['client_id']);
        $existing = $this->find($user->id, $clientId);

        if ($existing !== null) {
            return ['message' => $existing, 'created' => false];
        }

        $this->ensureWithinLimits($user->id);

        $text = SupportText::normalize($input['message']);

        if ($text === null || ! SupportText::isValidLength($text)) {
            throw ApiException::validation('message', 'The message must be between '.SupportText::MIN_LENGTH.' and '.SupportText::MAX_LENGTH.' characters.');
        }

        try {
            $message = SupportMessage::query()->create([
                'source' => SupportMessage::SOURCE_APP,
                'user_id' => $user->id,
                'family_id' => $admin->family_id,
                'name' => mb_substr((string) SupportText::singleLine($user->display_name), 0, 100),
                'email' => $user->email,
                'locale' => $user->locale,
                'topic' => $input['topic'],
                'message' => $text,
                'status' => SupportMessage::STATUS_NEW,
                'app_version' => $this->optional($input['app_version'] ?? null),
                'platform' => $input['platform'] ?? null,
                'device' => $this->optional($input['device'] ?? null),
                'client_id' => $clientId,
                'last_activity_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = $this->find($user->id, $clientId);

            if ($existing === null) {
                throw ApiException::validation('client_id', 'This client_id could not be used.');
            }

            return ['message' => $existing, 'created' => false];
        }

        RateLimiter::hit(self::hourKey($user->id), 3600);
        RateLimiter::hit(self::dayKey($user->id), 86400);

        Mail::to((string) config('legal.support_email'))->queue(new SupportMessageReceived($message));

        return ['message' => $message, 'created' => true];
    }

    public static function hourKey(int $userId): string
    {
        return 'support-message-hour|'.$userId;
    }

    public static function dayKey(int $userId): string
    {
        return 'support-message-day|'.$userId;
    }

    private function ensureWithinLimits(int $userId): void
    {
        $retryAfter = null;

        if (RateLimiter::tooManyAttempts(self::hourKey($userId), self::PER_HOUR)) {
            $retryAfter = RateLimiter::availableIn(self::hourKey($userId));
        }

        if (RateLimiter::tooManyAttempts(self::dayKey($userId), self::PER_DAY)) {
            $retryAfter = max($retryAfter ?? 0, RateLimiter::availableIn(self::dayKey($userId)));
        }

        if ($retryAfter !== null) {
            $retryAfter = max(1, $retryAfter);

            throw new ApiException(
                'rate_limited',
                'You have sent several messages to support recently. Please wait for our answer or try again later.',
                429,
                extra: ['retry_after' => $retryAfter],
                headers: ['Retry-After' => (string) $retryAfter],
            );
        }
    }

    private function find(int $userId, string $clientId): ?SupportMessage
    {
        return SupportMessage::query()
            ->where('user_id', $userId)
            ->where('client_id', $clientId)
            ->first();
    }

    private function optional(?string $value): ?string
    {
        $value = $value !== null ? SupportText::singleLine($value) : null;

        return $value !== null && $value !== '' ? $value : null;
    }
}
