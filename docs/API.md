# Brainy Clan API v1 — Contract

The single source of truth for the mobile/web app ↔ backend interface. The backend implements it,
the frontend (`Brainy-Clan-Frontend`) codes against it. Change it here first, then in both repos,
and add a line to the changelog at the bottom.

## Conventions

- Base path `/api/v1`. JSON only (`Accept: application/json`).
- Auth: Sanctum personal access token per device, `Authorization: Bearer <token>`.
- **Envelope:** every success body is `{ "data": ... }` (Laravel `JsonResource`). Lists are
  `{ "data": [ ... ] }`. Plain resources, not full JSON:API (no `type`/`attributes` nesting) —
  simpler to type in TypeScript.
- Keys are `snake_case`. Ids are integers. Timestamps are ISO 8601 UTC strings (`2026-10-10T14:00:00Z`).
- Localized strings (category names, content) are resolved server-side to the member's `locale`
  (fallback `en`); the API returns plain strings.
- **Errors:** `{ "message": "Human readable", "code": "snake_case_code", "errors": { "field": ["..."] } }`.
  `errors` only on 422. Status codes: 401 unauthenticated, 403 forbidden / limit, 404, 409 state
  conflict, 422 validation, 426 app too old, 429 rate limited.
- Optional header `X-App-Version: 1.0.0`. If lower than `min_app_version` the API answers
  **426** with `code: "app_update_required"` (health endpoint is exempt).
- Rate limits: `auth/*` 10/min per IP, everything else 120/min per token.

### Error codes

| code | status | meaning |
| --- | --- | --- |
| `validation_failed` | 422 | Field errors in `errors` |
| `unauthenticated` | 401 | Missing/invalid token |
| `invalid_credentials` | 401 | Wrong email/password |
| `forbidden` | 403 | Not allowed (e.g. non-admin calling an admin endpoint) |
| `member_limit_reached` | 403 | Tier member limit reached |
| `category_not_allowed` | 403 | Category outside tier/age policy |
| `no_family` | 409 | Endpoint needs a family membership and the user has none |
| `already_in_family` | 409 | User already belongs to a family |
| `invite_invalid` | 422 | Invite code unknown, used or expired |
| `session_not_active` | 409 | Play session is not in a state that allows this action |
| `item_already_answered` | 409 | Session item was already answered |
| `second_chance_unavailable` | 403 | No second chance of that method left / allowed |
| `goal_limit_reached` | 403 | Tier limit on active goal plans reached |
| `app_update_required` | 426 | App older than `min_app_version` |
| `not_found` | 404 | |

## Shared objects

### User
```json
{ "id": 1, "display_name": "Mom", "avatar": "owl", "email": "mom@example.com",
  "locale": "en", "birth_year": 1988, "birth_month": 4 }
```
`email` is `null` for children. Birth data only appears for the user themself and to family admins.

**Avatars** are keys from a fixed set the app renders:
`fox, owl, panda, lion, koala, penguin, tiger, rabbit, bear, frog, cat, dog, unicorn, dragon, turtle, octopus`.

### Family
```json
{ "id": 1, "name": "The Golshans", "timezone": "America/Toronto", "country_code": "CA",
  "tier": "free", "owner_user_id": 1, "member_count": 4 }
```

### Member
```json
{ "id": 7, "user_id": 3, "nickname": "Sam", "avatar": "fox", "role": "member",
  "age_band": "child", "is_child": true, "birth_year": 2017, "birth_month": 3,
  "difficulty_override": null,
  "accessibility": { "text_scale": 1.0, "reduced_motion": false, "calm_timers": false, "theme": null },
  "default_ui_theme": "playful", "status": "active", "joined_at": "2026-10-10T14:00:00Z" }
```
- `role`: `admin` | `member`. The family owner is always `admin`.
- `age_band`: `child` | `teen` | `adult` | `senior` (codes come from the `age_bands` table).
- `default_ui_theme`: the theme the member's age band policy gives (what "Automatic" resolves to). Lets an admin preview another member's look.
- `accessibility.theme`: `null` (use the policy default) or a theme key (see Policy). Lets a member
  pick another look; it never changes rules.
- `birth_year` / `birth_month` only for the member themself and for admins.

