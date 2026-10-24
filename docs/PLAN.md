# Brainy Clan Backend — Plan

Laravel 13 (PHP 8.5) REST API under `/api/v1` plus the Filament 5 admin, run with Docker.
The full product and architecture design is in the design doc linked in `CLAUDE.md`; this file
turns it into ordered backend milestones. Track status in `docs/PROGRESS.md`.

The app ↔ API interface is defined in **`docs/API.md`** (the contract). Implement endpoints exactly
as written there; if something must change, update `docs/API.md` first and note it in its changelog.

## Decisions taken during development

- **Response format (2026-10-10):** plain Laravel `JsonResource` envelope `{ "data": ... }` with
  snake_case keys, not full JSON:API (`type`/`attributes`). Errors always carry a machine `code`.
  Reason: much simpler to type in the TypeScript client; still versioned and consistent.
- **Age-adaptive UI theme (2026-10-10):** each age band's policy JSON includes a `ui_theme`
  (`child → playful`, `teen → sleek`, `adult → modern`, `senior → classic`) plus accessibility
  defaults (seniors: larger text, calm timers). Editable in the admin like the rest of the policy.
  Members can pick another theme in their accessibility settings; it never changes rules.
- **Age bands seeded:** child 0–12, teen 13–17, adult 18–64, senior 65+ (`senior` added to the
  design doc's bands so the classic theme and calmer defaults have a home).
- **Adults joining by invite** may set email + password during join; children never do.
- **Goal progress from the ledger, targets snapshotted per period (2026-10-11):** progress is always
  computed from `point_entries` (window × scope subtrees × plan members), never cached per session,
  because one answer can count toward several plans. Each `goal_periods` row snapshots
  `target_points` and `member_targets` when it opens, so plan edits apply from the next period;
  scopes and member nicknames are read live. A closed period stores its full GoalProgress +
  `winner_member_ids` in `result`, so later ledger rows never change history.
- **Goal period windows (2026-10-11):** family timezone, weeks start Monday; the first period is
  clipped to `starts_on` and the last to `ends_on`. The scheduler catches up missed periods one by one.
  Competition winners = rank 1 with more than 0 points (ties all win); cooperation winners = every
  plan member when the team reached the target.
- **Malformed answers are 422, not mistakes (2026-10-11):** each challenge type validates the shape of
  a response (`ChallengeType::validateResponse`) before checking it.
- **Void redemption items (2026-10-11):** after a failed redemption round its unanswered items are
  hidden from `items` and can no longer be answered.
- **Parallel development:** the frontend is built in parallel against `docs/API.md`, so the
  contract is fixed before each milestone starts.
- **Member sign-in without email (2026-10-11, requested by Moe):** children have no email, so
  an admin issues a **one-time sign-in code** (15 min, single use) for any member from their own
  phone; the member types it on the new device (`POST /auth/code`) and gets a device-bound token.
  Chosen over "the parent's email + a per-child code" because nobody shares a password, the
  parent stays in control, and it also works for adults who never set a password. Admins can list
  and sign out a member's devices (lost tablet). Admins can add, change or remove a member's
  **email login** when the member's band allows it (policy `email_login_allowed`: child false,
  teen/adult/senior true), e.g. once a child turns 13; the member then sets a password on their own
  device (`PUT /me/password`). `collect_email` keeps meaning "may self-register". Milestone B2b.
- **No N+1 queries, ever (2026-10-11, requested by Moe):** `Model::preventLazyLoading()` outside
  production (on in tests, so a lazy load fails the suite); every API action/resource and every
  Filament table eager-loads what it shows (`modifyQueryUsing(...->with(...))`, `withCount`,
  `->counts()` columns); list endpoints and Filament list pages get query-count tests
  (constant number of queries for 1 vs. many rows). Part of every milestone's definition of done.
- **Generated challenge items and points (2026-10-11):** Sudoku and Math Sprint items are generated
  per session from a seed (`GeneratesItems` contract, generators declared per category in
  `categories.generators`; payload + answer snapshotted on the session item). Items carry
  `points` (Sudoku 3/5/8 by size, word search 3, others 1); batches are sized so the target stays
  reachable within the blunder cap. Free-tier category order: Geography, Science, Wisdom (free),
  then Math Sprint, Sudoku, Word Puzzles, History, Vocabulary.
