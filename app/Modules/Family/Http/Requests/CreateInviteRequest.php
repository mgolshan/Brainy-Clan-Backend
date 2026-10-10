<?php

namespace App\Modules\Family\Http\Requests;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateInviteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nickname' => ['required', 'string', 'max:40'],
            'birth_year' => ['required', 'integer', 'min:1900', 'max:'.now()->year],
            'birth_month' => ['required', 'integer', 'min:1', 'max:12'],
            'avatar' => ['sometimes', 'nullable', 'string', Rule::in(User::AVATARS)],
        ];
    }
}
