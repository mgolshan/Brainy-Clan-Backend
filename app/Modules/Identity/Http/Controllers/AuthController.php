<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\LoginWithPassword;
use App\Modules\Identity\Actions\RegisterAdult;
use App\Modules\Identity\Actions\RevokeCurrentDevice;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Resources\MeResource;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController
{
    public function register(RegisterRequest $request, RegisterAdult $register): JsonResponse
    {
        /** @var array{display_name: string, email: string, password: string, birth_year: int, birth_month: int, locale?: string|null, device_name: string, platform: string} $data */
        $data = $request->validated();

        $result = $register->handle($data, $data['device_name'], $data['platform'], $request->appVersion());

        return MeResource::withToken($result['user'], $result['token'])->response()->setStatusCode(201);
    }

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

    public function logout(Request $request, RevokeCurrentDevice $revoke): Response
    {
        /** @var User $user */
        $user = $request->user();
        $revoke->handle($user);

        return response()->noContent();
    }
}
