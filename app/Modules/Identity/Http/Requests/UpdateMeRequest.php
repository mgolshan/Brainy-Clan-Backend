<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'string', 'min:1', 'max:60'],
            'avatar' => ['sometimes', 'string', Rule::in(User::AVATARS)],
            'locale' => ['sometimes', 'string', Rule::in(User::LOCALES)],
        ];
    }
}
