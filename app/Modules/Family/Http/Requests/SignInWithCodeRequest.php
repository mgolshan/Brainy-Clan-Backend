<?php

namespace App\Modules\Family\Http\Requests;

use App\Modules\Identity\Http\Requests\DeviceRules;
use Illuminate\Foundation\Http\FormRequest;

class SignInWithCodeRequest extends FormRequest
{
    use DeviceRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The admin-issued sign-in code; case-insensitive, dashes and spaces are ignored. */
            'code' => ['required', 'string', 'max:20'],
            ...$this->deviceRules(),
        ];
    }
}
