<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
     * Social sign-in (README "Google and Facebook sign-in — setup").
     * Google: the backend only verifies ID tokens, so it needs no client secret. `client_ids` is
     * the list of accepted `aud` values: the web client ID (also used by Android and by iOS with
     * `serverClientID`) plus, optionally, the iOS and Android client IDs.
     */
    'google' => [
        'client_ids' => array_values(array_filter([
            env('GOOGLE_CLIENT_ID_WEB'),
            env('GOOGLE_CLIENT_ID_IOS'),
            env('GOOGLE_CLIENT_ID_ANDROID'),
        ])),
    ],

    /*
     * Facebook: verified with the Graph API (`debug_token` + `/me`) through Laravel Socialite.
     * `redirect` is unused (the app signs in natively) but Socialite expects the key.
     */
    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/'),
        'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v23.0'),
    ],

    /*
     * RevenueCat (B8): the webhook's Authorization header must be `Bearer <webhook_secret>`
     * (set the same value in the RevenueCat dashboard). Empty = every webhook is rejected.
     */
    'revenuecat' => [
        'webhook_secret' => env('REVENUECAT_WEBHOOK_SECRET'),
        'entitlement' => env('REVENUECAT_ENTITLEMENT', 'family'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
