<?php

/*
 * Operations: alerts and the deep health check (docs/PLAN.md "B11 — Ops hardening",
 * docs/OPERATIONS_HANDBOOK.md §6, docs/DEPLOY.md §8).
 *
 * Run `php artisan config:cache` and restart `horizon` and `scheduler` after changing.
 */

return [

    /*
     * Where Horizon's "long wait" notifications and the failed-job alerts are mailed. Empty = no mail.
     */
    'alert_email' => env('OPS_ALERT_EMAIL', 'support@'.env('CENTRAL_DOMAIN', 'brainyclan.com')),

    /*
     * Optional incoming-webhook URL (Slack "Incoming Webhooks", or anything accepting a JSON
     * `{"text": "..."}` POST) that receives the same alerts. Empty = off. It is a secret.
     */
    'alert_webhook_url' => env('OPS_ALERT_WEBHOOK_URL'),

    /*
     * At most one failed-job alert per job class within this many minutes (Horizon's own long-wait
     * notifications are limited to one per queue every 5 minutes by Horizon).
     */
    'failed_job_alert_cooldown_minutes' => (int) env('OPS_FAILED_JOB_ALERT_COOLDOWN', 15),

    'health' => [
        /*
         * Token for `GET /api/v1/health/deep` (header `X-Health-Token`). Empty = the deep check
         * answers 404, so it is never public. A long random string (e.g. `openssl rand -hex 32`).
         */
        'deep_token' => env('HEALTH_DEEP_TOKEN'),

        /* The scheduler heartbeat (written every minute) must be younger than this. */
        'scheduler_max_age_seconds' => 180,

        /* Requests per minute per IP for the deep check. */
        'deep_rate_per_minute' => 30,
    ],

];
