<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LegalAcceptanceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The terms version the app showed (must be the current one from `GET /legal`). */
            'terms_version' => ['required', 'string', 'max:40'],
            /** The privacy policy version the app showed (must be the current one). */
            'privacy_version' => ['required', 'string', 'max:40'],
        ];
    }
}
