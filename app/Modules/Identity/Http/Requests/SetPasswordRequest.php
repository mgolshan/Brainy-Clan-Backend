<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetPasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            /** Required only when the user already has a password (`has_password: true`). */
            'current_password' => [Rule::requiredIf($user instanceof User && $user->password !== null), 'nullable', 'string', 'max:255'],
        ];
    }
}
