# Brainy Clan Backend — Progress

Update this file at the end of every work session: milestone status, what changed, and what is next.
Milestones are defined in `docs/PLAN.md`.

## Status

| Milestone | Status | Notes |
| --- | --- | --- |
| Scaffold | Done | Laravel 13 skeleton, Docker stack, setup script, CLAUDE.md |
| B0 Local environment | Done | Setup ran end to end; Mailpit moved to host ports 8026/1026; Boost installed non-interactively |
| B1 Foundation | Done | Modules, `/api/v1`, error format, version check, rate limits, CORS, Sanctum device tokens, audit log |
| B2 Identity, Family and invites | Done | Auth (register/login/join/logout), me, family, members, invites, PolicyResolver, Filament |
| B3 Content | Done | Category tree, challenge type plugins + registry, content items, tags, batches, Filament (CSV import, preview page), 8 categories seeded |
| B4 First challenge types | Done | Multiple Choice + Wisdom Card handlers; 320 MC + 50 wisdom seed items |
| B5 Play and Scoring | Done | All /play/* + /me/stats + /family/leaderboard; Pest-tested; live smoke-tested; demo ledger history seeded |
| B6 Goals | Done | Migration, models, factory, actions, 6 endpoints, scheduled close job, events, Filament list + view page with periods/results, member removal cleanup, batched progress (no N+1), tests |
| B2b Member sign-in without email | Done | `sign_in_codes`, `POST /auth/code`, issue codes, member devices list/revoke, admin-managed `email`, `PUT /me/password`, policy `email_login_allowed`, Filament members (email login, devices, issue code, sign out device), Pest + live smoke test |
| No-N+1 guard | Done | `Model::preventLazyLoading()` outside production; query-count tests for every list endpoint and the busy Filament tables |
| B6a API documentation (Scramble) | Done | Module tags, contract error envelope + codes per endpoint (`ErrorResponsesExtension`, `#[ApiErrors]`), typed answer/leaderboard responses, summaries on every endpoint, spec coverage test |
| B6b Social sign-in (Google, Facebook) | Done | Socialite 5.31, `social_accounts`, verifiers (Google ID token via JWKS, Facebook `debug_token` + `/me`), sign-in/link/unlink/list, Filament column, README setup guide, Pest + live smoke test. Needs real Google/Facebook IDs to try end to end |
| B6c Sign in with Apple | Done | **Account deletion + Apple token revocation done** (session 12): `GET /me/deletion-check`, `DELETE /me`, `DELETE /family`, child erasure on member removal, one `EraseUser` action, `AppleTokenClient` (code exchange + revoke, ES256 client secret, skipped without keys), 12 Pest tests. **Sign-in done** (session 9): `AppleIdTokenVerifier` (JWKS `appleid.apple.com/auth/keys`, cached + refetch on unknown kid, `iss`, `aud` ∈ `APPLE_CLIENT_IDS`, `exp`, optional `nonce` = sha256 hex), provider `apple` on `/auth/social/{provider}` and `/me/social-accounts/{provider}`, `display_name` from the app (fallback "Player", never a private relay), Filament column "Social sign-in", README "Sign in with Apple — setup", Pest + live curl. |
| B7 Remaining launch content | In progress | Sudoku + Math Sprint generated per session, Word Puzzles seeded, item `points`, Filament preview + category generators; **AI batch generator done** (Laravel AI SDK, Horizon `ai` queue, review/approve page; needs `ANTHROPIC_API_KEY` to run for real). **Session 17:** launch question bank (`data/questions/*.php`, en + fr, strict validator + CI test), French content (translation groups, picker dedupe, French capitals/continents/math/word puzzles) seeded on dev. Fact-checked by independent reviewers (quotes string-matched against the public-domain texts) and re-seeded. Left: more content via the AI generator |
| B8 Billing, ads and notifications | Done | Billing core (s8), push token + preferences (s10), sending + goal notifications (s11); **session 13:** `has_own_login`, `family_activity` (1/member/hour), `daily_reminder` (every 15 min, family tz), `POST /family/subscription/sync` (RevenueCat REST v1), hourly reconciliation, AdMob SSV (`ad_rewards`, ECDSA) + ad second chance (409 `ad_reward_pending`), Policy `ad_request`, Filament subscription section + Webhook events, README setup guide. Needs real RevenueCat/AdMob accounts to try end to end |
| B9 Production | Done (needs a real server + lawyer) | **Session 14:** legal API (consents, `GET /legal`, terms/privacy pages en+fr drafts, `accept_terms`, `parental_consent`, invite preview, `me.legal`, legal acceptance, `/me/export`), account deletion web page `/account/delete`, Filament consents, Sentry (`sentry/sentry-laravel` 4.29, PII scrubber), production image (`docker/prod/Dockerfile`), `docker-compose.prod.yml` (Caddy HTTPS), encrypted off-site backups + restore drill, `docs/DEPLOY.md`, route security test. Not done on a real server: DNS/HTTPS, Storage Box upload, Sentry DSN. Texts need lawyer review |

## Next up

0. **State at last stop (session 17, 2026-10-17, launch question bank + French content):** done (see log). Dev DB:
   `migrate` (1 migration, `add_translation_fields_to_content_items`) + `ContentSeeder` and `WordPuzzleSeeder` re-run
   (idempotent via `source_ref`; no fresh). Expo receipts check done. **Next:**
   a. Done (coordinator, 2026-10-17): independent fact-check pass on all bank files, `countries_fr.php` and `words_fr.php`;
      both seeders re-run. Moe can archive any item in Filament (filter by locale / "Offered to other languages").
   b. Known seed quirks (pre-existing, not changed): the MC anagram refs `seed:anagram:<WORD>` collide with the
      word-puzzle anagram refs for words in both lists (APPLE, TIGER, …); whichever seeder runs last owns those rows
      (run `ContentSeeder` then `WordPuzzleSeeder`, as now). One orphan `seed:wisdom:99716a…` item (id 365 on dev)
      from an older wisdom text has no `translation_group` — archived by the coordinator (old Ramsey-like budget text).
   c. Contract note for docs/API.md (coordinator): none (content locale rule already noted 2026-10-17; no shape change).
   d. Then the items of session 16 below.
   Previous: **State at last stop (session 16, 2026-10-17, hardening pass part 2):** session 15's items a–c are **done**
   (indexes, security review, README; see log). Dev DB: `migrate` (1 migration, `add_hot_path_indexes`), no
   fresh, no reseed. **Next:**
   a. Item d below (admin config tables in the constant-query test) — optional, low value.
   b. Contract notes for `docs/API.md` (coordinator): new request limits — `POST /auth/login` `email`/`password`
      max 255; `PUT /me/password` `current_password` max 255; goals `category_ids` max 200 and `members` max 50;
      `POST /play/sessions/{id}/answers` `response` at most 10 keys, `response.found` max 100, `response.grid` max 16
      (all 422 `validation_failed`). `auth/*` throttle is now also 10/min **per email** (429 `rate_limited`).
   c. Then the items of session 14 below (Moe: server, lawyer, Norton cert, content).
   Previous: **State at last stop (session 15, 2026-10-16, hardening pass — cut short by a usage limit):** Expo connection
   errors handled with a bounded retry; N+1 audit partly done (new query-count tests, see log). No migrations.
   **Not done yet, in order (done in session 16 except d):**
   a. **Index check:** `devices.push_token` has no index but is looked up by value (`DeliverPushNotification` clears
      invalid tokens, `SetPushToken` clears the same token from other devices) → add an index via migration after an
      `EXPLAIN` on a seeded dataset. `play_sessions` "started since local midnight" (daily reminders) uses
      `member_id,status`; consider `(member_id, started_at)`. Other hot paths already indexed: `point_entries`
      `(family_id, created_at)` / `(member_id, created_at)`, `goal_periods (status, ends_at)` + `(goal_plan_id, starts_at)`,
      `ad_rewards` `transaction_id` unique + `reward_ref`, `consents (user_id, type, id)`, `notification_preferences`
      `family_member_id` unique, `session_items (play_session_id, position)`. EXPLAIN not run yet.
   b. **Security review** (not started): cross-family 404/403 tests for every route id (`member`, `device`, `invite`,
      `plan`, `session`), `$fillable`/guarded review, validation limits (string lengths, array sizes, numeric ranges),
      rate limits on auth/code/export/deletion page (exist; verify tests), secrets at rest (sign-in codes are
      `code_hash`, deletion tokens SHA-256, Apple refresh token encrypted cast — verified from schema/log only; invite
      codes are plaintext by design because admins re-display them), no PII in logs/activity log, `canAccessPanel`.
   c. **README refresh** (not started): setup, run, seeded logins/tokens, index of all setup guides, `docs/DEPLOY.md`
      link, tests/checks; "admin tour" still thin.
   d. ~~Admin config tables (age bands, settings, challenge types) have no relation columns; they are only in the
      render test, not the constant-query test (their row count does not grow with families).~~ Done 2026-10-22.
   Then the items of session 14 below (Moe: server, lawyer, Norton cert, content).
   Previous: **State at last stop (session 14, 2026-10-16):** **B9 done locally** (see log). Dev DB: `migrate` (2 migrations:
   `consents`, `account_deletion_requests`) + `AdminUserSeeder` and `DemoFamilySeeder` re-run (demo consents). **Next:**
   a. Moe: follow `docs/DEPLOY.md` on a Hetzner server (DNS for api.brainyclan.app, `.env.prod`, Storage Box, Sentry DSN,
      SMTP provider for the deletion emails), run the restore drill there.
   b. Lawyer review of `resources/views/legal/{en,fr}/*` (items marked "[DRAFT FOR LAWYER REVIEW]" / "[Lawyer review: …]",
      company details via `LEGAL_*`), then `LEGAL_DRAFT=false`; confirm the server location in the privacy policy.
   c. Dev machine: Norton "Web/Mail Shield" intercepts HTTPS, so containers get `cURL error 60` (and PECL/Alpine
      downloads fail in builds). Turn off HTTPS scanning for Docker or drop the Norton root cert into
      `docker/php/extra-ca/` (git-ignored) and `docker compose build app` (README "TLS-inspecting antivirus"). I could
      not export the certificate myself (not permitted).
   d. Content for the 8 categories (AI generator needs `ANTHROPIC_API_KEY`).
   Contract notes for docs/API.md (coordinator): `POST /me/legal-acceptance` for a child → 403 `forbidden`; stale
   version → 422 on `terms_version` / `privacy_version`; `/me/export` also has `social_accounts` and per-membership
   `notification_preferences`, response has `Content-Disposition: attachment` and `Cache-Control: no-store`;
   social sign-up without `accept_terms` → 422 `validation_failed` on `accept_terms` (checked after the birth/age
   checks); webhooks are now rate-limited (600/min per IP).
   Previous: **State at last stop (session 13, 2026-10-16):** **B8 done** (see log): `has_own_login`, `family_activity`,
   `daily_reminder`, Filament subscription + webhook events, subscription sync + reconciliation, AdMob SSV +
   `ad_rewards` + ad second chance, Policy `ad_request`, README setup guide. Dev DB: `migrate` (1 migration,
   `ad_rewards`) + `PolicySeeder` re-run (no fresh). **Next:** B9 / content (AI generator needs the key). To try
   billing/ads for real Moe follows README "Subscriptions, rewarded ads and push — setup" (needs RevenueCat, App
   Store / Play products, AdMob units on a public host). Contract additions for docs/API.md (coordinator):
   error `ssv_signature_invalid` (400) on the SSV webhook; SSV 200 body `{ data: { transaction_id, outcome:
   stored|duplicate|ignored } }`; `reward_ref` missing on `method: "ad"` → 422 `validation_failed`;
   `family_activity` push `data` `{ type, member_id, session_id }`; `daily_reminder` `data` `{ type }`.
   Optional later: Expo receipts check (`/push/getReceipts`). (Expo connection errors: fixed in session 15.)
   Previous: **State at last stop (session 12, 2026-10-16):** B6c **account deletion done** (see log). Migration
   `2026_10_16_100000_add_account_deletion_fields` run on the dev DB (`migrate`, no fresh). B8 `family_activity` /
   `daily_reminder` **not started** (time budget). **Next, in order:** B8 item 3 rest (a below), then b, c.
   Contract additions to confirm in docs/API.md: blocker `child_account` + 403 `forbidden` on `DELETE /me` for
   non-adults; `DELETE /family` is 403 for non-owner admins.
   Previous: **State at last stop (session 11, 2026-10-15):** flaky `GeneratedPlayTest` fixed (test-only); B8 item 3 (sending)
   **partly done**: Expo push sender, `SendMemberNotification` (preferences, quiet hours, 12 h drop), queued listeners
   for `GoalReached` / `PeriodClosed` (see log). No migrations. **Next, in order:**
   a. B8 item 3 rest: `family_activity` (listener on the play-session-finished event; recipients = other active family
      members; max 1 per finishing member per hour per recipient — e.g. a `Cache::add` key per member/hour; not to
      themself) and `daily_reminder` (scheduled every 15 min: members whose `reminder_time` falls in the window in the
      family timezone, `daily_reminder` on, not played today; one batched query). Both reuse `SendMemberNotification`.
   b. Optional: Expo **receipts** check (`/push/getReceipts`) — currently only ticket errors clear tokens.
   c. Then B8 items 4–6 (account deletion is done, session 12).
   Previous: **State at last stop (session 9, 2026-10-15):** goal time-of-day test failures fixed (test factory, see log) and
   B6c Sign in with Apple **sign-in** done; no migrations this session. Push token / notification preferences were
   not started (time budget). **Next, in order:**
   a. **Account deletion (B6c, before App Store submission):** `DELETE /me` for adults (owner: delete or hand over the
      family), plus Apple token revocation — the app sends the `authorizationCode` once, the server exchanges it at
      `https://appleid.apple.com/auth/token` (client secret = ES256 JWT signed with the `.p8` key: Team ID, Key ID,
      client id) for a refresh token, stores it encrypted on the Apple `social_accounts` row, and calls
      `/auth/revoke` on deletion. Needs `APPLE_TEAM_ID`, `APPLE_KEY_ID`, `APPLE_PRIVATE_KEY` and a contract change
      (an `authorization_code` field on `/auth/social/apple` + `DELETE /me`) — coordinator decides the contract.
   b. B8 items below — items 1 and 2 are **done** (session 10, 2026-10-15; see log); next is item 3 (sending).
   To try Apple for real Moe follows README "Sign in with Apple — setup" and sets `APPLE_CLIENT_IDS`.
   Previous: **State at last stop (session 8, 2026-10-14):** B8 billing core done (see log); dev DB `migrate`d (billing tables,
   no fresh); `.env` has a dev `REVENUECAT_WEBHOOK_SECRET`. **B8 left, in order:**
   1. ~~Done (session 10)~~ `PUT/DELETE /me/push-token` on the current device (the device row — and so the token — is already deleted on
      logout by `RevokeCurrentDevice`; also clear the same token from other devices when it moves accounts).
   2. ~~Done (session 10)~~ `GET/PATCH /me/notification-preferences` with defaults (children quiet hours 19:30–07:30), admin
      `GET` + `PATCH /family/members/{member}/notification-preferences` (GET added to the contract 2026-10-14).
   3. Sending: Expo push client (queued jobs, quiet-hours delay, drop after 12 h), listeners for `GoalReached` /
      `PeriodClosed`, `family_activity` (1/member/hour), `daily_reminder` scheduler.
   4. AdMob SSV `GET /webhooks/admob-ssv` + `reward_ref` on second chance `method: "ad"`.
   5. Filament: subscription + webhook events (read-only) on the family resource (eager-loaded, query-count test).
   6. Optional safety net: a scheduled sweep that downgrades families whose `active`/`grace` subscription is past
      expiry without an EXPIRATION webhook (RevenueCat normally sends it).
   - For the coordinator / `docs/API.md`: the webhook lives at **`/api/v1/webhooks/revenuecat`** (module routes are
     under `/api/v1`; the contract line says `POST /webhooks/revenuecat`). Its 200 body is
     `{ "data": { "event_id", "outcome": "applied|ignored|stale|duplicate" } }`; a body without `event.id`/`event.type`
     → 422. Point the RevenueCat dashboard at `https://<host>/api/v1/webhooks/revenuecat`.
   Previous: **State at last stop (session 7, 2026-10-11):** AI batch generator built (B7) and four app-reported play bugs fixed
   (see log). Dev DB `migrate`d (1 migration) and `CategorySeeder` re-run (9×9 Sudoku now min_age 10). Full suite 323
   green, Pint, PHPStan clean. **To use the generator** Moe sets `ANTHROPIC_API_KEY` in `.env` (README "AI batch
   generator"), `config:cache`, restarts app + horizon, then generates and reviews batches to fill the 8 categories.
   First real batch: check that 50 items fit in `CONTENT_AI_MAX_TOKENS` (20000); lower the count or raise the limit if a
   batch fails with a JSON/length error.
   Previous: **State at last stop (session 6, 2026-10-11):** B6b (social sign-in, incl. the coordinator's clarifications)
   and B6a (docs polish) done; dev DB `migrate`d (1 new migration, no fresh). Full suite 290 green, Pint, PHPStan clean.
   To try social sign-in for real, Moe creates the Google/Meta apps (README "Google and Facebook sign-in — setup")
   and fills `GOOGLE_CLIENT_ID_*` / `FACEBOOK_CLIENT_*` in `.env`. **Sign in with Apple** is still required before
   App Store submission (guideline 4.8) — not built.
   Previous: **State at last stop (session 5, 2026-10-11):** B7 minus the AI generator is done and live-tested
   (see log). Dev DB: `migrate` (1 new migration) + `CategorySeeder`, `ChallengeTypeSeeder`, `WordPuzzleSeeder`
   (no fresh). Decision for Moe: free-tier order (see log, "category order").
   Earlier (session 4): B2b done and live-tested; B6 leftovers done; the
   no-N+1 guard is on with query-count tests. Full suite / Pint / PHPStan green. Dev DB migrated
   (`migrate`, not fresh) and `PolicySeeder` re-run.
1. **Small open decisions for Moe:**
   - Only the owner may change the owner's email login (`PATCH /family/members/{owner}` with `email`
     from a co-admin → 403 `forbidden`). Not spelled out in `docs/API.md`; mirrors "an admin cannot
     demote the owner". Add a line to the contract if you agree.
   - Removing a member's email (`email: null`) clears the password but keeps their signed-in
     devices; sign them out via the devices endpoint if needed.
2. `Goals\Events\PeriodClosed` / `GoalReached` still have no listeners (notifications are B8).
3. README "admin tour" section is still thin.
4. Next: finish B8 (item 0), fill content for all 8 categories with the AI generator (needs the key), account deletion + Apple token revocation (B6c).
5. For the frontend / `docs/API.md` (coordinator): generated "Simplify a/b" math items now accept only the fully reduced
   fraction (`answer.reduced_only: true`; "14/16" or "0.875" for 21/24 is a wrong answer, not a 422).

## Known issues and notes

- **Performance on the Windows drive:** `.env` has `PHP_OPCACHE_VALIDATE_TIMESTAMPS=0` and config is cached.
  After changing PHP code: `docker compose restart app horizon`. After changing `.env` or `config/*`:
  `docker compose exec app php artisan config:cache` (or `config:clear`). First request per PHP-FPM worker
  after a restart is still slow (~10 s); warm requests ~0.5–0.8 s. Moving the repo into WSL2 removes this.
- **API docs:** generated by Scramble at http://localhost:8080/docs/api (local env only; `viewApiDocs` gate
  closed elsewhere). `docs/API.md` remains the contract; the generated page is a view of the current code.
- Tests always use in-memory SQLite and never read dev caches; `tests/TestCase.php` aborts if not SQLite.

- Boost: `boost.json` (committed) holds the choices (Claude Code, guidelines + MCP), so
  `boost:install --no-interaction` works in the setup scripts. `.mcp.json` runs Boost through Docker.
- Mailpit's host ports are 8026 (UI) / 1026 (SMTP) because a host Mailpit (Laragon) already uses
  8025/1025. Override with `MAILPIT_UI_PORT` / `MAILPIT_SMTP_PORT`.
- `EnsureAppVersion` is global middleware (so 426 comes before 401); it only acts on `api/*`.
- The test suite takes ~1 min on the Windows bind mount (first test boots slowly).
- **OPcache file cache survives `docker compose restart`.** With `validate_timestamps=0`, modified
  (not new) PHP files stay stale after a restart because `/tmp/opcache` (file cache) is inside the
  container. After PHP changes run: `docker compose exec app sh -c "rm -rf /tmp/opcache/*"` then
  `docker compose restart app horizon`.
- Goal periods are closed by `php artisan goals:close-periods` (scheduled every 5 minutes by
  `GoalsServiceProvider`; the `scheduler` container runs it). Current periods are also opened on
  demand when the API reads plans.
- **Per-email auth limit (session 16):** someone who knows an adult's email can keep that account's
  `/auth/login` throttled (10/min) — accepted trade-off against distributed password guessing; the
  person can still sign in with Google/Apple/Facebook (no email in that request) or an admin-issued sign-in code.
- PHPStan: `treatPhpDocTypesAsCertain: false` because Sanctum's `currentAccessToken()` can be a
  `TransientToken` at runtime.

## How to test (B0–B2)

```powershell
docker compose --profile workers up -d
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app ./vendor/bin/pest
curl http://localhost:8080/api/v1/health
```

- Log in as Mom: `POST /api/v1/auth/login` with `mom@brainyclan.test` / `password`.
- Call the API as the 9-year-old: `Authorization: Bearer demo-sam-child-token` (teen: `demo-alex-teen-token`).
- Join with invite codes `CUBS2222` (child) or `TEEN2828` (teen) via `POST /api/v1/auth/join`.
- Admin: http://localhost:8080/admin (`admin@brainyclan.test` / `password`) → Policy (age bands,
  settings), Families (members, invites with reset/revoke), System → Audit log.

## How to test (B3–B4)

- `GET /api/v1/categories` as Sam (`demo-sam-child-token`): age-filtered tree; as a free-tier
  user only the first 3 top-level categories are unlocked.
- Admin → Content: Categories (filter by parent, drag to reorder), Content items (filters, bulk
  publish/archive/retag, "Import CSV" with a downloadable example, Preview), Challenge types
  (switch a type off), Tags, Content batches, Content preview (pick an item and an age band).
- CSV import runs on the queue (Horizon must be running).

## How to test (B5)

- As Sam (`demo-sam-child-token`): `POST /api/v1/play/sessions {"category_id":1}` → session + 13 items;
  `POST /api/v1/play/sessions/{id}/answers {"session_item_id":..,"response":{"choice_id":"a"},"time_ms":1200}`;
  three wrong answers → `failed`; then `POST .../second-chance {"method":"free"}` (Family plan, 3/day) or
  `POST .../redemption` and answer the 3 `is_redemption` items.
- `GET /api/v1/me/stats`, `GET /api/v1/family/leaderboard?period=today|week|month|all`.
- `DemoActivitySeeder` adds ~2 weeks of `demo_seed` ledger rows + streaks for The Demos (runs once).
- Tests: `tests/Feature/Api/PlayTest.php`, `tests/Feature/Api/ScoringTest.php`.

## How to test (B6)

- `GET /api/v1/goals` as anyone in The Demos → "Pizza Friday" (weekly cooperation, target 100,
  Sam's target 20) with `current_period` progress.
- As Mom (login `mom@brainyclan.test` / `password`): `POST /api/v1/goals` with `name, mode,
  period_type, target_points, starts_on (today or later), category_ids [..], members?`;
  `PATCH /api/v1/goals/{id}`, `DELETE /api/v1/goals/{id}`, `GET /api/v1/goals/{id}/history`.
- `docker compose exec app php artisan goals:close-periods` closes ended periods and opens next ones.
- Admin → Families → Goal plans.
- Tests: `tests/Feature/Api/GoalsTest.php`.

## How to test (B2b — member sign-in without email)

- As Mom: `POST /api/v1/family/members/3/sign-in-codes` (Sam) → `{ code, member_id, expires_at }`.
- On the new device: `POST /api/v1/auth/code {"code":"ABCD-EFGH","device_name":"Sam iPad","platform":"ios"}`
  → AuthPayload; `GET /api/v1/me` with that token. Reusing the code, an older code, or waiting
  15 minutes → 422 `sign_in_code_invalid`.
- `GET /api/v1/family/members/3/devices` (admin or Sam) → devices with `is_current`;
  `DELETE /api/v1/family/members/3/devices/{id}` signs one out (its token stops working).
- Members in `GET /family` carry `email_login_allowed` for admins/self. `DELETE /api/v1/goals/{id}` → the plan is gone from `GET /goals` (404 on show).
- `PATCH /api/v1/family/members/4 {"email":"alex@example.com"}` (Alex, teen) → then as Alex
  `PUT /api/v1/me/password {"password":"...","password_confirmation":"..."}`; `email` for Sam (child) → 422.
- Admin → Families → The Demos → Members: email login, password flag, devices; **Sign-in code**
  and **Sign out device** row actions. Admin → Policy → Age bands: "Admins may give an email login".
  Admin → Families → Goal plans → view: periods and results.
- README section "Signing a member in without email" has copy-paste curl commands.
- Tests: `tests/Feature/Api/MemberSignInTest.php`, `tests/Feature/Actions/MemberSignInActionsTest.php`,
  `tests/Feature/Api/QueryCountTest.php`, `tests/Feature/Admin/AdminQueryCountTest.php`.

## How to test (B7 — Sudoku, Math Sprint, Word Puzzles)

- As Sam (`demo-sam-child-token`, 9 years, difficulty 1–4): `GET /api/v1/categories` → Math Sprint, Sudoku and
  Word Puzzles list `math_sprint` / `sudoku` / `word_puzzle` in `challenge_types`.
- `POST /api/v1/play/sessions {"category_id": <sudoku>}` → 4×4 and 6×6 Sudokus (`points` 3 / 5; 7–9 items instead of 13,
  since fewer bigger items reach the target). Solve one and send `{"grid": [[...]]}`; a malformed grid is a 422,
  an incomplete one a mistake. An adult gets 6×6 and 9×9 (`points` 5 / 8).
- `POST /api/v1/play/sessions {"category_id": <math-sprint>}` → generated `math_sprint` items mixed with the older
  multiple-choice ones; answer `{"value": "3/4"}` (also `"6/8"`, `"0.75"`, `"-12"`), `"abc"` → 422.
- Word Puzzles: anagram `{"word": "cat"}`, missing letter `{"word": "elephant"}`, word search
  `{"found": [{"word": "CAT", "start": [0,0], "end": [0,2]}]}` (3 points).
- Ledger: each correct answer appends one `point_entries` row with `amount` = the item's `points`.
- Admin: Content → Content preview → "…or a generated sample" (Sudoku 4/6/9, math modes; difficulty, seed);
  word puzzle items render too. Content → Categories → edit Sudoku / Math Sprint sub-scopes → "Generated items".
- Tests: `tests/Feature/Actions/GeneratedChallengeTypesTest.php`, `tests/Feature/Api/GeneratedPlayTest.php`,
  `QueryCountTest` (payload with generated items), `AdminPanelTest` (B7 preview).

## How to test (B7 — AI batch generator)

- Without a key: Admin → Content → **AI batch generator** shows "AI generation is not configured", form disabled.
- With a key (README "AI batch generator"): pick a category, type, difficulty, ages, locale, count, note → **Generate
  drafts** → batch `queued` → `running` (Horizon `/horizon`, queue `ai`) → `review`. **Review** → preview per draft →
  approve / edit / reject, bulk, or "Approve all drafts" → batch `done`; approved items are `published` with
  `reviewed_by`. A failed batch shows the error and offers **Retry**.
- Tests: `tests/Feature/Actions/AiBatchGeneratorTest.php` (faked model via `ContentBatchAgent::fake()`: valid items,
  invalid dropped, duplicates skipped, provider error / bad JSON / nothing usable → failed, wisdom-card public-domain
  rule, word puzzle variants, queueing + validation, job failure, approve/reject), `tests/Feature/Admin/AiBatchAdminTest.php`
  (page states, form → job, retry, review actions, query counts for the batch table and the review list).

## How to test (play fixes, session 7)

- `POST /api/v1/play/sessions {"category_id":28}` as `demo-alex-teen-token` → 201 (was 500 on MySQL).
- Math Sprint sessions never contain the same question twice (nor a content item's question).
- "Simplify 21/24": `{"value":"7/8"}` correct, `"14/16"` / `"0.875"` wrong.
- Sam (9) no longer sees the 9×9 Sudoku scope (`min_age` 10).
- Tests: `tests/Feature/Api/PlayFixesTest.php`.

## How to test (B6b — Google / Facebook sign-in, B6a — API docs)

- Without credentials: `POST /api/v1/auth/social/google {"token":"x","device_name":"curl","platform":"web"}` → 401
  `social_token_invalid` ("not configured"). As Mom: `GET /api/v1/me/social-accounts` → `{"data":[]}`;
  `DELETE /api/v1/me/social-accounts/google` → 404 `not_found`.
- With real IDs: README "Google and Facebook sign-in — setup" (Step 4: OAuth Playground ID token, Graph API Explorer
  access token, curl calls: 422 `social_signup_incomplete` → retry with birth data → 201 → later 200; link/list/unlink).
- Admin → Families → a family → Members: "Google / Facebook" column.
- Docs: http://localhost:8080/docs/api — 7 module groups; every error response shows `{message, code, errors?}` with
  the possible codes; `POST /play/sessions/{session}/answers` → `session` is `SessionResource`, typed `correct_response`;
  leaderboard entries fully typed.
- Tests: `tests/Feature/Api/SocialSignInTest.php` (faked verifiers, every path incl. rate limit),
  `tests/Feature/Actions/SocialTokenVerifiersTest.php` (real RS256 JWTs with a throwaway key + `Http::fake`, Facebook
  `debug_token` + mocked Socialite), `tests/Feature/Api/ApiDocsTest.php` (every `/api/v1` route in the spec, tags,
  error envelope), `QueryCountTest` (social accounts), `AdminQueryCountTest` (members with linked accounts).

## How to test (B8 — billing core)

- Tests: `docker compose exec app php artisan test --compact tests/Feature/Api/BillingTest.php` (fixtures in
  `tests/Fixtures/revenuecat/*.json`).
- Live: `.env` `REVENUECAT_WEBHOOK_SECRET=dev-revenuecat-secret`, then
  `curl -X POST http://localhost:8080/api/v1/webhooks/revenuecat -H "Authorization: Bearer dev-revenuecat-secret" -H "Content-Type: application/json" -H "Accept: application/json" -d @payload.json`
  with a fixture whose `app_user_id` is `family-1`, `event.id` unique and `expiration_at_ms` in the future.
  Check `GET /api/v1/me` (`policy.tier`) and `GET /api/v1/family/subscription` as `mom@brainyclan.test`.
  **The demo family (id 1) is seeded with tier `family` and no subscription** — after an EXPIRATION test restore it:
  delete its `subscriptions` row + test `webhook_events` and set `families.tier = 'family'`.

## How to test (B6c — Sign in with Apple)

- Without Apple: `docker compose exec app ./vendor/bin/pest tests/Feature/Actions/SocialTokenVerifiersTest.php tests/Feature/Api/SocialSignInTest.php`
  (Apple-shaped identity tokens signed with a throwaway RSA key; JWKS faked).
- Live contract check: `curl -X POST http://localhost:8080/api/v1/auth/social/apple -H "Accept: application/json"
  -H "Content-Type: application/json" -d '{"token":"junk","device_name":"curl","platform":"ios"}'` → 401
  `{"message":"...","code":"social_token_invalid"}`.
- Real token: README "Sign in with Apple — setup" (Step 7: device / TestFlight or the web authorize URL).

## How to test (B6c — account deletion)

- `docker compose exec app php artisan test --compact tests/Feature/Api/AccountDeletionTest.php`
- `curl -H "Authorization: Bearer demo-alex-teen-token" -H "Accept: application/json" http://localhost:8080/api/v1/me/deletion-check`
  (do not call `DELETE /me` or `DELETE /family` with demo tokens on the dev DB — erasure is permanent).
- Apple revocation for real needs README "Sign in with Apple — setup" Step 4–5 keys; without them it is skipped (log).

## How to test (B8 — push token, notification preferences)

- Tests: `docker compose exec app php artisan test --compact tests/Feature/Api/NotificationsTest.php` (13 tests).
- Live (demo seed): `curl -H "Authorization: Bearer demo-sam-child-token" -H "Accept: application/json" http://localhost:8080/api/v1/me/notification-preferences`
  → child defaults (quiet 19:30–07:30, reminder 17:00, `daily_reminder` off).
- Push token: `curl -X PUT http://localhost:8080/api/v1/me/push-token -H "Authorization: Bearer demo-sam-child-token" -H "Accept: application/json" -H "Content-Type: application/json" -d '{"token":"ExponentPushToken[abc123]","platform":"android"}'` → 204;
  `DELETE` the same URL → 204.

## How to test (B8 — sending notifications)

- Tests: `docker compose exec app php artisan test --compact tests/Feature/Api/PushNotificationsTest.php` (7 tests; fake
  sender bound via `app()->instance(ExpoPushSender::class, ...)`, HTTP sender via `Http::fake`).
- Deploy: `composer dump-autoload -o`, clear `/tmp/opcache/*`, `php artisan config:cache`, restart app + horizon
  (new queued listeners/job). Optional `.env`: `EXPO_ACCESS_TOKEN` (only if "enhanced push security" is on in Expo).

## How to test (B8 — finish: activity/reminders, sync, reconciliation, AdMob SSV, admin)

- Tests: `docker compose exec app php artisan test --compact tests/Feature/Api/PushNotificationsTest.php
  tests/Feature/Api/SubscriptionSyncTest.php tests/Feature/Api/AdRewardsTest.php tests/Feature/Admin/AdminQueryCountTest.php`
  (RevenueCat client, AdMob keys and Expo sender are faked; the SSV signature is made with a generated P-256 key).
- Deploy: `composer dump-autoload -o`, `php artisan migrate` (`ad_rewards`), `php artisan db:seed --class=PolicySeeder`
  (`ad_request`), clear `/tmp/opcache/*`, `php artisan config:cache`, `docker compose restart app horizon scheduler`.
- Scheduler: `php artisan schedule:list` shows `notifications:daily-reminders` (every 15 min) and
  `billing:reconcile-subscriptions` (hourly); both can be run by hand.
- Live: `GET /api/v1/me` as Mom → `data.member.has_own_login: true`; `POST /api/v1/family/subscription/sync` as Mom →
  current state (no `REVENUECAT_API_KEY` locally); `GET /api/v1/webhooks/admob-ssv?...&signature=AAAA&key_id=1` → 400
  `ssv_signature_invalid`. Admin: Families → (family) → *Subscription* section; Billing → Webhook events.

## How to test (B9 — legal, consent, privacy, production)

- Tests: `docker compose exec app php artisan test --compact tests/Feature/Api/LegalTest.php
  tests/Feature/Api/AccountDeletionPageTest.php tests/Feature/Admin/ConsentsAdminTest.php
  tests/Feature/Actions/SentryScrubberTest.php tests/Feature/Api/RouteSecurityTest.php`.
- Live (dev): `curl http://localhost:8080/api/v1/legal?lang=fr`; open http://localhost:8080/legal/privacy?lang=fr
  (Draft banner); `GET /api/v1/me` as Mom → `data.legal.needs_acceptance: false`;
  `GET /api/v1/family/invites/preview?birth_year=2018&birth_month=1` as Mom → child, `parental_consent_required: true`;
  `GET /api/v1/me/export` as Mom (a second call within an hour → 429). Account deletion: http://localhost:8080/account/delete,
  submit `dad@brainyclan.test`, open the link from Mailpit (http://localhost:8026) — **confirming really erases Dad**.
- Admin: Families → The Demos → *Consents* tab (read-only).
- Production image: see `docs/DEPLOY.md` "Local test of the production image"; backups: DEPLOY.md section 9.

## Log

### 2026-10-23 theme worlds + sound setting
- **Theme worlds** (PLAN "Theme worlds (2026-10-23, Moe)"): `AgeBand::THEMES` adds `jungle`, `space`,
  `neon`; `AgeBand::THEME_LABELS` gives the admin names (Candy Land, Glass, Modern, Classic, Jungle
  Adventure, Space Explorers, Neon Arcade) for the age band select and table. Band defaults unchanged.
  Content preview phone has palettes for the new worlds.
- **Sound effects**: member `accessibility.sound_effects` (null | bool, stored in the
  `family_members.accessibility` JSON, returned as stored; null = policy default) via
  `PATCH /me/accessibility` and the admin member PATCH; included in `/me/export`. Policy
  `accessibility_defaults.sound_effects` (PolicyResolver, fallback false), editable in the age band
  form. Seeder: child true, others false; data migration `2026_10_23_100000_...` backfills existing
  bands (keeps a value already set). PolicySeeder not re-run on dev (it overwrites admin policy edits).
- Tests: `tests/Feature/Api/ThemeWorldsTest.php`; `MeTest` uses `volcano` as the unknown theme.

### 2026-10-22 lockout, password reset, play perf
- **Sign-in lockout** (API.md Auth → "Lockout"): `Identity\Lockout\LoginLockout` (Redis via the
  cache, atomic counters, HMAC-SHA256 keys of email / IP), wired into `LoginWithPassword`,
  `SignInWithCode`, `JoinFamilyWithInvite`. 429 `login_locked` + `retry_after` + `Retry-After`
  (also on the failure that starts a lock); credentials not checked while locked; unknown emails
  hash a dummy password. Owner notice mail (`SignInAttemptsNotice`, once per 24 h). Thresholds in
  `config/auth.php` → `lockout`. `php artisan auth:unlock {email} {--ip=}`. Scramble documents
  `login_locked` with `retry_after`.
- **Forgot password**: `POST /auth/password/forgot` (always 202, 3/h per email, 10/h per IP
  silently dropped), token SHA-256 in `password_reset_tokens` (one row per email, 60 min), web
  page `GET/POST /password/reset` (legal layout, en/fr + switch, CSRF, 10 posts/h per IP). Success
  sets the password, revokes all tokens + devices (`RevokeAllDevices`), clears lockouts, queues
  `PasswordChanged`. Links built from `app.website_url`. `auth:clear-resets` scheduled daily.
- **Play performance**: `POST /play/sessions` 53 → 12 queries (one bulk insert of session items,
  one history upsert, no lookups on a brand-new session); `POST /play/sessions/{id}/answers`
  17 → 12 in tests (session loaded once with the lock, member reused; 4 of the 12 are the queued
  streak listener, which runs sync in tests). `tests/Feature/Api/PlayQueryCountTest.php`.
- Tests: `SignInLockoutTest`, `PasswordResetTest`, `PlayQueryCountTest`; `SecurityReviewTest`
  code-guess test now expects `login_locked` from the 5th guess.

### 2026-10-22 website + domains
- **Domains** (PLAN "Decisions (2026-10-22)"): every `brainyclan.app` in config/docs/tests → website
  `brainyclan.com` (`www` → 301 apex), API + admin `api.brainyclan.com`; support `support@brainyclan.com`
  (`config('legal.support_email')`, `LEGAL_SUPPORT_EMAIL`; `LEGAL_CONTACT_EMAIL` default too), mail from
  `no-reply@brainyclan.com`. New config `app.website_url` (`WEBSITE_URL`, falls back to `APP_URL`) and
  `app.website_host` (`WEBSITE_HOST`, empty = no host routing). `.env.prod.example`, `.env.example`,
  `docker-compose.prod.yml` (Caddy gets `WEBSITE_HOST`), Caddyfile (API host, website host, `www` redirect).
- **Website** served by this app (`routes/web.php`, `WebsiteController`, `resources/views/website/`): bilingual
  landing page `/` (`?lang=`, else Accept-Language; what it is, 8 categories, safe for kids, free vs Family plan
  with limits read from the policy settings, "coming soon" store notes), `/support` (FAQ: child sign-in code,
  forgot password, lockout, deletion, subscription, data; support email), `/terms` + `/privacy` (old
  `/legal/{terms,privacy}` → 301, query kept), `robots.txt` (API host: `Disallow: /`), `sitemap.xml` (en + fr with
  hreflang). Inline CSS, light/dark, no JS/cookies/analytics/external fonts; strict CSP (`SecurityHeaders`).
  Owl copied to `public/images/owl.svg`; Laravel `welcome` view and static `public/robots.txt` removed (dev nginx
  now sends `/robots.txt` to Laravel).
- **Host routing** (only with `WEBSITE_HOST`): `RouteByHost` (global; `www` → apex, `/api`, `/admin`, `/horizon`,
  `/docs`, `/livewire`, `/up` on the website host → API host) + `RedirectToWebsiteHost` (website group; website
  paths on the API host → website host; 301, 308 for non-GET). `Website::route()` builds legal URLs (`GET /legal`)
  and the deletion email link on `WEBSITE_URL`. The other agent's `/password/reset` routes sit inside the website group.
- **Admin:** read-only "Deletion requests" resource (Families group; created, account `#id · name · m***@domain`,
  status, confirmed at, completed at; status filter; view page; eager-loaded); dashboard "Pending deletions"
  card links to it (filtered to pending). Admin config tables (age bands, settings, challenge types) and
  deletion requests are now in the constant-query test (PROGRESS item d done).
- **Tests:** `tests/Feature/Api/WebsiteTest.php` (pages en/fr, language switch, links, plans, SEO, robots,
  sitemap, host routing, website URLs in `/legal` and the deletion email), LegalTest/SentryScrubberTest paths,
  AdminQueryCountTest, DashboardTest link.
- **Moe:** DNS A/AAAA for `brainyclan.com`, `www`, `api`; `support@` mailbox (MX at the mail provider);
  SMTP provider domain verification for `no-reply@` (SPF/DKIM/DMARC) — `docs/DEPLOY.md` section 4. Store
  listing URLs: `https://brainyclan.com`, `/support`, `/privacy`, `/account/delete`.

### 2026-10-17 caching
- **Audit** (queries per warm request, before → after): `GET /health` 0, `GET /legal` 0 (config),
  `GET /me` 6 → 6, `GET /categories` 1 → 0, `GET /family` 4, `GET /family/leaderboard` 3, `GET /goals` 1+,
  `GET /me/stats` 4, `POST /play/sessions` ~53 (writes), `POST …/answers` 17 (writes), `GET /play/sessions/{id}` 5.
  Plus per real request: Sanctum token lookup, user, `last_used_at` update (never cached). What is left is
  per-member/live data, so only reference data is cached.
- **Gaps found in the existing caches and fixed:** (1) Filament's category **reorder** is a mass `CASE`
  update that fires no model events → the category tree stayed in the old order forever; (2) any
  query-builder update/delete/insert (`Category::query()->update()`, `Setting::insert()`) and
  `withoutEvents`/`saveQuietly` writes bypassed invalidation; (3) the cache was forgotten **inside** the
  transaction, so a concurrent reader could refill it with the old rows before the commit (stale
  forever); (4) the same race with a slow reader that read the DB before the write and stored after the
  forget. Fix: one helper `App\Modules\Core\Support\ReferenceCache` — versioned keys
  (`ref:{name}:version` holds a random token, the value lives under `ref:{name}:{token}`, 7-day TTL for
  garbage), the token is replaced **after commit** (`DB::afterCommit`; a rollback leaves the cache alone),
  values computed inside an open transaction are never stored; and `ReferenceDataBuilder` (Eloquent
  builder via `#[UseEloquentBuilder]`) that invalidates after every write method (update, delete, insert*,
  upsert, increment*, touch, truncate…), which also covers model saves/deletes with events off.
  `php artisan reference-cache:flush` for raw-SQL edits; `MigrationsEnded` flushes everything. No tags
  (tag flushes are not atomic either; versioned keys work on every store).
- **Tests run with a Redis-like store:** array store with `serialize` on (`CACHE_ARRAY_SERIALIZE=true` in
  phpunit.xml; values must survive a round trip and contain no objects, as on Redis).

| Cached | Key | Invalidated by | Gain |
| --- | --- | --- | --- |
| Age bands + settings (`PolicyResolver`) | `ref:policy.v2:{token}` | any `AgeBand`/`Setting` Eloquent write: Filament age-band edit, settings create/edit/delete, `PolicySeeder`, mass updates, migrations | 2 queries per request/job that asks a rule (was cached before; now race-free and mass-update-proof) |
| Category tree (`CategoryTree`) | `ref:category-tree.v2:{token}` | any `Category` write: Filament edit/create, **reorder**, active toggle, `CategorySeeder`, deletes, mass updates | 1 query (`/categories`, play start, admin pages) |
| Challenge type records (`ChallengeTypeRegistry`) | `ref:challenge-types.v2:{token}` | any `ChallengeType` write: Filament toggle/edit, `ChallengeTypeSeeder`, mass updates | 1 query (`/categories`, play, session payloads) |
| Content availability (`ContentAvailability`, new) — distinct category/type/locale/cross_locale/age rows of published items; age, locale and active-type filters in PHP | `ref:content-availability.v1:{token}` | any `ContentItem` write: `SaveContentItem` (Filament create/edit), bulk publish/review/archive, CSV import, AI batch approve/reject, `ContentSeeder`/`WordPuzzleSeeder` (under `withoutEvents`), deletes, mass updates | 1 `DISTINCT` scan of `content_items` per `GET /categories` → 0 |

- **Deliberately not cached:** points/ledger, streaks, leaderboard, `/me/stats`, goal progress (live,
  append-only ledger); answers, play sessions and session items; subscriptions/tier (webhook-driven,
  entitlement); Sanctum tokens, sign-in codes, throttles, consents, second-chance usage (security/limits);
  `activeMembership()` and family member counts (per-user, a removed member must lose access at once —
  would need risky per-family invalidation for 1 indexed query); the play picker query (per member,
  depends on history). `GET /legal` and `/health` already run 0 queries (config + policy cache). Scramble
  docs are closed outside local (Scramble has its own `scramble:cache` if they are opened). The
  `exists:categories` rule on play start (1 PK lookup) stays: replacing it would change the API docs.
- **Production config verified:** `docker/prod/entrypoint.sh` runs `php artisan optimize` per container
  (config, events, routes, views + package tasks `filament:optimize` and `icons:cache`); OPcache on with
  `validate_timestamps=0`; optimized Composer autoloader. `route:cache`, `event:cache`, `view:cache`,
  `filament:cache-components` and `icons:cache` all succeed on this code base. Documented in
  `docs/DEPLOY.md` §7 (plus `reference-cache:flush`). Dev OPcache settings unchanged.
- Non-cache opportunities noticed (not done): play start does one insert per session item and one
  `member_item_history` select+insert per item (~53 queries; could be batched); answer loads the session
  and family twice (17 queries).
- Tests: `tests/Feature/Actions/ReferenceCacheTest.php` (helper: commit/rollback, no store inside a
  transaction, stale late writer, lost version key, flush command + migrations; each cache: 0 queries on
  the next request and every write path incl. Filament reorder/toggle/edit/delete, seeders, mass updates,
  `withoutEvents`), `tests/Feature/Api/ContentAvailabilityTest.php` (equivalence with the old SQL for
  ages × locales × type filters; publish, Filament bulk archive, CSV import, AI approval, mass update,
  delete, ContentSeeder). With invalidation switched off, 13 of these tests fail. Full suite 596 passed;
  Pint and PHPStan clean.

### 2026-10-17 admin dashboard

- PLAN "Admin dashboard (2026-10-17)". The Filament dashboard (`/admin`) replaces the lone account
  widget with widgets in `app/Filament/Widgets` (auto-discovered, polled every 60 s):
  - stat cards: **Activity** (families + new in 7 d, members + played today / 7 d, plays today with a
    7-day sparkline, points awarded today / 7 d with sparkline), **Subscriptions and content** (paying /
    trial / grace, family-plan share, published items with en/fr split, items awaiting review + AI
    batches to review), **System health** (failed jobs + last 24 h → Horizon, webhook errors 7 d →
    filtered webhook events, push-ready devices + failed push jobs, pending/blocked deletion requests);
  - charts: plays per day (30 d, full width), new families per day (30 d), plays per top-level
    category (7 d, bar);
  - lists: recent families (plan, subscription, `withCount` members), AI batches awaiting review
    (→ batch review page), recent webhook errors (shown only when there was one in 7 days).
  Cards and lists link to the matching resource page (with table filters where useful).
- All aggregates live in `App\Filament\Support\DashboardMetrics` (sections activity / trend /
  plays-per-category / business / health): fixed GROUP BY / COUNT queries, `Cache::remember` 60 s,
  UTC calendar days (`DATE()` works in MySQL and SQLite). Category names come from the cached
  `CategoryTree`. Chart.js options in `DashboardChartOptions`.
- Migration `2026_10_17_400000_add_dashboard_indexes`: covering indexes `play_sessions(started_at,
  member_id, category_id)` and `point_entries(created_at, amount)` (EXPLAIN showed full scans of both
  for the 7/30-day windows; now range scans).
- Tests: `tests/Feature/Admin/DashboardTest.php` (10 tests): dashboard renders for staff (403
  otherwise), exact figures for a known dataset per section and widget, lists, cache TTL, constant
  query count per widget for small vs. large data (cache flushed between), no aggregate queries
  while the cache is warm.

### 2026-10-17 (session 17: launch question bank, French content, Expo receipts)

- PLAN "Launch question bank and French content (2026-10-17)". Migration
  `2026_10_17_300000_add_translation_fields_to_content_items`: `translation_group` (nullable string 64),
  `cross_locale` (bool, default true), index `(translation_group, locale, status)`. Model fillable/casts;
  `SaveContentItem` stores them (blank group → null); CSV import accepts optional `translation_group` / `cross_locale`
  columns (empty cells keep the stored value).
- `ContentItem::whereAvailableInLocale()`: `en` member → `en` items only; other locale L → items in L, plus `en` items
  with `cross_locale` and no published L item in the same `translation_group` (one correlated `NOT EXISTS`). Used by
  `PickContentItems::query()` and (without the dedupe) by `BuildCategoryTree` so a category's `challenge_types` only
  lists types playable in the member's locale.
- `ContentSeeder`: existing items keep their `source_ref`s and now get `translation_group` = ref without `seed:`;
  `LANGUAGE_BOUND_CATEGORIES` constant (anagrams, missing-letter, word-search, spelling, idioms, synonyms,
  grades-1-3/4-6/7-9) → those English items `cross_locale` = false (also MC anagrams). French times tables and mental
  math ("Combien font 3 × 4 ?", same group, ref `…:fr`). French capitals/continents from `data/countries_fr.php`
  (optional `capital_question`/`continent_question`, else a generic French wording; distractors = French capitals of
  the same scope / French continent names; capital item skipped when a scope has < 3 other French capitals).
  Question bank: `QuestionBank::load()` (new `database/seeders/QuestionBank.php`) reads `data/questions/*.php` sorted,
  validates strictly (duplicate id across files, unknown/non-leaf category, difficulty 1-10, 3 wrong for MC / 2 for
  wisdom cards, answer or duplicates in `wrong`, empty strings, no locale, unknown type; message names file + id),
  then seeds per locale: ref `seed:q:<id>:<locale>`, group `q:<id>`, `min_age` override, `cross_locale` (entry value,
  default true except language-bound categories), deterministic shuffle seeded with the ref; wisdom question
  "What is the main idea?" / "Quelle est l'idée principale ?". Existing seed rows prefetched once per run.
- `WordPuzzleSeeder`: English puzzles `cross_locale` = false; `data/words_fr.php` (validated: uppercase A-Z, hint)
  seeds French anagrams / missing letters / word searches (locale `fr`, refs `seed:fr:…`).
- Filament content items: locale column + filter, "Offered to other languages" ternary filter, `cross_locale` toggle,
  `translation_group` shown read-only on edit (editable on create).
- Expo receipts (stretch): `HttpExpoPushSender` returns `ok` ticket ids → token (`PushSendResult::$tickets`);
  `DeliverPushNotification` queues `CheckPushReceiptsJob` 15 min later; `CheckPushReceipts` action posts to
  `services.expo.receipts_url` (`EXPO_RECEIPTS_URL`, default `https://exp.host/--/api/v2/push/getReceipts`) in batches
  of 1000 ids, clears `DeviceNotRegistered` tokens, logs other errors as counts per code (no tokens, ids or bodies);
  failed requests are logged and skipped (best effort). Ticket ids live in the job payload (no table).
- Tests: `tests/Feature/Actions/ContentLocaleTest.php` (fr gets the fr version not both, en without translation still
  offered, `cross_locale` false hidden, en member never gets fr, draft fr keeps en, constant queries, category tree),
  `tests/Feature/Actions/QuestionBankTest.php` (real data files incl. countries_fr/words_fr, 13 validation cases,
  empty/missing dir, seeding en+fr idempotently, French geography, language-bound flags, French word puzzles),
  admin tests (translation group read-only + toggle, locale filter), 4 receipts tests in `PushNotificationsTest`.
- Dev: `migrate` (1 migration), `db:seed --class=ContentSeeder` then `--class=WordPuzzleSeeder`; OPcache cleared,
  `config:cache`, app/horizon/scheduler restarted. Seed content now: en MC 787 (213 language-bound), en wisdom 151,
  en word puzzles 493; fr MC 617, fr wisdom 100, fr word puzzles 385; 1126 question-bank items. Data files were still
  being fact-checked during the session (transient parse errors seen in `wisdom.php`, `vocabulary.php`,
  `words_fr.php`); all parsed and validated at seed time — re-run the two seeders after later edits.
- Pint, PHPStan clean. Full Pest suite: 564 passed (5233 assertions).

### 2026-10-17 (unfinished games — continue / abandon / idle sweep)

- Per PLAN "Unfinished games (2026-10-17)" and API.md Play: `GET /play/sessions/current` (`FindCurrentSession`)
  → the member's most recent `active`/`failed`/`redemption` session as `{ data: { session, items } }` (same
  `SessionPayload` as `GET /play/sessions/{session}`) or `{ data: null }`; registered before `{session}` (which is
  `whereNumber` anyway). Scramble `@response` documents the nullable `data`.
- `StartSession` abandons the member's previous unfinished sessions inside its transaction via `AbandonSession`
  (a failed start, e.g. empty category, rolls back and keeps the old game). No row lock (avoids gap-lock deadlocks;
  a racing duplicate is picked up by the idle sweep).
- `play:abandon-idle-sessions` (`AbandonIdleSessions`), hourly, `withoutOverlapping()->onOneServer()`: unfinished
  sessions whose last activity (last `answered_at`, else `started_at`) is more than 24 h old → `abandoned`. Batches
  of 500: one id query + one `UPDATE … WHERE id IN (…)` (conditions re-checked) per batch, `NOT EXISTS` on
  `session_items.answered_at >= cutoff`. Migration `2026_10_17_200000_add_play_sessions_status_index`
  (`status, started_at`) for the cross-member sweep. `PlaySession::UNFINISHED_STATUSES` constant.
- Tests: `tests/Feature/Api/UnfinishedSessionsTest.php` (none/completed/abandoned → null; active/failed/redemption
  returned = GET session payload; most recent wins; other member not returned; no route clash; start abandons
  previous only for that member; failed start keeps old game; idle boundaries at exactly 24 h for start and answer;
  2 queries per batch for 2 vs 8 sessions; hourly schedule; constant query count for `current`).
- Dev: `migrate` (1 migration), OPcache cleared, `config:cache`, app/horizon/scheduler restarted; `schedule:list`
  shows `0 * * * * play:abandon-idle-sessions`. Live curl `GET /api/v1/play/sessions/current` as
  `demo-sam-child-token` → 200 with Sam's active session 12 + items. Note: that demo session started 2026-10-11, so
  the first hourly sweep abandons it (status change only, nothing deleted); `current` then returns `null` until Sam
  starts a new game. Scramble `/docs/api.json` shows `data` as `object | null` with `session` + `items`.
- Pint, PHPStan clean. Full Pest suite: 528 passed, 1 failed (`AdRewardsTest` "another member or another session" started a second session, which now abandons the first) — test now restores the first session to `failed`; that file re-run green (16/16).

### 2026-10-17 (session 16: hardening part 2 — indexes, security review, README)

- **Indexes:** migration `2026_10_17_100000_add_hot_path_indexes` (reversible, explicit names):
  `devices_push_token_index` and `play_sessions_member_id_started_at_index`. `EXPLAIN` on the dev DB: the push-token
  `UPDATE … WHERE push_token = ?` and `WHERE push_token IN (…)` use the new index (range, was a full scan); the daily
  reminder's `member_id IN (…) AND started_at >= ? GROUP BY member_id` uses the new composite ("Using index for
  group-by"). Also checked: leaderboard window (`point_entries_family_id_created_at_index`), sign-in code lookup
  (`code_hash`), reminder candidates (`notification_preferences` unique + PK), active session (`member_id,status`) —
  all indexed already.
- **Security review** (`tests/Feature/Api/SecurityReviewTest.php`, 33 tests):
  - Cross-family: every route id (`member`, `device`, `invite`, `plan`, `session`, sign-in codes, member notification
    preferences) of another family → 404 `not_found` and nothing changes; removed members and another member's
    session in the same family → 404; another family's member in goal `members` / another session's item in an
    answer → 422. All were already correct (scoped lookups); now locked in by tests.
  - Mass assignment: every model has a non-empty `#[Fillable]` (test walks `app/Modules/*/Models`); no request
    `->all()` into create/update (only the RevenueCat webhook stores its raw payload, by design); `is_staff`,
    `tier`, `owner_user_id`, `family_id`, `status` can't be set through the API (test).
  - Validation limits added: login `email`/`password` max 255, `current_password` max 255, goals `category_ids` max
    200 + `members` max 50, answers `response` max 10 keys + `response.found` max 100 + `response.grid` max 16.
  - Rate limits: `auth` limiter now also 10/min **per email** (hashed key) on top of 10/min per IP, so guessing one
    account's password from many IPs is slowed; tests for that, for `/auth/code` and the export / deletion-page /
    webhook limiters.
  - Secrets at rest verified by tests: sign-in codes only as HMAC; Apple refresh token encrypted + hidden. Invite
    codes stay plain by design (admins re-display them) — documented in README "Security notes".
  - Logs / audit log: log contexts carry no tokens or PII (reviewed every `Log::` call). **Fixed:** deleting a family
    left its old name in earlier activity-log entries (`created`/renamed) — `DeleteFamily` now clears
    `attribute_changes`/`properties` of the family's entries (kept as who/when).
  - Admin: `canAccessPanel` = `is_staff` (existing test). **Fixed:** the Horizon gate was the stub (empty email list =
    nobody outside local); now staff only (test).
  - Production config: `SESSION_SECURE_COOKIE` defaults to true when `APP_ENV=production`; `.env.prod.example` adds
    `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, `SESSION_ENCRYPT=true` and an empty `CORS_ALLOWED_ORIGINS`;
    `config/cors.php` no longer falls back to the localhost Expo origins in production. Test checks these.
