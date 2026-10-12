<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * `PUT /me/password`: sets or changes the password of the user's email login. Needs an email
 * (an admin adds it first); the current password is required once one is set.
 */
class SetPassword
{
    public function handle(User $user, string $password, ?string $currentPassword = null): void
    {
        if ($user->email === null) {
            throw ApiException::validation('password', 'Ask a family admin to add an email login before setting a password.');
        }

        if ($user->password !== null && ($currentPassword === null || ! Hash::check($currentPassword, $user->password))) {
            throw ApiException::validation('current_password', 'The current password is incorrect.');
        }

        $user->forceFill(['password' => $password])->save();
    }
}