- **Rewarded-ad verification (2026-10-16):** AdMob's `transaction_id` is only visible server-side,
  so the app generates its own `reward_ref` and passes it as SSV custom data; the SSV webhook stores
  the reward and the second chance must present a matching, unused `reward_ref` (409
  `ad_reward_pending` while the callback is in flight). See `docs/API.md` → Rewarded ads.
- **Tier switches only via the server (2026-10-16):** after a purchase the app polls; an admin-only
  `POST /family/subscription/sync` pulls the subscriber from the RevenueCat REST API when a key is
  configured, plus a scheduled reconciliation for subscriptions whose expiry passed without a webhook.
- **Unfinished games (2026-10-17):** leaving a game keeps it resumable; the app offers "Continue
  your game" (`GET /play/sessions/current`); a new game abandons older unfinished ones; idle games
  are abandoned after 24 h so sessions don't pile up.
- **Full API documentation with Scramble (2026-10-11, requested by Moe):** every endpoint is
  documented with [Scramble](https://scramble.dedoc.co) (`dedoc/scramble`, free, MIT). It
  generates an OpenAPI 3.1 spec from the code itself (routes, Form Requests, API Resources), so
  the docs can't drift from the code. UI at `/docs/api`, JSON at `/docs/api.json` (importable in
  Postman/Insomnia, usable for client generation). Local only for now; in production behind the
  `viewApiDocs` gate. `docs/API.md` stays the hand-written contract (design intent, error codes,
  rules); Scramble is the complete reference. Add PHPDoc summaries/descriptions on controllers
  where the generated docs are unclear.
- **Social sign-in: Google and Facebook (2026-10-11, requested by Moe):** adults only (children
  keep invite codes, no email). Backend uses Laravel Socialite; the app signs in natively with the
  provider and sends the provider token to the API, which verifies it, links or creates the user,
  and returns a Sanctum device token like `/auth/login`. Setup instructions (Google Cloud console,
  Meta for Developers, keys in `.env`) must ship in the README with the feature. See milestone B6b.

- **AI batch generator (2026-10-11):** Laravel AI SDK (`laravel/ai`), provider Anthropic, default model
  `claude-sonnet-5-5` (`CONTENT_AI_MODEL`), structured output per challenge type, run on Horizon's own `ai` queue
  (long timeout, no automatic retries). Every item passes the type handler's validation; duplicates (normalized
  question / card text / word, any category, including rejected items) are skipped; items land as drafts and are
  published only by a reviewer. Reject = archive, never delete. Wisdom-card quotes need author, source and an author
  who died 71+ years ago; everything else must be original text. Sudoku and Math Sprint stay code-generated.
- **Admin dashboard (2026-10-17, requested by Moe):** the Filament dashboard shows stat cards and
  widgets for the things an operator checks daily: families/members/active players, plays and
  points (today, 7 days, trend), subscriptions (active, trial, grace, churn), content (published per
  category and locale, drafts waiting for review, AI batches), notifications/push health, failed jobs
  and webhook errors, and recent sign-ups. Each widget is a fixed small number of aggregate queries
  (no per-row queries; query-count test), aggregates cached for 60 s at most (dashboard figures may
  be a minute old; every list/resource page stays live). Staff-only like the rest of the panel.
- **Caching (2026-10-17, requested by Moe):** cache only where it cannot serve wrong data:
  reference data that changes only through the admin (policies, category tree, challenge types,
  settings, age bands, legal versions…) is cached forever and invalidated on every write (model
  events / the saving Action, including imports and seeders); per-member data is cached only with
  explicit invalidation on the events that change it, never TTL-only. Scores, points, answers,
  sessions, subscriptions and anything security-related (tokens, codes, throttles) are never served
  from a cache. Redis is the store; tests prove invalidation. Mechanism (2026-10-17): one helper,
  `ReferenceCache` (versioned keys, version bumped after the transaction commits, nothing stored
  from inside a transaction); cached models use `ReferenceDataBuilder`, so every Eloquent write
  (saves, mass updates, Filament reorder, seeders under `withoutEvents`) invalidates; raw SQL needs
  `php artisan reference-cache:flush`; `migrate` flushes automatically. No per-family caches yet.
