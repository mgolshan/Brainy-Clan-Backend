<?php

namespace App\Modules\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListChatReactionsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The opaque `meta.cursor` of the previous call; without it: no rows and the current cursor. */
            'since' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