- **README:** quick start, setup-guide index (social, Apple, RevenueCat, AdMob, Expo, AI generator, legal, Sentry,
  DEPLOY.md, antivirus) with their `.env` keys, "Tests and checks", non-destructive reseeding (which seeders are
  idempotent), "Security notes", Horizon access row.
- Contract-relevant limits listed in Next up 0b (no `docs/API.md` edit by me).
- Checks: full Pest 515 passed (5024 assertions), Pint and PHPStan clean. Deployed to dev once (opcache cleared,
  `config:cache`, app/horizon/scheduler restarted; `/api/v1/health` and `/me` 200).

### 2026-10-16 (session 15: hardening — Expo retries, N+1 audit part 1)

- **Expo push failures** (was: a connection error threw inside the queued job, and Horizon `tries` is 1, so the push
  was lost with a failed job): `ExpoPushSender::send()` now returns `Notifications\Support\PushSendResult`
  (`invalidTokens`, `retryTokens`) and never throws for transport problems. `HttpExpoPushSender`: connection errors and
  HTTP 429/5xx (after the in-request retries) → the batch's tokens are retryable; ticket `MessageRateExceeded` →
  retryable; other HTTP errors (400, bad credentials) are dropped; logs carry status, Expo error code and message
  count only (no tokens, no response body — it can echo tokens). `DeliverPushNotification` queues a follow-up job for
  only the undelivered tokens after 60 s, then 300 s (3 attempts total; still dropped after 12 h), so delivered devices
  never get duplicates; a retry only reaches devices of the same members that still hold the token. 7 new tests in
  `PushNotificationsTest`.
