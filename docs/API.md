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
| `sign_in_code_invalid` | 422 | Sign-in code unknown, used or expired |
| `social_token_invalid` | 401 | Google/Facebook token could not be verified |
| `social_signup_incomplete` | 422 | New social account: birth year/month needed (adult check) |
| `social_account_taken` | 409 | That Google/Facebook account is linked to another user |
| `last_sign_in_method` | 409 | Can't unlink the only way this user can sign in |
| `family_owner_cannot_leave` | 409 | The family owner must delete the family (or be its only member) to delete their account |
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
  "default_ui_theme": "playful", "status": "active", "joined_at": "2026-10-10T14:00:00Z",
  "email": null, "has_password": false, "email_login_allowed": false }
```
- `role`: `admin` | `member`. The family owner is always `admin`.
- `age_band`: `child` | `teen` | `adult` | `senior` (codes come from the `age_bands` table).
- `default_ui_theme`: the theme the member's age band policy gives (what "Automatic" resolves to). Lets an admin preview another member's look.
- `accessibility.theme`: `null` (use the policy default) or a theme key (see Policy). Lets a member
  pick another look; it never changes rules.
- `birth_year` / `birth_month` only for the member themself and for admins.
- `email` / `has_password` / `email_login_allowed` (from the member's band policy) only for the member themself and for admins (otherwise omitted).
  `email` is the member's email login, `null` when they sign in with codes only.

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
  "email_login_allowed": false,
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
- `collect_email`: the band may **self-register** with email + password (adults, seniors).
  `email_login_allowed`: an admin may give a member of this band an email login (seeded: child
  `false`, teen/adult/senior `true`), so a child who turns 13 can get one. Both editable in the admin.

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
- `POST /auth/code` — sign in on a new device with a one-time **sign-in code** an admin issued
  for a member (see Family → sign-in codes). Body: `code, device_name, platform`. Codes are
  case-insensitive, dashes/spaces ignored. → 200, 422 `sign_in_code_invalid`. This is how children
  (no email, no password) sign in again after a new phone, a reinstall or a sign-out.
- `POST /auth/logout` (auth) — revokes the current token and its device. → 204

- `POST /auth/social/{provider}` — sign in or sign up with `google` | `facebook` | `apple` (adults only).
  Body: `token` (Google: the **ID token**; Facebook: the **access token**), `device_name, platform`,
  plus for a first-time sign-up `birth_year, birth_month` and optionally `display_name, locale`.
  The server verifies the token with the provider, then: a linked account → sign in (**200**);
  no link but a user with the same provider-verified email → link and sign in (**200**); otherwise
  create an adult user (**201**). Without birth data on a would-be new user → 422
  `social_signup_incomplete` (the app asks for birth year/month and retries with the same token);
  an age below the self-registration threshold → 422 like `/auth/register`. 401 `social_token_invalid`.
  Returns `AuthPayload`. Children never use social sign-in.
  Google ID tokens are accepted when `aud` is any of the configured Google client IDs (web,
  Android, iOS — Android and web tokens carry the web client ID, iOS ones may carry the iOS ID).
  Facebook: the standard user access token (not iOS "Limited Login" tokens).
  Apple: `token` = the **identity token** (JWT); `aud` must be the iOS bundle ID or the web Services
  ID; optional `nonce` (the raw nonce whose SHA-256 the app passed to Apple). Apple sends the user's
  name only on the first authorization, so the app sends it as `display_name` then; the email may
  be a private relay address (`…@privaterelay.appleid.com`) and counts as verified. A new user's
  `display_name` defaults to the provider's name (first name) when not sent.
  SocialAccount: `{ "provider": "google", "email": "mom@gmail.com" | null, "linked_at": "..." }`
  (`email` is `null` when the provider shares none, e.g. a phone-only Facebook account).
  Further rules: if the provider email matches an existing non-adult user, sign-in is 403
  `forbidden` (linking from `/me/social-accounts` as a child/teen too). One link per provider:
  linking another account of the same provider replaces the old link. Unlinking a provider that is
  not linked → 404 `not_found`. Provider network errors or a server without keys → 401 `social_token_invalid`.

**How members sign in (summary):** adults with an email login use `/auth/login` or Google/Facebook (`/auth/social/{provider}`); anyone — and
always children — can be signed in on a device with an admin-issued sign-in code; first-time
members use their invite code (`/auth/join`). No shared passwords, no parent email reuse.

### Me
- `GET /me` (auth) → `{ "data": { "user", "member" | null, "family" | null, "policy" } }`. The app calls it on launch.
- `PATCH /me` (auth) — Body (all optional): `display_name, avatar, locale`. → `{ data: User }`
- `PATCH /me/accessibility` (auth, needs family) — Body (all optional): `text_scale (0.8–1.6),
  reduced_motion, calm_timers, theme (null | playful | sleek | modern | classic)`. → `{ data: Member }`
- `GET /me/social-accounts` (auth) → `{ data: [ { "provider": "google", "email": "mom@gmail.com", "linked_at": "..." } ] }`
- `POST /me/social-accounts/{provider}` (auth) — Body: `token`, `nonce?` (Apple). Links Google/Facebook to the
  signed-in adult (e.g. after joining by invite). → **201** `{ data: SocialAccount }`, 409 `social_account_taken`.
- `DELETE /me/social-accounts/{provider}` (auth) → 204; 409 `last_sign_in_method` when the user has
  no password and no other linked provider.
- `PUT /me/password` (auth) — set or change the password of the user's email login. Body:
  `password (min 8), password_confirmation, current_password` (`current_password` required only
  when `has_password` is true). 422 when the user has no email. → 204
- `GET /me/stats` (auth, needs family) →
  `{ "data": { "points_today": 4, "points_week": 23, "points_total": 120, "streak": { "current": 3, "longest": 7 } } }`

**Account deletion** (App Store guideline 5.1.1(v); also on Android/web):
- `GET /me/deletion-check` (auth) → `{ "data": { "can_delete": true, "blockers": [],
  "deletes_family": false, "active_subscription": false, "store": null } }`. `blockers`:
  `family_owner` (owner of a family with other members — delete the family instead), `child_account`
  (a member below the adult band — children and teens are managed by the family admin, who removes them).
  `deletes_family`: true when the caller is the owner and the only member (the family goes too).
  `active_subscription`: the family has an active/grace store subscription — the app must tell the
  user to cancel it in the App Store / Google Play (deleting the account does not cancel billing).
- `DELETE /me` (auth) — Body: `confirm: "DELETE"`. Deletes the account now: signs out every
  device, unlinks social accounts (Apple tokens are revoked with Apple), removes the membership,
  and erases personal data (display name → "Deleted player", email, password, birth data, avatar
  cleared). Points ledger rows stay (append-only) but no longer point to personal data; past goal
  results show the member as "Deleted player". → 204, 422 without `confirm`, 409 `family_owner_cannot_leave`, 403 `forbidden` for a `child_account`.
- `DELETE /family` (owner) — Body: `confirm: "<family name>"`. Deletes the family, erases every
  account in it **without its own login** (no email login and no linked Google/Apple/Facebook — e.g.
  children and code-only teens, who have no account outside the family), removes the other
  members' memberships (their accounts remain, without a family) and erases the owner's account too
  when Body `delete_my_account: true`. → 204, 422 on a wrong `confirm`, 403 `forbidden` for a
  non-owner. The family row is kept as "Deleted family" (`deleted_at`) so the points ledger stays intact.
  `active_subscription` / `store` in the deletion check describe the caller's family (any member).
- Children can't delete themselves; an admin removing a child (`DELETE /family/members/{member}`)
  also erases that child's account.
- **Apple revocation:** `POST /auth/social/apple` and `POST /me/social-accounts/apple` accept an
  optional `authorization_code` (from Apple's sign-in result). The server exchanges it for a
  refresh token (stored encrypted) and revokes it on unlink and deletion.

### Family
- `POST /families` (auth) — Body: `name, timezone (IANA), country_code (ISO-2)`. Creates the family
  (tier `free`), the caller becomes owner and `admin`. → **201** `{ data: Family }`, 409 `already_in_family`.
- `GET /family` (auth, needs family) → `{ data: { family: Family, members: Member[] } }`
- `PATCH /family` (admin) — `name?, timezone?` → `{ data: Family }`
- `PATCH /family/members/{member}` (admin) — Body (all optional): `nickname, avatar,
  difficulty_override (null | 1–10), birth_year, birth_month, role, accessibility { ... },
  email (null | email)`. An admin cannot demote the owner. `email` adds, changes or removes the
  member's email login: 422 on `email` when the member's policy has `email_login_allowed: false`
  or the address is taken; only the owner may change the owner's email (403 `forbidden`). Removing it (`null`) also clears the password (signed-in devices stay signed in; sign them out via devices). After an email is added
  the member sets a password on their own device (`PUT /me/password`). → `{ data: Member }`
- `POST /family/members/{member}/sign-in-codes` (admin) — issue a one-time sign-in code for that
  member's new device. → **201** `{ data: { "code": "K7QM4XPA", "member_id": 7, "expires_at": "..." } }`.
  Same alphabet as invites, valid **15 minutes**, single use; issuing a new one voids the member's
  older unused codes.
- `GET /family/members/{member}/devices` (admin, or the member themself) →
  `{ data: [ { "id": 5, "name": "Sam's iPad", "platform": "ios", "app_version": "1.0.0",
  "last_seen_at": "...", "is_current": false } ] }` — signed-in devices.
- `DELETE /family/members/{member}/devices/{device}` (admin, or the member themself) — signs that
  device out (revokes its token). → 204
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
`answered` counts answered **regular** items only (redemption answers never change it, right or wrong).

Session item (answers stripped):
```json
{ "id": 55, "position": 1, "type": "multiple_choice", "is_redemption": false,
  "points": 1, "payload": { ... }, "answered": false }
