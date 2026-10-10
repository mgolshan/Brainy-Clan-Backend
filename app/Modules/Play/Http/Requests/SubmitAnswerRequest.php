<?php

namespace App\Modules\Play\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAnswerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'session_item_id' => ['required', 'integer'],
            'response' => ['present', 'array'],
            'time_ms' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:3600000'],
        ];
    }
}
