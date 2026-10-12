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
            /** Google: the ID token (a JWT). Facebook: the user access token. */
            'token' => ['required', 'string', 'max:8192'],
            /** Only for a first-time sign-up; defaults to the name from Google/Facebook. */
            'display_name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'locale' => ['sometimes', 'nullable', 'string', Rule::in(User::LOCALES)],
            ...$this->birthRules(required: false),
            ...$this->deviceRules(),
        ];
    }

    /**
     * @return array{birth_year?: int|null, birth_month?: int|null, display_name?: string|null, locale?: string|null}
     */
    public function profile(): array
    {
        /** @var array{birth_year?: int|null, birth_month?: int|null, display_name?: string|null, locale?: string|null} $profile */
        $profile = $this->safe()->only(['birth_year', 'birth_month', 'display_name', 'locale']);

        return $profile;
    }
}
