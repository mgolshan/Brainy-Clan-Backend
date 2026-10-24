<?php

namespace App\Modules\Core\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Operational alerts (docs/OPERATIONS_HANDBOOK.md §6.2): a plain-text mail to `ops.alert_email`
 * and, when configured, a JSON `{"text": "..."}` POST to `ops.alert_webhook_url` (Slack incoming
 * webhooks accept that body). Each alert key is sent at most once per cooldown. Alerts never
 * contain personal data: callers pass class names, queue names and counts only. Delivery failures
 * are reported (Sentry) but never thrown, so an alert can't break the job or request that raised it.
 */
class OpsAlert
{
    public function send(string $key, string $subject, string $body, int $cooldownSeconds): bool
    {
        if (! $this->acquire($key, $cooldownSeconds)) {
            return false;
        }

        $subject = '[Brainy Clan '.app()->environment().'] '.$subject;
        $email = config('ops.alert_email');

        if (is_string($email) && $email !== '') {
            try {
                Mail::raw($body, fn ($message) => $message->to($email)->subject($subject));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $this->postToWebhook($subject."\n".$body);

        return true;
    }

    /**
     * POST to the optional webhook only (Horizon already mails its own long-wait notifications).
     */
    public function webhookOnly(string $key, string $text, int $cooldownSeconds): bool
    {
        if (! $this->webhookUrl()) {
            return false;
        }

        if (! $this->acquire($key, $cooldownSeconds)) {
            return false;
        }

        $this->postToWebhook('[Brainy Clan '.app()->environment().'] '.$text);

        return true;
    }

    /**
     * `Cache::add` is atomic: only the first caller within the cooldown sends. Without a working
     * cache (Redis down) the alert goes out anyway — better twice than never.
     */
    private function acquire(string $key, int $cooldownSeconds): bool
    {
        try {
            return Cache::add('ops:alert:'.sha1($key), now()->timestamp, $cooldownSeconds);
        } catch (Throwable $exception) {
            report($exception);

            return true;
        }
    }

    private function postToWebhook(string $text): void
    {
        $url = $this->webhookUrl();

        if ($url === null) {
            return;
        }

        try {
            Http::timeout(5)->asJson()->post($url, ['text' => $text]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function webhookUrl(): ?string
    {
        $url = config('ops.alert_webhook_url');

        return is_string($url) && $url !== '' ? $url : null;
    }
}
