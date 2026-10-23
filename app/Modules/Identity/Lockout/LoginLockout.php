<?php

namespace App\Modules\Identity\Lockout;

use App\Modules\Core\Exceptions\ApiException;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Log;

/**
 * Progressive sign-in lockout (docs/API.md Auth → "Lockout"), on top of the `auth` rate limits.
 *
 * - `POST /auth/login` per email + IP: every `attempts` failures start a lock that escalates
 *   (5 min, 1 h, then 24 h); a successful sign-in resets it, and so do `reset_after` seconds
 *   without a failure (counted from the end of the last lock).
 * - `POST /auth/login` per email from all IPs: `attempts` failures within `window` → `duration`.
 * - Per IP across login, sign-in codes and invite codes: `attempts` failures within `window` →
 *   that IP may not use any of the three for `duration`.
 * - `POST /auth/code` / `POST /auth/join` per IP: escalation like email + IP.
 *
 * State lives in the cache store (Redis) as atomic counters plus "locked until" timestamps. Keys
 * are HMAC-SHA256 hashes of the normalized email / IP, never the raw values. Locks of an email
 * are cleared by a password reset or `auth:unlock` (email + IP pairs through a per-email epoch).
 */
class LoginLockout
{
    private const string PREFIX = 'login-lockout:';

    public function __construct(private readonly CacheFactory $cache) {}

    /**
     * 429 `login_locked` when the email + IP pair, the email or the IP is locked. Checked before
     * the credentials, so the answer is the same whether or not the email has an account.
     */
    public function ensureLoginAllowed(string $email, ?string $ipAddress): void
    {
        $retryAfter = max(
            $this->remaining($this->pairKey($email, $ipAddress)),
            $this->remaining($this->emailKey($email)),
            $this->remaining($this->ipKey($ipAddress)),
        );

        if ($retryAfter > 0) {
            throw ApiException::loginLocked($retryAfter);
        }
    }

    /**
     * 429 `login_locked` when this IP may not redeem sign-in or invite codes right now.
     */
    public function ensureCodeAllowed(?string $ipAddress): void
    {
        $retryAfter = max(
            $this->remaining($this->codeKey($ipAddress)),
            $this->remaining($this->ipKey($ipAddress)),
        );

        if ($retryAfter > 0) {
            throw ApiException::loginLocked($retryAfter);
        }
    }

    /**
     * A wrong email or password.
     *
     * @return array{retry_after: int, email_locked: bool} the longest lock this failure started
     *                                                     (0: none) and whether the per-email lock started
     */
    public function recordLoginFailure(string $email, ?string $ipAddress): array
    {
        $config = $this->config();

        $pair = $this->escalate($this->pairKey($email, $ipAddress), $config['email_ip']);
        $emailLock = $this->countInWindow($this->emailKey($email), $config['email']);
        $ipLock = $this->countInWindow($this->ipKey($ipAddress), $config['ip']);

        $this->logLocks(['email_ip' => $pair, 'email' => $emailLock, 'ip' => $ipLock]);

        return ['retry_after' => max($pair, $emailLock, $ipLock), 'email_locked' => $emailLock > 0];
    }

    /**
     * An unknown, used or expired sign-in / invite code.
     *
     * @return int the longest lock this failure started (0: none)
     */
    public function recordCodeFailure(?string $ipAddress): int
    {
        $config = $this->config();

        $code = $this->escalate($this->codeKey($ipAddress), $config['codes']);
        $ipLock = $this->countInWindow($this->ipKey($ipAddress), $config['ip']);

        $this->logLocks(['codes' => $code, 'ip' => $ipLock]);

        return max($code, $ipLock);
    }

    /**
     * A successful password sign-in resets the email + IP count and its escalation.
     */
    public function recordLoginSuccess(string $email, ?string $ipAddress): void
    {
        $this->forget($this->pairKey($email, $ipAddress));
    }

    /**
     * A successful code sign-in (or invite) resets the IP's code count and its escalation.
     */
    public function recordCodeSuccess(?string $ipAddress): void
    {
        $this->forget($this->codeKey($ipAddress));
    }

    /**
     * At most one "someone tried to sign in" email per `notify_every` seconds for this email.
     */
    public function claimOwnerNotice(string $email): bool
    {
        return $this->store()->add($this->emailKey($email).':notified', 1, (int) $this->config()['email']['notify_every']);
    }

    /**
     * Clears every lock of this email: the per-email lock and all its email + IP pairs (a new
     * epoch makes the old pair keys unreachable; they expire on their own).
     */
    public function clearEmail(string $email): void
    {
        $this->store()->increment($this->epochKey($email));
        $this->forget($this->emailKey($email));
    }

