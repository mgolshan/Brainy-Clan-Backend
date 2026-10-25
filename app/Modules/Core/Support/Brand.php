<?php

namespace App\Modules\Core\Support;

/**
 * The brand: name, root domain and the logo files configured in `config/brand.php`
 * (README "Brand and logo").
 *
 * Web pages and the admin use `logoUrl()` (SVG, root-relative: same origin on the API and the
 * website host, as the website's CSP and image tests require); emails use `emailLogoUrl()` (PNG,
 * absolute on the website URL, because a mail is rendered in a queue worker without a request
 * and mail clients do not show SVG). Every URL carries a `?v=` fingerprint of the file's
 * modification time, so a swapped logo is not served from a cache.
 */
final class Brand
{
    /**
     * The product name shown next to the logo (APP_NAME).
     */
    public static function name(): string
    {
        return (string) config('app.name', 'Brainy Clan');
    }

    /**
     * Root domain of the product (CENTRAL_DOMAIN, e.g. `brainyclan.com`).
     */
    public static function domain(): string
    {
        return (string) config('app.central_domain');
    }

    /**
     * Vector logo for web pages and the admin: `/images/brand/logo.svg?v=…`.
     */
    public static function logoUrl(): string
    {
        return self::versioned('/'.self::path('brand.logo'), self::path('brand.logo'));
    }

    /**
     * PNG logo for web pages (touch icon): `/images/brand/logo.png?v=…`.
     */
    public static function pngLogoUrl(): string
    {
        return self::versioned('/'.self::path('brand.logo_png'), self::path('brand.logo_png'));
    }

    /**
     * PNG logo for emails: absolute on the website (WEBSITE_URL), independent of any request.
     */
    public static function emailLogoUrl(): string
    {
        return self::versioned(Website::url(self::path('brand.logo_png')), self::path('brand.logo_png'));
    }

    /**
     * Website home page for links in emails (`?lang=fr` for French).
     */
    public static function websiteUrl(string $locale = 'en'): string
    {
        return Website::url('/').($locale === 'fr' ? '?lang=fr' : '');
    }

    /**
     * The website address as shown in text (`brainyclan.com`; `localhost:8080` in dev).
     */
    public static function websiteLabel(): string
    {
        return (string) preg_replace('#^https?://#', '', rtrim(Website::url('/'), '/'));
    }

    /**
     * A configured logo path relative to public/, without a leading slash.
     */
    private static function path(string $key): string
    {
        return ltrim((string) config($key), '/');
    }

    private static function versioned(string $url, string $path): string
    {
        $file = public_path($path);

        if (! is_file($file)) {
            return $url;
        }

        return $url.'?v='.substr(md5((string) filemtime($file)), 0, 8);
    }
}
