<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Core\Docs\ApiErrors;
use App\Modules\Identity\Actions\LoginWithPassword;
use App\Modules\Identity\Actions\RegisterAdult;
use App\Modules\Identity\Actions\RevokeCurrentDevice;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Resources\MeResource;
use App\Modules\Identity\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Identity', 'Sign-up and sign-in (email, Google, Facebook), the signed-in user (`/me`), password and linked social accounts.', 1)]
class AuthController
{
    /**
     * Register an adult.
     *
     * Email + password sign-up. 422 when the computed age band may not self-register
     * (`collect_email` false: children, teens) and on `accept_terms` unless it is `true` (the
     * acceptance of the current terms + privacy versions is recorded). Creates a device-bound token.
     */
    public function register(RegisterRequest $request, RegisterAdult $register): JsonResponse
    {
        /** @var array{display_name: string, email: string, password: string, birth_year: int, birth_month: int, locale?: string|null, device_name: string, platform: string} $data */
        $data = $request->validated();

        $result = $register->handle($data, $data['device_name'], $data['platform'], $request->appVersion(), $request->ip());

        return MeResource::withToken($result['user'], $result['token'])->response()->setStatusCode(201);
    }

    /**
     * Sign in with email and password.
     *
     * 401 `invalid_credentials` for a wrong email or password. Creates a device-bound token.
     */
    #[ApiErrors('invalid_credentials')]
    public function login(LoginRequest $request, LoginWithPassword $login): MeResource
    {
        $result = $login->handle(
            (string) $request->string('email'),
            (string) $request->string('password'),
            (string) $request->string('device_name'),
            (string) $request->string('platform'),
            $request->appVersion(),
        );

        return MeResource::withToken($result['user'], $result['token']);
    }

    /**
     * Sign out this device.
     *
     * Revokes the current token and its device row.
     */
    public function logout(Request $request, RevokeCurrentDevice $revoke): Response
    {
        /** @var User $user */
        $user = $request->user();
        $revoke->handle($user);

        return response()->noContent();
    }
}