- **Decisions (2026-10-22, Moe's answers):**
  - **Domains:** website `https://brainyclan.com` (`www` redirects to it), API and admin
    `https://api.brainyclan.com` (replaces every `brainyclan.app` in config/docs). Support email
    `support@brainyclan.com`, mail from `no-reply@brainyclan.com`. The backend serves the website
    from the same app/server (Caddy host routing): a bilingual landing page, `/support`,
    `/privacy`, `/terms`, `/account/delete`, `/password/reset`. These are the store listing URLs.
  - **Audience:** parents and children under 13 can both find and install the app. Google Play
    target audience: ages 6–8, 9–12, 13–15, 16–17, 18+ (mixed audience under the Families Policy):
    Families-certified ad SDK only (AdMob), no ads ever for children (policy), child-directed and
    under-age-of-consent tags where they apply, IARC rating "Everyone", Data safety form, no
    advertising ID for children. Apple: not in the Kids Category (it forbids third-party ads, which
    teens/adults on the free tier see); primary category Education, age rating 4+. All content is
    reviewed before publishing (seed bank fact-checked; AI batches need a reviewer).
  - **Ads:** free-tier teens and adults see rewarded ads (opt-in second chance); children never.
    Every ad request is non-personalized with max content rating **G**; teens are also tagged
    under the age of consent. Moe additionally blocks sensitive ad categories in AdMob (README).
    Business reason (Moe): ads on the free tier nudge families to the paid plan.
  - **Login lockout:** progressive, per email+IP, per email and per IP (`docs/API.md` Auth →
    Lockout), 429 `login_locked`; plus `POST /auth/password/forgot` with a web reset page so a
    locked-out or forgetful adult can always get back in. Replaces the "per-email throttle"
    trade-off question.
  - **Devices:** iPhone and iPad (app milestone; layouts get a max content width on tablets).
- **Family chat (2026-10-23, Moe):** one group chat per family, emoji text only, safe against
  XSS/script injection (plain text end to end: validated + normalized on the server, rendered as
  plain text in the app; no HTML/Markdown/links/attachments), retention free 7 days / Family plan
  1 year, only family members can see it — not even staff in Filament (no message text in the
  admin, logs, Sentry, activity log or push payloads; encrypted at rest with the app key). No
  end-to-end encryption for now (Moe: optional if complicated; it would break multi-device
  history for kids signing in with codes). Polling first (Laravel Reverb later if needed).
  Parents can turn chat off per member and delete any message. Contract: `docs/API.md` "Family
  chat". Privacy policy draft must mention the chat and its retention (lawyer review).
  **Built (2026-10-24):** `Chat` module; read marker as `family_members.chat_last_read_id`; a member
  who leaves keeps the `family_member_id` link (shown as "Former member" because the membership is
  no longer active) instead of nulling it, so account erasure still deletes those messages and
  `/me/export` still finds them; non-members get 404 (not 409 `no_family`); privacy version bumped
  to `2026-10-23` (adults/teens re-accept).
- **More categories and brain games (2026-10-23, Moe: "8 categories is not enough"; picked all):**
  8 new knowledge categories (Logic & Riddles, Language Swap EN↔FR, Inventions & Technology, Our
  Planet, Art & Music, Food Around the World, Sports & Games, Life Skills & Safety) with
  hand-written, fact-checked bilingual banks (Language Swap is language-bound: each locale learns the
  other language), plus a 17th category **Brain Games** with four new server-generated challenge
  types (`memory_match`, `pattern_sequence`, `nonogram`, `spot_difference`; contract in
  `docs/API.md` "Brain games"). Free tier still unlocks the first 3 top-level categories; new
  categories go at the end. Avoided on purpose: pop culture, celebrities, brands, current events,
  religion.
- **Landing page v2 (2026-10-23, Moe: "too simple, not motivational… show what the game is and
  how it looks without reading"):** visual-first page: hero with real app screenshots in phone
  frames (several worlds), a swipeable worlds gallery (CSS scroll-snap, works without JS; tiny
  optional enhancement script allowed only under the existing CSP with a nonce), "How it works"
  in 4 illustrated steps, categories with the Clan category art, "Meet the Clan" character strip
  (kids + parents + grandparents), real game screens (question, Sudoku, word search, goals,
  leaderboard). Images are real screenshots of the app from the demo family (no personal data),
  optimized WebP with fallback, lazy-loaded, explicit sizes, total page weight ≤ 2.5 MB. No
  cookies, trackers or external hosts. All claims stay true to PLAN/API. en + fr.
- **Theme worlds (2026-10-23, Moe):** theme keys gain `jungle`, `space`, `neon` (app-side design
  in the frontend PLAN); member `accessibility.sound_effects` (null|bool) with policy default
  `accessibility_defaults.sound_effects` (child true, others false; editable per age band in the
  admin). **Phase 2 (2026-10-23):** keys `sea`, `dino`, `sticker`, `lofi`, `nature`, `midnight`;
  a world catalog (key, order, unlock type free/points/streak/family_plan, threshold) stored as
  reference data editable in Filament (cached via ReferenceCache, invalidated on write); unlock
  evaluation per member from lifetime points + longest streak + family tier in a fixed number of
  queries (not cached); `GET /me/worlds`, admin `GET /family/members/{member}/worlds`, 403
  `world_locked`, Member `theme_unlocked` (see `docs/API.md`).
- **Launch question bank and French content (2026-10-17):** while the AI generator waits for an API key, the
  launch bank is hand-written as seed data in `database/seeders/data/questions/*.php` (one file per area, stable
  `id` per entry, English plus an optional French version; language-specific items such as spelling or idioms may
  exist in one language only). Seeded items are published (source `seed`) after a separate fact-check pass; Moe can
  archive any item in Filament. Rules: well-established facts only (no records, populations, "current" holders,
  disputed facts), exactly one defensible answer, plausible but clearly wrong distractors, original wording,
  child-safe. Translations share a `translation_group`; a member whose locale is not `en` gets the item in their
  locale and the English item only when no translation of it is published (picker dedupe, one `NOT EXISTS`).

## Working rules

- One milestone at a time. At the end of each, stop, summarize what was built and explain how to test it.
- Never commit. Moe reviews and commits from PhpStorm.
- Run every PHP, Composer, Artisan, Pest, Pint and PHPStan command through `docker compose exec app ...`.
- Check the current stable version of a package before adding it.
- Each milestone is done when: migrations, models, factories and seeders exist for new tables;
  Pest tests cover every Action and endpoint touched and pass; Pint and PHPStan (level 5) are clean;
  a Filament resource exists for any new content or settings table; every new or changed
  endpoint appears correctly in the Scramble docs (`/docs/api`); no N+1 queries (lazy loading is
  off in tests; list endpoints and Filament tables have query-count tests); `PROGRESS.md` is updated.

## Milestones

### B0 — Local environment
- Run `scripts\setup.ps1`: image build, Composer packages, Filament, Horizon, Sanctum, Pest, Larastan, IDE helper, Boost.
- Confirm `/up`, `/admin`, `/horizon` and Mailpit work; `pest`, `pint --test` and `phpstan` pass on the skeleton.
- Connect PhpStorm: remote PHP interpreter from the `app` service, database tool on `127.0.0.1:3307`, Boost MCP.

### B1 — Foundation
- Module layout `app/Modules/<Name>/{Models,Actions,Events,Listeners,Http,Policies}` with autoloading and a base module service provider.
- API versioning: `/api/v1` route group, JSON:API-style resources, consistent error format, rate limiting.
- `GET /api/v1/health` (JSON) for the app; `min_app_version` from settings.
- `HasApiTokens` on `User`; Sanctum device tokens.
- Activity log for the admin (audit trail).

### B2 — Identity, Family and invites
- Tables: `users` (birth year and month, locale, optional email/password), `devices`, `families`, `family_members`, `invites`.
- Age-band resolution and `PolicyResolver` (`age_bands`, `settings` tables) — ads allowed, difficulty range, data rules.
- Endpoints: adult sign-up and sign-in, create family, create invite (nickname + birth data), join with invite code (device-bound token, no password for children), list members, update member (difficulty override, accessibility).
- Filament: families and members (read-mostly, children by nickname), age bands, settings and feature flags.

### B3 — Content
- Tables: `categories` (tree, per-locale names, age range), `challenge_types`, `content_items` (payload and answer separated), `tags`, `content_batches`.
- `ChallengeType` interface and registry: validate payload, prepare for client (no answers), check answer, score.
- Filament: category tree editor, content items with filters and bulk actions, CSV import, content preview per age.
- Seed the 8 launch categories and their sub-scopes.

### B4 — First challenge types end to end
- Multiple Choice and Wisdom Card handlers with tests.
- About 50 seed items each.

### B5 — Play and Scoring
- Tables: `play_sessions`, `session_items`, `second_chances`, `member_item_history`, `point_entries`, `member_period_totals`, `streaks`.
- Start session (items without answers), submit answer (server checks), blunder cap, redemption round, free second chances for the Family tier.
- Append-only points ledger; events `AnswerSubmitted`, `SessionCompleted` with queued listeners for totals and streaks.

### B6 — Goals
- Tables: `goal_plans`, `goal_plan_scopes`, `goal_plan_members`, `goal_periods`.
- Periods in the family's timezone; scheduled job closes ended periods, writes results, opens the next one.
- Competition ranking vs cooperation team total; real-world reward text; history endpoints.

### B2b — Member sign-in without email
- Per `docs/API.md` (2026-10-11 changelog): `sign_in_codes` table, `POST /family/members/{member}/sign-in-codes`,
  `POST /auth/code`, member devices list/revoke, admin-managed member `email`, `PUT /me/password`,
  policy `email_login_allowed`. Filament: show a member's email login and devices; issue a code for support.

### B6a — API documentation (Scramble)
- Install `dedoc/scramble`, document all `/api/v1` endpoints with the Sanctum bearer scheme.
- Review the generated docs per module; add PHPDoc summaries, response examples and error codes
  where Scramble can't infer them. Keep it current in every later milestone (definition of done).

### B6b — Social sign-in (Google, Facebook)
- Contract first: add `POST /auth/social/{provider}` (`google` | `facebook`) to `docs/API.md`
  (body: provider token + device name; response like `/auth/login`; error codes for an invalid
  token, an email already used by a password account, and child accounts).
- Laravel Socialite (check the current version), a `social_accounts` table (user_id, provider,
  provider_user_id, email) so one user can link several providers; verify tokens server-side
  (Google ID token, Facebook access token via the Graph API); link by verified email or create
  a new adult user; never for children.
- Pest tests with faked provider responses; Filament shows linked providers on the user.
- **README setup guide** (step by step for Moe): Google Cloud console project, OAuth consent
  screen, client IDs for Android, iOS and web; Meta for Developers app, Facebook Login product,
  app ID/secret, Android key hashes and iOS bundle ID, app review for public use; the `.env` keys.
- Note: Apple requires **Sign in with Apple** when an iOS app offers third-party sign-in
  (App Store guideline 4.8) — plan it in the same milestone or before store submission.

### B6c — Sign in with Apple (requested by Moe, 2026-10-15)
- Status of the other providers: **Google and Facebook are done (B6b)**; Apple is added here so the
  iOS app can ship (App Store guideline 4.8: an app offering third-party sign-in must offer Sign in
  with Apple too).
- `POST /auth/social/apple` per `docs/API.md`: verify Apple's **identity token** (JWT signed with
  Apple's keys from `https://appleid.apple.com/auth/keys`, `iss` `https://appleid.apple.com`,
  `aud` = the iOS bundle ID or the web Services ID, `exp`, nonce when sent); `sub` is the stable
  user id; the email may be a private relay address; the name is only sent by the app on the very
  first sign-in. Link/unlink like Google/Facebook.
- **Account deletion** (App Store guideline 5.1.1(v), required for any app with account creation):
  `DELETE /me` for adults (and the owner deleting the family) plus Apple token revocation
  (`/auth/revoke` with the authorization code → refresh token, client secret JWT signed with the
  Sign in with Apple key). Plan it in this milestone or before store submission.
- **README setup guide** (step by step for Moe): Apple Developer account → App ID with the "Sign in
  with Apple" capability → Services ID + return URL for web → key (.p8, Key ID, Team ID) for
  token revocation → `.env` keys → test on a device/TestFlight.

### B7 — Remaining launch content
- Sudoku (seeded generator, verified on submit), Word Puzzles, Math Sprint (generated by code, age-scaled).
- AI batch generator page in Filament using the Laravel AI SDK; review and approve flow.
- Content for all 8 categories.

### B8 — Billing, ads and notifications
- RevenueCat webhooks (idempotent via `webhook_events`), `subscriptions`, entitlement checks, tier limits.
- Server-side verification of rewarded-ad second chances.
- Push tokens, reminders, family activity alerts, quiet hours.

### B9 — Production
- Production Docker image (multi-stage, OPcache on, no dev deps), `docker-compose.prod.yml` with
  Caddy (automatic HTTPS), MySQL, Redis, Horizon, scheduler; health checks and restart policies.
- Backups: nightly `mysqldump` (compressed, encrypted) to an off-site target (Hetzner Storage Box or
  S3-compatible), retention 14 days, restore drill documented.
- Sentry (`sentry/sentry-laravel`, approved in the design doc) with PII scrubbing (no child data).
- Step-by-step deploy guide for Moe: Hetzner VPS sizing (CX22 ≈ €5/month fits the budget), SSH keys,
  firewall, Docker install, DNS for brainyclan.com (website) and api.brainyclan.com, `.env` for production, first deploy, updates,
  rollbacks, monitoring.
- Privacy policy, terms and parental consent support in the API per `docs/API.md` "Legal, consent
  and privacy (B9)" — drafts must be reviewed by a lawyer before store submission.

### B10 — Pricing, affiliates and web checkout (Paddle) (planned 2026-10-24, Moe)
Decisions (Moe, 2026-10-24):
- **Pricing (Family plan, per family up to 7 members):** monthly US$6.99 / CA$8.99; 6 months
  US$29.99 / CA$39.99; annual US$49.99 / CA$64.99 (highlighted). 7-day free trial on 6-month and
  annual. Launch offer "founding families": 50% off the first year (store offer codes / Paddle
  discount). Store fee assumption: 15% (Google subscriptions; Apple Small Business Program). Prices
  live in the stores, in Paddle and in RevenueCat offerings — never hard-coded in the app; the
  website reads display prices from config.
- **Store product IDs (2026-10-24, docs fix):** the same IDs in App Store Connect and Google Play:
  `brainyclan_family_monthly`, `brainyclan_family_6months`, `brainyclan_family_annual`; all
  attached to the RevenueCat entitlement `family` (READMEs and the app's `docs/STORE.md` use these).
- **Web checkout = Paddle (merchant of record), not plain Stripe:** Moe does not want to handle
  sales tax; Paddle is the legal seller and collects/remits GST/HST/QST, US sales tax and VAT
  (fee ≈ 5% + 50¢). Same prices as the stores at launch. Rules: the website, emails and social
  posts may promote web checkout everywhere; inside the app, link to web checkout only where the
  store rules allow it (currently the US storefronts) — re-check Apple 3.1.1/3.1.3 and Google
  Play payments policy right before building; elsewhere the app only offers store purchases, and a
  plan bought on the web unlocks the app (multiplatform rule: the same plan is also sold in-app).
- **Affiliate program (in-house, commission-based):** influencer codes + links, commission on
  net revenue, monthly manual payouts; no third-party tracking/fingerprinting SDKs (kids app,
  Families Policy, ATT). Influencers promote to parents, with #ad disclosure.
Scope (backend):
1. **Billing sources:** the family's tier keeps one source of truth on our server. Add Paddle
   Billing: checkout created server-side for an adult admin (`POST /family/web-checkout` → Paddle
   checkout URL with `custom_data { family_id, affiliate_code? }`), Paddle webhooks
   (`/api/v1/webhooks/paddle`, signature-verified, idempotent like RevenueCat) for
   subscription.created/updated/canceled/past_due and transaction.completed/refunded, mapped to
   the same subscription states (active/trial/grace/expired) and `ApplyFamilyTier`. A family
   can't hold two active plans: block web checkout when a store plan is active and vice versa
   (paywall shows "managed in the App Store / Google Play / on the web"). If RevenueCat's Paddle
   integration is mature at build time, prefer routing Paddle through RevenueCat (one entitlement
   source); decide then and record it.
2. **Customer portal:** `GET /family/web-subscription/manage` → Paddle customer portal link
   (cancel, card update, invoices). Refunds/cancellations handled by Paddle.
3. **Affiliates:** tables `affiliates` (name, contact, payout method note, commission_rate default
   25%, commission_months default 12, status), `affiliate_codes` (code unique, case-insensitive,
   affiliate_id, optional store offer-code ids and Paddle discount id, active), `family_attributions`
   (family_id unique, code, source link|code|offer_code|web_checkout, first-touch, locked at the
   first paid transaction, editable only by staff), `commission_entries` (append-only ledger like
   points: family, affiliate, source event id unique, gross, store/Paddle fee, tax, net,
   commission, currency, status pending → payable after a 30-day refund window → paid; refunds and
   chargebacks add negative entries). Sources: RevenueCat webhooks (store purchases incl.
   `offer_code`) and Paddle webhooks. Endpoints: `POST /family/referral-code` (admin, once, before
   the first paid transaction), website `/r/{code}` landing variant (code banner + passes the code to
   web checkout; no cookies/fingerprinting). Filament: affiliates, codes, attributions, monthly
   payout report + CSV, "mark paid". Optional later: read-only influencer stats page via magic link
   (counts and amounts only, never family identities).
4. Privacy/legal: privacy policy + terms drafts mention Paddle as seller for web purchases and the
   referral code; Paddle seller onboarding needs the website, pricing, terms, refund policy and
   support email live.
Contract to be written in `docs/API.md` before building (coordinator).

### B11 — Ops hardening (from the operations handbook review, 2026-10-24)
- Horizon: route long-wait/failed-job notifications (mail to support@ or a webhook), schedule
  `horizon:snapshot` (metrics graphs), and add a scheduler heartbeat (a cache timestamp written every
  minute and checked by health).
- `/api/v1/health` deep mode for monitoring (MySQL, Redis, queue/scheduler freshness), still cheap,
  no secrets; the app keeps using the shallow check.
- Dashboard: count AdMob SSV rejections (and later Paddle webhook failures) next to RevenueCat
  webhook errors.
- Staff roles: a read-only "support" role in Filament (no edits, no chat — chat is never visible
  anyway) and a staff action to start a child's data export/deletion on a parent's verified request.
- App: `env` for EAS `preview`/`development` profiles so builds never fall back to localhost.

## Open questions
- **Answered by Moe (2026-10-22)** — see "Decisions (2026-10-22)" above: iPhone + iPad; Play
  target audience includes children (Families Policy); website `brainyclan.com`, support
  `support@brainyclan.com`; teens on the free tier see rewarded ads (age-appropriate only); login
  lockout designed by Claude; UMP message set up by Moe with the AdMob account.
- **Still waiting for Moe:**
  1. ~~Norton~~ — done 2026-10-22 (root cert in `docker/php/extra-ca/`, container rebuilt, outbound HTTPS OK).
  2. Lawyer review of the legal drafts, company details (`LEGAL_*`), then `LEGAL_DRAFT=false`.
  3. Accounts (Moe, a weekend): Hetzner, Apple Developer, Google Play Console, RevenueCat, AdMob
     (+ UMP message), Sentry, SMTP provider, Anthropic API key; production review account.
  4. ~~Mascot~~ — answered 2026-10-22: the Clan characters with Ollie the owl as leader and app icon.
  5. External AI agent team (Grok Bot) at launch: if it reads support emails or user data, that is
     personal-data processing by a third party — decide with the lawyer (DPA, privacy policy
     mention, what data it may see); the handbook already forbids sending personal data to
     external AI services until this is decided.
  6. Paddle seller account (web checkout, B10) once the website, terms and refund policy are live.
- Production hosting: same Docker images on Hetzner (default) or Forge without Docker.
- CIPO and USPTO trademark search for "Brainy Clan".
