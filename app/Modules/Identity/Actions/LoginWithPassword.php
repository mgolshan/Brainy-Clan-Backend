<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Identity\Lockout\LoginLockout;
use App\Modules\Identity\Mail\SignInAttemptsNotice;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * `POST /auth/login`. Checks the sign-in lockout first (429 `login_locked`, credentials not
 * checked), then the credentials; a failure counts towards the locks (docs/API.md Auth →
 * "Lockout"). A failure that starts a lock answers `login_locked` right away. Unknown emails take
 * as long as wrong passwords (a dummy hash is checked) and are counted the same way.
 */
class LoginWithPassword
{
    private static ?string $dummyHash = null;

    public function __construct(
        private readonly IssueDeviceToken $issueToken,
        private readonly LoginLockout $lockout,
    ) {}

    /**
     * @return array{user: User, token: string}
     */
    public function handle(string $email, string $password, string $deviceName, string $platform, ?string $appVersion = null, ?string $ipAddress = null): array
    {
        $this->lockout->ensureLoginAllowed($email, $ipAddress);

        $user = User::query()->where('email', strtolower($email))->first();

        if ($user === null || $user->password === null) {
            Hash::check($password, self::dummyHash());
            $this->fail($email, $ipAddress, $user);
        }

        if (! Hash::check($password, $user->password)) {
            $this->fail($email, $ipAddress, $user);
        }

        $this->lockout->recordLoginSuccess($email, $ipAddress);

        return ['user' => $user, 'token' => $this->issueToken->handle($user, $deviceName, $platform, $appVersion)];
    }

    private function fail(string $email, ?string $ipAddress, ?User $user): never
    {
        $result = $this->lockout->recordLoginFailure($email, $ipAddress);

        if ($result['email_locked'] && $user?->email !== null && $this->lockout->claimOwnerNotice($email)) {
            $minutes = (int) ceil((int) config('auth.lockout.email.duration') / 60);
            Mail::to($user->email)->queue(new SignInAttemptsNotice($user->locale === 'fr' ? 'fr' : 'en', $minutes));
        }

        if ($result['retry_after'] > 0) {
            throw ApiException::loginLocked($result['retry_after']);
        }

        throw ApiException::invalidCredentials();
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make('brainy-clan-timing-equalizer');
    }
}
