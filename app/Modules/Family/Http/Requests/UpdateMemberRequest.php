<?php

namespace App\Modules\Family\Http\Requests;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Support\Accessibility;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nickname' => ['sometimes', 'string', 'min:1', 'max:40'],
            'avatar' => ['sometimes', 'string', Rule::in(User::AVATARS)],
            'difficulty_override' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10'],
            'birth_year' => ['sometimes', 'integer', 'min:1900', 'max:'.now()->year],
            'birth_month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'role' => ['sometimes', 'string', Rule::in(FamilyMember::ROLES)],
            'accessibility' => ['sometimes', 'array'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            /** Family chat on/off for this member (docs/API.md "Family chat"). */
            'chat_enabled' => ['sometimes', 'boolean'],
            ...Accessibility::rules('accessibility.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $email = strtolower(trim($this->input('email')));
            $this->merge(['email' => $email === '' ? null : $email]);
        }
    }
}
