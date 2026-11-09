<?php

namespace App\Modules\Support\Http\Requests;

use App\Modules\Core\Support\Website;
use App\Modules\Support\Models\SupportMessage;
use App\Modules\Support\Support\FormToken;
use App\Modules\Support\Support\SupportText;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;

/**
 * The contact form on the website's `/support` page. The route is stateless (no session), so a
 * failed validation must not redirect back with flashed errors: this request keeps the errors
 * ({@see self::formErrors()}) and the controller re-renders the page with them and the old input.
 * Error texts follow the page language (en/fr).
 *
 * Bot checks: `website` is a honeypot hidden from people ({@see self::isBot()}); `form_token` is the
 * signed form-time token ({@see FormToken}).
 */
class WebsiteSupportMessageRequest extends FormRequest
{
    /** Field error texts per page language. */
    public const array ERRORS = [
        'name' => ['en' => 'Please enter your name (up to 100 characters).', 'fr' => 'Veuillez entrer votre nom (100 caractères au plus).'],
        'email' => ['en' => 'Please enter a valid email address, so we can answer you.', 'fr' => 'Veuillez entrer une adresse courriel valide pour que nous puissions vous répondre.'],
        'topic' => ['en' => 'Please choose a topic.', 'fr' => 'Veuillez choisir un sujet.'],
        'message' => ['en' => 'Please write your message (10 to 5000 characters).', 'fr' => 'Veuillez écrire votre message (de 10 à 5000 caractères).'],
    ];

    private ?MessageBag $formErrors = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, Closure $fail): void {
                $name = is_string($value) ? SupportText::singleLine($value) : null;

                if ($name === null || $name === '') {
                    $fail($this->error('name'));
                }
            }],
            // RFC syntax only (no DNS lookup); the address is only used as Reply-To on our inbox mail.
            'email' => ['required', 'string', 'max:254', 'email:rfc', 'not_regex:/[\r\n]/'],
            'topic' => ['required', 'string', Rule::in(SupportMessage::TOPICS)],
            'message' => ['required', 'string', 'max:'.SupportText::MAX_RAW_LENGTH, function (string $attribute, mixed $value, Closure $fail): void {
                $text = is_string($value) ? SupportText::normalize($value) : null;

                if ($text === null || ! SupportText::isValidLength($text)) {
                    $fail($this->error('message'));
                }
            }],
            'website' => ['nullable'],
            'form_token' => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];

        foreach (['name', 'email', 'topic', 'message'] as $field) {
            foreach (['required', 'string', 'max', 'email', 'not_regex', 'in'] as $rule) {
                $messages["{$field}.{$rule}"] = $this->error($field);
            }
        }

        return $messages;
    }

    /**
     * Keep the errors for the page instead of redirecting (no session to flash them to).
     */
    protected function failedValidation(Validator $validator): void
    {
        $this->formErrors = $validator->errors();
    }

    public function formErrors(): ?MessageBag
    {
        return $this->formErrors;
    }

    /**
     * The honeypot field (hidden from people) was filled in.
     */
    public function isBot(): bool
    {
        return $this->filled('website');
    }

    /**
     * {@see FormToken::VALID}, `invalid`, `too_fast` or `expired`.
     */
    public function tokenState(): string
    {
        return FormToken::check($this->input('form_token'));
    }

    /**
     * What the person typed, to put back in the form (strings only, bounded).
     *
     * @return array{name: string, email: string, topic: string, message: string}
     */
    public function formInput(): array
    {
        $value = fn (string $key): string => is_string($this->input($key)) ? mb_substr((string) $this->input($key), 0, SupportText::MAX_RAW_LENGTH) : '';

        return ['name' => $value('name'), 'email' => $value('email'), 'topic' => $value('topic'), 'message' => $value('message')];
    }

    private function error(string $field): string
    {
        return self::ERRORS[$field][Website::locale($this)] ?? self::ERRORS[$field]['en'];
    }
}