- **N+1 audit (part 1):** new query-count tests — API: `GET /me` (2 vs 6 members), `GET /me/stats` (2 vs 30 ledger
  rows), `GET /goals/{plan}` (2 vs 6 plan members); admin: invites + goal periods relation managers, family / goal
  plan / content batch view pages and the categories list (2 vs 6 rows). All were already constant; no code change
  needed. Index review from the dev schema (read-only) — see Next up 0a.
- No contract changes, no migrations.

### 2026-10-16 (session 14: B9 — legal & consent API, account deletion page, production infra)

- **Consents:** `consents` table (append-only; user, type `terms`/`privacy`/`parental`, version, subject member/invite,
  `accepted_at`, `ip_hash` = HMAC-SHA256 of the IP with the app key), `Consent` model + factory, `RecordConsent`,
  `AcceptLegalTerms`, `GetLegalStatus`. `config/legal.php` (versions `2026-10-16`, en/fr summaries, `LEGAL_DRAFT`,
  `LEGAL_*` contact placeholders). `LegalDocuments` helper.
- **Endpoints:** `GET /legal` (public, `?lang=fr`), `POST /me/legal-acceptance` (204; 422 stale version; 403 children),
  `GET /me/export` (`throttle:export` 1/hour/user; 403 children; fixed 12 queries; no tokens, password, push token, Apple
  refresh token or IP hash), `GET /family/invites/preview` (admin). `me.legal` in every MeResource (one query).
  `accept_terms` on register (request rule), social sign-up (only when an account is created) and adult join with email
  (action check); `parental_consent` on child invites (checked before the member limit), recorded with the privacy
  version and linked to the member on join. Scramble tag **Legal**.