### Policy (resolved for the signed-in member)
```json
{
  "age_band": "child",
  "age": 9,
  "is_child": true,
  "ui_theme": "playful",
  "tier": "free",
  "ads_allowed": false,
  "purchases_allowed": false,
  "collect_email": false,
  "difficulty": { "min": 1, "max": 4, "default": 2 },
  "second_chance": { "free_per_day": 0, "free_used_today": 0, "ad_allowed": false, "redemption_allowed": true },
  "limits": { "max_members": 2, "max_categories": 3, "max_active_goals": 1 },
  "accessibility_defaults": { "text_scale": 1.0, "reduced_motion": false, "calm_timers": false },
  "feature_flags": {}
}
```
- Produced by `PolicyResolver` from age band + tier + settings. The app **renders** from it and
  never computes these rules itself.
- `ui_theme` default per band (editable in the admin): `child → playful`, `teen → sleek`,
  `adult → modern`, `senior → classic`. Seniors also default to `calm_timers: true`. `accessibility_defaults.text_scale` stays `1.0` for every band: the `classic` theme already has a larger type scale, so a policy scale on top would compound (×1.44).
- Seed age bands: child 0–12, teen 13–17, adult 18–64, senior 65+.
- Before a family exists the user still gets a policy (tier `free`).

## Endpoints

### Health
`GET /health` (public, exempt from the version check)
```json
{ "data": { "status": "ok", "api_version": "1", "min_app_version": "1.0.0", "time": "2026-10-10T14:00:00Z" } }
```

### Auth
All return `AuthPayload`:
```json
{ "data": { "token": "1|abc...", "user": { User }, "member": { Member } | null,
            "family": { Family } | null, "policy": { Policy } } }
```
Every auth call takes `device_name` (string) and `platform` (`ios` | `android` | `web`) and creates
a `devices` row bound to the new token.

- `POST /auth/register` — adult sign-up. Body: `display_name, email, password (min 8), birth_year,
  birth_month, locale?, device_name, platform`. 422 if the computed age is below the adult
  threshold (`collect_email` false for that band). → **201**
- `POST /auth/login` — Body: `email, password, device_name, platform`. → 200, 401 `invalid_credentials`.
- `POST /auth/join` — join a family with an invite code. Body: `code, device_name, platform,
  email?, password?`. Creates the user from the invite (nickname, birth data) and the membership.
  `email`/`password` are accepted only when the invitee's band has `collect_email: true`
  (adults); children never send them. Codes are case-insensitive. → **201**, 422 `invite_invalid`.
- `POST /auth/logout` (auth) — revokes the current token and its device. → 204

### Me
- `GET /me` (auth) → `{ "data": { "user", "member" | null, "family" | null, "policy" } }`. The app calls it on launch.
- `PATCH /me` (auth) — Body (all optional): `display_name, avatar, locale`. → `{ data: User }`
- `PATCH /me/accessibility` (auth, needs family) — Body (all optional): `text_scale (0.8–1.6),
  reduced_motion, calm_timers, theme (null | playful | sleek | modern | classic)`. → `{ data: Member }`
- `GET /me/stats` (auth, needs family) →
  `{ "data": { "points_today": 4, "points_week": 23, "points_total": 120, "streak": { "current": 3, "longest": 7 } } }`

### Family
- `POST /families` (auth) — Body: `name, timezone (IANA), country_code (ISO-2)`. Creates the family
  (tier `free`), the caller becomes owner and `admin`. → **201** `{ data: Family }`, 409 `already_in_family`.
- `GET /family` (auth, needs family) → `{ data: { family: Family, members: Member[] } }`
- `PATCH /family` (admin) — `name?, timezone?` → `{ data: Family }`
- `PATCH /family/members/{member}` (admin) — Body (all optional): `nickname, avatar,
  difficulty_override (null | 1–10), birth_year, birth_month, role, accessibility { ... }`.
  An admin cannot demote the owner. → `{ data: Member }`
- `DELETE /family/members/{member}` (admin, not the owner) → 204
- `POST /family/invites` (admin) — Body: `nickname, birth_year, birth_month, avatar?`.
  → **201** `{ data: Invite }`, 403 `member_limit_reached` (members + pending invites ≥ limit).
- `GET /family/invites` (admin) → pending (unused, unexpired) invites.
- `DELETE /family/invites/{invite}` (admin) → 204

