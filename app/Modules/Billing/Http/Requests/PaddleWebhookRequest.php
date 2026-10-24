<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Support\PaddleConfig;
use App\Modules\Billing\Support\WebhookRejections;
use App\Modules\Core\Exceptions\ApiException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A Paddle Billing webhook, authorized by its `Paddle-Signature` header:
 * `ts=<unix time>;h1=<hex HMAC-SHA256 of "<ts>:<raw body>" with PADDLE_WEBHOOK_SECRET>` (several
 * `h1` while Paddle rotates secrets). The timestamp must be within the tolerance (5 minutes).
 */
class PaddleWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $valid = self::signatureIsValid(
            (string) $this->header('Paddle-Signature', ''),
            $this->getContent(),
            PaddleConfig::webhookSecret(),
            (int) config('billing.paddle.webhook_tolerance_seconds', 300),
        );

        if (! $valid) {
            app(WebhookRejections::class)->record(WebhookRejections::PROVIDER_PADDLE, WebhookRejections::REASON_SIGNATURE_INVALID);
        }

        return $valid;
    }

    protected function failedAuthorization(): never
    {
        throw new ApiException('unauthenticated', 'Invalid webhook signature.', 401);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'string', 'max:100'],
            'event_type' => ['required', 'string', 'max:40'],
            'occurred_at' => ['nullable', 'string', 'max:40'],
            'data' => ['required', 'array'],
        ];
    }

    public static function signatureIsValid(string $header, string $body, string $secret, int $toleranceSeconds, ?int $now = null): bool
    {
        if ($secret === '' || $header === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(';', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 'ts' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'h1' && $value !== '') {
                $signatures[] = strtolower($value);
            }
        }

        if ($timestamp === null || $signatures === [] || abs(($now ?? time()) - $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.':'.$body, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
