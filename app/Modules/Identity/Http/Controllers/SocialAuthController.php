<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Core\Docs\ApiErrors;
use App\Modules\Identity\Actions\SignInWithSocial;
use App\Modules\Identity\Http\Requests\SocialSignInRequest;
use App\Modules\Identity\Http\Resources\MeResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Identity')]
class SocialAuthController
{
    /**
     * Sign in with Google, Facebook or Apple.
     *
     * Adults only. The app signs in natively with the provider and sends its token: Google → the
     * **ID token**, Facebook → the **access token**, Apple → the **identity token** (plus the raw
     * `nonce` when the app used one, and on the first authorization the name Apple returned as
     * `display_name` — Apple never puts the name in the token). The server verifies it with the provider, then
     * signs in a linked account (200), links a user with the same provider-verified email (200), or
     * creates a new adult user (201). A would-be new user without `birth_year`/`birth_month` gets
     * 422 `social_signup_incomplete`: ask for them and retry with the same token. Under the adult
     * age → 422 `validation_failed` (like register). 401 `social_token_invalid` when the token
     * can't be verified. 403 `forbidden` when the matching user is not an adult.
     *
     * @unauthenticated
     */
    #[ApiErrors('social_token_invalid', 'social_signup_incomplete', 'forbidden')]
    public function signIn(SocialSignInRequest $request, string $provider, SignInWithSocial $signIn): JsonResponse
    {
        $result = $signIn->handle(
            $provider,
            (string) $request->string('token'),
            $request->profile(),
            (string) $request->string('device_name'),
            (string) $request->string('platform'),
            $request->appVersion(),
            $request->filled('nonce') ? (string) $request->string('nonce') : null,
            $request->filled('authorization_code') ? (string) $request->string('authorization_code') : null,
        );

        return MeResource::withToken($result['user'], $result['token'])
            ->response()
            ->setStatusCode($result['created'] ? 201 : 200);
    }
}
