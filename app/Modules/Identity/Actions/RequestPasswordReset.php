<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Core\Support\Website;
use App\Modules\Identity\Lockout\LoginLockout;
use App\Modules\Identity\Mail\PasswordResetLink;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * `POST /auth/password/forgot`: when a user has this email, stores a one-time token (SHA-256 in
 * `password_reset_tokens`, one row per email so a newer link replaces older ones) and emails the
 * link to `/password/reset` (queued). The caller answers the same either way. Over 3 requests
 * per hour per email or 10 per IP the request is dropped silently (logged without the email).
 */
class RequestPasswordReset
{
    public function handle(string $email, ?string $locale = null, ?string $ipAddress = null): void
    {
        $email = LoginLockout::normalizeEmail($email);

        if (! $this->withinLimits($email, $ipAddress)) {
            Log::info('Password reset request dropped (rate limit)', ['ip_hash' => self::hash($ipAddress ?? 'unknown')]);

            return;
        }

        $user = User::query()->where('email', $email)->whereNull('erased_at')->first();

        if ($user === null || $user->email === null) {
            return;
        }

        $token = Str::random(64);

        DB::table(self::table())->upsert(
            [['email' => $user->email, 'token' => self::hashToken($token), 'created_at' => now()]],
            ['email'],
            ['token', 'created_at'],
        );

        $mailLocale = in_array($locale, User::LOCALES, true) ? $locale : (in_array($user->locale, User::LOCALES, true) ? $user->locale : 'en');
        $url = Website::url(route('password.reset', ['token' => $token, ...($mailLocale === 'en' ? [] : ['lang' => $mailLocale])], false));

        Mail::to($user->email)->queue(new PasswordResetLink($url, $mailLocale, self::expireMinutes()));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function table(): string
    {
        return (string) config('auth.passwords.users.table', 'password_reset_tokens');
    }

    public static function expireMinutes(): int
    {
        return (int) config('auth.passwords.users.expire', 60);
    }

    private function withinLimits(string $email, ?string $ipAddress): bool
    {
        $decay = (int) config('auth.password_forgot.decay', 3600);
        $emailKey = 'password-forgot-email|'.self::hash($email);
        $ipKey = 'password-forgot-ip|'.self::hash($ipAddress ?? 'unknown');

        if (RateLimiter::tooManyAttempts($emailKey, (int) config('auth.password_forgot.per_email', 3))
            || RateLimiter::tooManyAttempts($ipKey, (int) config('auth.password_forgot.per_ip', 10))) {
            return false;
        }

        RateLimiter::hit($emailKey, $decay);
        RateLimiter::hit($ipKey, $decay);

        return true;
    }

    private static function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
