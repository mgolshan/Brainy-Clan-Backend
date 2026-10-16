<?php

namespace App\Modules\Core\Support;

use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\UserDataBag;

/**
 * `before_send` for Sentry (config/sentry.php): no personal data leaves the server, children's
 * least of all. Keeps only the user id; removes cookies, auth headers and the request body; masks
 * personal fields, emails, bearer tokens and one-time link tokens anywhere in the event (messages,
 * exception values, breadcrumbs, extra and contexts).
 */
final class SentryScrubber
{
    public const string FILTERED = '[Filtered]';

    /** Request/extra keys that are always masked (lower-case, compared case-insensitively). */
    public const array SENSITIVE_KEYS = [
        'authorization', 'cookie', 'set-cookie', 'x-xsrf-token', 'x-csrf-token', 'php-auth-user', 'php-auth-pw',
        'password', 'current_password', 'password_confirmation', 'token', 'access_token', 'refresh_token', 'id_token',
        'authorization_code', 'code', 'nonce', 'push_token', 'email', 'display_name', 'nickname', 'name', 'avatar',
        'birth_year', 'birth_month', 'ip_address', 'ip', 'reward_ref', 'secret', 'api_key', '_token',
    ];

    public static function beforeSend(Event $event, ?EventHint $hint = null): Event
    {
        $user = $event->getUser();
        $event->setUser($user !== null && $user->getId() !== null ? UserDataBag::createFromUserIdentifier($user->getId()) : null);

        $request = $event->getRequest();

        if ($request !== []) {
            unset($request['cookies'], $request['data'], $request['env']);

            if (isset($request['headers']) && is_array($request['headers'])) {
                $request['headers'] = self::scrubArray($request['headers']);
            }

            foreach (['url', 'query_string'] as $key) {
                if (isset($request[$key]) && is_string($request[$key])) {
                    $request[$key] = self::scrubString($request[$key]);
                }
            }

            $event->setRequest($request);
        }

        $message = $event->getMessage();

        if ($message !== null) {
            $event->setMessage(self::scrubString($message), array_map(fn ($param) => is_string($param) ? self::scrubString($param) : $param, $event->getMessageParams()));
        }

        foreach ($event->getExceptions() as $exception) {
            $exception->setValue(self::scrubString($exception->getValue()));
        }

        $event->setBreadcrumb(array_map(function (Breadcrumb $breadcrumb): Breadcrumb {
            $scrubbed = new Breadcrumb(
                $breadcrumb->getLevel(),
                $breadcrumb->getType(),
                $breadcrumb->getCategory(),
                $breadcrumb->getMessage() !== null ? self::scrubString($breadcrumb->getMessage()) : null,
                self::scrubArray($breadcrumb->getMetadata()),
                $breadcrumb->getTimestamp(),
            );

            return $scrubbed;
        }, $event->getBreadcrumbs()));

        $event->setExtra(self::scrubArray($event->getExtra()));

        foreach ($event->getContexts() as $name => $context) {
            if (is_array($context)) {
                $event->setContext((string) $name, self::scrubArray($context));
            }
        }

        return $event;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function scrubArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $data[$key] = self::FILTERED;
            } elseif (is_array($value)) {
                $data[$key] = self::scrubArray($value);
            } elseif (is_string($value)) {
                $data[$key] = self::scrubString($value);
            }
        }

        return $data;
    }

    /**
     * Masks email addresses, bearer tokens, Sanctum tokens (`12|abc…`) and long random tokens
     * (e.g. the account deletion link) inside free text.
     */
    public static function scrubString(string $value): string
    {
        $patterns = [
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
            '/Bearer\s+[A-Za-z0-9._~+\/=|\-]+/i',
            '/\b\d+\|[A-Za-z0-9]{20,}\b/',
            '/[A-Za-z0-9]{40,}/',
        ];

        return (string) preg_replace($patterns, self::FILTERED, $value);
    }
}
