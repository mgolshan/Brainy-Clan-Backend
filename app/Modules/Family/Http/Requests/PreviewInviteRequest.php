<?php

namespace App\Modules\Family\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewInviteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'birth_year' => ['required', 'integer', 'min:1900', 'max:'.now()->year],
            'birth_month' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }
}
