<?php

namespace App\Modules\Support\Support;

/**
 * The website contact form's signed time token (docs/PLAN.md "Support channel without a public
 * mailto"): the page embeds `<issued unix time>.<HMAC-SHA256(APP_KEY)>` in a hidden field. No
 * cookie or session is needed. A submission is accepted only when the signature is valid and the
 * form was shown at least {@see self::MIN_SECONDS} seconds and at most {@see self::MAX_SECONDS}
 * seconds before (bots post instantly; a token cannot be forged or reused forever).
 */
final class FormToken
{
    public const int MIN_SECONDS = 3;

    public const int MAX_SECONDS = 7200;

    public const string VALID = 'valid';

    /** Missing, malformed or wrong signature (a bot or a tampered form). */
    public const string INVALID = 'invalid';

    /** Posted less than 3 seconds after the page was shown. */
    public const string TOO_FAST = 'too_fast';

    /** Older than 2 hours (a person who left the page open). */
    public const string EXPIRED = 'expired';

    public static function issue(?int $now = null): string
    {
        $issuedAt = (string) ($now ?? time());

        return $issuedAt.'.'.self::sign($issuedAt);
    }

    /**
     * @return self::VALID|self::INVALID|self::TOO_FAST|self::EXPIRED
     */
    public static function check(mixed $token, ?int $now = null): string
    {
        if (! is_string($token) || preg_match('/^(\d{1,12})\.([a-f0-9]{64})$/', $token, $parts) !== 1) {
            return self::INVALID;
        }

        if (! hash_equals(self::sign($parts[1]), $parts[2])) {
            return self::INVALID;
        }

        $age = ($now ?? time()) - (int) $parts[1];

        return match (true) {
            $age < self::MIN_SECONDS => self::TOO_FAST,
            $age > self::MAX_SECONDS => self::EXPIRED,
            default => self::VALID,
        };
    }

    private static function sign(string $issuedAt): string
    {
        return hash_hmac('sha256', 'support-form|'.$issuedAt, (string) config('app.key'));
    }
}
