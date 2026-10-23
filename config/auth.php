<?php

use App\Modules\Identity\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    /*
    |--------------------------------------------------------------------------
    | Sign-in lockout (docs/API.md Auth → "Lockout")
    |--------------------------------------------------------------------------
    |
    | On top of the 10/min `auth` rate limits. Counters and locks live in the cache store (Redis),
    | under HMAC-SHA256 keys of the normalized email / IP (never the raw values). Durations and
    | windows are in seconds. `durations` escalate: the first lock uses the first entry, the second
    | lock the second, every later lock the last one; the escalation resets after `reset_after`
    | seconds without a failure and after a successful sign-in. Unlock by hand: `auth:unlock`.
    |
    */

    'lockout' => [
        'store' => env('AUTH_LOCKOUT_STORE'),

        // POST /auth/login per email + IP: every 5 failures → 5 min, then 1 h, then 24 h.
        'email_ip' => [
            'attempts' => 5,
            'durations' => [300, 3600, 86400],
            'reset_after' => 86400,
        ],

        // POST /auth/login per email from all IPs: 20 failures within 1 h → 15 min; the owner gets
        // at most one "someone tried to sign in" email per `notify_every` seconds.
        'email' => [
            'attempts' => 20,
            'window' => 3600,
            'duration' => 900,
            'notify_every' => 86400,
        ],

        // Per IP across login, sign-in codes and invite codes: 30 failures within 1 h → 1 h.
        'ip' => [
            'attempts' => 30,
            'window' => 3600,
            'duration' => 3600,
        ],

        // POST /auth/code and POST /auth/join per IP: every 5 invalid codes → 5 min, 1 h, 24 h.
        'codes' => [
            'attempts' => 5,
            'durations' => [300, 3600, 86400],
            'reset_after' => 86400,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Forgotten password (POST /auth/password/forgot, /password/reset)
    |--------------------------------------------------------------------------
    |
    | The one-time link uses the `password_reset_tokens` table (SHA-256 of the token, one row per
    | email so a newer link replaces older ones) and expires after `passwords.users.expire`
    | minutes. Over a limit the API still answers 202 and no email is sent.
    |
    */

    'password_forgot' => [
        'per_email' => 3,
        'per_ip' => 10,
        'decay' => 3600,
        // POST /password/reset (the web form), per IP.
        'reset_per_ip' => 10,
    ],

];
