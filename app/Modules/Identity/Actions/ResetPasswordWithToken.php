<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Lockout\LoginLockout;
use App\Modules\Identity\Mail\PasswordChanged;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * The `/password/reset` page: finds the user of a reset link and sets the new password. On
 * success (the token works once): the password is set (also for a social-only adult), every
 * token and device of the user is revoked, all sign-in locks of the email are cleared and a
 * "password changed" email is queued.
 */
class ResetPasswordWithToken
{
    public function __construct(
        private readonly RevokeAllDevices $revokeAll,
        private readonly LoginLockout $lockout,
    ) {}

    /**
     * The user a still valid (unused, not replaced, not expired) link belongs to, or null.
     */
    public function find(string $token): ?User
    {
        if ($token === '') {
            return null;
        }

        $row = DB::table(RequestPasswordReset::table())
            ->where('token', RequestPasswordReset::hashToken($token))
            ->first(['email', 'created_at']);

        if ($row === null || $row->created_at === null || now()->subMinutes(RequestPasswordReset::expireMinutes())->gt($row->created_at)) {
            return null;
        }

        return User::query()->where('email', $row->email)->whereNull('erased_at')->first();
    }

    /**
     * @return User|null the user, or null when the link is not valid (any more)
     */
    public function handle(string $token, string $password): ?User
    {
        $user = $this->find($token);

        if ($user === null || $user->email === null) {
            return null;
        }

        $reset = DB::transaction(function () use ($user, $token, $password): bool {
            // Single use: only the request that deletes the row goes on.
            $deleted = DB::table(RequestPasswordReset::table())
                ->where('email', $user->email)
                ->where('token', RequestPasswordReset::hashToken($token))
                ->delete();

            if ($deleted === 0) {
                return false;
            }

            $user->forceFill(['password' => $password])->save();
            $this->revokeAll->handle($user);

            return true;
        });

        if (! $reset) {
            return null;
        }

        $this->lockout->clearEmail($user->email);
        Mail::to($user->email)->queue(new PasswordChanged(in_array($user->locale, User::LOCALES, true) ? $user->locale : 'en'));

        return $user;
    }
}
