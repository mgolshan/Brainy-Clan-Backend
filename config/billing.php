<?php

/*
 * Pricing, web checkout (Paddle) and the affiliate program (docs/PLAN.md B10, docs/API.md "Web
 * checkout (Paddle) and pricing (B10)" and "Referral / affiliate codes (B10)").
 *
 * RevenueCat settings stay in config/services.php ("revenuecat").
 */

return [

    /*
     * Billing periods for the website and `GET /api/v1/pricing`. They mirror the prices set in App
     * Store Connect, Google Play, RevenueCat offerings and Paddle — change them there first. The
     * app never shows these: it shows the store prices from the RevenueCat offering.
     * `months` is used for the per-month price and the saving against the monthly plan.
     */
    'plans' => [
        'annual' => ['months' => 12, 'trial_days' => 7, 'highlighted' => true],
        'semiannual' => ['months' => 6, 'trial_days' => 7, 'highlighted' => false],
        'monthly' => ['months' => 1, 'trial_days' => 0, 'highlighted' => false],
    ],

    /*
     * Paid plan tiers in rank order (docs/API.md "Plans and tiers", B12): `family` < `big_clan` <
     * `classroom`. `highlighted` marks the tier the website and `GET /pricing` recommend.
     */
    'tiers' => [
        'family' => ['highlighted' => true],
        'big_clan' => ['highlighted' => false],
        'classroom' => ['highlighted' => false],
    ],

    'default_currency' => 'USD',

    /*
     * Prices per currency, tier and billing period, as decimal strings (docs/PLAN.md B12).
     */
    'prices' => [
        'USD' => [
            'family' => ['monthly' => '6.99', 'semiannual' => '29.99', 'annual' => '49.99'],
            'big_clan' => ['monthly' => '9.99', 'semiannual' => '39.99', 'annual' => '69.99'],
            'classroom' => ['monthly' => '14.99', 'semiannual' => '69.99', 'annual' => '119.99'],
        ],
        'CAD' => [
            'family' => ['monthly' => '8.99', 'semiannual' => '39.99', 'annual' => '64.99'],
            'big_clan' => ['monthly' => '12.99', 'semiannual' => '52.99', 'annual' => '89.99'],
            'classroom' => ['monthly' => '19.99', 'semiannual' => '89.99', 'annual' => '159.99'],
        ],
    ],

    'paddle' => [
        /* `sandbox` (https://sandbox-api.paddle.com) or `production` (https://api.paddle.com). */
        'environment' => env('PADDLE_ENVIRONMENT', 'sandbox'),

        /* Server-side API key (Paddle → Developer tools → Authentication). Never in the app or website. */
        'api_key' => env('PADDLE_API_KEY'),

        /* Secret key of the webhook destination (Developer tools → Notifications). */
        'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),

        /* Webhook timestamp tolerance (docs/API.md: 5 minutes). */
        'webhook_tolerance_seconds' => (int) env('PADDLE_WEBHOOK_TOLERANCE', 300),

        /*
         * Hosted checkout link from Paddle → Checkout → Hosted checkouts (e.g.
         * https://pay.paddle.io/checkout/hsc_…). The server appends `transaction_id`, `user_email`
         * and `locale`. Empty: the transaction's own `checkout.url` is used instead (your default
         * payment link, which must be the website page `/subscribe/pay`).
         */
        'hosted_checkout_url' => env('PADDLE_HOSTED_CHECKOUT_URL'),

        /* Client-side token for Paddle.js on `/subscribe/pay` (only needed without a hosted checkout). */
        'client_token' => env('PADDLE_CLIENT_TOKEN'),

        /*
         * Paddle price ids per tier and billing period (Catalog → Products → Prices, `pri_…`): one
         * Paddle product per tier with 3 prices. A tier is offered on the web only when all 3 of
         * its prices are set.
         */
        'price_ids' => [
            'family' => [
                'monthly' => env('PADDLE_PRICE_FAMILY_MONTHLY'),
                'semiannual' => env('PADDLE_PRICE_FAMILY_SEMIANNUAL'),
                'annual' => env('PADDLE_PRICE_FAMILY_ANNUAL'),
            ],
            'big_clan' => [
                'monthly' => env('PADDLE_PRICE_BIG_CLAN_MONTHLY'),
                'semiannual' => env('PADDLE_PRICE_BIG_CLAN_SEMIANNUAL'),
                'annual' => env('PADDLE_PRICE_BIG_CLAN_ANNUAL'),
            ],
            'classroom' => [
                'monthly' => env('PADDLE_PRICE_CLASSROOM_MONTHLY'),
                'semiannual' => env('PADDLE_PRICE_CLASSROOM_SEMIANNUAL'),
                'annual' => env('PADDLE_PRICE_CLASSROOM_ANNUAL'),
            ],
        ],

        /* Before B12 (Family plan only): still used as the Family prices when the FAMILY ones are empty. */
        'legacy_price_ids' => [
            'monthly' => env('PADDLE_PRICE_MONTHLY'),
            'semiannual' => env('PADDLE_PRICE_SEMIANNUAL'),
            'annual' => env('PADDLE_PRICE_ANNUAL'),
        ],

        /* Days a `past_due` subscription keeps its paid plan (Paddle's dunning period). */
        'grace_days' => (int) env('PADDLE_GRACE_DAYS', 30),
    ],

    'web_checkout' => [
        /*
         * Store countries (ISO 3166-1 alpha-3, as StoreKit / Play Billing report them) where the
         * app may link to web checkout (`GET /family/web-checkout/availability`). Empty by
         * default: re-check Apple 3.1.1/3.1.3 and Google Play payments policy before adding one.
         * Comma-separated in WEB_CHECKOUT_LINK_STOREFRONTS, e.g. "USA".
         */
        'link_storefronts' => array_values(array_filter(array_map(
            fn (string $code): string => strtoupper(trim($code)),
            explode(',', (string) env('WEB_CHECKOUT_LINK_STOREFRONTS', '')),
        ))),

        /* Lifetime of the emailed checkout link (docs/API.md: 30 minutes). */
        'link_ttl_minutes' => 30,

        /* `POST /web/subscribe` limits (docs/API.md). */
        'per_ip_per_hour' => 5,
        'per_email_per_hour' => 3,
    ],

    /*
     * Where the app sends an admin to manage a store subscription.
     */
    'store_manage_urls' => [
        'app_store' => 'https://apps.apple.com/account/subscriptions',
        'play_store' => 'https://play.google.com/store/account/subscriptions',
    ],

    'affiliates' => [
        /* Defaults for new affiliates (editable per affiliate). */
        'commission_rate' => 0.25,
        'commission_months' => 12,

        /* Commission entries become payable after this refund window. */
        'refund_window_days' => 30,

        /* Store fee assumed when RevenueCat sends no `commission_percentage` (Apple SBP / Google subscriptions). */
        'store_fee_rate' => 0.15,

        /*
         * Payout terms shown on the public partners page (`/partners`, docs/PARTNER_GUIDE.md). The
         * page reads every number from here (and the defaults above) and leaves out a line whose
         * value is empty. Payouts are manual (Billing → Monthly payouts → Export CSV → Mark paid).
         */
        'payout' => [
            /* `monthly` (the only schedule the admin report supports today), or null to hide. */
            'schedule' => 'monthly',

            /* Currencies commission is earned and paid in (the currencies we sell in). */
            'currencies' => ['CAD', 'USD'],

            /* Minimum balance per currency before a payout, decimal strings, e.g. ['CAD' => '50.00']. Empty: no minimum. */
            'minimum' => [],

            /* Methods offered (labels on the page): paypal, interac (Interac e-Transfer, Canada), bank (bank transfer). */
            'methods' => ['paypal', 'interac', 'bank'],
        ],
    ],

];
