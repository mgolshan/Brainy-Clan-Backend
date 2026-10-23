<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Core\Docs\ApiErrors;
use App\Modules\Identity\Actions\LoginWithPassword;
use App\Modules\Identity\Actions\RegisterAdult;
use App\Modules\Identity\Actions\RequestPasswordReset;
use App\Modules\Identity\Actions\RevokeCurrentDevice;
use App\Modules\Identity\Http\Requests\ForgotPasswordRequest;
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
     * Sign-in lockout: 429 `login_locked` (body `retry_after` seconds + `Retry-After` header) after
     * repeated failures per email + IP (5 → 5 min, 1 h, then 24 h), per email (20 within 1 h →
     * 15 min) or per IP (30 within 1 h → 1 h); the same answer whether or not the email exists.
     */
    #[ApiErrors('invalid_credentials', 'login_locked')]
    public function login(LoginRequest $request, LoginWithPassword $login): MeResource
    {
        $result = $login->handle(
            (string) $request->string('email'),
            (string) $request->string('password'),
            (string) $request->string('device_name'),
            (string) $request->string('platform'),
            $request->appVersion(),
            $request->ip(),
        );

        return MeResource::withToken($result['user'], $result['token']);
    }

    /**
     * Forgot password: email a reset link.
     *
     * Always 202 `{ "data": { "sent": true } }`, whether or not the email has an account. When it
     * has, a one-time link to the web page `/password/reset` is emailed (valid 60 minutes; a newer
     * link replaces older ones). The page sets the new password, signs the user out of every
     * device and clears the sign-in lockouts of the email. 3 requests per hour per email and 10
     * per IP; over that the answer is the same and no email is sent.
     *
     * @status 202
     */
    public function forgotPassword(ForgotPasswordRequest $request, RequestPasswordReset $requestReset): JsonResponse
    {
        $locale = $request->input('locale');

        $requestReset->handle((string) $request->string('email'), is_string($locale) ? $locale : null, $request->ip());

        return new JsonResponse(['data' => ['sent' => true]], 202);
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
