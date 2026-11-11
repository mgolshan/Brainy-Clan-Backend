<?php

namespace App\Modules\Chat\Http\Requests;

use App\Modules\Chat\Support\ChatReactions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReactToChatMessageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** One of `heart`, `thumbs_up`, `laugh`, `wow`, `party`, `star`. */
            'key' => ['required', 'string', Rule::in(ChatReactions::KEYS)],
        ];
    }
}
