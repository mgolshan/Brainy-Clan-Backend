<?php

namespace App\Modules\Support\Http\Requests;

use App\Modules\Identity\Models\User;
use App\Modules\Support\Actions\ResolveSupportSender;
use App\Modules\Support\Models\SupportMessage;
use App\Modules\Support\Support\SupportText;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportMessageRequest extends FormRequest
{
    /**
     * Family admins only: everyone else gets 403 `forbidden` before validation.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user instanceof User) {
            app(ResolveSupportSender::class)->handle($user);
        }

        return $user instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** `account`, `billing`, `bug`, `content`, `privacy`, `idea` or `other`. */
            'topic' => ['required', 'string', Rule::in(SupportMessage::TOPICS)],
            /** Plain text, 10–5000 characters (Unicode code points) after trimming. Never HTML. */
            'message' => ['required', 'string', 'max:'.SupportText::MAX_RAW_LENGTH, function (string $attribute, mixed $value, Closure $fail): void {
                $text = is_string($value) ? SupportText::normalize($value) : null;

                if ($text === null || ! SupportText::isValidLength($text)) {
                    $fail('The message must be between '.SupportText::MIN_LENGTH.' and '.SupportText::MAX_LENGTH.' characters.');
                }
            }],
            /** A UUID the app makes per message; re-sending it returns the existing message (200). */
            'client_id' => ['required', 'string', 'uuid'],
            /** The app version, e.g. `1.0.0`. */
            'app_version' => ['nullable', 'string', 'max:20'],
            'platform' => ['nullable', 'string', Rule::in(SupportMessage::PLATFORMS)],
            /** Device model and OS only, e.g. `iPad (iPadOS 18.1)`. */
            'device' => ['nullable', 'string', 'max:80'],
        ];
    }
}
