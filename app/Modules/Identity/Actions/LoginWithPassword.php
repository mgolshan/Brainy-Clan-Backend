<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginWithPassword
{
    public function __construct(private readonly IssueDeviceToken $issueToken) {}

    /**
     * @return array{user: User, token: string}
     */
    public function handle(string $email, string $password, string $deviceName, string $platform, ?string $appVersion = null): array
    {
        $user = User::query()->where('email', strtolower($email))->first();

        if ($user === null || $user->password === null || ! Hash::check($password, $user->password)) {
            throw ApiException::invalidCredentials();
        }

        return ['user' => $user, 'token' => $this->issueToken->handle($user, $deviceName, $platform, $appVersion)];
    }
}
