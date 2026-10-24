<?php

namespace App\Modules\Chat\Http\Requests;

use App\Modules\Chat\Support\ChatText;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class PostChatMessageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** Plain text with emoji, 1–1000 characters (Unicode code points) after normalization. Never HTML. */
            'body' => ['required', 'string', 'max:'.ChatText::MAX_RAW_LENGTH, function (string $attribute, mixed $value, Closure $fail): void {
                $text = is_string($value) ? ChatText::normalize($value) : null;

                if ($text === null || ! ChatText::isValidLength($text)) {
                    $fail('The message must be between 1 and '.ChatText::MAX_LENGTH.' characters.');
                }
            }],
            /** A UUID the app makes per message; re-sending it returns the existing message (200). */
            'client_id' => ['required', 'string', 'uuid'],
        ];
    }
}
