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
            /** Google: the ID token (a JWT). Facebook: the user access token. */
            'token' => ['required', 'string', 'max:8192'],
        ];
    }
}
