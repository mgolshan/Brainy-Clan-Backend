<?php

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Billing\Models\AffiliateCode;
use App\Modules\Billing\Support\PaddleConfig;
use App\Modules\Core\Http\Middleware\SecurityHeaders;
use App\Modules\Core\Support\HelpCenter;
use App\Modules\Core\Support\Website;
use App\Modules\Policy\PolicyResolver;
use App\Modules\Policy\Support\ThemeWorldCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The public website (brainyclan.com in production; docs/DEPLOY.md "Website and hosts"): landing
 * page, support page, the "How to play" help center, the partners page, robots.txt and sitemap.xml. Static HTML, no
 * cookies, no third-party requests; the only script is the help center's same-origin search.
 * Language: `?lang=en|fr`, else the browser's Accept-Language. Images are static files in
 * `public/images/site/` (screens and help: WebP captures of the demo family; art and clan:
 * SVGs exported from the app).
 */
class WebsiteController
{
    /** Pages listed in the sitemap (route names), each in English and French. */
    private const array SITEMAP_ROUTES = ['website.home', 'website.help', 'website.support', 'website.partners', 'legal.privacy', 'legal.terms', 'account.delete'];

    /**
     * The landing page. Plan limits and the world unlock catalog come from the same data the
     * app's policy and `GET /me/worlds` use, so the page never promises more than the app does.
     */
    public function home(Request $request, PolicyResolver $policy, ThemeWorldCatalog $worlds): Response
    {
        return $this->page($request, 'website.home', $this->homeData($policy, $worlds));
    }

    /**
     * @return array<string, mixed>
     */
    private function homeData(PolicyResolver $policy, ThemeWorldCatalog $worlds): array
    {
        return [
            'plans' => [
                'free' => $this->plan($policy, 'free'),
                'family' => $this->plan($policy, 'family'),
            ],
            'worlds' => $worlds->all(),
            'referral' => null,
        ];
    }

    /**
     * Affiliate link `/r/{code}` (docs/API.md "Referral / affiliate codes"): the landing page with a
     * "Code MAYA" banner, the store offer-code hint when the code has one, and a link to the
     * subscribe form with the code filled in. Unknown or inactive codes show the normal page. No
     * cookies, nothing stored about the visitor.
     */
    public function referral(Request $request, string $code, PolicyResolver $policy, ThemeWorldCatalog $worlds): Response
    {
        $affiliateCode = AffiliateCode::findUsable($code);

        return $this->page($request, 'website.home', [
            ...$this->homeData($policy, $worlds),
            'canonicalRoute' => 'website.home',
            'referral' => $affiliateCode !== null ? [
                'code' => $affiliateCode->code,
                'has_offer_code' => ($affiliateCode->store_offer_codes ?? []) !== [],
                'subscribe_url' => PaddleConfig::webCheckoutAvailable()
                    ? Website::route('website.subscribe', ['code' => $affiliateCode->code, 'src' => 'link', ...(Website::locale($request) === 'fr' ? ['lang' => 'fr'] : [])])
                    : null,
            ] : null,
        ]);
    }

    public function support(Request $request, PolicyResolver $policy): Response
    {
        return $this->page($request, 'website.support', [
            'signInCodeMinutes' => $policy->signInCodeTtlMinutes(),
        ]);
    }

    /**
     * The partner (affiliate) program page (docs/PLAN.md "Partner (affiliate) instructions
     * (2026-10-24)"; same content as docs/PARTNER_GUIDE.md). Every commission and payout number
     * comes from `billing.affiliates` (the defaults new affiliates get in the admin); a line whose
     * value is not configured is left out. The web discount is only mentioned while web checkout
     * is open.
     */
    public function partners(Request $request): Response
    {
        $affiliates = (array) config('billing.affiliates', []);
        $payout = (array) ($affiliates['payout'] ?? []);
        $rate = is_numeric($affiliates['commission_rate'] ?? null) ? (float) $affiliates['commission_rate'] : 0.0;
        $months = (int) ($affiliates['commission_months'] ?? 0);
        $refundDays = (int) ($affiliates['refund_window_days'] ?? 0);
        $minimum = array_filter((array) ($payout['minimum'] ?? []), fn (mixed $amount): bool => is_numeric($amount) && (float) $amount > 0);

        return $this->page($request, 'website.partners', [
            'terms' => [
                'rate' => $rate > 0 ? rtrim(rtrim(number_format($rate * 100, 2, '.', ''), '0'), '.') : null,
                'months' => $months > 0 ? $months : null,
                'refund_days' => $refundDays > 0 ? $refundDays : null,
                'schedule' => ($payout['schedule'] ?? null) === 'monthly' ? 'monthly' : null,
                'currencies' => array_values(array_filter((array) ($payout['currencies'] ?? []), 'is_string')),
                'minimum' => array_map(fn (string $currency, mixed $amount): string => number_format((float) $amount, 2, '.', '').' '.$currency, array_keys($minimum), $minimum),
                'methods' => array_values(array_intersect(['paypal', 'interac', 'bank'], (array) ($payout['methods'] ?? []))),
            ],
            'webCheckout' => PaddleConfig::webCheckoutAvailable(),
        ]);
    }

    /**
     * The "How to play" help center (docs/PLAN.md, 2026-10-24): every section and article on one
     * page (collapsed `<details>`, anchors = article ids). `?q=` filters on the server so search
     * works without JavaScript; public/js/help-search.js does the same instantly while typing.
     * Cached privately only: the page carries a per-request CSP nonce.
     */
    public function help(Request $request, HelpCenter $help): Response
    {
        $locale = Website::locale($request);
        $sections = $help->sections($locale);
        $query = $request->query('q');
        $query = is_string($query) ? trim(mb_substr($query, 0, HelpCenter::MAX_QUERY_LENGTH)) : '';
        $matches = HelpCenter::terms($query, $locale) !== [] ? $help->search($sections, $query, $locale) : null;

        return $this->page($request, 'website.help', [
            'sections' => $sections,
            'query' => $query,
            'matches' => $matches,
            'stopWords' => HelpCenter::STOP_WORDS[$locale] ?? HelpCenter::STOP_WORDS['en'],
            'nonce' => SecurityHeaders::nonce($request),
        ])->header('Cache-Control', 'private, max-age=300');
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
            ? "User-agent: *\nDisallow: /account/delete/confirm/\nDisallow: /password/\nDisallow: /subscribe/checkout\nDisallow: /subscribe/pay\nDisallow: /r/\n\nSitemap: ".Website::route('website.sitemap')."\n"
            : "User-agent: *\nDisallow: /\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    /**
     * AdMob's app-ads.txt (authorized seller line) for the developer website; 404 until the
     * publisher id is configured.
     */
    public function appAds(): Response
    {
        $publisherId = (string) config('services.admob.publisher_id');

        abort_unless(preg_match('/^pub-\d{10,20}$/', $publisherId) === 1, 404);

        return response("google.com, {$publisherId}, DIRECT, f08c47fec0942fa0\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
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
