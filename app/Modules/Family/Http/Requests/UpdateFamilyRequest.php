<?php

namespace App\Modules\Family\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFamilyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:80'],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
        ];
    }
}
