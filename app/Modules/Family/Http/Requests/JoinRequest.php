<?php

namespace App\Modules\Family\Http\Requests;

use App\Modules\Identity\Http\Requests\DeviceRules;
use Illuminate\Foundation\Http\FormRequest;

class JoinRequest extends FormRequest
{
    use DeviceRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255', 'required_with:email'],
            ...$this->deviceRules(),
        ];
    }
}
