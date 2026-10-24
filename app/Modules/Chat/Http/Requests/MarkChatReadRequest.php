<?php

namespace App\Modules\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkChatReadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The newest message id the member has seen; the marker only moves forward. */
            'last_read_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
