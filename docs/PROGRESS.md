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
| B6c Sign in with Apple | In progress | **Sign-in done** (session 9): `AppleIdTokenVerifier` (JWKS `appleid.apple.com/auth/keys`, cached + refetch on unknown kid, `iss`, `aud` ∈ `APPLE_CLIENT_IDS`, `exp`, optional `nonce` = sha256 hex), provider `apple` on `/auth/social/{provider}` and `/me/social-accounts/{provider}`, `display_name` from the app (fallback "Player", never a private relay), Filament column "Social sign-in", README "Sign in with Apple — setup", Pest + live curl. **Left:** account deletion `DELETE /me` + Apple token revocation (App Store 5.1.1(v)) |
| B7 Remaining launch content | In progress | Sudoku + Math Sprint generated per session, Word Puzzles seeded, item `points`, Filament preview + category generators; **AI batch generator done** (Laravel AI SDK, Horizon `ai` queue, review/approve page; needs `ANTHROPIC_API_KEY` to run for real). Left: content for all 8 categories (generate + review batches) |
| B8 Billing, ads and notifications | In progress | **Billing core done** (session 8): `BillingServiceProvider`, `POST /api/v1/webhooks/revenuecat` (Bearer secret, idempotent via `webhook_events`, stale/out-of-order guard, `ApplyRevenueCatEvent` sets the family `tier`), `GET /family/subscription` (admin), factories, fixtures + 13 Pest tests, live smoke test. Dev DB migrated. Not touched in session 9. **Push token + notification preferences done** (session 10: `Notifications` module, 13 Pest tests). **Sending started** (session 11: Expo sender, `SendMemberNotification`, `goal_reached` / `period_closed` listeners, 7 Pest tests). **Left:** `family_activity`, `daily_reminder`, AdMob SSV / rewarded-ad second chance, Filament subscription view (see Next up) |
| B9 Production | Not started | |

## Next up

0. **State at last stop (session 11, 2026-10-15):** flaky `GeneratedPlayTest` fixed (test-only); B8 item 3 (sending)
   **partly done**: Expo push sender, `SendMemberNotification` (preferences, quiet hours, 12 h drop), queued listeners
   for `GoalReached` / `PeriodClosed` (see log). No migrations. **Next, in order:**
   a. B8 item 3 rest: `family_activity` (listener on the play-session-finished event; recipients = other active family
      members; max 1 per finishing member per hour per recipient — e.g. a `Cache::add` key per member/hour; not to
      themself) and `daily_reminder` (scheduled every 15 min: members whose `reminder_time` falls in the window in the
      family timezone, `daily_reminder` on, not played today; one batched query). Both reuse `SendMemberNotification`.
   b. Optional: Expo **receipts** check (`/push/getReceipts`) — currently only ticket errors clear tokens.
   c. Then B8 items 4–6 and account deletion (item a of session 9 below).
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

## Log

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
