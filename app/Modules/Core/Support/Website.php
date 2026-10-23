<?php

namespace App\Modules\Core\Support;

use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;

/**
 * The public website served by this app (docs/DEPLOY.md "Website and hosts"): its host, absolute
 * URLs on it and the page language.
 *
 * Production: `WEBSITE_HOST=brainyclan.com`, `WEBSITE_URL=https://brainyclan.com`, the API on
 * `APP_URL=https://api.brainyclan.com`. Local dev: `WEBSITE_HOST` empty, so there is no host
 * routing and URLs are built from the current request like any other route.
 */
final class Website
{
    /**
     * Paths that belong to the API host. Requested on the website host they are redirected there.
     */
    public const array API_HOST_PATHS = ['api', 'api/*', 'admin', 'admin/*', 'horizon', 'horizon/*', 'docs/*', 'livewire*', 'up'];

    /**
     * The website host (`brainyclan.com`), or null when host routing is off (local dev).
     */
    public static function host(): ?string
    {
        $host = config('app.website_host');

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    /**
     * Absolute URL of a path on the website (`WEBSITE_URL`, falls back to `APP_URL`).
     */
    public static function url(string $path = '/'): string
    {
        return rtrim((string) config('app.website_url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * Absolute URL of a named website route. With host routing on it always points at the
     * website host, also when built on the API host or in a queued job (emails).
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function route(string $name, array $parameters = []): string
    {
        return self::host() === null ? route($name, $parameters) : self::url(route($name, $parameters, false));
    }

    /**
     * Page language: `?lang=` when supported, else the browser's Accept-Language (en or fr), else en.
     */
    public static function locale(Request $request): string
    {
        $requested = $request->query('lang');

        if (is_string($requested) && in_array($requested, User::LOCALES, true)) {
            return $requested;
        }

        return $request->getPreferredLanguage(User::LOCALES) ?? 'en';
    }
}
