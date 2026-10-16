<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LinkSocialAccountRequest extends FormRequest
{
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
        ];
    }
}