- **Pages:** `/legal/terms`, `/legal/privacy` (Blade, en+fr, mobile, dark mode, Draft banner, no session cookie,
  strict CSP/headers via `SecurityHeaders`). Drafts written for this app (PIPEDA, Quebec Law 25, COPPA; children's data
  minimisation; no ads/tracking for children; non-personalized ads; processors Hetzner, Apple/Google/Meta, RevenueCat,
  AdMob, Expo/APNs/FCM, Sentry; retention; access/export/deletion rights) — every item needing counsel is marked.
- **Account deletion web page** (coordinator request, Play requirement): `/account/delete` (en/fr) explains in-app
  deletion and takes an email; `account_deletion_requests` (email HMAC only, token SHA-256, 60-min TTL, superseded on a
  new request), honeypot, `throttle:account-deletion` 5/hour/IP, queued `AccountDeletionConfirmation` mail; the emailed
  link opens a confirmation page whose POST button runs `DeleteAccount` (same as `DELETE /me`); owner-with-members and
  child accounts get an explanation; identical response whether or not the email exists.
- **Admin:** Families → *Consents* relation manager (read-only, eager-loaded, query-count test).
- **Seeders:** Mom, Dad, Alex and the admin accepted the current versions; Mom's parental consent for Sam and Robin's invite.
- **Sentry:** `sentry/sentry-laravel` ^4.29 (approved), `config/sentry.php` (`send_default_pii` forced false, traces
  0.05, SQL bindings off), `Integration::handles` in `bootstrap/app.php`, `SentryScrubber` for events and transactions
  (keeps only the user id; removes bodies, cookies, auth headers; masks emails, tokens, names, birth data). Off with an
  empty DSN (forced empty in phpunit.xml).
