# Brainy Clan - Backend

REST API and admin panel for **Brainy Clan**, a family learning and quiz mobile app.

- Laravel 13 on PHP 8.5, Filament 5 admin panel (Livewire 4)
- MySQL 8.4, Redis, Laravel Horizon for queues, Sanctum for API tokens
- Pest, Laravel Pint, Larastan, Laravel Boost (MCP server + AI guidelines)
- Everything runs in Docker. No PHP, Composer or MySQL needs to be installed on Windows.

Project rules and architecture notes for AI assistants and developers: [CLAUDE.md](CLAUDE.md).

**Production:** website `https://brainyclan.com`, API and admin `https://api.brainyclan.com`, support
`support@brainyclan.com`. Step-by-step deploy guide (Hetzner, Docker, DNS, HTTPS, email DNS, backups, Sentry):
[docs/DEPLOY.md](docs/DEPLOY.md).

**Project docs:** [docs/PLAN.md](docs/PLAN.md) (milestones and decisions), [docs/PROGRESS.md](docs/PROGRESS.md)
(status, next steps, session log), [docs/API.md](docs/API.md) (the app ↔ API contract).

### Quick start

1. Docker Desktop running, then once: `powershell -ExecutionPolicy Bypass -File scripts\setup.ps1`
   ([One-time setup](#one-time-setup)).
2. Every day: `docker compose --profile workers up -d` ([Daily use](#daily-use)).
3. Demo data: `docker compose exec app php artisan db:seed` on an empty database
   ([Seeded data](#seeded-data-php-artisan-migratefresh---seed)), then sign in to
   http://localhost:8080/admin as `admin@brainyclan.test` / `password`, or call the API as a demo
   member (`Authorization: Bearer demo-sam-child-token`).
4. Before handing in a change: [Tests and checks](#tests-and-checks).

### Setup guides (external accounts)

Everything below is optional for local development; each feature is skipped or answers a clear
error until its keys are in `.env`.

| Feature | Guide | `.env` keys |
| --- | --- | --- |
| Google and Facebook sign-in | [Google and Facebook sign-in — setup](#google-and-facebook-sign-in--setup) | `GOOGLE_CLIENT_ID_*`, `FACEBOOK_CLIENT_*` |
| Sign in with Apple + token revocation | [Sign in with Apple — setup](#sign-in-with-apple--setup) | `APPLE_CLIENT_IDS`, `APPLE_TEAM_ID`, `APPLE_KEY_ID`, `APPLE_PRIVATE_KEY` |
| Subscriptions (RevenueCat) | [RevenueCat](#revenuecat-family-plan-subscription) | `REVENUECAT_WEBHOOK_SECRET`, `REVENUECAT_API_KEY` |
| Rewarded ads (AdMob SSV) | [AdMob rewarded ads](#admob-rewarded-ads-free-tier-second-chance) | none required (`ADMOB_SSV_KEYS_URL` optional) |
| Push notifications (Expo) | [Expo push notifications](#expo-push-notifications) | none required (`EXPO_ACCESS_TOKEN` optional) |
| AI content generator | [AI batch generator](#ai-batch-generator-filament--content--ai-batch-generator) | `ANTHROPIC_API_KEY`, `CONTENT_AI_*` |
| Legal texts, consent, export, deletion page | [Legal, consent and privacy](#legal-consent-and-privacy-b9) | `LEGAL_*` |
| Error monitoring | [Error monitoring (Sentry)](#error-monitoring-sentry) | `SENTRY_LARAVEL_DSN` |
| Production server, HTTPS, backups | [docs/DEPLOY.md](docs/DEPLOY.md) | `.env.prod` (from `.env.prod.example`) |
| Antivirus breaking HTTPS in Docker | [TLS-inspecting antivirus](#tls-inspecting-antivirus-on-the-dev-machine) | - |

## Prerequisites

- Windows 11 with **Docker Desktop** using the **WSL 2 backend** (Settings > General >
  "Use the WSL 2 based engine"). Docker Desktop must be running.
- Free host ports: 8080 (web), 3307 (MySQL), 6380 (Redis), 8026 and 1026 (Mailpit; a Laragon Mailpit usually holds 8025/1025).
  MySQL and Redis use non-default ports so they do not clash with Laragon or other local
  installs.

## One-time setup

From the repository root, in PowerShell (e.g. PhpStorm's terminal):

```powershell
powershell -ExecutionPolicy Bypass -File scripts\setup.ps1
```

(WSL, Linux or macOS: `bash scripts/setup.sh`.)

The script:

1. builds the PHP 8.5 image and starts MySQL, Redis and Mailpit,
2. runs `docker/scripts/bootstrap.sh` inside the `app` container, which runs
   `composer install`, writes `.env`, adds Filament, Horizon, Sanctum, Boost, IDE Helper,
   Larastan and Pest, runs their installers and the migrations, and creates the admin user,
3. starts the whole stack (app, nginx, Horizon, scheduler),
4. runs `php artisan boost:install --no-interaction` (choices in `boost.json`: Claude Code,
   guidelines + MCP),
5. rewrites `.mcp.json` so the Boost MCP server runs inside Docker.

The first run downloads a lot and can take 5 to 10 minutes. It is safe to run again; steps
that are already done are skipped.

## Daily use

```powershell
docker compose --profile workers up -d      # start everything
docker compose --profile workers down       # stop everything (data is kept in volumes)
docker compose ps                           # what is running
docker compose logs -f app horizon          # follow logs
```

The `workers` profile adds Horizon (queue workers) and the scheduler. Without it,
`docker compose up -d` starts only the web stack, MySQL, Redis and Mailpit.

Run PHP-side commands inside the `app` container:

```powershell
docker compose exec app php artisan migrate
docker compose exec app php artisan make:model Example -m
docker compose exec app php artisan tinker
docker compose exec app composer require vendor/package
docker compose exec app php artisan test                 # Pest test suite
docker compose exec app ./vendor/bin/pest --filter=Example
docker compose exec app ./vendor/bin/pint                # fix code style (--test to check only)
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app bash                             # shell in the container
```

Handy PowerShell shortcuts (add to your profile: `notepad $PROFILE`):

```powershell
function art { docker compose exec app php artisan @args }
function dcomposer { docker compose exec app composer @args }
function pest { docker compose exec app ./vendor/bin/pest @args }
```

After changing queued jobs, restart the workers: `docker compose restart horizon`.
After changing `docker/php/Dockerfile` or `docker/php/php.ini`, rebuild:
`docker compose build app` then `docker compose --profile workers up -d`.

## Tests and checks

Every change is done when all four are green (run from the repository root):

```powershell
docker compose exec app php artisan test --compact                        # full Pest suite (~8 min on a Windows drive)
docker compose exec app php artisan test --compact tests/Feature/Api/GoalsTest.php   # one file while working
docker compose exec app ./vendor/bin/pint --dirty --format agent          # code style for changed files
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G    # Larastan, level 5
```

- Tests use in-memory SQLite and the array cache, never the dev database, so they are safe to run
  while the app is in use.
- Lazy loading is forbidden outside production (`Model::preventLazyLoading()`), so an N+1 fails the
  suite; list endpoints and busy Filament tables have query-count tests (`QueryCountTest`,
  `AdminQueryCountTest`).
- Guard tests worth knowing: `RouteSecurityTest` (every `/api/v1` route authenticated unless
  allowlisted, every route throttled), `SecurityReviewTest` (cross-family 404s, mass assignment,
  validation limits, rate limits, secrets at rest, audit log, production cookie/CORS settings),
  `ApiDocsTest` (every route in the Scramble spec).
- After changing PHP code on the running dev stack (OPcache does not watch files, see
  [Performance](#performance-important-on-a-windows-drive)):
  `docker compose exec app php artisan config:cache` then `docker compose restart app horizon scheduler`.
  Run `docker compose exec app composer dump-autoload -o` after adding or renaming classes.

## URLs and credentials

| What | Where | Login |
| --- | --- | --- |
| API | http://localhost:8080 (health check: http://localhost:8080/up) | Sanctum tokens |
| Admin panel (Filament) | http://localhost:8080/admin | `admin@brainyclan.test` / `password` |
| Horizon dashboard | http://localhost:8080/horizon | open in the local environment; elsewhere staff only (sign in at `/admin` first) |
| API docs (Scramble) | http://localhost:8080/docs/api (OpenAPI JSON: http://localhost:8080/docs/api.json) | open in the local environment |
| Mailpit (caught e-mails) | http://localhost:8026 | - |
| MySQL from the host | `127.0.0.1:3307`, database `brainy_clan` | `brainy` / `secret` (root: `root`) |
| Redis from the host | `127.0.0.1:6380` | - |
| Website (landing page, support) | http://localhost:8080/, http://localhost:8080/support (`?lang=fr`; default from the browser language) | public |
| Terms / privacy (drafts) | http://localhost:8080/terms, http://localhost:8080/privacy (`?lang=fr`; the old `/legal/...` URLs redirect) | public |
| Account deletion page | http://localhost:8080/account/delete (`?lang=fr`; confirmation mail in Mailpit) | public |

Inside the containers the services are reached by name: `mysql:3306`, `redis:6379`,
`mailpit:1025`. That is what `.env` uses.

## Website

This app also serves the public website (in production `https://brainyclan.com`; the API and admin
stay on `https://api.brainyclan.com`): landing page `/`, `/support`, `/privacy`, `/terms`,
`/account/delete`, `/password/reset`, `robots.txt`, `sitemap.xml`. Plain Blade pages in
`resources/views/website/` (and `legal/`, `account/`): English and French (`?lang=fr`, otherwise the
browser's language), light/dark, inline CSS, no JavaScript, no cookies, no analytics, no external
fonts or CDNs. The owl mascot is `public/images/owl.svg` (copied from the app repo's
`assets/brand/owl.svg`). Plan limits on the landing page come from the same policy settings as the
app. Routes: `routes/web.php`; host routing: `WEBSITE_URL` / `WEBSITE_HOST` (empty locally, so every
page works on `http://localhost:8080`; production setup in [docs/DEPLOY.md](docs/DEPLOY.md) section 4).

## API documentation

Interactive docs for every `/api/v1` endpoint: **http://localhost:8080/docs/api** (raw OpenAPI 3.1:
http://localhost:8080/docs/api.json).

- Generated by [Scramble](https://scramble.dedoc.co/) (`dedoc/scramble`, MIT) straight from the code -
  routes, Form Requests and API Resources. Config: `config/scramble.php`.
- Endpoints are grouped by module (`#[Group(...)]` on each controller: Health, Identity, Family,
  Content, Play, Scoring, Goals); summaries/descriptions come from the controller methods' PHPDoc,
  and `@response` fills in shapes Scramble can't infer.
- Error responses use the contract envelope `{ message, code, errors? }` with the possible `code`s
  per status (`App\Modules\Core\Docs\ErrorResponsesExtension`): codes implied by middleware are
  added automatically; domain errors thrown by Actions are declared with `#[ApiErrors('code', ...)]`
  on the controller method (status per code in `App\Modules\Core\Docs\ErrorCodes`).
- `tests/Feature/Api/ApiDocsTest.php` fails when an `/api/v1` route is missing from the spec.
- Sanctum bearer auth is documented automatically: routes behind `auth:*` middleware require a token,
  the rest (register, login, join, health) are marked public. Use "Try it" with `Bearer <token>`.
- Access: always open in the `local` environment; elsewhere the `viewApiDocs` gate
  (`AppServiceProvider`) decides, and it is closed for now.
- Export a static file: `docker compose exec app php artisan scramble:export` (writes `api.json`).
  Check generation problems: `docker compose exec app php artisan scramble:analyze`.
- **`docs/API.md` stays the contract.** The generated docs are a convenience view of the current
  code; if they disagree with `docs/API.md`, `docs/API.md` wins and the code gets fixed.
- Config is cached: after editing `config/scramble.php` run `php artisan config:cache`.

## PhpStorm tips

- **Database tool:** add a MySQL data source with host `127.0.0.1`, port `3307`, user
  `brainy`, password `secret`, database `brainy_clan`.
- **PHP interpreter (completion, Pest runner):** Settings > PHP > CLI Interpreter > `...` >
  `+` > *From Docker, Vagrant, VM, WSL, Remote...* > **Docker Compose**, configuration file
  `docker-compose.yml`, service **app**. Choose "Connect to existing container
  (docker compose exec)". Path mapping: project root -> `/var/www/html`. Then set the
  Pest/PHPUnit test framework to use this interpreter with `vendor/autoload.php`.
- **Code completion for facades and models:** the setup generates `_ide_helper.php`,
  `_ide_helper_models.php` and `.phpstorm.meta.php` (git-ignored). Regenerate after adding
  models or packages:
  `docker compose exec app php artisan ide-helper:generate`,
  `docker compose exec app php artisan ide-helper:meta`,
  `docker compose exec app php artisan ide-helper:models -N`.
- **Laravel Boost MCP / Claude:** `.mcp.json` starts the Boost MCP server with
  `docker compose exec -T app php artisan boost:mcp`, so the stack must be running. If
  `php artisan boost:install` or `boost:update` rewrites `.mcp.json` with a plain `php`
  command, restore the Docker version with
  `docker compose exec -T app php docker/scripts/write-mcp-json.php`.

## Seeded data (`php artisan migrate:fresh --seed`)

`migrate:fresh --seed` wipes the database; use it only on a fresh install. On a database you want to
keep, run `php artisan migrate` for new migrations and re-run single seeders, which are idempotent:
`php artisan db:seed --class=PolicySeeder` (age bands, tiers, settings), `CategorySeeder`,
`ChallengeTypeSeeder`, `ContentSeeder`, `WordPuzzleSeeder`, `AdminUserSeeder`, `DemoFamilySeeder`
(resets the demo tokens and invite codes) and `DemoActivitySeeder` (demo points history and goals).

| Who | How to sign in |
| --- | --- |
| Admin panel (staff) | `admin@brainyclan.test` / `password` |
| Mom (family owner, admin) | `POST /api/v1/auth/login` `mom@brainyclan.test` / `password` |
| Dad (adult) | `dad@brainyclan.test` / `password` |
| Sam (child, 9) | `Authorization: Bearer demo-sam-child-token` |
| Alex (teen, 14) | `Authorization: Bearer demo-alex-teen-token` |
| Join as a new child / teen | `POST /api/v1/auth/join` with code `CUBS2222` / `TEEN2828` (re-run `php artisan db:seed --class=DemoFamilySeeder` to reset) |

```sh
curl http://localhost:8080/api/v1/health
curl -X POST http://localhost:8080/api/v1/auth/login -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"mom@brainyclan.test","password":"password","device_name":"curl","platform":"web"}'
curl http://localhost:8080/api/v1/me -H "Accept: application/json" -H "Authorization: Bearer demo-sam-child-token"
curl http://localhost:8080/api/v1/categories -H "Accept: application/json" -H "Authorization: Bearer demo-sam-child-token"
```

### Signing a member in without email (sign-in codes)

Children (and anyone without an email login) sign in on a new device with a one-time code an
admin issues: 8 characters, valid 15 minutes (`sign_in_codes.ttl_minutes` setting), single use; a
new code voids the member's older unused codes. No code is seeded because they expire; issue one:

```sh
# 1. As Mom (token from /auth/login above), issue a code for Sam (member id 3 in the demo family; see GET /family)
curl -X POST http://localhost:8080/api/v1/family/members/3/sign-in-codes -H "Accept: application/json" -H "Authorization: Bearer <mom-token>"
# -> {"data":{"code":"VAXBD7KH","member_id":3,"expires_at":"..."}}

# 2. On the "new device": redeem it (case-insensitive, dashes/spaces ignored)
curl -X POST http://localhost:8080/api/v1/auth/code -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"code":"VAXBD7KH","device_name":"Sam iPad","platform":"ios"}'
# -> same payload as /auth/login, with Sam's new token

# 3. Use it, list/sign out devices
curl http://localhost:8080/api/v1/me -H "Accept: application/json" -H "Authorization: Bearer <sam-token>"
curl http://localhost:8080/api/v1/family/members/3/devices -H "Accept: application/json" -H "Authorization: Bearer <sam-token>"
```

In the admin, Families → a family → Members shows each member's email login, password flag and
devices, with **Sign-in code** (shown once in a notification) and **Sign out device** actions.
Admins can also give a teen/adult an email login with `PATCH /family/members/{id}` `{"email": "..."}`;
the member then sets a password with `PUT /me/password`. Only an HMAC of each code is stored.

## Google and Facebook sign-in — setup

Adults can sign in with Google or Facebook (children never: they use invite and sign-in codes).
The app signs in **natively** with the provider and sends the provider's token to
`POST /api/v1/auth/social/{google|facebook}`. The backend verifies it **with the provider** and
then signs in the linked user, links a user with the same provider-verified email, or creates a
new adult user (the app asks for birth year/month first: 422 `social_signup_incomplete`).

| | The app sends | The backend checks |
| --- | --- | --- |
| Google | the **ID token** (a JWT) | Google's signature (public keys from `googleapis.com/oauth2/v3/certs`, cached), issuer, expiry, and that the token's audience (`aud`) is one of **our** client IDs. The email is used only when Google says `email_verified`. |
| Facebook | the user **access token** (not iOS "Limited Login" tokens) | Graph API `debug_token` with our app ID + secret: valid and issued for **our** app; then `/me?fields=id,first_name,name,email` (via Laravel Socialite). |

Nothing breaks before this is set up: without the keys every social sign-in answers 401
`social_token_invalid` ("... is not configured on this server").

### Step 1 – create the Google and Facebook apps (consoles)

The console steps are shared with the app, so they live in **one place**: the frontend README,
section **"Google and Facebook sign-in setup"** (`Brainy-Clan-Frontend/README.md`, Step 1 = Google
Cloud console, Step 2 = Meta for Developers). Follow it once; it creates everything both repos need.
In short, you end up with:

- **Google Cloud project "Brainy Clan"** with the OAuth consent screen (Google Auth Platform:
  External, scopes `openid`, `email`, `profile`, test users while in Testing, **Publish app** to go
  public) and three OAuth clients:
  - **Web application** client — the important one. Its ID is the `aud` of ID tokens from the web
    app **and from Android** (the Android sign-in asks for a token "for" the web client).
  - **Android** client(s) — package `app.brainyclan` + SHA-1 of each signing key. They only have to
    exist; Android tokens still carry the web client ID.
  - **iOS** client — bundle ID `app.brainyclan`. iOS tokens may carry this ID as `aud`.
  - You never need a Google client **secret** on the backend (ID tokens are verified with Google's
    public keys).
- **Meta for Developers app "Brainy Clan"** (use case "Authenticate and request data from users with
  Facebook Login", permissions `public_profile` + `email`, Android/iOS/Website platforms) with an
  **App ID** and an **App secret** (App settings → Basic → App secret → **Show**). While the app is in
  **Development** mode only people with a role on the app (App roles → Testers) can sign in; switch
  it to **Live** when ready (Privacy policy URL, data deletion URL, category and icon are required;
  `public_profile` and `email` need no App Review).

### Step 2 – which ID goes where

| Value (from) | App (frontend `.env` / EAS) | Backend `.env` |
| --- | --- | --- |
| Google **Web** client ID | `EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID` | `GOOGLE_CLIENT_ID_WEB` (required) |
| Google **iOS** client ID | `EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID` | `GOOGLE_CLIENT_ID_IOS` |
| Google **Android** client ID | — | `GOOGLE_CLIENT_ID_ANDROID` (optional, only if a build ever sends tokens with this `aud`) |
| Facebook **App ID** | `EXPO_PUBLIC_FACEBOOK_APP_ID` | `FACEBOOK_CLIENT_ID` |
| Facebook **App secret** | **never** in the app | `FACEBOOK_CLIENT_SECRET` |

The backend accepts a Google ID token whose `aud` is **any** of the configured `GOOGLE_CLIENT_ID_*`.

### Step 3 – backend `.env`

Add to `.env` (the keys are in `.env.example`):

```dotenv
GOOGLE_CLIENT_ID_WEB=1234-abc.apps.googleusercontent.com
GOOGLE_CLIENT_ID_IOS=1234-def.apps.googleusercontent.com
GOOGLE_CLIENT_ID_ANDROID=
FACEBOOK_CLIENT_ID=1234567890
FACEBOOK_CLIENT_SECRET=0123456789abcdef0123456789abcdef
# FACEBOOK_GRAPH_VERSION=v23.0
```

Config is cached, so apply it with:

```sh
docker compose exec app php artisan config:cache
docker compose restart app horizon
```

(Config lives in `config/services.php` → `google.client_ids`, `facebook.*`.) In production set the
same variables on the server; the Facebook App secret is a secret (never commit it).

### Step 4 – test with a real token (no app needed)

**Google ID token via the OAuth 2.0 Playground**

1. Google Cloud console → your **Web** client → **Authorized redirect URIs** → add
   `https://developers.google.com/oauthplayground` → Save. Copy the web client's **Client secret**
   (only needed for the Playground, not for the backend).
2. Open https://developers.google.com/oauthplayground → gear icon (top right) → tick
   **Use your own OAuth credentials** → paste the Web client ID and secret. (Without this the token's
   `aud` is the Playground's own client and the backend correctly rejects it.)
3. Step 1: in "Input your own scopes" type `openid email profile` → **Authorize APIs** → sign in with
   a Google account that is a test user of the consent screen.
4. Step 2: **Exchange authorization code for tokens** → copy the `id_token` from the response
   (valid for 1 hour).

**Facebook access token via the Graph API Explorer**

1. Open https://developers.facebook.com/tools/explorer → **Meta App**: pick *Brainy Clan* (a token
   from another app is rejected) → **User or Page**: *Get User Access Token* → permissions
   `public_profile`, `email` → **Generate Access Token** → copy it. (You must have a role on the app
   while it is in Development mode.)
2. Optional check: https://developers.facebook.com/tools/debug/accesstoken shows the token's app ID.

**Call the API**

```sh
# First time: 422 social_signup_incomplete -> send birth data with the same token -> 201
curl -X POST http://localhost:8080/api/v1/auth/social/google -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"token":"<id_token>","device_name":"curl","platform":"web"}'
curl -X POST http://localhost:8080/api/v1/auth/social/google -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"token":"<id_token>","device_name":"curl","platform":"web","birth_year":1988,"birth_month":4}'
# Again later -> 200 (linked account). Facebook: same with /auth/social/facebook and the access token.

# Link / list / unlink for a signed-in adult (e.g. Mom from /auth/login)
curl -X POST http://localhost:8080/api/v1/me/social-accounts/facebook -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer <mom-token>" -d '{"token":"<facebook access token>"}'
curl http://localhost:8080/api/v1/me/social-accounts -H "Accept: application/json" -H "Authorization: Bearer <mom-token>"
curl -X DELETE http://localhost:8080/api/v1/me/social-accounts/facebook -H "Accept: application/json" -H "Authorization: Bearer <mom-token>"
```

If the Google email equals an existing user's email (e.g. you sign in with the Gmail you used to
register), the account is linked to that user and you get **200** instead of creating a new one.

**Troubleshooting** (`social_token_invalid` 401):

| Message / cause | Fix |
| --- | --- |
| "... is not configured on this server" | `.env` keys missing → Step 3 (`config:cache`!) |
| Google token rejected | `aud` isn't one of `GOOGLE_CLIENT_ID_*` (e.g. Playground without "own credentials"), or the token expired (1 h) |
| Facebook token rejected | token from another app (Graph Explorer app picker), expired, or an iOS Limited Login token |
| 403 `forbidden` | the matching user is not an adult (social sign-in is adults only) |

In the admin, Families → a family → Members shows each member's linked Google/Facebook/Apple accounts (column *Social sign-in*).
Code: `app/Modules/Identity/Social/*` (verifiers), `Actions/SignInWithSocial.php`,
`LinkSocialAccount.php`, `UnlinkSocialAccount.php`; tests fake the verifiers
(`tests/Feature/Api/SocialSignInTest.php`) or sign real JWTs with a throwaway key
(`tests/Feature/Actions/SocialTokenVerifiersTest.php`).

## Sign in with Apple — setup

Apple requires Sign in with Apple in an iOS app that offers Google/Facebook sign-in (App Store
guideline 4.8). The app signs in natively and sends Apple's **identity token** (a JWT) to
`POST /api/v1/auth/social/apple`; the backend verifies it against Apple's public keys
(`https://appleid.apple.com/auth/keys`), checks `iss`, `exp`, the optional `nonce`, and that `aud` is
one of **your** IDs. The backend needs **no secret** for sign-in — only the list of accepted IDs.

### Step 1 – Apple Developer Program

1. Enrol at https://developer.apple.com/programs/ (99 USD/year; an organisation needs a D-U-N-S
   number). Note your **Team ID** (Membership details, 10 characters, e.g. `A1B2C3D4E5`).

### Step 2 – App ID with the "Sign in with Apple" capability (iOS)

1. https://developer.apple.com/account → **Certificates, Identifiers & Profiles** → **Identifiers** → **+**
   → **App IDs** → **App** → Continue.
2. Description *Brainy Clan*, **Bundle ID** *Explicit*: the app's bundle ID (the one in the Expo
   `app.json` → `ios.bundleIdentifier`, e.g. `app.brainyclan`).
3. Under **Capabilities** tick **Sign in with Apple** (leave "Enable as a primary App ID") → Continue → Register.
4. In the app: Expo `ios.usesAppleSignIn: true` (expo-apple-authentication) and rebuild — the
   provisioning profile must include the capability.

The **bundle ID** is the `aud` of identity tokens from the iOS app.

### Step 3 – Services ID (only for web / Android sign-in)

Skip this if Apple sign-in is iOS-only. For sign-in on the web (or Android via the web flow):

1. **Identifiers** → **+** → **Services IDs** → Continue. Description *Brainy Clan Web*, Identifier
   e.g. `app.brainyclan.web` (must differ from the bundle ID) → Register.
2. Open it → tick **Sign in with Apple** → **Configure**: Primary App ID = the App ID from Step 2;
   **Domains and Subdomains** = your web domain (e.g. `brainyclan.com`, no `https://`);
   **Return URLs** = the exact HTTPS URL Apple posts back to (e.g. `https://brainyclan.com/auth/apple/callback`).
   Apple does not accept `localhost` or plain HTTP. → Next → Done → Continue → Save.

The **Services ID** is the `aud` of identity tokens from the web flow.

### Step 4 – Key for Sign in with Apple (token revocation on account deletion)

Not needed for sign-in itself. Account deletion (App Store guideline 5.1.1(v)) uses it to revoke
Apple tokens when a user deletes their account or unlinks Apple: **Keys** → **+** → name *Brainy Clan SIWA*, tick
**Sign in with Apple** → Configure → the App ID from Step 2 → Save → Continue → Register →
**Download** the `.p8` (only once!) and note the **Key ID**. Store the `.p8` safely (password
manager); it is a secret and never goes in git.

### Step 5 – backend `.env`

```dotenv
# comma-separated: iOS bundle ID, plus the Services ID if you use web sign-in
APPLE_CLIENT_IDS=app.brainyclan,app.brainyclan.web
# token revocation (Step 4): Team ID (Membership page), the key's Key ID, the .p8 contents
APPLE_TEAM_ID=ABCDE12345
APPLE_KEY_ID=XYZ987WVU6
# one line with \n for the line breaks (or a quoted multi-line value)
APPLE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nMIGT...\n-----END PRIVATE KEY-----"
```

Then:

```sh
docker compose exec app php artisan config:cache
docker compose restart app horizon
```

(Config: `config/services.php` → `apple.client_ids`.) Without `APPLE_CLIENT_IDS` every Apple token
gets 401 `social_token_invalid` ("Sign in with Apple is not configured on this server.").
Without the three key values sign-in still works, but Apple tokens are not exchanged or revoked
(logged as "Apple token exchange/revocation skipped").

### Step 6 – what the app sends

```json
{ "token": "<identityToken>", "nonce": "<raw nonce>", "display_name": "Moe",
  "birth_year": 1985, "birth_month": 3, "device_name": "iPhone 16", "platform": "ios" }
```

- `nonce` (optional, recommended): the app generates a random string, passes **SHA-256 hex** of it
  to Apple (`nonce` option of `signInAsync`), and sends the **raw** string here; the backend checks
  `sha256(raw) == token.nonce`.
- Apple returns the user's name (`fullName`) **only on the very first authorization** — send it as
  `display_name` then. Without it a new user is called "Player" (a private relay email is never
  used as a name); they can rename in `PATCH /me`.
- The email may be a private relay (`…@privaterelay.appleid.com`); it counts as verified. A real
  email that matches an existing adult links the Apple account to that user.
- First time without birth data → 422 `social_signup_incomplete` → ask and retry with the same token
  (identity tokens are valid for 10 minutes).
- `authorization_code` (optional, needed for revocation): the `authorizationCode` from the Apple
  sign-in result, on `POST /auth/social/apple` and `POST /me/social-accounts/apple`. The backend
  exchanges it once (valid 5 minutes) for a refresh token, stores it encrypted, and revokes it with
  Apple on unlink and on account deletion (`DELETE /me`, `DELETE /family`).

### Step 7 – test

- **Real device / TestFlight** (simplest): Sign in with Apple does not work in Expo Go for your own
  bundle ID — use a development build on an iPhone (signed in to an Apple ID with 2FA). Log the
  `identityToken` in a dev build if you want to replay it with curl within 10 minutes:

  ```sh
  curl -X POST http://localhost:8080/api/v1/auth/social/apple -H "Accept: application/json" -H "Content-Type: application/json" \
    -d '{"token":"<identityToken>","device_name":"curl","platform":"ios","birth_year":1985,"birth_month":3,"display_name":"Moe"}'
  ```
- **Web token without the app**: open
  `https://appleid.apple.com/auth/authorize?client_id=<Services ID>&redirect_uri=<Return URL>&response_type=code%20id_token&response_mode=form_post&scope=name%20email&nonce=<sha256 hex of a raw nonce>`
  — Apple POSTs `id_token` to the Return URL (check your server log / a request bin you registered
  as Return URL); send it as above with `"nonce":"<raw nonce>"`.
- **Without Apple at all**: `docker compose exec app ./vendor/bin/pest tests/Feature/Actions/SocialTokenVerifiersTest.php`
  signs Apple-shaped tokens with a throwaway key; a junk token must give 401:

  ```sh
  curl -X POST http://localhost:8080/api/v1/auth/social/apple -H "Accept: application/json" -H "Content-Type: application/json" \
    -d '{"token":"junk","device_name":"curl","platform":"ios"}'
  # -> 401 {"code":"social_token_invalid", ...}
  ```

Link / unlink for a signed-in adult: `POST` / `DELETE /api/v1/me/social-accounts/apple` (body
`token`, optional `nonce`). The admin shows linked Apple accounts under Families → Members → *Social sign-in*.
Code: `app/Modules/Identity/Social/AppleIdTokenVerifier.php` (shares `JwksIdTokenVerifier` with Google).

**Account deletion** (`DELETE /me`, `DELETE /family`) revokes Apple tokens when `APPLE_TEAM_ID`,
`APPLE_KEY_ID` and `APPLE_PRIVATE_KEY` are set (built in session 12).

## Subscriptions, rewarded ads and push — setup

The server decides the tier, ad rewards and notifications; the app only reports. Three outside
services are involved. Everything below works locally without them (tests fake all three); you
need real accounts only to try it on a device.

### RevenueCat (Family plan subscription)

1. **Create the project** at [app.revenuecat.com](https://app.revenuecat.com) → *Projects* → *New
   project* "Brainy Clan". Add the apps: *App Store* (bundle ID `app.brainyclan`, plus the App Store
   Connect in-app purchase key) and *Play Store* (package name, plus the Google Play service account
   JSON). Follow RevenueCat's own wizard for the store credentials.
2. **Products:** create the subscriptions in App Store Connect and Google Play Console first (e.g.
   `brainy_family_monthly`, `brainy_family_yearly`), then *Product catalog → Products → Import*.
3. **Entitlement:** *Product catalog → Entitlements → New* with identifier **`family`** (must match
   `REVENUECAT_ENTITLEMENT`) and attach every Family product. Create an *Offering* (`default`) with
   the packages the paywall shows.
4. **App user id:** the app calls `Purchases.logIn("family-{family_id}")` with
   `revenuecat_app_user_id` from `GET /api/v1/family/subscription`, so one purchase upgrades the whole
   family. Nothing to configure, just don't use anonymous ids for purchases.
5. **Webhook:** *Project settings → Integrations → Webhooks → Add*:
   - URL: `https://<your-host>/api/v1/webhooks/revenuecat`
   - Authorization header value: `Bearer <secret>` — generate a long random secret (e.g.
     `openssl rand -hex 32`) and put the same `<secret>` into `.env` as `REVENUECAT_WEBHOOK_SECRET`.
   - Environment: both (sandbox and production). Use *Send test event*: it answers 200 with
     `outcome: ignored`, and appears in the admin under **Billing → Webhook events**.
6. **REST API key (optional, recommended):** *Project settings → API keys → + New secret API key*
   (v1, read access is enough). Put it into `.env` as `REVENUECAT_API_KEY` (server only, never in the
   app). It enables `POST /api/v1/family/subscription/sync` (the app calls it while polling after a
   purchase/restore, so the tier switches without waiting for the webhook) and lets the hourly
   `billing:reconcile-subscriptions` re-check RevenueCat before downgrading a family whose expiry
   passed without an EXPIRATION webhook. Without the key, sync just returns the current state and
   the reconciliation marks such subscriptions expired after `REVENUECAT_RECONCILE_AFTER_MINUTES` (60).
7. `docker compose exec app php artisan config:cache` and `docker compose restart app horizon`.

Admin: **Families → (family) → Subscription** shows status, store, product, expiry and the
RevenueCat app user id; **Billing → Webhook events** lists every webhook with filters (provider,
outcome) and the raw payload. Both read-only — to fix a tier by hand use the family's *Edit* page.

Local check (dev secret from your `.env`):

```powershell
curl.exe -s -X POST http://localhost:8080/api/v1/webhooks/revenuecat -H "Authorization: Bearer $env:RC_SECRET" `
  -H "Content-Type: application/json" -d '{\"api_version\":\"1.0\",\"event\":{\"id\":\"test-1\",\"type\":\"TEST\"}}'
```

### AdMob rewarded ads (free-tier second chance)

1. In [AdMob](https://admob.google.com) add the iOS and Android apps and create one **Rewarded** ad
   unit per platform (reward item e.g. "Second chance", amount 1).
2. In each rewarded ad unit → *Server-side verification* → callback URL
   `https://<your-host>/api/v1/webhooks/admob-ssv` → *Verify URL* (answers 200 with
   `outcome: ignored`) → save. AdMob signs every callback; the server checks the signature with
   Google's public keys (`https://www.gstatic.com/admob/reward/verifier-keys.json`, cached for a day,
   refetched on an unknown key id). No secret to configure. AdMob must reach the URL, so this only
   works on a public host (or a tunnel such as `cloudflared` / `ngrok` while developing).
3. The app, before showing the ad, generates a UUID `reward_ref` and sets the SSV options
   `customData = "<reward_ref>:<session_id>"` and `userId = <member id>`. After the reward it calls
   `POST /api/v1/play/sessions/{id}/second-chance` with `method: "ad", reward_ref` — 409
   `ad_reward_pending` until the callback arrived (retry every 2 s), then 200; a used or foreign
   reward is 403. Rewards are stored in `ad_rewards` (single use).
4. **Test ad units** while developing (Google's samples, always fill):
   rewarded iOS `ca-app-pub-3940256099942544/1712485313`, Android `ca-app-pub-3940256099942544/5224354917`.
   Test units never call your SSV URL — to try the full flow use your real units with your device
   registered as a *test device* in AdMob (Settings → Test devices).
5. How ads are requested per age band (non-personalized, under age of consent, max rating) comes
   from `policy.ad_request` — edit it in the admin under **Policy → Age bands → Ad requests**.

Local check: a request with a bad signature must be rejected:

```powershell
curl.exe -s "http://localhost:8080/api/v1/webhooks/admob-ssv?ad_network=1&transaction_id=x&signature=AAAA&key_id=1"
# → 400 {"message":"…","code":"ssv_signature_invalid"}
```

### Expo push notifications

No key is needed: the server posts to Expo's push API (`https://exp.host/--/api/v2/push/send`) with
the `ExponentPushToken[...]` the app registers via `PUT /api/v1/me/push-token`. Delivery to devices
needs the app's push credentials in Expo (EAS: `eas credentials` → APNs key for iOS, FCM V1 service
account for Android) — that is configured on the app side, not here.

- Optional: if you turn on *Enhanced push security* in the Expo dashboard (Account → Access tokens),
  create a token and set `EXPO_ACCESS_TOKEN` in `.env`.
- Sent by the server (queued on Horizon, never during the member's quiet hours): `goal_reached`,
  `period_closed`, `family_activity` (a member finished a session; at most one per finishing member
  per hour; not to themself) and `daily_reminder` (scheduled every 15 min by
  `notifications:daily-reminders`: members whose reminder time falls in the current 15 minutes of the
  family timezone, who turned the reminder on and haven't started a session today).
- The `scheduler` container runs `goals:close-periods`, `notifications:daily-reminders` and
  `billing:reconcile-subscriptions`; `docker compose exec app php artisan schedule:list` shows them.

## AI batch generator (Filament → Content → AI batch generator)

Generates quiz content as **drafts** with Claude through the Laravel AI SDK (`laravel/ai`). Nothing
reaches players until you approve it. Sudoku and Math Sprint are generated by code and are not offered.

### Configure the key and model

1. Create an API key at <https://platform.claude.com> (Settings → API keys). Set a monthly spend limit there.
2. In `.env` (never commit it):

   ```dotenv
   ANTHROPIC_API_KEY=sk-ant-...
   # optional, defaults shown
   CONTENT_AI_MODEL=claude-sonnet-5-5
   CONTENT_AI_MAX_TOKENS=20000
   CONTENT_AI_TIMEOUT=300
   ```

3. Apply it:

   ```powershell
   docker compose exec app php artisan config:cache
   docker compose restart app horizon
   ```

Without a key the page shows **"AI generation is not configured"** and the form is disabled; existing
batches can still be reviewed. Other settings live in `config/content_generator.php`. Generation runs
on Horizon's `ai` queue (`supervisor-ai`, one worker, 420 s timeout, no automatic retry so a model
call is never paid twice). Check it at `/horizon`.

### Cost (rough)

Claude Sonnet 5.5 costs $2 per million input tokens and $10 per million output tokens. A 50-item
multiple-choice batch uses about 2–4k input tokens (rules, category, up to 40 "do not repeat"
questions) and about 8–15k output tokens (items plus the model's thinking), so roughly
**$0.10–0.20 per 50-item batch**; wisdom cards and word searches are a little more. Each batch shows
its real token counts in the table and on the review screen.

### Workflow

1. **Generate:** pick the category (the full path, e.g. *World Geography → Capitals → Europe*, goes
   into the prompt), type (multiple choice, wisdom card, anagram, missing letter, word search),
   difficulty range, ages, language, number of items (max 50) and an optional note
   ("focus on rivers"). Click **Generate drafts**. The batch is `queued`, then `running` (1–3 minutes).
2. **What the generator does:** sends the content rules (age-appropriate, factual, an explanation on
   every item, only original text or public-domain quotes with author and source; no modern authors,
   lyrics or book summaries), asks for structured JSON, then checks every item with the challenge
   type's own validation. Invalid items are dropped; wisdom-card quotes whose author died less than
   71 years ago (or without author/source) are dropped; duplicates of existing items (same normalized
   question / card text / word, in any category and status, including rejected ones) are skipped.
   The rest are stored as `draft` items of the batch, and the batch goes to `review`.
   A provider error or an answer with nothing usable sets `failed` (the error is shown; **Retry**
   runs it again).
3. **Review:** click **Review** on the batch. Each draft is shown as the app would show it (same
   preview as Content preview), with its explanation. Per item: **Approve** (publishes it, records you
   as reviewer), **Edit** (the normal content item form), **Reject** (archives it, so it is never
   generated again). Select several for bulk approve / reject, or **Approve all drafts**. The
   "Not stored" panel lists dropped and duplicate items; the prompt is shown for reference. When no
   drafts are left the batch is `done`.
4. **Check facts.** The model is told to use only well-established facts, but you are the editor:
   read every item before approving, especially dates, numbers and quote attributions.

## Performance (important on a Windows drive)

Each PHP file check over the Windows bind mount is slow, which made API requests take 6–14 s.
The setup now caches config and `.env` can switch off OPcache timestamp checks:

- `.env`: `PHP_OPCACHE_VALIDATE_TIMESTAMPS=0` (fast mode). **After changing PHP code run
  `docker compose restart app horizon`.** Remove the line (or set `1`) to see edits instantly but slower.
- **After changing `.env` or `config/*.php` run `docker compose exec app php artisan config:cache`**
  (or `config:clear` to stop caching).
- Tests are unaffected: they always use in-memory SQLite and ignore the dev caches.
- The first request per PHP worker after a restart still takes ~10 s; then ~0.5 s.

## Windows performance note

The code is bind-mounted into the containers from the Windows drive (e.g. `F:\`). File
access across the Windows/Linux boundary is noticeably slower than WSL 2's own filesystem;
OPcache and a large realpath cache are enabled to compensate. If requests or tests still
feel slow, move the project into the WSL 2 filesystem, for example
`\\wsl$\Ubuntu\home\<user>\projects\Brainy-Clan-Backend`, open it from there in PhpStorm
and run `bash scripts/setup.sh` from a WSL terminal (it builds the image with your WSL user
id so file ownership stays correct).

## Repository layout (infrastructure)

```
docker-compose.yml          services: app, nginx, mysql, redis, horizon, scheduler, mailpit
docker/php/Dockerfile       PHP 8.5 FPM image (extensions, Composer, non-root user)
docker/php/php.ini          PHP settings for development (OPcache, limits)
docker/php/php-fpm.conf     PHP-FPM overrides (FPM log: /tmp/php-fpm.log in the container)
docker/nginx/default.conf   nginx site for Laravel
docker/scripts/bootstrap.sh one-time setup that runs inside the app container
scripts/setup.ps1           one-time setup entry point (Windows)
scripts/setup.sh            one-time setup entry point (WSL / Linux / macOS)
phpstan.neon                Larastan configuration
```

## Legal, consent and privacy (B9)

- **Documents:** `config/legal.php` holds the current versions and "what changed" summaries; the texts are
  Blade views in `resources/views/legal/{en,fr}/`. They are **drafts for lawyer review** (Canada: PIPEDA,
  Quebec Law 25; US: COPPA) — the pages show a "Draft" banner until `LEGAL_DRAFT=false`. Fill the company
  details with `LEGAL_COMPANY_NAME`, `LEGAL_CONTACT_EMAIL`, `LEGAL_POSTAL_ADDRESS`, `LEGAL_PRIVACY_OFFICER`.
- **New version:** edit the text, bump `version` in `config/legal.php` and write the `summary`; every adult
  and teen then gets `me.legal.needs_acceptance: true` and the app asks again (`POST /me/legal-acceptance`).
- **Consents** (`consents` table, append-only): terms + privacy on sign-up (`accept_terms`), parental
  consent on child invites (`parental_consent`, linked to the member when the child joins). IPs are stored
  only as an HMAC. Visible read-only in the admin: Families → a family → *Consents*.
- **Data export:** `GET /api/v1/me/export` (1 per hour). **Account deletion without the app:**
  `/account/delete` (emailed one-time link; the confirmation page runs the same deletion as `DELETE /me`).

```sh
curl http://localhost:8080/api/v1/legal?lang=fr
curl -H "Authorization: Bearer <Mom's token>" http://localhost:8080/api/v1/me              # data.legal
curl -H "Authorization: Bearer <Mom's token>" "http://localhost:8080/api/v1/family/invites/preview?birth_year=2018&birth_month=1"
curl -H "Authorization: Bearer <Mom's token>" http://localhost:8080/api/v1/me/export
```

## Security notes

- **Tenancy:** every family-scoped id in a URL (`member`, `device`, `invite`, `plan`, `session`) is looked
  up inside the caller's family (or their own sessions), so another family's id answers **404
  `not_found`**, never 403. Ids inside bodies (goal members, session items) are checked the same way (422).
- **Rate limits** (`CoreServiceProvider`): `auth/*` (login, register, social, join, sign-in code)
  10/min per IP and 10/min per email address; everything else 120/min per user; `GET /me/export`
  1/hour; the `/account/delete` form 5/hour per IP; webhooks 600/min per IP.
- **Secrets at rest:** passwords bcrypt; sign-in codes only as an HMAC (`code_hash`); account
  deletion links only as SHA-256 (`token_hash`); deletion-page emails and consent IPs only as an
  HMAC; Apple refresh tokens encrypted with `APP_KEY` (`encrypted` cast, hidden from serialization);
  Sanctum tokens hashed by Sanctum. **Invite codes are stored in plain text on purpose:** admins
  see pending invites again (`GET /family/invites`) to read them out to a child; they are single
  use, expire after `invites.ttl_days` (default 7 days) and have 8 characters from a 32-letter alphabet (~10¹² combinations).
- **Logs and audit log:** no tokens, codes or personal data in log context; Sentry events are
  scrubbed (`SentryScrubber`). The audit log (Filament → Activity) records content and admin
  config changes and family name/timezone/tier changes; deleting a family drops the old values
  from its entries. Members, users and devices are not audited.
- **Admin access:** `/admin` and (outside `local`) `/horizon` only for users with `is_staff`
  (never settable through the API); the API docs are closed outside `local`.
- **Production settings** (`.env.prod.example`): `APP_DEBUG=false` (the container refuses to start
  otherwise), secure + httpOnly + SameSite=lax session cookies (secure by default when
  `APP_ENV=production`), encrypted sessions, and `CORS_ALLOWED_ORIGINS` empty unless a web build
  needs it (in production there is no localhost default).

## Sign-in lockout and password reset

**Lockout** (on top of the 10/min `auth` throttle; full rules in `docs/API.md` Auth → "Lockout"):
password sign-in (`/auth/login`) per email + IP: 5 failures → 5 min, the next 5 → 1 h, then 24 h
(reset by a successful sign-in or 24 h without failures); per email from all IPs: 20 failures in
1 h → 15 min, and the owner gets at most one "someone tried to sign in" email per 24 h; per IP:
30 failures in 1 h (login, sign-in codes, invite codes) → 1 h; `/auth/code` and `/auth/join` per
IP: 5 invalid codes → 5 min / 1 h / 24 h. A locked request gets 429 `login_locked` with
`retry_after` and a `Retry-After` header, the same for existing and unknown emails. Counters live
in Redis under HMAC-SHA256 keys (no raw emails or IPs). Thresholds: `config/auth.php` → `lockout`.

**Unlock someone by hand** (e.g. a support request):

```sh
docker compose exec app php artisan auth:unlock mom@example.com                 # email (all IPs)
docker compose exec app php artisan auth:unlock mom@example.com --ip=203.0.113.7 # also that IP
```

**Forgotten password:** the app calls `POST /api/v1/auth/password/forgot` (always 202). When the
email has an account, a one-time link to `WEBSITE_URL/password/reset?token=…` is emailed (valid
60 min, newest link wins, 3/h per email and 10/h per IP). The page (en/fr) sets the new password,
signs the user out of every device, clears all lockouts of that email and emails a "password
changed" notice. Locally the emails land in Mailpit (http://localhost:8025). Expired tokens are
removed daily (`auth:clear-resets`, scheduled).

## Error monitoring (Sentry)

`sentry/sentry-laravel` reports exceptions when `SENTRY_LARAVEL_DSN` is set (empty = off, the default in
development). `App\Modules\Core\Support\SentryScrubber` removes personal data from every event (no
bodies, cookies, auth headers, names, emails, birth data or IPs; only the numeric user id). Setup:
[docs/DEPLOY.md](docs/DEPLOY.md#10-sentry).

## TLS-inspecting antivirus on the dev machine

If outbound HTTPS from the containers fails with `cURL error 60` (e.g. Norton "Web/Mail Shield" scanning
HTTPS), either turn off HTTPS scanning for Docker or put the antivirus root certificate into
`docker/php/extra-ca/` and rebuild — see [docker/php/extra-ca/README.md](docker/php/extra-ca/README.md).
