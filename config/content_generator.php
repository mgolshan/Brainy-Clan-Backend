<?php

/*
 * AI batch generator (Filament → Content → AI batch generator), built on the Laravel AI SDK
 * (laravel/ai). The provider's API key lives in config/ai.php (e.g. ANTHROPIC_API_KEY); without
 * it the page shows "AI generation is not configured" and nothing is dispatched.
 * Run `php artisan config:cache` after changing these values.
 */

return [
    /* Provider name from config/ai.php "providers". */
    'provider' => env('CONTENT_AI_PROVIDER', 'anthropic'),

    'model' => env('CONTENT_AI_MODEL', 'claude-sonnet-5-5'),

    /* Upper bound for the model's answer; 50 items need roughly 8–12k output tokens. */
    'max_tokens' => (int) env('CONTENT_AI_MAX_TOKENS', 20000),

    /* Seconds the HTTP request to the provider may take. */
    'timeout' => (int) env('CONTENT_AI_TIMEOUT', 300),

    'max_items' => 50,

    /*
     * Queue for the generation job. `redis-ai` has a long retry_after and its own Horizon
     * supervisor (config/horizon.php, timeout 420 s) so a slow model call is never retried
     * by a second worker while it is still running.
     */
    'queue_connection' => env('CONTENT_AI_QUEUE_CONNECTION', 'redis-ai'),
    'queue' => env('CONTENT_AI_QUEUE', 'ai'),

    /* How many existing items of the category are listed in the prompt as "do not repeat". */
    'avoid_examples' => 40,
];