- **Infra:** `docker/prod/Dockerfile` (stages base → vendor (`composer install --no-dev`, optimized autoloader, Filament
  assets) → `prod` (non-root, OPcache `validate_timestamps=0`, healthcheck, entrypoint caching config and refusing
  `APP_DEBUG=true`) and `web` (Caddy + public/)); built and booted locally (`php artisan about`: production, debug off,
  config/routes/views cached; php-fpm healthy as `brainy`, no dev packages). `docker-compose.prod.yml` (Caddy auto-HTTPS,
  app, horizon, scheduler, MySQL 8.4, Redis with password, backup; restart policies, healthchecks, log rotation; only
  80/443 published; MySQL/Redis on an internal network) — `docker compose config` valid. `.env.prod.example` with every
  secret. `bootstrap/app.php` trusts private-range proxies (correct client IP behind Caddy).
- **Backups:** `docker/backup` (Debian slim: mariadb-dump `--single-transaction` → gzip → openssl AES-256-CBC/PBKDF2 →
  rclone; cron; 14-day local + remote retention; `restore.sh` into a scratch DB). Round trip tested against the dev DB:
  identical row counts; wrong passphrase restores nothing. Scratch DBs dropped afterwards.
- **Security pass:** `RouteSecurityTest` (every `/api/v1` route has `auth:sanctum` except an explicit public allowlist,
  every route throttled, admin family routes behind `family:admin`, prod APP_DEBUG guard). Webhooks got `throttle:webhooks`.
- **Dev image:** `docker/php/extra-ca/` for TLS-inspecting antivirus roots (cause of `cURL error 60` here: Norton Web/Mail
  Shield re-signs HTTPS; `ca-certificates` was already installed). Not active until a cert is added (see Next up c).
- **Docs:** `docs/DEPLOY.md` (Hetzner → firewall → Docker → DNS → `.env.prod` checklist → first deploy → admin user →
  checks → updates/rollbacks → monitoring → backups/restore drill → Sentry → costs), README sections and link.

### 2026-10-16 (session 13: B8 finish — activity/reminders, billing sync + reconciliation, AdMob SSV, admin)

- **Member `has_own_login`** (self/admin only): `User::hasOwnLogin()` (email or a linked social account; reads an
  eager-loaded `withExists('socialAccounts')`), used by `isChildAccount()` too. `GET /family` and member updates load
  `user` with `withExists('socialAccounts')`; `QueryCountTest` now has social-only adults (no email) in the family list.
- **`family_activity`**: queued `Notifications\Listeners\NotifyFamilyActivity` on `Play\Events\SessionCompleted` →
  other active members (not the finisher) through `SendMemberNotification` (prefs + quiet hours). Throttle: atomic
  `Cache::add("notifications:family_activity:{member}", …, 3600)` = max 1 per finishing member per hour (only taken when
  there is someone to notify). Push data `{ type, member_id, session_id }`. Two queries regardless of family size.
- **`daily_reminder`**: `Notifications\Actions\SendDailyReminders` + `notifications:daily-reminders`, scheduled
  `everyFifteenMinutes()->withoutOverlapping()->onOneServer()`. Members with saved preferences (the type is off by
  default), chunked 500; per chunk: effective prefs (1 query), reminder time in the current local 15-minute window
  (`[17:00, 17:15)`, works for +5:30/+5:45 zones and DST), not started a session since the family's local midnight
  (1 grouped query), `Cache::add` guard per member per local day (no double reminders on re-runs). Tests cover
  Toronto after-midnight-UTC, Kathmandu/Kolkata, the Berlin DST switch and constant query counts.
- **Billing**: one tier switch for all paths, `Billing\Actions\ApplyFamilyTier` (saves the subscription, sets
  `family`/`free`, dispatches `Billing\Events\SubscriptionChanged` with `source` webhook|sync|reconcile);
  `ApplyRevenueCatEvent` uses it. `Billing\Contracts\RevenueCatClient` + `HttpRevenueCatClient` (REST v1
  `GET /subscribers/{app_user_id}`, Bearer `REVENUECAT_API_KEY`). `SyncSubscription` maps the `family` entitlement
  (active / grace on billing issue / expired; store, product, sandbox, will_renew) and counts as the newest event
  (`last_event_type` `SYNC`; older webhooks delivered later are `stale`); without a key or on an API error nothing
  changes. `POST /family/subscription/sync` (admin) returns the same shape as GET. `ReconcileSubscriptions` +
  `billing:reconcile-subscriptions` (hourly): `active` past `expires_at` / `grace` past `grace_expires_at` by more than
  `REVENUECAT_RECONCILE_AFTER_MINUTES` (60) → with a key re-synced from RevenueCat (missed RENEWAL stays paid; API down
  → next run), without a key marked `expired` + tier `free`.