```
`points`: what a correct answer is worth (1 for most types; bigger puzzles are worth more, see
Challenge payloads). A wrong answer is always 1 mistake.

- `POST /play/sessions` — Body: `category_id`. Server picks items (category subtree, member's
  difficulty range, least-seen first). Target and cap come from settings (default target 10,
  blunder cap 30% → 3). → **201** `{ data: { session: Session, items: SessionItem[] } }`
  (initial batch of `target_points + blunder_cap` items). 403 `category_not_allowed`.
- `GET /play/sessions/{session}` → `{ data: { session, items } }` (own sessions only).
- `POST /play/sessions/{session}/answers` — Body: `session_item_id, response, time_ms`.
  → `{ data: { is_correct: true, correct_response: { ... }, explanation: null | "...",
  points_awarded: 1, session: Session } }`. Answering an already answered item → 409 `item_already_answered`.
  A correct answer earns the item's `points`. Reaching `target_points` → `completed`; reaching `blunder_cap`
  mistakes → `failed`. A `response` that is malformed for the item (e.g. a `choice_id` that is not one
  of the item's choices) → **422** `validation_failed` on `response.choice_id`; it is not counted as a
  mistake. Answering while the session is not in the right state (regular item outside `active`,
  redemption item outside `redemption`) → 409 `session_not_active`.
- `POST /play/sessions/{session}/second-chance` — Body: `method: "free" | "ad"`, `reward_ref?`
  (AdMob reward id, verified server-side in B8). Only when `failed`. Resets `mistakes` to 0,
  status back to `active`, increments `second_chances_used`. → `{ data: { session, items } }`
  (always **all** session items, answered ones included, plus any fresh ones). 403 `second_chance_unavailable`;
  409 `session_not_active` when the session is not `failed`.
- `POST /play/sessions/{session}/redemption` — Only when `failed` and redemption is allowed.
  Status → `redemption`; adds 3 easier items with `is_redemption: true` and returns all session items.
  → `{ data: { session, items } }`. Answer them via `/answers`. All 3 correct → like a second chance
  (status `active`, mistakes 0, `second_chances_used` +1). Any wrong → status `failed` with
  `redemption_available: false`; the round's **unanswered** redemption items are void and are no longer
  listed in any `items` array (answered ones stay). 409 `session_not_active` when not `failed`;
  403 `second_chance_unavailable` when redemption is not allowed or already failed in this session.
- `POST /play/sessions/{session}/abandon` → `{ data: Session }`

### Challenge payloads (`type` → `payload`, `response`, `correct_response`)

| type | payload | response | correct_response |
| --- | --- | --- | --- |
| `multiple_choice` | `{ "question": "Capital of Peru?", "choices": [ { "id": "a", "text": "Lima" }, ... ], "image_url": null }` | `{ "choice_id": "a" }` | `{ "choice_id": "a" }` |
| `wisdom_card` | `{ "card": { "text": "We suffer more in imagination than in reality.", "author": "Seneca", "source": "Letters to Lucilius" }, "question": "What is the main idea?", "choices": [ { "id": "a", "text": "..." } ] }` | `{ "choice_id": "b" }` | `{ "choice_id": "b" }` |
| `sudoku` | `{ "size": 4, "box": [2, 2], "grid": [[0,1,0,0], ...] }` — `size` 4, 6 or 9; `box` = [rows, cols] of a box (4→[2,2], 6→[2,3], 9→[3,3]); 0 = empty | `{ "grid": [[...]] }` (the full grid) | `{ "grid": [[...]] }` (the solution) |
| `math_sprint` | `{ "question": "7 × 8", "answer_format": "integer", "time_limit_ms": 10000 }` — `answer_format`: `integer` \| `decimal` \| `fraction` | `{ "value": "56" }` (string; fractions as `"3/4"`) | `{ "value": "56" }` |
| `word_puzzle` | by `variant`, see below | see below | see below |

**`sudoku`** — solved on the device, verified on submit: correct when the submitted grid keeps
every given and is a valid complete solution. Worth 3 points (4×4), 5 (6×6) or 8 (9×9). A wrong
or incomplete grid is a mistake and `correct_response` shows the solution. The app may check
rows/columns/boxes for duplicates locally (pure Sudoku rules) but never knows the solution.

**`math_sprint`** — generated per session by the server, age-scaled (times tables, mental math,
fractions). "Simplify …" questions accept only the fully reduced fraction (`"7/8"`, not `"14/16"` or `"0.875"`; anything else is a wrong answer, not a 422). Answers may be negative in every format (`"-12"`, `"-0.5"`, `"-3/4"`). Equivalent answers are accepted (`"0.5"` for `"1/2"` when the format allows, `"3/4"` =
`"6/8"`). `time_limit_ms` is **advisory** (a countdown bar; `null` = no timer): answering late
still counts; the app hides the bar when the member has `calm_timers`.

**`word_puzzle`** variants (`payload.variant`):
| variant | payload | response | correct_response | points |
| --- | --- | --- | --- | --- |
| `anagram` | `{ "variant": "anagram", "letters": ["T","A","C"], "hint": "An animal" }` | `{ "word": "cat" }` | `{ "word": "CAT" }` | 1 |
| `missing_letter` | `{ "variant": "missing_letter", "pattern": "EL_PHANT", "hint": null }` (`_` = one missing letter, maybe several) | `{ "word": "elephant" }` | `{ "word": "ELEPHANT" }` | 1 |
| `word_search` | `{ "variant": "word_search", "grid": [["C","A","T"], ...], "words": ["CAT", "DOG"] }` (words run in 8 directions) | `{ "found": [ { "word": "CAT", "start": [0,0], "end": [0,2] } ] }` ([row, col], zero-based) | `{ "found": [ ... ] }` (all placements) | 3 |
Words are compared case-insensitively and Unicode-aware (NFC-normalized, `mb_` case folding, so
French accents work); an anagram accepts any listed valid word made of exactly those letters. A word
search is correct when every word is found at a valid placement; `start`/`end` may be given in
either order (reading direction or reversed).

**Sessions and generated items:** Sudoku and Math Sprint items are generated by the server from a
seed when a session starts (they are not content rows); the API shape is the same, and an
item's payload never changes once generated (re-fetching the session returns the same payload). A session in a
category with big puzzles still uses `target_points` (e.g. two 9×9 Sudokus reach 10 points); the
server sizes the initial batch so the target is reachable within the blunder cap.

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
`status`: `open` | `closed`. `entries` sorted by `rank` (ties share a rank). `starts_at` is inclusive,
`ends_at` **exclusive** (UTC instants of the family-timezone boundaries). The first period of a plan
starts at `starts_on` (so it can be shorter than a full week/month); the last ends after `ends_on`.
- `reached` (top level) is the **cooperation** result: team total ≥ `target_points`. In competition
  mode use each entry's `reached` (points ≥ that member's `target`); the top-level value there is just
  "at least one member reached their target".
- `target` per entry = the member's `target_override ?? target_points`, snapshotted when the period
  opened (plan edits apply from the next period).

- `GET /goals` (needs family) → `{ data: GoalPlan[] }` active plans first, each with `current_period`.
- `GET /goals/{plan}` → `{ data: GoalPlan }`
- `POST /goals` (admin) — Body: `name, mode, period_type, target_points (1–10000), reward_text?,
  starts_on (date, ≥ today in family tz), ends_on?, category_ids (≥1, top-level or sub-scope),
  members? ([{ member_id, target_override? }], default: all members)`.
  → **201** `{ data: GoalPlan }`. 403 `goal_limit_reached`; 403 `category_not_allowed` for locked categories.
- `PATCH /goals/{plan}` (admin) — same fields, all optional, plus `is_active`. Changes apply from the
  next period; the open period keeps its target. → `{ data: GoalPlan }`
- `DELETE /goals/{plan}` (admin) → 204. Soft delete: the plan disappears from `GET /goals` and `GET /goals/{plan}` (404); its periods and ledger history are kept in the database. Pausing is `PATCH is_active: false` (stays listed).
- `GET /goals/{plan}/history?page=1` → closed periods, newest first, 20 per page:
```json
{ "data": [ { "period_id": 30, "starts_at": "...", "ends_at": "...", "status": "closed",
              "team_total": 112, "target_points": 100, "reached": true,
              "winner_member_ids": [7], "entries": [ ... ] } ],
  "meta": { "current_page": 1, "last_page": 3 } }
