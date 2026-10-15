<?php

namespace App\Modules\Notifications\Http\Requests;

use App\Modules\Identity\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePushTokenRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** Expo push token, e.g. `ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]`. */
            'token' => ['required', 'string', 'max:255', 'regex:/^Expo(nent)?PushToken\[[A-Za-z0-9_\-]+\]$/'],
            'platform' => ['required', 'string', Rule::in(Device::PLATFORMS)],
        ];
    }
}