Invite:
```json
{ "id": 3, "code": "K7QM4XPA", "nickname": "Sam", "avatar": "fox", "age_band": "child",
  "birth_year": 2017, "birth_month": 3, "expires_at": "2026-10-17T14:00:00Z", "created_at": "..." }
```
Codes: 8 characters from `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` (no 0/O/1/I), valid 7 days, single use.

### Content
- `GET /categories` (auth, needs family) — the category tree visible to the member (active,
  age range contains the member's age). Each node:
```json
{ "id": 1, "slug": "world-geography", "name": "World Geography", "icon": "globe",
  "parent_id": null, "min_age": 6, "max_age": null, "is_locked": false,
  "challenge_types": ["multiple_choice"], "children": [ { ... } ] }
```
  `is_locked` is true for top-level categories beyond the tier's `max_categories` (free tier) — the
  app shows them with an upgrade hint instead of hiding them. `icon` is a short key
  (`grid, puzzle, quote, globe, landmark, leaf, calculator, book`) the app maps to an icon.

### Play
Session:
```json
{ "id": 12, "category_id": 4, "status": "active", "target_points": 10, "blunder_cap": 3,
  "mistakes": 1, "points": 6, "answered": 7, "second_chances_used": 0,
  "second_chance": { "free_remaining_today": 3, "ad_available": false, "redemption_available": true },
  "started_at": "...", "completed_at": null }
```
`status`: `active` | `redemption` (redemption round in progress) | `failed` (blunder cap hit; a
second chance may still be possible) | `completed` (target reached) | `abandoned`.
`second_chance` is meaningful when `status = failed`.

Session item (answers stripped):
```json
{ "id": 55, "position": 1, "type": "multiple_choice", "is_redemption": false,
  "payload": { ... }, "answered": false }
```

- `POST /play/sessions` — Body: `category_id`. Server picks items (category subtree, member's
  difficulty range, least-seen first). Target and cap come from settings (default target 10,
  blunder cap 30% → 3). → **201** `{ data: { session: Session, items: SessionItem[] } }`
  (initial batch of `target_points + blunder_cap` items). 403 `category_not_allowed`.
- `GET /play/sessions/{session}` → `{ data: { session, items } }` (own sessions only).
- `POST /play/sessions/{session}/answers` — Body: `session_item_id, response, time_ms`.
  → `{ data: { is_correct: true, correct_response: { ... }, explanation: null | "...",
  points_awarded: 1, session: Session } }`. Answering an already answered item → 409 `item_already_answered`.
  1 point per correct answer. Reaching `target_points` → `completed`; reaching `blunder_cap`
  mistakes → `failed`.
- `POST /play/sessions/{session}/second-chance` — Body: `method: "free" | "ad"`, `reward_ref?`
  (AdMob reward id, verified server-side in B8). Only when `failed`. Resets `mistakes` to 0,
  status back to `active`, increments `second_chances_used`. → `{ data: { session, items } }`
  (always **all** session items, answered ones included, plus any fresh ones). 403 `second_chance_unavailable`.
- `POST /play/sessions/{session}/redemption` — Only when `failed` and redemption is allowed.
  Status → `redemption`; adds 3 easier items with `is_redemption: true` and returns all session items.
  → `{ data: { session, items } }`. Answer them via `/answers`. All 3 correct → like a second chance
  (status `active`, mistakes 0, `second_chances_used` +1). Any wrong → status `failed` with
  `redemption_available: false`.
- `POST /play/sessions/{session}/abandon` → `{ data: Session }`

### Challenge payloads (`type` → `payload`, `response`, `correct_response`)

| type | payload | response | correct_response |
| --- | --- | --- | --- |
| `multiple_choice` | `{ "question": "Capital of Peru?", "choices": [ { "id": "a", "text": "Lima" }, ... ], "image_url": null }` | `{ "choice_id": "a" }` | `{ "choice_id": "a" }` |
| `wisdom_card` | `{ "card": { "text": "We suffer more in imagination than in reality.", "author": "Seneca", "source": "Letters to Lucilius" }, "question": "What is the main idea?", "choices": [ { "id": "a", "text": "..." } ] }` | `{ "choice_id": "b" }` | `{ "choice_id": "b" }` |
| `sudoku` (B7) | `{ "size": 4, "grid": [[0,1,0,0], ...] }` (0 = empty) | `{ "grid": [[...]] }` | `{ "valid": true }` |
| `word_puzzle` (B7) | defined in B7 | | |
| `math_sprint` (B7) | defined in B7 | | |

### Leaderboard
- `GET /family/leaderboard?period=today|week|month|all` (default `week`, computed in the family's
  timezone from the points ledger) →
```json
{ "data": { "period": "week", "starts_at": "...", "ends_at": "...", "team_total": 57,
  "entries": [ { "member_id": 7, "nickname": "Sam", "avatar": "fox", "points": 23, "rank": 1 } ] } }
```
  Goal-plan leaderboards are under Goals below.

### Goals (B6)
A **goal plan** is what an admin configures; a **goal period** is one concrete window (a day, week
or month in the family's timezone). Progress is computed from the points ledger: points earned
inside the period window, in categories within the plan's scopes (a scope includes its subtree),
by the plan's members. One answer can count toward several plans.

GoalPlan:
```json
{ "id": 2, "name": "Pizza Friday", "mode": "cooperation", "period_type": "weekly",
  "target_points": 100, "reward_text": "Pizza night!", "starts_on": "2026-10-12", "ends_on": null,
  "is_active": true,
  "scopes": [ { "category_id": 4, "name": "World Geography" } ],
  "members": [ { "member_id": 7, "nickname": "Sam", "avatar": "fox", "target_override": 20 } ],
  "current_period": GoalProgress | null }
```
- `mode`: `competition` (members ranked; each member's target is `target_override ?? target_points`;
  the top scorer wins the reward) | `cooperation` (team total vs `target_points`).
- `period_type`: `daily` | `weekly` (weeks start Monday) | `monthly`.
- Policy gains `limits.max_active_goals` (free tier `1`, family `null` = unlimited).

GoalProgress:
```json
{ "period_id": 31, "starts_at": "...", "ends_at": "...", "status": "open",
  "team_total": 57, "target_points": 100, "reached": false,
  "entries": [ { "member_id": 7, "nickname": "Sam", "avatar": "fox", "points": 23,
                 "target": 20, "reached": true, "rank": 1 } ] }
```
`status`: `open` | `closed`. `entries` sorted by `rank` (ties share a rank).

- `GET /goals` (needs family) → `{ data: GoalPlan[] }` active plans first, each with `current_period`.
- `GET /goals/{plan}` → `{ data: GoalPlan }`
- `POST /goals` (admin) — Body: `name, mode, period_type, target_points (1–10000), reward_text?,
  starts_on (date, ≥ today in family tz), ends_on?, category_ids (≥1, top-level or sub-scope),
  members? ([{ member_id, target_override? }], default: all members)`.
  → **201** `{ data: GoalPlan }`. 403 `goal_limit_reached`; 403 `category_not_allowed` for locked categories.
- `PATCH /goals/{plan}` (admin) — same fields, all optional, plus `is_active`. Changes apply from the
  next period; the open period keeps its target. → `{ data: GoalPlan }`
- `DELETE /goals/{plan}` (admin) → 204 (soft: deactivates, history kept).
- `GET /goals/{plan}/history?page=1` → closed periods, newest first, 20 per page:
```json
{ "data": [ { "period_id": 30, "starts_at": "...", "ends_at": "...", "status": "closed",
              "team_total": 112, "target_points": 100, "reached": true,
              "winner_member_ids": [7], "entries": [ ... ] } ],
  "meta": { "current_page": 1, "last_page": 3 } }
```
  `winner_member_ids`: competition → top rank; cooperation → everyone when `reached`, else `[]`.
- A scheduled job closes ended periods (writes `result`), opens the next one, and dispatches
  `PeriodClosed` / `GoalReached`.

## Changelog
- 2026-10-10 — Initial contract for B1–B5 / F1–F3.
- 2026-10-10 — Member gains `default_ui_theme`; senior `text_scale` default is 1.0 (classic theme carries the larger type); redemption returns all session items, like second-chance.
- 2026-10-10 — Goals (B6/F4): goal plans, progress, history; `limits.max_active_goals`; `goal_limit_reached`.
- 2026-10-10 — Error code `item_already_answered` (409) for answering a session item twice.
