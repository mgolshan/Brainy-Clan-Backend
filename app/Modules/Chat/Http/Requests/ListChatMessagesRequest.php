<?php

namespace App\Modules\Chat\Http\Requests;

use App\Modules\Chat\Actions\ListChatMessages;
use Illuminate\Foundation\Http\FormRequest;

class ListChatMessagesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** Older messages than this id, newest first (scrolling back). */
            'before' => ['sometimes', 'integer', 'min:1', 'prohibits:after'],
            /** Newer messages than this id, oldest first (polling). */
            'after' => ['sometimes', 'integer', 'min:0'],
            /** Page size, default 50. */
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.ListChatMessages::MAX_LIMIT],
        ];
    }
}
