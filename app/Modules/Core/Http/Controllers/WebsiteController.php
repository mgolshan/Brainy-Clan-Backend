<?php

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Support\Website;
use App\Modules\Policy\PolicyResolver;
use App\Modules\Policy\Support\ThemeWorldCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The public website (brainyclan.com in production; docs/DEPLOY.md "Website and hosts"): landing
 * page, support page, robots.txt and sitemap.xml. Static HTML, no cookies, no scripts, no
 * third-party requests. Language: `?lang=en|fr`, else the browser's Accept-Language. Images are
 * static files in `public/images/site/` (screens: WebP captures of the demo family; art and clan:
 * SVGs exported from the app).
 */
class WebsiteController
{
    /** Pages listed in the sitemap (route names), each in English and French. */
    private const array SITEMAP_ROUTES = ['website.home', 'website.support', 'legal.privacy', 'legal.terms', 'account.delete'];

    /**
     * The landing page. Plan limits and the world unlock catalog come from the same data the
     * app's policy and `GET /me/worlds` use, so the page never promises more than the app does.
     */
    public function home(Request $request, PolicyResolver $policy, ThemeWorldCatalog $worlds): Response
    {
        return $this->page($request, 'website.home', [
            'plans' => [
                'free' => $this->plan($policy, 'free'),
                'family' => $this->plan($policy, 'family'),
            ],
            'worlds' => $worlds->all(),
        ]);
    }

    public function support(Request $request, PolicyResolver $policy): Response
    {
        return $this->page($request, 'website.support', [
            'signInCodeMinutes' => $policy->signInCodeTtlMinutes(),
        ]);
    }

    /**
     * The old legal URLs (`/legal/terms`, `/legal/privacy`, used by app versions and store
     * listings before 2026-10-22) moved to `/terms` and `/privacy`.
     */
    public function legacyLegal(Request $request, string $document): RedirectResponse
    {
        return redirect()->to(Website::route("legal.{$document}", $request->query()), 301);
    }

    /**
     * Crawlers may index the website host only; any other host (the API) is disallowed.
     */
    public function robots(Request $request): Response
    {
        $websiteHost = Website::host();
        $isWebsite = $websiteHost === null || strtolower($request->getHost()) === $websiteHost;

        $body = $isWebsite
            ? "User-agent: *\nDisallow: /account/delete/confirm/\nDisallow: /password/\n\nSitemap: ".Website::route('website.sitemap')."\n"
            : "User-agent: *\nDisallow: /\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function sitemap(): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

        foreach (self::SITEMAP_ROUTES as $name) {
            $alternates = [
                'en' => e(Website::route($name, ['lang' => 'en'])),
                'fr' => e(Website::route($name, ['lang' => 'fr'])),
            ];

            foreach ($alternates as $url) {
                $xml .= "  <url>\n    <loc>{$url}</loc>\n";

                foreach ($alternates as $hreflang => $alternate) {
                    $xml .= "    <xhtml:link rel=\"alternate\" hreflang=\"{$hreflang}\" href=\"{$alternate}\"/>\n";
                }

                $xml .= "  </url>\n";
            }
        }

        return response($xml."</urlset>\n", 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(Request $request, string $view, array $data = []): Response
    {
        $locale = Website::locale($request);

        return response()->view($view, [
            'locale' => $locale,
            'supportEmail' => (string) config('legal.support_email'),
            'canonicalRoute' => $request->route()?->getName(),
            ...$data,
        ])
            ->header('Content-Language', $locale)
            ->header('Vary', 'Accept-Language')
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * What a tier really includes, from the same settings the app's policy is built from.
     *
     * @return array{members: int, categories: int, goals: int|null, free_second_chances: int, ads: bool, chat_days: int}
     */
    private function plan(PolicyResolver $policy, string $tier): array
    {
        return [
            'members' => (int) $policy->setting('limits.max_members', $tier),
            'categories' => (int) $policy->setting('limits.max_categories', $tier),
            'goals' => $policy->maxActiveGoalsFor($tier),
            'free_second_chances' => (int) $policy->setting('second_chance.free_per_day', $tier),
            'ads' => (bool) $policy->setting('ads.enabled', $tier),
            'chat_days' => $policy->chatRetentionDays($tier),
        ];
    }
}