    /**
     * Clears the per-IP lock and the IP's code lock.
     */
    public function clearIp(string $ipAddress): void
    {
        $this->forget($this->ipKey($ipAddress));
        $this->forget($this->codeKey($ipAddress));
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Counts one failure in an escalating series; every `attempts`-th failure starts the next
     * lock. The count expires `reset_after` seconds after the last failure (after the end of a
     * lock that just started), which resets the escalation.
     *
     * @param  array{attempts: int, durations: list<int>, reset_after: int}  $config
     * @return int lock seconds started, 0 for none
     */
    private function escalate(string $key, array $config): int
    {
        $store = $this->store();
        $attempts = max(1, (int) $config['attempts']);
        $resetAfter = (int) $config['reset_after'];

        $store->add($key.':n', 0, $resetAfter);
        $failures = (int) $store->increment($key.':n');

        if ($failures % $attempts !== 0) {
            $store->touch($key.':n', $resetAfter);

            return 0;
        }

        $durations = array_values($config['durations']);
        $level = min(intdiv($failures, $attempts), count($durations));
        $duration = (int) $durations[$level - 1];

        $this->lock($key, $duration);
        $store->touch($key.':n', $duration + $resetAfter);

        return $duration;
    }

    /**
     * Counts one failure in a fixed window that starts with the first failure; reaching
     * `attempts` starts a lock and a new window.
     *
     * @param  array{attempts: int, window: int, duration: int}  $config
     * @return int lock seconds started, 0 for none
     */
    private function countInWindow(string $key, array $config): int
    {
        $store = $this->store();
        $window = (int) $config['window'];

        $store->add($key.':n', 0, $window);
        $failures = (int) $store->increment($key.':n');

        if ($failures === 1) {
            // The window may have expired between add() and increment(): never keep a counter forever.
            $store->touch($key.':n', $window);
        }

        if ($failures < (int) $config['attempts']) {
            return 0;
        }

        $store->forget($key.':n');
        $this->lock($key, (int) $config['duration']);

        return (int) $config['duration'];
    }

    private function lock(string $key, int $seconds): void
    {
        $this->store()->put($key.':lock', now()->getTimestamp() + $seconds, $seconds);
    }

    /**
     * Seconds until the lock under this key ends (0: not locked).
     */
    private function remaining(string $key): int
    {
        $until = $this->store()->get($key.':lock');

        return is_numeric($until) ? max(0, (int) $until - now()->getTimestamp()) : 0;
    }

    private function forget(string $key): void
    {
        $this->store()->forget($key.':n');
        $this->store()->forget($key.':lock');
    }

    /**
     * @param  array<string, int>  $locks  scope => seconds started
     */
    private function logLocks(array $locks): void
    {
        foreach ($locks as $scope => $seconds) {
            if ($seconds > 0) {
                Log::notice('Sign-in lockout started', ['scope' => $scope, 'seconds' => $seconds]);
            }
        }
    }

    private function pairKey(string $email, ?string $ipAddress): string
    {
        $epoch = (int) $this->store()->get($this->epochKey($email), 0);

        return self::PREFIX.'email-ip:'.hash('sha256', $this->hash(self::normalizeEmail($email)).'|'.$this->hash($this->ip($ipAddress)).'|'.$epoch);
    }

    private function emailKey(string $email): string
    {
        return self::PREFIX.'email:'.$this->hash(self::normalizeEmail($email));
    }

    private function epochKey(string $email): string
    {
        return $this->emailKey($email).':epoch';
    }

    private function ipKey(?string $ipAddress): string
    {
        return self::PREFIX.'ip:'.$this->hash($this->ip($ipAddress));
    }

    private function codeKey(?string $ipAddress): string
    {
        return self::PREFIX.'codes:'.$this->hash($this->ip($ipAddress));
    }

    private function ip(?string $ipAddress): string
    {
        return $ipAddress !== null && $ipAddress !== '' ? $ipAddress : 'unknown';
    }

    private function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    private function store(): Repository
    {
        $store = config('auth.lockout.store');

        return $this->cache->store(is_string($store) && $store !== '' ? $store : null);
    }

    /**
     * @return array{email_ip: array{attempts: int, durations: list<int>, reset_after: int}, email: array{attempts: int, window: int, duration: int, notify_every: int}, ip: array{attempts: int, window: int, duration: int}, codes: array{attempts: int, durations: list<int>, reset_after: int}}
     */
    private function config(): array
    {
        /** @var array{email_ip: array{attempts: int, durations: list<int>, reset_after: int}, email: array{attempts: int, window: int, duration: int, notify_every: int}, ip: array{attempts: int, window: int, duration: int}, codes: array{attempts: int, durations: list<int>, reset_after: int}} $config */
        $config = config('auth.lockout');

        return $config;
    }
}
