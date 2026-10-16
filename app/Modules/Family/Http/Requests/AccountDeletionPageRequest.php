<?php

namespace App\Modules\Family\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The email form on `/account/delete`. `website` is a honeypot hidden from people.
 */
class AccountDeletionPageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function isBot(): bool
    {
        return $this->filled('website');
    }
}
