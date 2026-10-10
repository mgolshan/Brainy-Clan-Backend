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
| B5 Play and Scoring | In progress | /play/* endpoints + actions + events + ledger/streak listeners written and registered, but UNTESTED (no Pest tests, no smoke test). Missing: /me/stats + /family/leaderboard routes/controllers, tests, demo ledger history |
| B6 Goals | Not started | |
| B7 Remaining launch content | Not started | |
| B8 Billing, ads and notifications | Not started | |
| B9 Production | Not started | |

## Next up

0. **State at last stop (session 2, end):** DONE but untested: Play actions (StartSession, SubmitAnswer,
   GrantSecondChance, StartRedemption, AbandonSession, AddSessionItems/topUp, PickContentItems), resources
   (SessionPayload returns ALL items), `Play/Http/routes.php` (6 routes live), events, Scoring actions
   (AppendPoints, RecordDailyActivity, ComputeMemberStats, BuildLeaderboard), listeners (RecordAnswerPoints
   sync, UpdateStreak queued, RefreshMemberTotals), Play+Scoring providers registered,
   `limits.max_active_goals` in policy + PolicySeeder (re-run `db:seed --class=PolicySeeder` in dev).
   REMAINING: Scoring HTTP (`GET /me/stats`, `GET /family/leaderboard` controllers + `Scoring/Http/routes.php`),
   Pest tests for all of B5, pint/phpstan/pest run, live curl smoke test, demo ledger history seeder.
   **Gotcha:** config is cached, which also freezes the provider list — after adding a provider run
   `php artisan config:cache` again (then `docker compose restart app horizon`).
1. **Finish B5** (details below). Originally on disk:
   migration `2026_10_10_170000_create_play_and_scoring_tables` (migrated in dev), models
   `app/Modules/Play/Models/*` and `app/Modules/Scoring/Models/*` (PointEntry throws on update/delete),
   `App\Modules\Family\Support\FamilyClock` (tz windows), `Play/Actions/PickContentItems`.
   Still to write:
   - `PlayServiceProvider` + `ScoringServiceProvider` (register in `bootstrap/providers.php`); Play binds
     `Policy\Contracts\SecondChanceUsage` (count `second_chances` method `free` since family-tz midnight).
   - Play actions: StartSession (ResolveCategoryAccess::canPlay → 403 `category_not_allowed`; rules from
     `PolicyResolver::playRules`; 422 on `category_id` when no content), SubmitAnswer (lock session; 409
     `item_already_answered` / `session_not_active`; redemption items need status `redemption`), GrantSecondChance
     (free/ad via `secondChanceForMember`, record `reward_ref`, stub verification), StartRedemption (3 easier items,
     403 `second_chance_unavailable` when `redemption_failed` or not allowed), AbandonSession, top-up so
     unanswered normal items ≥ (target−points)+(cap−mistakes). Update `member_item_history`.
   - **All of `/second-chance` and `/redemption` return ALL session items** (contract changelog).
   - Events `Play\Events\AnswerSubmitted` / `SessionCompleted`; Scoring sync listener appends the ledger
     row inside the transaction; queued (`afterCommit`) listeners UpdateStreak + RefreshMemberTotals.
   - `GET /me/stats`, `GET /family/leaderboard?period=` (FamilyClock windows, ties share rank, `all` → null bounds).
   - Session/SessionItem resources (`answered` = answered non-redemption items), Pest tests, demo ledger history.
   - Add `item_already_answered` (409) to the error table in `docs/API.md` + changelog line.
2. **Policy `limits.max_active_goals`** (contract update): free `1`, family `null`; add settings to
   `PolicySeeder` and the key to `PolicyResolver::build()` (not done yet — frontend type expects it).
3. **B6 Goals** per `docs/API.md` "Goals (B6)" (not started).
4. README: refresh fully (setup, seeded logins/codes, curl examples, admin tour). Partially updated.

## Known issues and notes

- **Performance on the Windows drive:** `.env` has `PHP_OPCACHE_VALIDATE_TIMESTAMPS=0` and config is cached.
  After changing PHP code: `docker compose restart app horizon`. After changing `.env` or `config/*`:
  `docker compose exec app php artisan config:cache` (or `config:clear`). First request per PHP-FPM worker
  after a restart is still slow (~10 s); warm requests ~0.5–0.8 s. Moving the repo into WSL2 removes this.
- Tests always use in-memory SQLite and never read dev caches; `tests/TestCase.php` aborts if not SQLite.

- Boost: `boost.json` (committed) holds the choices (Claude Code, guidelines + MCP), so
  `boost:install --no-interaction` works in the setup scripts. `.mcp.json` runs Boost through Docker.
- Mailpit's host ports are 8026 (UI) / 1026 (SMTP) because a host Mailpit (Laragon) already uses
  8025/1025. Override with `MAILPIT_UI_PORT` / `MAILPIT_SMTP_PORT`.
- `EnsureAppVersion` is global middleware (so 426 comes before 401); it only acts on `api/*`.
- The test suite takes ~1 min on the Windows bind mount (first test boots slowly).
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

## Log

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
