<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\OpenWebCheckout;
use App\Modules\Billing\Actions\RequestWebCheckout;
use App\Modules\Billing\Http\Requests\SubscribePageRequest;
use App\Modules\Billing\Models\AffiliateCode;
use App\Modules\Billing\Models\FamilyAttribution;
use App\Modules\Billing\Support\PaddleConfig;
use App\Modules\Billing\Support\PricingCatalog;
use App\Modules\Core\Support\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The website's web checkout pages (docs/API.md "Web checkout (Paddle) and pricing (B10)"); no
 * password on the web:
 *
 * - `/subscribe` — plans, email and an optional referral code ("coming soon" without Paddle keys);
 * - `POST /web/subscribe` — always the same "check your email" page (`RequestWebCheckout`);
 * - `/subscribe/checkout?token=…` — the emailed link: creates the Paddle transaction and redirects
 *   to Paddle's checkout (`OpenWebCheckout`), or explains why not;
 * - `/subscribe/pay?_ptxn=…` — the default payment link page that opens Paddle.js (only used
 *   without a Paddle hosted checkout);
 * - `/subscribe/thanks` — where Paddle returns after paying.
 */
class SubscribePageController
{
    public function show(Request $request): Response
    {
        $code = AffiliateCode::findUsable($request->query('code'));
        $plan = $request->query('plan');

        return $this->page($request, PaddleConfig::webCheckoutAvailable() ? 'form' : 'unavailable', [
            'selectedPlan' => is_string($plan) && in_array($plan, PricingCatalog::planIds(), true) ? $plan : 'annual',
            'code' => $code?->code,
            'src' => $request->query('src') === FamilyAttribution::SOURCE_LINK ? FamilyAttribution::SOURCE_LINK : null,
        ]);
    }

    /**
     * Same answer whether or not the email has an account (and for the honeypot).
     */
    public function store(SubscribePageRequest $request, RequestWebCheckout $requestCheckout): Response
    {
        if (! PaddleConfig::webCheckoutAvailable()) {
            return $this->page($request, 'unavailable');
        }

        if (! $request->isBot()) {
            $requestCheckout->handle(
                (string) $request->string('email'),
                (string) $request->string('plan'),
                $request->referralCode(),
                $request->input('src') === FamilyAttribution::SOURCE_LINK ? FamilyAttribution::SOURCE_LINK : FamilyAttribution::SOURCE_WEB_CHECKOUT,
                Website::locale($request),
                $request->ip(),
            );
        }

        return $this->page($request, 'sent');
    }

    public function checkout(Request $request, OpenWebCheckout $open): Response|RedirectResponse
    {
        $token = $request->query('token');
        $result = $open->handle(is_string($token) && strlen($token) <= 255 ? $token : '');

        if ($result['state'] === 'redirect' && isset($result['url'])) {
            return redirect()->away($result['url'], 303)->header('Cache-Control', 'no-store, private');
        }

        return $this->page($request, $result['state'], ['reason' => $result['reason'] ?? null]);
    }

    public function thanks(Request $request): Response
    {
        return $this->page($request, 'thanks');
    }

    /**
     * Paddle.js opens the checkout for `?_ptxn=` by itself. The only website page with a script:
     * Paddle's, from its CDN (CSP below), plus our same-origin starter file.
     */
    public function pay(Request $request): Response
    {
        $token = PaddleConfig::clientToken();
        $response = $this->page($request, $token !== '' ? 'pay' : 'unavailable', [
            'clientToken' => $token,
            'paddleEnvironment' => PaddleConfig::environment(),
            'successUrl' => Website::route('website.subscribe.thanks', Website::locale($request) === 'fr' ? ['lang' => 'fr'] : []),
        ]);

        $paddle = 'https://*.paddle.com https://*.paddle.io';
        // Not behind SecurityHeaders (its CSP forbids scripts): the same headers, with Paddle allowed.
        $response->headers->set('Content-Security-Policy', "default-src 'none'; script-src 'self' https://cdn.paddle.com; style-src 'unsafe-inline' {$paddle}; img-src 'self' data: {$paddle}; connect-src {$paddle}; frame-src {$paddle}; font-src {$paddle}; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        // Paddle checks the approved website domain, so the origin (never the path) is sent.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');

        return $response;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(Request $request, string $state, array $data = []): Response
    {
        $locale = Website::locale($request);
        $currency = strtoupper((string) $request->query('currency', ''));
        $currency = in_array($currency, PricingCatalog::currencies(), true) ? $currency : PricingCatalog::defaultCurrency();

        return response()->view('subscribe.page', [
            'locale' => $locale,
            'state' => $state,
            'supportEmail' => (string) config('legal.support_email'),
            'canonicalRoute' => $state === 'form' || $state === 'unavailable' ? 'website.subscribe' : null,
            'pricing' => PricingCatalog::forCurrency($currency),
            'currencies' => PricingCatalog::currencies(),
            'selectedPlan' => 'annual',
            'code' => null,
            'src' => null,
            'reason' => null,
            ...$data,
        ])
            ->header('Content-Language', $locale)
            ->header('Cache-Control', 'no-store, private');
    }
}
