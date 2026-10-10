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
- **Parallel development:** the frontend is built in parallel against `docs/API.md`, so the
  contract is fixed before each milestone starts.

## Working rules

- One milestone at a time. At the end of each, stop, summarize what was built and explain how to test it.
- Never commit. Moe reviews and commits from PhpStorm.
- Run every PHP, Composer, Artisan, Pest, Pint and PHPStan command through `docker compose exec app ...`.
- Check the current stable version of a package before adding it.
- Each milestone is done when: migrations, models, factories and seeders exist for new tables;
  Pest tests cover every Action and endpoint touched and pass; Pint and PHPStan (level 5) are clean;
  a Filament resource exists for any new content or settings table; `PROGRESS.md` is updated.

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

### B7 — Remaining launch content
- Sudoku (seeded generator, verified on submit), Word Puzzles, Math Sprint (generated by code, age-scaled).
- AI batch generator page in Filament using the Laravel AI SDK; review and approve flow.
- Content for all 8 categories.

### B8 — Billing, ads and notifications
- RevenueCat webhooks (idempotent via `webhook_events`), `subscriptions`, entitlement checks, tier limits.
- Server-side verification of rewarded-ad second chances.
- Push tokens, reminders, family activity alerts, quiet hours.

### B9 — Production
- Production Docker image, Hetzner VPS, HTTPS, backups, Sentry, Horizon supervision.
- Privacy policy and parental consent support in the API.

## Open questions
- Whether teens on the free tier see rewarded ads.
- Production hosting: same Docker images on Hetzner (default) or Forge without Docker.
- CIPO and USPTO trademark search for "Brainy Clan".