- **AdMob SSV**: `GET /api/v1/webhooks/admob-ssv` (`AdMobSsvController` → `RecordAdReward`). `AdMobSignatureVerifier`:
  signed content = raw `QUERY_STRING` up to `&signature=`, web-safe base64 DER signature, `openssl_verify` SHA-256 with
  the key's PEM; keys from `Billing\Contracts\AdMobVerifierKeys` (`HttpAdMobVerifierKeys`: gstatic JSON, cached 24 h,
  refetched once for an unknown `key_id`). 400 `ssv_signature_invalid`; 200 `stored` / `duplicate` (unique
  `transaction_id`) / `ignored` (no usable custom data, unknown member/session — e.g. AdMob's "Verify URL").
  New table `ad_rewards` (transaction_id unique, reward_ref, member, session, ad_unit, reward item/amount, payload,
  used_at) + model/factory. `GrantSecondChance` `ad`: policy `ad_allowed` first (403), then `reward_ref` required (422),
  no reward row → 409 `ad_reward_pending`, used/foreign member/other session → 403, else consumed (`used_at`) under a row
  lock.
- **Policy `ad_request`** (coordinator request): `PolicyResolver::adRequest()` from the band policy (fallback
  non-personalized, rating G, not under age of consent); seeded teen `under_age_of_consent: true`; AgeBand admin form
  section "Ad requests (AdMob)". `AgeBand::AD_CONTENT_RATINGS` = G, PG, T, MA.
- **Filament**: family view page has a read-only *Subscription* section (status badge, store, product, environment,
  expiry, grace, will renew, last event, RevenueCat app user id); record loaded with `owner` + `subscription`.
  New **Billing → Webhook events** resource (list with provider/outcome filters, family eager-loaded; view page with
  the pretty-printed payload; no create/edit/delete). Added to the admin render + constant-query tests.
- README "Subscriptions, rewarded ads and push — setup" (RevenueCat project/products/entitlement/webhook/API key,
  AdMob SSV URL + test units, Expo) and `.env.example` keys (`REVENUECAT_API_KEY`, `REVENUECAT_API_URL`,
  `REVENUECAT_RECONCILE_AFTER_MINUTES`, `ADMOB_SSV_KEYS_URL`, `EXPO_ACCESS_TOKEN`; placeholders only). Fixed the stale
  "account deletion not built" note in the Apple section.
- Scramble: new endpoints documented under the Billing tag (SSV query parameters, `ssv_signature_invalid`,
  `ad_reward_pending` on second chance); `ErrorCodes` gained both codes.
- HTTP clients (AdMob keys, RevenueCat) catch `ConnectionException` (found in the live smoke test: the dev container
  has no CA bundle for outbound HTTPS → cURL error 60 → was a 500; now SSV 400 / sync returns the current state). Note:
  the existing Expo sender has the same gap (a failed connection fails the queued job, which Horizon retries).
- Verified: full suite 432 passed (then +2 connection-failure tests, targeted files green), Pint, PHPStan clean.
  Live: SSV bad signature → 400 `ssv_signature_invalid`; `POST /family/subscription/sync` as Mom → current state
  (demo family: tier family, status none); `GET /me` → `has_own_login: true`, `policy.ad_request`; `/docs/api.json` 200;
  `schedule:list` shows both new commands.

### 2026-10-16 (session 12: B6c account deletion + Apple token revocation)

- **Migration** `2026_10_16_100000_add_account_deletion_fields`: `social_accounts.refresh_token` (encrypted cast,
  hidden) + `token_client_id`, `users.erased_at`, `families.deleted_at` (plain column, no SoftDeletes trait: removed
  members still load their family).
- **`Identity/Actions/EraseUser`** (`handle` / `handleMany`, fixed query count): deletes Sanctum tokens, device rows
  (push tokens), social accounts; user → "Deleted player", email/password/birth/avatar/staff cleared, `erased_at`;
  memberships' nickname → "Deleted player", accessibility cleared; unused sign-in codes voided; notification
  preferences deleted; the invite they used and unused invites they created deleted; password reset rows deleted.
  User rows stay (ledger → members → users). One activity log entry `account`/`erased` with user ids only. Apple
  tokens revoked via `DB::afterCommit`.
- **Family**: `CheckAccountDeletion` (blockers `family_owner`, `child_account`; `deletes_family`;
  `active_subscription` + `store`), `DeleteAccount` (`DELETE /me`, 409 `family_owner_cannot_leave`, 403 for
  non-adults, owner-only family → `DeleteFamily`), `DeleteFamily` (members `removed`, codes voided, pending invites
  deleted, children = no email + no social erased, adults detached, owner erased with `delete_my_account`; family
  → "Deleted family" + `deleted_at` via query update so the activity log keeps no name; `FamilyDeleted` event →
  Goals `DeleteFamilyGoalPlans` soft-deletes its plans). `RemoveMember` now erases child accounts (after
  `MemberRemoved`). `AccountDeletionController`, `DeleteAccountRequest`, `DeleteFamilyRequest`.
- **Apple**: `Identity/Apple/AppleTokenClient` + `HttpAppleTokenClient` (`/auth/token`, `/auth/revoke`; client
  secret ES256 via `firebase/php-jwt`, `sub` = client id = the identity token `aud`, new `SocialIdentity::audience`);
  missing `APPLE_TEAM_ID`/`APPLE_KEY_ID`/`APPLE_PRIVATE_KEY` → skipped + logged. `authorization_code` on
  `POST /auth/social/apple` and `POST /me/social-accounts/apple` (`StoreAppleRefreshToken`); unlink revokes
  (`RevokeAppleTokens`). Error code `family_owner_cannot_leave` in `ErrorCodes`. `.env.example`, README Step 4–6.
- **Tests**: `tests/Feature/Api/AccountDeletionTest.php` (12): check endpoint, adult self-delete (ledger kept,
  token 401, email login 401, activity log without email), owner blocker 409, child 403, owner-only family, `DELETE
  /family` (children erased, adults detached, sign-in code invalid, subscription kept), non-owner admin 403,
  `delete_my_account`, child removal erased, Apple exchange + revoke on deletion + re-sign-in makes a new user,
  revoke on unlink, query count for 2 vs 8 children equal. Targeted files 130 passed; Pint + Larastan clean.
- Live: `GET /api/v1/me/deletion-check` as `demo-alex-teen-token` → `can_delete: false`, `blockers: ["child_account"]`.
- Not done: B8 `family_activity` / `daily_reminder` (Next up 0).

### 2026-10-15 (session 11: flaky test + B8 sending notifications, part 1)

- **Flaky test fixed:** `GeneratedPlayTest` "plays math sprint items…" answered the first item with an unreduced
  equivalent (×3). At difficulty 3 the fractions generator makes a "Simplify a/b" item half the time, and those are
  `reduced_only` (only the reduced fraction counts, by design) → `is_correct` false. The test now picks a
  non-`reduced_only` item for the equivalence check. Production code unchanged.
- `Notifications\Contracts\ExpoPushSender` (bound to `Support\HttpExpoPushSender`): POST
  `services.expo.push_url` (default `https://exp.host/--/api/v2/push/send`, optional `EXPO_ACCESS_TOKEN`) in batches
  of ≤100, retries 2×; returns tokens whose ticket error is `DeviceNotRegistered`.
- `Actions\SendMemberNotification`: effective preferences for all recipients in one query
  (`GetNotificationPreferences::forMembers`), skips members with the type off, groups members by send time — now or
  the end of their quiet hours in the family timezone (overnight windows supported) — and dispatches one
  `Jobs\DeliverPushNotification` per group (delayed job for quiet hours). The job drops deliveries older than 12 h,
  loads all push tokens in one query, sends, and nulls tokens Expo reported as not registered.
- Queued listeners (`afterCommit`) `NotifyGoalReached` / `NotifyPeriodClosed`; recipients = plan members (active),
  loaded with `family` + `planMembers.member.user` (`Support\GoalRecipients`). Push `data`:
  `{ "type": "goal_reached" | "period_closed", "goal_id": <plan id>, "period_id": <period id> }`.
- Tests: `tests/Feature/Api/PushNotificationsTest.php` (7: goal_reached data + disabled type, period_closed fan-out,
  quiet-hours delay adult 07:00 / child 07:30 Berlin, 12 h drop, invalid token cleanup, constant query count for 2 vs 9
  recipients, HTTP batching + DeviceNotRegistered).
- For the coordinator / `docs/API.md`: push `data` also carries `period_id` (extra key, `goal_id` as documented).
- Not done: `family_activity`, `daily_reminder`, Expo receipts (Next up 0a/0b).

### 2026-10-15 (session 10: B8 push token + notification preferences)

- New `Notifications` module (`NotificationsServiceProvider` registered, Scramble group `Notifications`; `ApiDocsTest`
  tag expectation updated).
- `PUT /me/push-token` (`UpdatePushTokenRequest`: Expo format `ExponentPushToken[...]` / `ExpoPushToken[...]`,
  `platform` in `Device::PLATFORMS`) → `SetPushToken` stores token + platform on the device bound to the request's
  Sanctum token and clears the same token from any other device (phone changes account). `DELETE /me/push-token`
  clears it. Logout already deletes the device row (`RevokeCurrentDevice`), and admin device sign-out the same, so the
  token goes with it. A request without a device row (e.g. a transient token) → 422 on `token`.
- Migration `2026_10_15_100000_create_notification_preferences_table` (one row per `family_member_id`, `types` JSON,
  `quiet_from`/`quiet_to` nullable TIME, `reminder_time` TIME). No row = defaults (`NotificationDefaults`): all types on
  except `daily_reminder`, reminder 17:00; quiet hours children 19:30–07:30 (child = `PolicyResolver::isChild`, i.e.
  the age band's `is_child` rule), everyone else **21:30–07:00** (decision this session). Quiet hours can be turned
  off by PATCHing both to `null` (setting only one → 422).
- `GET/PATCH /me/notification-preferences` (`family`), `GET/PATCH /family/members/{member}/notification-preferences`
  (`family:admin`; other family → 404). Actions `GetNotificationPreferences` / `UpdateNotificationPreferences`
  (partial update merged over current/defaults, upsert), `NotificationPreferencesResource`.
- Tests: `tests/Feature/Api/NotificationsTest.php` (13: push token store/clear/move/format/logout, adult + child
  defaults, partial update + clearing quiet hours, validation, no family 409, admin read/write child, non-admin 403,
  other family 404, query-count bound). NotificationsTest + ApiDocsTest green; Pint, PHPStan clean. Full suite not
  run (coordinator).
- Deployed to dev: `migrate` (no fresh), opcache cleared, `config:cache`, app + horizon restarted. Live curl as Sam →
  child defaults.
- For `docs/API.md` (coordinator): adult default quiet hours 21:30–07:00; `quiet_from`/`quiet_to` nullable (both null =
  no quiet hours); `PUT /me/push-token` 422 when the sign-in has no device.

### 2026-10-15 (session 9: goal tz test fix, B6c Sign in with Apple)
- **Goal test failures at night — root cause:** test setup, not production. `GoalPlanFactory` set `starts_on` to
  `now()->toDateString()` (UTC). After 20:00 EDT the UTC date is already tomorrow, so the plan "starts tomorrow" in
  the family's timezone (America/Toronto) and `OpenGoalPeriod::window()` correctly returns no current period.
  Production paths were checked and are right: `SaveGoalPlan` validates `starts_on` against
  `FamilyClock::today($family)`, `OpenGoalPeriod`/`FamilyClock::window()` compute windows in the family timezone, and
  `DemoActivitySeeder` uses `CarbonImmutable::now($family->timezone)`. Fix: the factory computes "today" in the
  family's timezone. Tests pinned to 21:40 Toronto (01:40 UTC next day) with `travelTo`: QueryCountTest "lists goal
  plans…", MemberSignInActionsTest "removes a member … from goal plans", and a new GoalsTest "has a current period
  late in the evening" (factory plan + API-created plan both have the Wed-04:00Z period).
- **B6c Apple sign-in:** `Identity/Social/JwksIdTokenVerifier` (new abstract base: JWKS fetch/cache with `max-age`,
  refetch once on an unknown `kid`, RS256 decode, `sub`/`exp` required, `email_verified` bool or `"true"`);
  `GoogleIdTokenVerifier` now extends it (behaviour unchanged, its tests still green); `AppleIdTokenVerifier`
  (`iss` `https://appleid.apple.com`, `aud` ∈ `services.apple.client_ids` from comma-separated `APPLE_CLIENT_IDS`,
  optional nonce: `hash_equals(sha256(raw), token.nonce)`, name always null). `SocialTokenVerifier::verify()` gained
  `?string $nonce`; `SocialAccount::APPLE` added to `PROVIDERS` (routes pick it up via `whereIn`);
  `SocialSignInRequest`/`LinkSocialAccountRequest` accept optional `nonce`; `SignInWithSocial`/`LinkSocialAccount`
  pass it. New user's `display_name`: request → provider name → email local part (skipped for
  `@privaterelay.appleid.com`) → "Player". Filament members column renamed "Social sign-in" (shows Apple).
  `SocialAccountFactory::apple()`. `.env.example` + README "Sign in with Apple — setup" (Developer Program, App ID +
  capability, Services ID + domains/return URL, `.p8` key for later revocation, `.env`, nonce/name rules, testing).
- Tests: +6 Apple verifier tests (incl. an end-to-end API sign-up with a real signed token + nonce), +1 link with
  nonce (`POST /me/social-accounts/apple`, wrong nonce → 401, right → 201), +2 API tests (name fallback,
  link/unlink), +1 goal late-evening test. iOS bundle ID is `app.brainyclan` (coordinator): `.env.example`
  `APPLE_CLIENT_IDS=app.brainyclan` (append the Services ID for web), README and tests use it.
- `ApiDocsTest` "groups endpoints by module" expected the tag list without `Billing` (stale since session 8 added the
  Billing module); expectation updated.
- Full suite: 345 tests, 344 passed + the ApiDocsTest expectation fixed afterwards (re-run of the changed files green:
  ApiDocsTest + SocialTokenVerifiersTest 21/21); total now 346. Pint and PHPStan clean.
- Live: `POST /api/v1/auth/social/apple` with a junk token → 401 `social_token_invalid` (contract shape).
- Not done: account deletion / Apple token revocation (Next up 0a); B8 push token and notification preferences.
- For the coordinator / `docs/API.md`: the request now documents `nonce` for Apple only (Google/Facebook ignore it);
  `/me/social-accounts/apple` also accepts `nonce`. Account deletion will need an `authorization_code` field on the
  Apple sign-in body (to get a revocable refresh token) and `DELETE /me` — not in the contract yet.

### 2026-10-14 (session 8: B8 billing core)

- `BillingServiceProvider` registered; routes `POST /api/v1/webhooks/revenuecat` (public, Bearer secret via
  `RevenueCatWebhookRequest`, `hash_equals`, empty secret rejects everything → 401 `unauthenticated`) and
  `GET /api/v1/family/subscription` (`family:admin`, `FamilySubscriptionResource`, `status: none` without a row).
- `ApplyRevenueCatEvent`: stores each event once (`webhook_events` unique provider+event_id; duplicates and races
  return the stored row, reported as `outcome: duplicate`); finds the family from `app_user_id`, then
  `original_app_user_id`, then `aliases` (`family-{id}`); ignores unknown families, other entitlements and unhandled
  types (TEST, TRANSFER, SUBSCRIPTION_PAUSED, …) with 200; events older than `last_event_ms` → `stale`.
  INITIAL_PURCHASE / RENEWAL / UNCANCELLATION / PRODUCT_CHANGE / NON_RENEWING_PURCHASE → `active`; CANCELLATION →
  `will_renew: false`, still entitled until `expires_at`; BILLING_ISSUE → `grace` (`grace_expires_at` from
  `grace_period_expiration_at_ms`); EXPIRATION → `expired`. Family tier = `family` iff `Subscription::isEntitled()`.
  Tier change goes through `Family::update` (activity log).
- Config `services.revenuecat.{webhook_secret,entitlement}`; `.env.example` gained `REVENUECAT_WEBHOOK_SECRET`,
  `REVENUECAT_ENTITLEMENT`. `SubscriptionFactory` (was truncated) completed with `expired`/`grace` states;
  `WebhookEventFactory` added.
- Not started this session: push token, notification preferences, sending, AdMob SSV, Filament (Next up item 0).
- Live smoke test (curl, family 1): wrong secret 401; purchase `applied`; redelivery `duplicate`; `/family/subscription`
  active/app_store; EXPIRATION → `/me` `policy.tier` `free`. Demo family restored afterwards (tier `family`, smoke
  subscription + webhook rows deleted).
- Full suite: 336 tests, 333 passed, **3 failed in goal tests (pre-existing, time-of-day flake)**: run at ~21:40 EDT
  (UTC already the next day) a goal plan created by the factory had no `current_period` (e.g.
  `QueryCountTest` "lists goal plans…": `current_period` null) — the factory's `starts_on` and the family
  timezone (America/Toronto) disagree on "today" late in the evening. Unrelated to billing; fix the factory/test to
  use the family-local date. Billing tests 13/13 green; Pint and PHPStan clean.

### 2026-10-11 (session 7: B7 AI batch generator, play bug fixes)
- **Package:** `laravel/ai` **v1.2.0** (Laravel AI SDK, MIT, released 2026-10-07; supports Laravel 12/13, PHP ^8.3;
  approved for the content generator in the design doc). Config not published; the package's `config/ai.php` reads
  `ANTHROPIC_API_KEY`.
- **Pipeline** (`app/Modules/Content`): migration `2026_10_11_200000_add_generation_fields_to_content_batches_table`
  (locale, variant, difficulty and age range, requested_count, note, input/output tokens, skipped JSON, error,
  started/finished_at; old statuses mapped: generating → running, approved/rejected → done). `ContentBatch` statuses
  queued → running → review → done | failed (+ `draft` for hand-made batches), factory. `Generation/`: `ContentBatchAgent`
  (SDK agent, structured output `{"items": [...]}`, provider/model/max_tokens/timeout from `config/content_generator.php`,
  `isConfigured()`), `GenerationTypes` (multiple_choice, wisdom_card, word_puzzle:anagram|missing_letter|word_search:
  item JSON schema, type guidance, mapping through the handler's `fromFlatRow()` + `validate()`; wisdom quotes need
  author + source + an author death year at least 71 years ago), `BatchPromptBuilder` (content rules as system prompt;
  category path, ranges, note and up to 40 existing items "do not repeat"), `ContentFingerprint` (normalized question /
  card text / word / sorted word list; `source_ref` = `ai:{locale}:{sha1}`). Actions `QueueContentBatch` (validates,
  count ≤ 50, dispatches), `GenerateContentBatch` (drops invalid, skips duplicates against all items of the type+locale
  incl. archived, clamps difficulty, stores drafts via `SaveContentItem`, records tokens/model/prompt/errors),
  `ReviewContentBatchItems` (approve = `ChangeContentStatus` publish with reviewer; reject = archive + reviewer; batch →
  done when no drafts are left). Job `GenerateContentBatchJob` on connection `redis-ai` / queue `ai` (new queue
  connection, retry_after 600; new Horizon `supervisor-ai`, timeout 420, 1 process, tries 1; `failed()` marks the batch failed).
- **Filament:** page `AiBatchGenerator` (Content; form + batches table with `creator` eager-loaded and `withCount`
  items/drafts, polling, Retry for failed batches), hidden page `AiBatchReview` (batch summary, dropped/duplicate list,
  prompt, per-draft phone preview via the new shared partial `filament/partials/content-phone`, which Content preview now
  uses too; approve / edit / reject, bulk, approve all). The old "Content batches" resource is now "Batches (raw)".
- **Decisions:** reject = archive (not delete) so the same item is never generated again; duplicates are checked across
  all categories; 0 usable items → `failed`; no automatic retries (a retry pays twice). `phpunit.xml` forces
  `ANTHROPIC_API_KEY=""` and `CONTENT_AI_QUEUE_CONNECTION=sync` so tests never call a provider.
- **Play bugs (from the app):** (1) `ORDER BY ABS(difficulty - ?)` overflowed MySQL's UNSIGNED column → 500; now a
  portable `CASE` distance (`PickContentItems::DIFFICULTY_DISTANCE_SQL`). (2) Generated items are de-duplicated within a
  session by normalized question (also against content items in the session; up to 20 rebuilds). (3) "Simplify" math
  items set `answer.reduced_only` (and `allow_decimal: false`); `Rational::parse()` reports `reduced`. (4) 9×9 Sudoku
  scope `min_age` 9 → 10 (`database/seeders/data/categories.php`), `CategorySeeder` re-run in dev.
- Tests: full suite 323 passed (new: 15 generator action tests, 10 admin tests, 8 play-fix tests). Pint, PHPStan clean.
- Dev: `migrate` (1 migration), `CategorySeeder`, `config:cache`, OPcache cleared, app/horizon restarted (Horizon shows
  `supervisor-ai`). Live: the failing `POST /play/sessions {"category_id":28}` as Alex → 201 (left 2 extra active demo
  sessions for Alex); `/admin/ai-batch-generator` renders 200 for staff with the "not configured" state.
### 2026-10-11 (session 6: B6b social sign-in, B6a docs polish)
- **Packages:** `laravel/socialite` **v5.31.0** (MIT, supports Laravel 13; Moe approved Socialite in PLAN.md). It pulls in
  `firebase/php-jwt` v7.2.1, `phpseclib/phpseclib` 4.0.2 and `league/oauth1-client` v1.12.0. No extra provider package is
  needed (Google and Facebook drivers are built in). `firebase/php-jwt` is used directly by the Google verifier but is only a
  transitive dependency — suggest adding it to `composer.json` explicitly (`^7.0`), not done without approval.
- **B6b** per `docs/API.md` (incl. the coordinator's clarifications): migration `2026_10_11_150000_create_social_accounts_table`
  (unique `provider`+`provider_user_id` and `user_id`+`provider`), `SocialAccount` model + factory, `User::socialAccounts()`.
  `Identity/Social`: `SocialTokenVerifier` interface, `SocialTokenVerifiers` (provider → class, resolved from the container so
  tests swap fakes), `GoogleIdTokenVerifier` (RS256 via Google JWKS, cached for `max-age`, one refetch on unknown `kid`; `iss`,
  `aud` ∈ `services.google.client_ids` = `GOOGLE_CLIENT_ID_WEB/IOS/ANDROID`, `exp` required, 60 s leeway, email only when
  `email_verified`; Socialite's Google driver isn't used because it accepts one client ID and ignores `email_verified`),
  `FacebookTokenVerifier` (Graph `debug_token` with the app token: valid + our `app_id`; then Socialite `userFromToken`
  (`appsecret_proof`) with `first_name,name,email`, id must match; JWT-looking iOS Limited Login tokens are refused).
  Actions `SignInWithSocial`, `LinkSocialAccount`, `UnlinkSocialAccount`, `EnsureSocialSignInAllowed` (band `collect_email`
  via PolicyResolver). Controllers `SocialAuthController`, `SocialAccountController`; `SocialAccountResource`. Routes under
  `throttle:auth` (`/auth/social/{google|facebook}`) and `throttle:api` (`/me/social-accounts`). `ApiException`:
  `socialTokenInvalid`, `socialSignupIncomplete` (422 with `errors.birth_year/birth_month`), `socialAccountTaken`,
  `lastSignInMethod`, `notFound`. Filament members relation manager: "Google / Facebook" column (eager-loaded
  `user.socialAccounts`). README setup guide (cross-references the frontend README for console steps).
- **Behaviour decisions (not spelled out in the contract):**
  - Linking by email to an existing **non-adult** user (a teen with an admin-given email login) → 403 `forbidden`; linking from
    `/me/social-accounts` as a child/teen → 403 `forbidden`.
  - One account per provider per user: linking another Google account replaces the old link (sign-in-by-email and
    `/me/social-accounts` alike); re-linking the same account is idempotent (201).
  - A provider without email (phone-only Facebook) creates a user with `email: null`; such users are not "child accounts":
    `User::isChildAccount()` is now "no email **and** no linked social account" (used by `RemoveMember`).
  - The linked account's `email` is refreshed on every sign-in; a new user's `display_name` = sent name → provider first name →
    email local part.
  - Misconfiguration (no client IDs / app secret) answers 401 `social_token_invalid` with "… not configured on this server"
    (and a log warning); network errors to Google/Facebook also map to 401.
- **B6a:** `App\Modules\Core\Docs\ErrorResponsesExtension` (Scramble operation extension, registered in `config/scramble.php`)
  replaces Scramble's default error responses with the contract envelope and lists the possible `code`s per status: from
  middleware (`auth:sanctum`, `family`, `family:admin`, `throttle`, app version), path ids (404), Form Requests (422) and
  `#[ApiErrors(...)]` on controller methods (status per code in `Docs\ErrorCodes`, mirrors the API.md table). Endpoints grouped
  by module with Scramble `#[Group]` (Health, Identity, Family, Content, Play, Scoring, Goals, ordered, with descriptions).
  PHPDoc summaries/descriptions on every endpoint. Answers: `session` is now a `SessionResource` (same JSON) and
  `correct_response` typed via `@response`; leaderboard entries typed via `@response`. 40 operations documented.
- Tests: full suite 290 passed (new: 34 social sign-in/verifier tests, 3 docs tests, 1 query-count test). Pint, PHPStan clean.
- Dev: `migrate` (1 migration), `config:cache`, OPcache cleared, `app`/`horizon` restarted. Live curl: invalid Google/Facebook
  token → 401 contract shape; Mom `GET /me/social-accounts` → `{"data":[]}`; `/docs/api.json` 200.

### 2026-10-11 (session 5: B7 without the AI generator)
- **Item points:** `ChallengeType::score()` replaced by `points(ContentItem)` (1 for choice types, Sudoku 3/5/8
  by size, word search 3). `session_items.points` snapshots it when the item is added; `SessionItem` resource
  returns `points`; a correct regular answer awards it (ledger `amount` = points; redemption still 0).
- **Batch sizing:** `AddSessionItems` cuts every batch to the shortest prefix for which the target stays reachable
  if all remaining allowed mistakes hit the most valuable unanswered items (`isReachable()`); with 1-point items
  that is the old `(target − points) + (cap − mistakes)`. Top-ups after second chances / redemption use the same rule.
- **Generated items:** new `GeneratesItems` contract (`generate(seed, difficulty, options)`, `pointsForOptions`,
  `validateOptions`). Categories declare generators in a new `categories.generators` JSON column
  (`GeneratorSpec`: type, options, min/max difficulty), editable in Filament. `PickGeneratedItems` offers the
  generators of the played subtree whose category is visible for the member's age and whose difficulty range
  overlaps the member's policy range (nearest ones otherwise); the item difficulty is random in the overlap.
  Each slot of a batch picks content or one of the generators at random (equal weight). Generated rows:
  `content_item_id` null, `seed`, `type_key`, sub-scope `category_id`, and a `payload`/`answer` snapshot
  (`answer` hidden, never sent); `SessionItem::playable()` turns them into an unsaved ContentItem so every
  plugin method works unchanged. Payloads never change after generation (contract clarification).
  Migration `2026_10_11_124250_add_generated_items_to_session_items`.
- **Sudoku** (`SudokuGenerator`): seeded (Xoshiro256**) full-grid fill + cell removal keeping a unique solution
  (bitmask solver, fewest-candidates branching); 4×4/6×6/9×9; givens 10→6, 22→12, 46→28 by difficulty.
  Benchmark in the container: 9×9 at 28 givens median ~9 ms, max ~27 ms over 100 seeds → generated at request
  time, no pool needed. Verified on submit: keeps givens + valid complete grid (any valid solution).
  Categories: 4×4 (diff 1–3, ages ≤12), 6×6 (3–6), 9×9 easy/medium/hard (5–6 / 7–8 / 9–10) → children get 4×4/6×6.
- **Math Sprint** (`MathSprintGenerator`): times tables, mental math (± → ×÷ → % → decimals → squares/brackets/
  negatives), fractions (same denominator → simplify → "1/4 of 20" → different denominators → × ÷); `answer_format`,
  advisory `time_limit_ms`; exact `Rational` comparison ("6/8" = "3/4" = "0.75", "1 1/4", decimal comma, negatives);
  decimals for a fraction answer only when it terminates (`allow_decimal`).
- **Word Puzzles** (`word_puzzle`, content items): anagram (any listed word of exactly those letters), missing
  letter, word search (grid built at seed time by `WordSearchBuilder`, placements in the answer; start/end in either
  order). Unicode-aware comparison (`Words`: NFC + `mb_` case folding; contract clarification). `WordPuzzleSeeder` +
  `data/words.php` (216 kid-safe words, 7 themes, 3 grades): 215 anagrams, 215 missing-letter, 63 word searches.
  CSV import supported for all three types (`fromFlatRow`). Existing MC anagrams kept.
- **Category order (decision for Moe):** every category is playable now; top-level order is Geography, Science,
  Wisdom (the free 3, unchanged), Math Sprint, Sudoku, Word Puzzles, History, Vocabulary. Sudoku is no longer last.
- Filament: category "Generated items" repeater + table badge; content preview renders Sudoku grids, math, word
  puzzles, points, and generated samples; content items helper text/summary for the new types (table still
  eager-loads `category`, `tags`).

### 2026-10-11 (session 4: no-N+1 guard, B2b, B6 leftovers)
- **No N+1:** `Model::preventLazyLoading(! isProduction())` in `AppServiceProvider` (no `shouldBeStrict`,
  mass-assignment behaviour unchanged). The existing suite passed with it on; Laravel only flags lazy
  loads on models fetched in multi-row results, so new multi-row tests do the real checking.
  Fixed the one real N+1: `GET /goals` ran ~4 queries per plan (period lookup, scope pluck, ledger,
  members). `ComputeGoalProgress::many()` now computes all open periods with one grouped ledger query
  (a conditional `SUM(CASE ...)` per period) plus one member query; `LoadGoalPlans` eager loads
  `categories`, `planMembers.member.user` and fetches current periods in one query (a period is only
  inserted when a new window starts). Goal history uses the same batch path. Explicit eager loads on the
  goal plan table and the new members relation manager.
  Query-count tests (2 vs 6 rows, equal counts): family, invites, member devices, leaderboard,
  categories, goals, goal history, play session payload; Filament families, goal plans, content items,
  content batches, tags, activities tables and the members relation manager; every list page,
  relation manager and the new goal plan view page render with several rows.
- **B2b** (exactly per `docs/API.md`, 2026-10-11 changelog): migration `2026_10_11_100000_create_sign_in_codes_table`
  (`member_id`, `code_hash`, `created_by`, `expires_at`, `used_at`, `device_id`, `voided_at`).
  **Codes are stored hashed** (HMAC-SHA256 with `APP_KEY` over the normalized code): a DB leak alone
  gives no usable code; the plain code is only returned at issue time. Old unused codes are voided
  (`voided_at`), not deleted, for audit. TTL is a setting (`sign_in_codes.ttl_minutes`, default 15) read
  through `PolicyResolver::signInCodeTtlMinutes()`. Actions: `Family\IssueSignInCode`, `SignInWithCode`,
  `ListMemberDevices`, `SignOutMemberDevice`, `UpdateMember` (email), `Identity\RevokeDevice`, `SetPassword`;
  `IssueDeviceToken::issue()` also returns the device. Controllers `SignInCodeController`,
  `MemberDeviceController`, `MeController::password`. `MemberResource` gains `email` / `has_password`
  (self + admins). Policy `email_login_allowed` (PolicyResolver falls back to `collect_email` for bands
  without the key; seeded child false, teen/adult/senior true), AgeBand form toggle + table column.
  `RemoveMember` voids the member's unused codes. Filament members relation manager: email login,
  password flag, devices list, "Sign-in code" (persistent notification, same Action) and "Sign out
  device" actions. `ApiException::signInCodeInvalid()`.
- **B6 leftovers:** `RemoveMember` dispatches `Family\Events\MemberRemoved`; Goals listens
  (`RemoveFromGoalPlans` → `RemoveMemberFromGoals`) and drops the member from `goal_plan_members` and
  from open periods' `member_targets` (closed results untouched). Filament goal plans: view page
  (infolist) + `PeriodsRelationManager` (status, team total, reached, winners, per-member points from
  the stored result, no per-row queries). **`member_period_totals` dropped** (migration
  `2026_10_11_100100_drop_member_period_totals`, with `play_sessions.goal_period_id` and
  `point_entries.goal_period_id`): nothing read them, progress is always computed from the ledger.
  Removed `MemberPeriodTotal` and the no-op `RefreshMemberTotals` listener.
- **Contract update (coordinator, same day):** Member gains `email_login_allowed` (self/admin only,
  from the band `MemberResource` already resolves, so no extra query per member). `DELETE /goals/{plan}`
  is now a **soft delete** (`SoftDeletes`, migration `2026_10_11_100200_add_deleted_at_to_goal_plans`,
  `SaveGoalPlan::delete()`): hidden from `GET /goals`, 404 on show/patch/history/delete, frees the tier
  slot, periods kept. Pausing is `PATCH is_active: false` (still listed; the admin "deactivate" action
  stays a pause). `CloseGoalPeriods` skips periods of deleted plans (`whereHas('plan')`); member removal
  also cleans deleted plans (`withTrashed`). Filament goal plans: "Deleted by the family" trashed filter,
  deleted plans open via the view page.
- **Deploy note:** a transient 500 the frontend saw (`RefreshMemberTotals` missing) came from the
  window between deleting the class and restarting PHP-FPM (stale OPcache provider still registering
  the listener). Fixed state: `composer dump-autoload -o` (classmap clean), `event:list` shows only
  `RecordAnswerPoints` + `UpdateStreak` on `AnswerSubmitted` and `RemoveFromGoalPlans` on `MemberRemoved`,
  OPcache cleared, `app`/`horizon`/`scheduler` restarted.
- Scramble: the five new endpoints are in `/docs/api.json` (36 operations); a test checks it.
- Dev: `migrate` (3 new migrations) + `db:seed --class=PolicySeeder` (no fresh). Live curl smoke test passed (Mom issues a
  code for Sam → `/auth/code` with a dashed code → `/me` → Mom signs the device out → 401).

### 2026-10-11 (API documentation)
- Added `dedoc/scramble` v0.13.47 (MIT; supports Laravel 13 / PHP 8.5). Published `config/scramble.php`:
  `api_path` `api/v1`, title "Brainy Clan API", version from `APP_VERSION` (new `config('app.version')`,
  default `0.1.0`), description points to `docs/API.md` as the contract, security strategy
  `MiddlewareAuthSecurityStrategy` (Sanctum bearer on `auth:*` routes, public routes marked `security: []`).
  `viewApiDocs` gate in `AppServiceProvider` (local only for now). README section "API documentation".
- Docs at http://localhost:8080/docs/api, JSON at `/docs/api.json`. All 31 `/api/v1` routes documented
  (auth, me, family, invites, members, categories, play, stats, leaderboard, goals, health);
  `scramble:analyze` clean. Pint, Larastan clean; Pest 148 passed.
- Weakly inferred spots (fine for now, improve with PHPDoc / typed Resources later): `POST /play/sessions/{session}/answers`
  `data.session` is an untyped array (should be the session payload); leaderboard `entries` items only show `rank`;
  `correct_response` is a free-form object; error responses (401/403/422 envelope) are Scramble defaults, not our
  documented error format.

### 2026-10-11 (session 3: B5 finish, B6)
- B5: Scoring HTTP (`Scoring/Http/routes.php`, `StatsController`, `LeaderboardController`,
  `LeaderboardRequest`). Pest: `PlayTest` (21 tests) and `ScoringTest` (6). Fixes: new sessions returned
  `null` for `points`/`mistakes`/`second_chances_used` (now set explicitly in `StartSession`);
  malformed responses (unknown `choice_id`) are now a 422 instead of a mistake (new
  `ChallengeType::validateResponse()`, implemented in `ChoiceChallenge`); unanswered redemption items
  are void and hidden from `items` after a failed round (`SessionPayload`); PHPStan fixes in
  `BuildLeaderboard::rank()` (now generic `array<string, mixed>` rows, reused by Goals) and `Streak`.
  `limits.max_active_goals` confirmed in PolicyResolver + PolicySeeder. `DemoActivitySeeder`.
  Live curl smoke test against :8080 passed.
- B6: migration `2026_10_10_180000_create_goals_tables` (migrated in dev), models `Goals/Models/*`,
  `GoalPlanFactory`, actions `OpenGoalPeriod`, `ComputeGoalProgress`, `CloseGoalPeriods`,
  `SaveGoalPlan` (create/update/deactivate, tier limit, locked categories, members), `LoadGoalPlans`;
  `GoalController` + requests + `GoalPlanResource`; `goals:close-periods` command scheduled every
  5 min; events `PeriodClosed`, `GoalReached`; `ApiException::goalLimitReached()`; Filament
  `GoalPlanResource` (list + deactivate). Pest: `GoalsTest` (9 tests), admin page test.
- API.md: clarifications changelog line (2026-10-11), see there.


### 2026-10-10 (session 2: B3–B4)
- Content module: `categories` (tree, per-locale names, ages, active), `challenge_types`,
  `content_items` (payload/answer split, `source_ref` for idempotent seeds/imports), `tags`,
  `content_batches`; `CategoryTree` cache; actions BuildCategoryTree, ResolveCategoryAccess,
  SaveContentItem, ChangeContentStatus, TagContentItems, ImportContentRow, PreviewContentItem.
- ChallengeTypes module: `ChallengeType` interface, `ChallengeTypeRegistry`
  (config/challenge_types.php + DB on/off), `MultipleChoice` and `WisdomCard` handlers.
- `GET /api/v1/categories`. Free-tier lock = first `limits.max_categories` visible top-level
  categories by sort order (playable categories are sorted first; Sudoku last until B7).
- Seeds: 8 launch categories (47 nodes); 320 multiple-choice items (hand-written + generated
  capitals/continents/times tables/mental math/anagrams); 50 wisdom cards (public-domain
  translations ≤1917, Franklin, Aesop, Shakespeare, Emerson, Thoreau, plus originals).
  Sudoku and Word Search have no content until B7.
- Latency fix (frontend saw ~14 s/request): OPcache timestamp checks over the Windows bind mount
  were the cause. Now `PHP_OPCACHE_VALIDATE_TIMESTAMPS` env (fast mode `0` in `.env`), OPcache
  file cache, php.ini mounted, and `php artisan config:cache` in bootstrap. Warm requests ~0.5 s.
  Tests ignore dev caches (phpunit.xml `APP_CONFIG_CACHE` etc.) and refuse to run on non-SQLite.
- Contract update applied: Member `default_ui_theme`; senior `text_scale` default 1.0.

### 2026-10-10 (session 2: B0–B2)
- B0: ran the setup steps (image build, bootstrap, workers). Fixed: Mailpit host port clash,
  Boost now installs non-interactively from `boost.json`, bootstrap seeds via `db:seed` instead of
  `make:filament-user` (users have `display_name`, not `name`). Verified `/up`, `/admin`, `/horizon`,
  Mailpit, Pest, Pint, PHPStan.
- B1: `app/Modules/Core` (base `ModuleServiceProvider` loads `Http/routes.php` under `/api/v1` and
  registers listeners; `ApiException` + `ApiErrorRenderer` for the contract error format;
  `EnsureAppVersion` (426), `EnsureHasFamily` (`family` / `family:admin`), `TouchDevice`),
  `GET /api/v1/health`, rate limiters (`auth` 10/min/IP, `api` 120/min/token), CORS for
  `http://localhost:8081`, spatie/laravel-activitylog 5.1 for the audit trail.
- B2: Identity (`User` moved to `App\Modules\Identity\Models`, `HasApiTokens`, devices bound to
  tokens), Policy (`age_bands`, `settings`, `PolicyResolver`), Family (families, members, invites).
  All B2 endpoints of `docs/API.md`. Filament: age bands, settings, families (+ members, invites),
  audit log. Seeders: policy, admin user, demo family.

### 2026-10-10
- Created the repository from the Laravel 13 skeleton with the Docker stack (PHP 8.5 FPM, nginx, MySQL 8.4, Redis, Horizon, scheduler, Mailpit) and the one-time setup scripts.
- Initial commit `7d3fcf5`. Added the design doc link to `CLAUDE.md`, plus `docs/PLAN.md` and `docs/PROGRESS.md` (not committed).
