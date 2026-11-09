<?php

namespace App\Modules\Support\Actions;

use App\Modules\Support\Mail\SupportMessageReceived;
use App\Modules\Support\Models\SupportMessage;
use App\Modules\Support\Support\SupportText;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * The website contact form on `/support` (docs/PLAN.md "Support channel without a public mailto"):
 * stores a `web` message (name, email, topic, text, page language) and mails it (queued) to the
 * support inbox with Reply-To = the sender. No mail ever goes to the sender (it would let anyone
 * send our mail to any address). Bot checks (honeypot, signed time token) happen before, in the
 * controller; this action only runs for submissions that passed them and the validation.
 *
 * Limit: {@see self::PER_HOUR} stored messages per hour per IP address ({@see self::isRateLimited()}).
 */
class SubmitWebsiteSupportMessage
{
    public const int PER_HOUR = 5;

    public function isRateLimited(string $ip): bool
    {
        return RateLimiter::tooManyAttempts(self::key($ip), self::PER_HOUR);
    }

    /**
     * @param  array{name: string, email: string, topic: string, message: string}  $input
     */
    public function handle(array $input, string $locale, string $ip): SupportMessage
    {
        $name = SupportText::singleLine($input['name']);
        $text = SupportText::normalize($input['message']);

        if ($name === null || $name === '' || $text === null || ! SupportText::isValidLength($text)) {
            throw new InvalidArgumentException('The form input must be validated first.');
        }

        $message = SupportMessage::query()->create([
            'source' => SupportMessage::SOURCE_WEB,
            'name' => mb_substr($name, 0, 100),
            'email' => trim($input['email']),
            'locale' => $locale,
            'topic' => $input['topic'],
            'message' => $text,
            'status' => SupportMessage::STATUS_NEW,
            'last_activity_at' => now(),
        ]);

        RateLimiter::hit(self::key($ip), 3600);

        Mail::to((string) config('legal.support_email'))->queue(new SupportMessageReceived($message));

        return $message;
    }

    private static function key(string $ip): string
    {
        return 'support-web|'.$ip;
    }
}
