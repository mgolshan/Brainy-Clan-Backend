<?php

namespace App\Modules\Family\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateFamilyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'country_code' => ['required', 'string', 'size:2', 'alpha'],
        ];
    }
}
