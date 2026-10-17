<?php

/*
 * CORS for the API. Native apps do not need CORS; the Expo web build does
 * (dev server: http://localhost:8081). Add more origins with CORS_ALLOWED_ORIGINS
 * (comma separated), e.g. the production web app URL. Outside production the local Expo dev
 * origins are the default; in production the default is none (set the variable explicitly).
 */

return [

    'paths' => ['api/*', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        env('APP_ENV') === 'production' ? '' : 'http://localhost:8081,http://127.0.0.1:8081,http://localhost:19006',
    ))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining'],

    'max_age' => 3600,

    'supports_credentials' => false,

];
