<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SocialSignInRequest extends FormRequest
{
    use DeviceRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** Google: the ID token (a JWT). Facebook: the user access token. Apple: the identity token (a JWT). */
            'token' => ['required', 'string', 'max:8192'],
            /** Apple only: the raw nonce whose SHA-256 (hex) the app passed to Apple; the token's `nonce` claim must match. */
            'nonce' => ['sometimes', 'nullable', 'string', 'max:255'],
            /** Apple only: the one-time `authorizationCode` from Apple's sign-in result. The server exchanges it for a refresh token (stored encrypted) and revokes it on unlink and account deletion. */
            'authorization_code' => ['sometimes', 'nullable', 'string', 'max:2048'],
            /** Only for a first-time sign-up; defaults to the name from Google/Facebook. Apple: send the name Apple gave the app on the first authorization. */
            'display_name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'locale' => ['sometimes', 'nullable', 'string', Rule::in(User::LOCALES)],
            /** Required (`true`) only when this sign-in creates a new account: the user accepts the current terms of service and privacy policy. 422 on `accept_terms` otherwise. */
            'accept_terms' => ['sometimes', 'nullable', 'boolean'],
            ...$this->birthRules(required: false),
            ...$this->deviceRules(),
        ];
    }

    /**
     * @return array{birth_year?: int|null, birth_month?: int|null, display_name?: string|null, locale?: string|null, accept_terms: bool}
     */
    public function profile(): array
    {
        /** @var array{birth_year?: int|null, birth_month?: int|null, display_name?: string|null, locale?: string|null} $profile */
        $profile = $this->safe()->only(['birth_year', 'birth_month', 'display_name', 'locale']);

        return [...$profile, 'accept_terms' => $this->boolean('accept_terms')];
    }
}
