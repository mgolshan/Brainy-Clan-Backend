<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The new-password form on `/password/reset` (field errors in the page's language).
 */
class PasswordResetPageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        if ($this->query('lang') !== 'fr') {
            return [
                'password.required' => 'Please enter a new password.',
                'password.min' => 'The password must be at least 8 characters.',
                'password.max' => 'The password may not be longer than 255 characters.',
                'password.confirmed' => 'The two passwords do not match.',
            ];
        }

        return [
            'token.required' => 'Ce lien n’est pas valide.',
            'password.required' => 'Entrez un nouveau mot de passe.',
            'password.string' => 'Entrez un nouveau mot de passe.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.max' => 'Le mot de passe ne peut pas dépasser 255 caractères.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
        ];
    }
}