```
  `winner_member_ids`: competition → top rank (only members with more than 0 points; ties all win);
  cooperation → every plan member when `reached`, else `[]`.
- A scheduled job closes ended periods (writes `result`), opens the next one, and dispatches
  `PeriodClosed` / `GoalReached`.

### Billing, push and notifications (B8)
**Subscriptions** are bought in the app through RevenueCat (F6); the server learns about them only
from RevenueCat webhooks and never trusts the app for the tier.
- RevenueCat `app_user_id` = `family-{family_id}` (one entitlement per family; the paying adult's
  purchase upgrades the whole family). Entitlement id `family`.
- `POST /api/v1/webhooks/revenuecat` (public, no Sanctum auth; `Authorization: Bearer <REVENUECAT_WEBHOOK_SECRET>`)
  — idempotent by `event.id` (`webhook_events`); updates `subscriptions` and the family's `tier`
  (`family` while the entitlement is active incl. grace period, else `free`). → 200 (also for
  duplicates and ignored event types) with `{ "data": { "event_id": "...", "outcome": "applied" | "ignored" | "stale" | "duplicate" } }`,
  401 on a wrong secret, 422 when `event.id` / `event.type` are missing.
- `GET /family/subscription` (admin) → `{ "data": { "tier": "family", "status": "active" | "grace" |
  "expired" | "none", "store": "app_store" | "play_store" | "stripe" | null, "product_id": "...",
  "expires_at": "...", "will_renew": true, "revenuecat_app_user_id": "family-1" } }`.
  The app passes `revenuecat_app_user_id` to `Purchases.logIn()`.
- Family members over the free limits keep their data when a subscription ends; limits apply to
  new members/goals/categories only.

**Rewarded ads:** `POST /play/sessions/{session}/second-chance` with `method: "ad"` requires
`reward_ref` = the AdMob server-side-verification `transaction_id`. `GET /webhooks/admob-ssv` (public)
receives AdMob's signed SSV callback (verified against Google's published keys) and stores the reward;
a second chance with an unknown or already used `reward_ref` → 403 `second_chance_unavailable`.
Custom data in the ad request: `session_id`.

**Push notifications** (Expo push service):
- `PUT /me/push-token` (auth) — Body: `token` (Expo push token `ExponentPushToken[...]`), `platform`.
  Stored on the current device (and removed from any other device that had it). → 204; 422 when the token has no device. `DELETE /me/push-token` → 204 (also cleared on logout).
- `GET /me/notification-preferences` (auth, needs family) → `{ "data": { "types": { "goal_reached": true,
  "period_closed": true, "family_activity": true, "daily_reminder": false }, "quiet_from": "20:00",
  "quiet_to": "07:30", "reminder_time": "17:00" } }` (times in the family timezone; children default
  to quiet hours 19:30–07:30, everyone else 21:30–07:00; `quiet_from`/`quiet_to` both `null` = no quiet
  hours, only one of them → 422). `PATCH /me/notification-preferences` — same shape, all optional.
  Admins may also `GET` / `PATCH /family/members/{member}/notification-preferences` (e.g. children's quiet hours).
- Sent by the server (queued; never during quiet hours: delayed until quiet hours end, dropped
  when older than 12 h): `goal_reached` (cooperation target reached / race winner), `period_closed` (results),
  `family_activity` (a member finished a session, max 1 per member per hour, not to themself),
  `daily_reminder` (at `reminder_time` if the member hasn't played today). Push `data`:
  `{ "type": "goal_reached", "goal_id": 2, "period_id": 31 }` (goal types carry `goal_id` + `period_id`) so the app can deep-link.

## Changelog
- 2026-10-10 — Initial contract for B1–B5 / F1–F3.
- 2026-10-10 — Member gains `default_ui_theme`; senior `text_scale` default is 1.0 (classic theme carries the larger type); redemption returns all session items, like second-chance.
- 2026-10-10 — Goals (B6/F4): goal plans, progress, history; `limits.max_active_goals`; `goal_limit_reached`.
- 2026-10-10 — Error code `item_already_answered` (409) for answering a session item twice.
- 2026-10-11 — Clarifications (B5/B6): malformed answer `response` → 422 (not a mistake); `answered` counts regular items only; void unanswered redemption items are dropped from `items` after a failed round; 409 `session_not_active` listed for `/second-chance` and `/redemption`; GoalProgress `reached` is the cooperation result (competition: per-entry `reached`), `ends_at` exclusive, first period clipped to `starts_on`, targets snapshotted per period.
- 2026-10-11 — Member sign-in without email: `POST /auth/code` with admin-issued sign-in codes (`POST /family/members/{member}/sign-in-codes`), member devices list/revoke, admin-managed member `email` (`PATCH /family/members/{member}`), `PUT /me/password`; Member gains `email`, `has_password`; Policy gains `email_login_allowed`; error `sign_in_code_invalid`.
- 2026-10-11 — B7/F5 challenge types: `sudoku`, `math_sprint`, `word_puzzle` (variants `anagram`, `missing_letter`, `word_search`) payload/response shapes; SessionItem gains `points` (value of a correct answer); points per correct answer = item `points`.
- 2026-10-11 — Social sign-in (B6b/F4b): `POST /auth/social/{provider}` (google, facebook), `/me/social-accounts` list/link/unlink; errors `social_token_invalid`, `social_signup_incomplete`, `social_account_taken`, `last_sign_in_method`.
- 2026-10-11 — Member gains `email_login_allowed` (self/admin only); `DELETE /goals/{plan}` hides the plan (soft delete, 404 afterwards) instead of looking paused.
- 2026-10-11 — Only the family owner can change the owner's email login (403 `forbidden` for co-admins).
- 2026-10-11 — B7 clarifications: Unicode-aware case-insensitive word comparison; word-search placements accepted in either direction; math answers may be negative in any format; generated item payloads are stable for the session.
- 2026-10-11 — Social sign-in clarifications: Google `aud` = any configured client ID; Facebook standard access tokens only; `display_name` defaults from the provider; SocialAccount `email` nullable.
- 2026-10-11 — Social sign-in edge rules: non-adult email match → 403 `forbidden`; one link per provider (re-link replaces); unlink of an unlinked provider → 404; provider/network failure → 401 `social_token_invalid`.
- 2026-10-11 — Math Sprint: "Simplify" questions require the fully reduced fraction.
- 2026-10-11 — B8 contract: RevenueCat webhook + `GET /family/subscription` (`family-{id}` app user id), AdMob SSV `reward_ref`, push token + notification preferences, notification types.
- 2026-10-14 — Admins can `GET /family/members/{member}/notification-preferences` (was PATCH only).
- 2026-10-14 — RevenueCat webhook lives at `/api/v1/webhooks/revenuecat`; 200 body `{ event_id, outcome }`; 422 without `event.id`/`event.type`.
- 2026-10-15 — Sign in with Apple (B6c/F4c): provider `apple` for `/auth/social/{provider}` and `/me/social-accounts/{provider}`; identity token + optional `nonce`.
- 2026-10-15 — `POST /me/social-accounts/{provider}` accepts `nonce` (Apple). App bundle ID / Apple `aud`: `app.brainyclan`.
- 2026-10-15 — `nonce` is used for Apple only (ignored for Google/Facebook). Planned for account deletion (B6c): `DELETE /me` and an optional Apple `authorization_code` on sign-in (to revoke Apple tokens) — not implemented yet.
- 2026-10-15 — Notification preferences: adult default quiet hours 21:30–07:00; both quiet times `null` = off; push token moves to the current device; 422 without a device.
- 2026-10-15 — Push `data` for goal notifications includes `period_id`.
- 2026-10-15 — Account deletion: `GET /me/deletion-check`, `DELETE /me`, `DELETE /family`, child erasure on member removal, Apple `authorization_code` for token revocation; error `family_owner_cannot_leave`.
- 2026-10-16 — Account deletion: blocker `child_account` (children and teens; `DELETE /me` → 403); `DELETE /family` non-owner → 403; deleted families are kept as anonymised rows.
- 2026-10-16 — `DELETE /family` erases members without their own login (no email, no social account), not only children.
