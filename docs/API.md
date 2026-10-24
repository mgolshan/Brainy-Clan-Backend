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
  (fallback `en`); the API returns plain strings. A play session never holds the same question in
  two languages: an English item is used only when it has no published translation in the
  member's locale.
- **Errors:** `{ "message": "Human readable", "code": "snake_case_code", "errors": { "field": ["..."] } }`.
  `errors` only on 422. Status codes: 401 unauthenticated, 403 forbidden / limit, 404, 409 state
  conflict, 422 validation, 426 app too old, 429 rate limited.
- Optional header `X-App-Version: 1.0.0`. If lower than `min_app_version` the API answers
  **426** with `code: "app_update_required"` (health endpoint is exempt).
- Rate limits: `auth/*` 10/min per IP **and** 10/min per email (login), everything else 120/min per token → 429 `rate_limited`.
- **Sign-in lockout** (on top of the rate limits; see Auth → "Lockout"): 429 `login_locked` with
  `retry_after` (seconds) in the body and a `Retry-After` header.
- Request size limits (422 `validation_failed`): login `email`/`password` ≤ 255 chars, `current_password` ≤ 255;
  goals `category_ids` ≤ 200 and `members` ≤ 50; answer `response` ≤ 10 keys, `response.found` ≤ 100,
  `response.grid` ≤ 16 rows.

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
| `world_locked` | 403 | That theme world isn't unlocked for this member (see `GET /me/worlds`) |
| `chat_disabled` | 403 | A family admin turned off chat for this member |
| `subscription_exists` | 409 | The family already has an active plan (store or web) |
| `web_checkout_unavailable` | 503 | Web checkout (Paddle) is not configured |
| `referral_code_invalid` | 422 | Unknown or inactive referral code |
| `referral_code_locked` | 409 | The family's referral code is already set or it already paid |
| `ad_reward_pending` | 409 | Rewarded-ad callback not received yet — retry shortly |
| `ssv_signature_invalid` | 400 | AdMob SSV callback signature could not be verified |
| `app_update_required` | 426 | App older than `min_app_version` |
| `rate_limited` | 429 | Too many requests (rate limit) |
| `login_locked` | 429 | Too many failed sign-in attempts; body has `retry_after` (seconds) |
| `not_found` | 404 | |

## Shared objects

### User
```json
{ "id": 1, "display_name": "Mom", "avatar": "owl", "email": "mom@example.com",
  "locale": "en", "birth_year": 1988, "birth_month": 4 }
```
`email` is `null` for children. Birth data only appears for the user themself and to family admins.

**Avatars** are keys from a fixed set the app renders (the "Clan" characters, 2026-10-22):
`<species>` or `<species>-<variant>`.
- Species (22): `fox, owl, panda, lion, koala, penguin, tiger, rabbit, bear, frog, cat, dog,
  unicorn, dragon, turtle, octopus, elephant, giraffe, hedgehog, otter, raccoon, monkey`.
- Variants: none (the kid character), `teen`, `mom`, `dad`, `grandma`, `grandpa` — e.g. `tiger`,
  `tiger-teen`, `tiger-mom`, `tiger-dad`, `tiger-grandma`, `tiger-grandpa` (132 keys).
- Any member may pick any key (no age rule); the app only orders the picker. Old keys stay valid
  (they are the kid characters). Anything else → 422 `validation_failed`.

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
  "accessibility": { "text_scale": 1.0, "reduced_motion": false, "calm_timers": false, "theme": null, "sound_effects": null },
  "default_ui_theme": "playful", "status": "active", "joined_at": "2026-10-10T14:00:00Z",
  "email": null, "has_password": false, "email_login_allowed": false, "has_own_login": false,
  "chat_enabled": true }
```
- `role`: `admin` | `member`. The family owner is always `admin`.
- `age_band`: `child` | `teen` | `adult` | `senior` (codes come from the `age_bands` table).
- `default_ui_theme`: the theme the member's age band policy gives (what "Automatic" resolves to). Lets an admin preview another member's look.
- `accessibility.theme`: `null` (use the policy default) or a theme key (see Policy). Lets a member
  pick another look; it never changes rules. **Theme keys ("worlds"):** `playful`
  (shown as "Candy Land"), `sleek` (shown as "Glass"), `modern`, `classic`, `jungle`, `space`,
  `neon`, `sea`, `dino`, `sticker`, `lofi`, `nature`, `midnight`. Any member may pick any
  **unlocked** world (no age rule; see `GET /me/worlds`). Unknown key → 422 `validation_failed`;
  a locked world → 403 `world_locked`.
- `theme_unlocked`: `false` when the stored `accessibility.theme` is no longer unlocked for this
  member (e.g. the family plan ended); the app then shows `default_ui_theme`. The stored choice is
  kept so it comes back if the world unlocks again. `true` when `theme` is null.

#### Worlds and unlocks (2026-10-23, phase 2)
- `GET /me/worlds` (auth, needs family) → the catalog for the signed-in member, in display order:
```json
{ "data": [
  { "key": "playful", "unlock": { "type": "free" }, "unlocked": true },
  { "key": "jungle", "unlock": { "type": "points", "required": 50, "progress": 23 }, "unlocked": false },
  { "key": "sea", "unlock": { "type": "streak", "required": 3, "progress": 2 }, "unlocked": false },
  { "key": "dino", "unlock": { "type": "family_plan" }, "unlocked": false }
] }
```
- Unlock types: `free` (always); `points` — the member's lifetime points (`/me/stats` `points_total`;
  strictly the points earned, so a later correction entry never lowers it) ≥ `required`; `streak` — the member's longest streak in days (`streak.longest`) ≥ `required`;
  `family_plan` — the family's tier is the paid Family plan (active, trial or grace). `progress` is
  present for `points`/`streak`. Once a `points`/`streak` world is unlocked it stays unlocked
  (lifetime and longest values never go down; a corrected ledger does not re-lock). `family_plan`
  worlds lock again when the plan ends.
- Seeded catalog (editable in the admin; thresholds and types are server data, the app never
  hard-codes them): free `playful`, `sleek`, `modern`, `classic`; points `jungle` 50, `sticker`
  100, `space` 150, `nature` 200; streak `sea` 3, `lofi` 7; family plan `dino`, `neon`, `midnight`.
- `PATCH /me/accessibility` and the admin member PATCH reject a locked `theme` with 403
  `world_locked` (`null` always allowed). An admin can only pick worlds unlocked for that member:
  `GET /family/members/{member}/worlds` (admin) returns the same catalog for that member.
- Computing the catalog is a fixed number of queries (no per-world queries); it is not cached
  (points and streaks change during play).
- `accessibility.sound_effects`: `null` (use the policy default
  `accessibility_defaults.sound_effects`; seeded child `true`, teen/adult/senior `false`) or a bool.
- `birth_year` / `birth_month` only for the member themself and for admins.
- `has_own_login`: the member can sign in without the family (email login or a linked
  Google/Apple/Facebook account) — members without one are erased with the family.
- `email` / `has_password` / `email_login_allowed` / `has_own_login` (`email_login_allowed` from the member's band policy) only for the member themself and for admins (otherwise omitted).
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
  "ad_request": { "non_personalized": true, "under_age_of_consent": false, "max_ad_content_rating": "G" },
  "limits": { "max_members": 2, "max_categories": 3, "max_active_goals": 1 },
  "accessibility_defaults": { "text_scale": 1.0, "reduced_motion": false, "calm_timers": false, "sound_effects": true },
  "chat": { "retention_days": 7 },
  "feature_flags": {}
}
```
- Produced by `PolicyResolver` from age band + tier + settings. The app **renders** from it and
  never computes these rules itself.
- `ui_theme` default per band (editable in the admin): `child → playful`, `teen → sleek`,
  `adult → modern`, `senior → classic`. Seniors also default to `calm_timers: true`. `accessibility_defaults.text_scale` stays `1.0` for every band: the `classic` theme already has a larger type scale, so a policy scale on top would compound (×1.44).
- Seed age bands: child 0–12, teen 13–17, adult 18–64, senior 65+.
- `ad_request`: how the app must request rewarded ads (AdMob `requestNonPersonalizedAdsOnly`,
  `tagForUnderAgeOfConsent`, `maxAdContentRating`), from the band policy (seeded: teen
  `under_age_of_consent: true`; everyone non-personalized, rating G). The app never decides these.
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
A liveness check only (the app uses it); it does not test MySQL, Redis or the workers.

`GET /health/deep` — **monitoring only, not for the app.** Header `X-Health-Token: <HEALTH_DEEP_TOKEN>`
(server env). Without a configured token → 404 `not_found`; missing/wrong token → 401
`unauthenticated`. Exempt from the version check; 30 requests/min per IP (429 `rate_limited`).
Checks MySQL (`select 1`), Redis (`PING`), the scheduler heartbeat (written every minute, must be
younger than 180 s) and Horizon (a master supervisor running, not paused) — these are critical —
and counts failed jobs in the last hour (warning only). **200** when every critical check is `ok`
(`status: "ok"`, or `"degraded"` when only `failed_jobs` warns), **503** with the same body when any
critical check fails (`status: "fail"`). Never contains error messages, hosts or versions.
```json
{ "data": { "status": "ok",
  "checks": { "database": { "status": "ok" }, "redis": { "status": "ok" },
              "scheduler": { "status": "ok", "age_seconds": 42 },
              "horizon": { "status": "ok", "state": "running" },
              "failed_jobs": { "status": "ok", "last_hour": 0 } },
  "time": "2026-10-24T14:00:00Z" } }
```
Check `status` values: `ok` | `fail` (`failed_jobs`: `ok` | `warn` | `fail`); Horizon `state`:
`running` | `paused` | `inactive` | `unknown`; `age_seconds` / `last_hour` are `null` when unreadable.

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
- `POST /auth/password/forgot` — Body: `email, locale?` (`en`|`fr`). **Always 202**
  `{ "data": { "sent": true } }`, whether or not the email exists (no account discovery). When a
  user with that email exists, the server emails a one-time link to the web page
  `GET /password/reset?token=…` (website host, en/fr by the user's locale, link valid 60 min, a
  newer link replaces older ones). The page asks for the new password twice (min 8) and on success:
  sets the password (also for a social-only adult), signs the user out of **all** devices (all
  tokens revoked), clears every sign-in lockout for that email, and emails a "password changed"
  notice. Throttled to 3/hour per email and 10/hour per IP; over the limit the response is still
  202 and no email is sent (silently dropped, logged without the email).
- **Lockout** (password and code sign-in; never affects social sign-in or already signed-in devices):
  - `POST /auth/login`, per **email + IP**: 5 failed attempts in a row → locked 5 min; the next 5
    failures after that lock → 1 h; after that every 5 failures → 24 h. A successful login resets
    the count and the escalation; escalation also resets after 24 h without failures.
  - `POST /auth/login`, per **email** from all IPs: 20 failures within 1 h → locked 15 min (slows
    guessing spread over many IPs while keeping the owner's worst case short). The account's owner
    gets at most one "someone tried to sign in" email per 24 h when this lock starts.
  - Per **IP** across all emails and codes: 30 failures within 1 h → that IP is locked 1 h for
    `/auth/login`, `/auth/code` and `/auth/join`.
  - `POST /auth/code` and `POST /auth/join`, per **IP**: 5 invalid codes in a row → 5 min, then
    1 h, then 24 h (same escalation; a successful code sign-in resets it).
  - While locked the credentials are not checked at all, and the answer is the same whether the
    email exists or not: **429** `{ "message": "...", "code": "login_locked", "retry_after": 300 }`
    + `Retry-After: 300`. The failure that starts a lock (e.g. the 5th wrong password) already
    answers 429 `login_locked`. The `message` is English; the app translates by `code`. Unlock paths for the owner: wait, reset the password
    (`/auth/password/forgot`), social sign-in, or a sign-in code from a family admin.

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
  reduced_motion, calm_timers, theme (null | a theme key), sound_effects (null | bool)`. → `{ data: Member }`
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
  (`grid, puzzle, quote, globe, landmark, leaf, calculator, book, bulb, language, rocket, earth,
  palette, restaurant, football, shield, sparkles`; unknown keys → a generic icon) the app maps to an icon.

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
- `GET /play/sessions/current` → `{ data: { session, items } | null }` — the member's most recent
  unfinished session (`active`, `failed` or `redemption`), so the app can offer "Continue your game".
  Starting a new session (`POST /play/sessions`) abandons the member's previous unfinished sessions.
  Sessions with no answer for **24 hours** are abandoned automatically (hourly job).

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

**Brain games (2026-10-23)** — four more server-generated types (category `brain-games`). Like
Sudoku, the app has what it needs to play (these are skill games, not secrets); the server
verifies the submitted moves and decides correctness and points. Positions are zero-based.

| type | payload | response | correct_response | points |
| --- | --- | --- | --- | --- |
| `memory_match` | `{ "cols": 4, "cards": ["fox","owl","fox","frog",…], "max_attempts": 12 }` — `cards` are face-down positions (row-major), each face is a Clan **kid** avatar key and appears exactly twice; 3–10 pairs by difficulty | `{ "attempts": [[0,2],[1,5],…] }` — every pair of positions the member turned over, in order | `{ "attempts": null, "cards": [ … ] }` | 2 (≤ 6 pairs), 3 (8–10 pairs) |
| `pattern_sequence` | `{ "pads": ["fox","owl","frog","panda"], "sequence": [2,0,3,1,…], "show_ms": 700 }` — 3–6 pads (Clan kid avatar keys); the app plays the sequence (highlight + the pad's sound when sounds are on), then the member repeats it; length 3–9 by difficulty | `{ "input": [2,0,3,1,…] }` | `{ "input": [ …the sequence ] }` | 1 (length ≤ 4), 2 (5–6), 3 (7+) |
| `nonogram` | `{ "rows": 5, "cols": 5, "row_clues": [[1,1],[5],…], "col_clues": [[2],[1,1],…] }` — every puzzle has exactly one solution (checked by the generator); 5×5, 8×8 or 10×10 by difficulty | `{ "grid": [[0,1,…],…] }` (1 = filled) | `{ "grid": [[…]], "title": "A heart" }` (`title` localized; the picture's name, revealed after) | 3 (5×5), 5 (8×8), 8 (10×10) |
| `spot_difference` | `{ "aspect": 1.25, "objects": [ { "id": "o1", "kind": "tree", "x": 0.2, "y": 0.6, "size": 0.18, "color": "green", "flip": false }, … ], "changes": [ { "id": "d1", "object_id": "o3", "change": "missing" \| "color" \| "size" \| "flip", "value": "red" \| 0.12 \| true \| null } ], "max_misses": 5 }` — `x`/`y`/`size` are fractions of the picture width; the app draws `objects` on the left and the same objects with `changes` applied on the right; `kind` is a fixed art key (Clan kid avatar keys plus `tree, sun, cloud, flower, house, ball, star, kite, boat, fish, bird, rock`), `color` a named palette key (`red, orange, yellow, green, blue, purple, pink, brown, white`); 3, 5 or 7 changes by difficulty | `{ "taps": [[0.71,0.22],…] }` — taps on either picture, normalized 0–1 within that picture | `{ "changes": [ …ids ] }` | 2 (3 changes), 3 (5), 5 (7) |

- **memory_match:** correct when replaying `attempts` matches every pair (each attempt = two
  different positions not yet matched) and the number of attempts ≤ `max_attempts`. Otherwise a
  mistake. Malformed attempts (out of range, same position twice, already matched) → 422
  `validation_failed`.
- **pattern_sequence:** correct when `input` equals `sequence` exactly.
- **nonogram:** correct when the grid satisfies every row and column clue (the solution is unique,
  so that is the solution). Wrong size → 422.
- **spot_difference:** all geometry is in **picture-width units**: object `x`, `y`, `size` are
  fractions of the width (the picture is 1 wide and `1 / aspect` tall). A tap `[tx, ty]` is
  normalized 0–1 in each axis, so its position in width units is `(tx, ty / aspect)`. A tap finds a
  change when that point is within the changed object's circle around (`x`, `y`) with radius
  `max(size, changed size) × 0.75`, at least 0.06 (`changed size` only for a `size` change; the
  larger one so the bigger version counts too); when two changes' circles overlap the nearest
  centre wins; each change counts once. Correct when
  every change is found with at most `max_misses` taps that hit nothing; extra taps after the last
  find are ignored. At most 60 taps (422 beyond).
- Difficulty comes from the member's difficulty range like Sudoku; the category's generators set
  the sizes (`database/seeders/data/categories.php`).
- Generator details (informative; the app must not rely on them, only on the payload):
  memory_match `max_attempts` = 2×pairs+2 (difficulty 1–4) or 2×pairs+1; pattern_sequence pads
  3/4/5/6, lengths 3–9, `show_ms` 900→500; nonogram 5×5 (≤5), 8×8 (6–8), 10×10 (9–10), pictures may
  be mirrored, `title` in the member's locale ("A mystery pattern" for the rare random fallback);
  spot_difference aspect 1.25, 8–14 objects, `max_misses` 5 (4 at 9–10), color changes only on props,
  flips only on asymmetric props. An empty pattern `input` is a wrong answer (not 422); a tap on an
  already-found change is neither a find nor a miss. Response caps: `attempts` ≤ 200, `input` ≤ 20,
  `taps` ≤ 60 (422 beyond).

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
  The app passes `revenuecat_app_user_id` to `Purchases.logIn()`. Only adult admins see the paywall
  (`policy.purchases_allowed` and role `admin`). After a purchase the app polls
  `GET /family/subscription` (and refetches `/me`) for up to ~30 s until the webhook has switched the
  tier — the app never sets the tier itself.
- `POST /family/subscription/sync` (admin) — asks the server to fetch the subscriber from the
  RevenueCat REST API now (when `REVENUECAT_API_KEY` is configured) and apply it, for a faster tier
  switch after purchase/restore. → `{ data: Subscription }` (same shape as GET); without a key it
  just returns the current state.
- Family members over the free limits keep their data when a subscription ends; limits apply to
  new members/goals/categories only.

**Web checkout (Paddle) and pricing (B10, 2026-10-24)** — Paddle is the merchant of record (it
sells, collects and remits sales tax/VAT). A family has at most one active plan from one source.
- Plans: `monthly`, `semiannual`, `annual` (Family plan). `GET /pricing` (public; for the website)
  → `{ "data": { "currency": "USD", "plans": [ { "id": "annual", "price": "49.99", "per_month": "4.17",
  "saving_percent": 40, "trial_days": 7, "highlighted": true }, … ] } }` (`?currency=CAD` for CAD;
  values from server config, mirroring the store/Paddle prices). The app shows store prices from the
  RevenueCat offering instead.
- **Website subscribe flow (no password on the web):** `POST /web/subscribe` (website form, CSRF,
  throttled 5/h per IP and 3/h per email) with `email, plan, code?` → always the same "check your
  email" page. If the email belongs to an adult admin of a family with purchases allowed, no active
  store plan and no active Paddle plan, the server emails a one-time link (signed token, 30 min)
  to `GET /subscribe/checkout?token=…`, which creates the Paddle transaction (`custom_data`:
  `family_id`, `affiliate_code` if any) and redirects to Paddle's hosted checkout; after paying,
  Paddle returns to `/subscribe/thanks`. Otherwise the email explains why (e.g. "your plan is
  managed in the App Store"). Without Paddle keys configured the form shows "coming soon".
- **In-app link (only where the server allows it):** `GET /family/web-checkout/availability?storefront=USA`
  (admin) → `{ "data": { "link_allowed": false, "url": null } }`. `storefront` is the store
  country the app reads from StoreKit / Play Billing (ISO 3166-1 alpha-3). `link_allowed` is true
  only for storefronts in the server config `billing.web_checkout.link_storefronts` (**empty by
  default**; Moe enables e.g. `USA` after re-checking the store rules) and when the family could
  buy; `url` then points to `https://brainyclan.com/subscribe?plan=annual&src=app`. The app shows
  no web prices or links when `link_allowed` is false.
- `POST /api/v1/webhooks/paddle` (public; `Paddle-Signature` HMAC verified with
  `PADDLE_WEBHOOK_SECRET`, 5-minute timestamp tolerance) — idempotent by `event_id`
  (`webhook_events`); handles `subscription.created|activated|updated|past_due|paused|resumed|canceled`,
  `transaction.completed`, `adjustment.created|updated` (refunds/chargebacks). Maps to the same
  subscription states and family tier as RevenueCat (`past_due` → grace). → 200 `{ data: { event_id,
  outcome } }`; 401 bad signature.
- `GET /family/subscription` gains `"store": "paddle"`; `POST /family/subscription/manage-link`
  (admin) → `{ "data": { "url": "…" } }` — a Paddle customer-portal link for `paddle` plans,
  the store's subscription page URL for store plans (the app opens it).
- Errors: 409 `subscription_exists` (trying to buy a second plan), 503 `web_checkout_unavailable`
  (Paddle not configured).

**Referral / affiliate codes (B10)** — influencers get codes; commissions are computed on the
server from RevenueCat and Paddle revenue events (admin-only reports; no public API).
- `POST /family/referral-code` (admin) — Body `{ code }` (case-insensitive, letters/digits/dash,
  ≤ 20). Sets the family's referral code once, before its first paid transaction. → 200
  `{ "data": { "code": "MAYA", "source": "code" } }`; 422 `referral_code_invalid` (unknown or
  inactive), 409 `referral_code_locked` (already set, or the family already paid).
- The Family object gains `"referral_code": "MAYA" | null` — the real value only for family admins (`null` for other members), wherever the Family object is returned (e.g. `GET /family`, `/me`).
- Website `/r/{code}`: the landing page with a "Code MAYA" banner, store badges (and the store
  offer-code hint if the code has one) and the subscribe form pre-filled with the code; unknown
  codes show the normal page. No cookies, no fingerprinting.
- Store offer codes (Apple Offer Codes, Google Play promo codes) mapped to an affiliate code in the
  admin: a RevenueCat event carrying that offer code attributes the family (`source: offer_code`) if
  it has no code yet. Paddle checkouts carry `affiliate_code` (`source: web_checkout`).

**Rewarded ads:** (free tier, only when `policy.second_chance.ad_allowed`; never for children)
- The app generates a random `reward_ref` (UUID v4) before showing the rewarded ad and passes it to
  AdMob as server-side-verification custom data: `custom_data = "<reward_ref>:<session_id>"`, `user_id`
  = the member id. AdMob calls `GET /api/v1/webhooks/admob-ssv` (public) with the signed callback;
  the server verifies the ECDSA signature against Google's published keys
  (`https://www.gstatic.com/admob/reward/verifier-keys.json`, cached), stores the reward
  (`transaction_id` unique; `reward_ref`, session, member from the custom data) → 200
  `{ data: { transaction_id, outcome: "stored" | "duplicate" | "ignored" } }` (`ignored`: no usable
  custom data, e.g. AdMob's "Verify URL" test); 400 `ssv_signature_invalid`.
- After the ad is closed with the reward earned, the app calls
  `POST /play/sessions/{session}/second-chance` with `method: "ad", reward_ref` (422 without `reward_ref`). The reward must exist,
  belong to that member and session, and be unused (single use). If AdMob's callback hasn't arrived
  yet → **409 `ad_reward_pending`** (the app retries every 2 s for up to ~20 s, then shows an error);
  an unknown-after-timeout, used or foreign `reward_ref` → 403 `second_chance_unavailable`.

**Push notifications** (Expo push service):
- `PUT /me/push-token` (auth) — Body: `token` (Expo push token `ExponentPushToken[...]`), `platform`.
  Stored on the current device (and removed from any other device that had it). → 204; 422 when the token has no device. `DELETE /me/push-token` → 204 (also cleared on logout).
- `GET /me/notification-preferences` (auth, needs family) → `{ "data": { "types": { "goal_reached": true,
  "period_closed": true, "family_activity": true, "daily_reminder": false, "family_chat": true, "family_chat_report": true }, "quiet_from": "20:00",
  "quiet_to": "07:30", "reminder_time": "17:00" } }` (times in the family timezone; children default
  to quiet hours 19:30–07:30, everyone else 21:30–07:00; `quiet_from`/`quiet_to` both `null` = no quiet
  hours, only one of them → 422). `PATCH /me/notification-preferences` — same shape, all optional.
  Admins may also `GET` / `PATCH /family/members/{member}/notification-preferences` (e.g. children's quiet hours).
- Sent by the server (queued; never during quiet hours: delayed until quiet hours end, dropped
  when older than 12 h): `goal_reached` (cooperation target reached / race winner), `period_closed` (results),
  `family_activity` (a member finished a session, max 1 per member per hour, not to themself),
  `daily_reminder` (at `reminder_time` if the member hasn't played today). Push `data`:
  `{ "type": "goal_reached", "goal_id": 2, "period_id": 31 }` (goal types carry `goal_id` + `period_id`;
  `family_activity`: `{ type, member_id, session_id }`; `daily_reminder`: `{ type }`) so the app can deep-link.

### Family chat (2026-10-23)
One group chat per family; only that family's active members can read or write it (every other
caller gets 404 — staff included: the admin panel never shows message text). Plain text with
emoji; no HTML, Markdown, links preview, images or attachments. Unlike other family endpoints, a
signed-in user without an active family membership gets **404 `not_found`** here (not 409 `no_family`).

**Message**
```json
{ "id": 812, "member_id": 7, "nickname": "Sam", "avatar": "fox", "body": "We won! 🎉",
  "deleted": false, "client_id": "6f1c…", "created_at": "2026-10-23T18:02:11Z", "reported": false }
```
- `body` is `null` when `deleted` is true (the app shows "Message deleted"). `nickname`/`avatar`
  are the author's current values; an author who left the family shows as "Former member"
  (`member_id` null, `nickname` "Former member" / "Ancien membre" in the viewer's locale, `avatar` null).
- `body`: 1–1000 characters after trimming — **characters = Unicode code points** (JS: `[...text].length`,
  not `text.length`; an emoji ZWJ sequence counts as several) — stored and returned as plain UTF-8 text (utf8mb4; any
  emoji). The server normalizes it (NFC), turns `\r\n`/`\r` into `\n`, removes control characters except `\n`, collapses more
  than 2 consecutive empty lines and rejects text that is empty after that (422). It never parses
  or renders it; **clients must render it as plain text** (never as HTML/Markdown, never inject it
  into a WebView or `innerHTML`).
- Stored encrypted at rest (application key); never written to logs, Sentry or the activity log.

**Endpoints** (auth, needs family; `chat_enabled` member required, else 403 `chat_disabled`)
- `GET /family/chat/messages?before=<id>&after=<id>&limit=50` (limit ≤ 100) → newest first for
  `before`/none, oldest first for `after` (polling):
  `{ "data": [Message], "meta": { "has_more": true, "retention_days": 7, "last_read_id": 790, "unread": 3 } }`.
  `before` and `after` together, or `limit` > 100 → 422. Messages past retention are never returned, even
  before the hourly job deletes them. `unread` counts other members' not-deleted messages after `last_read_id`.
- `POST /family/chat/messages` — Body `{ body, client_id }` (`client_id`: a UUID the app makes per
  message; re-sending the same `client_id` returns the existing message with 200 instead of 201, so
  retries never duplicate). → **201** `{ data: Message }`. Rate limit 20 messages/min per member
  (429 `rate_limited`).
- `DELETE /family/chat/messages/{id}` — the author, or a family admin, deletes a message (it stays
  as a tombstone). → 204. Others → 403 `forbidden`; another family's id → 404.
- `POST /family/chat/read` — Body `{ last_read_id }` (only moves forward; capped at the family's newest message id). → 204.
- `POST /family/chat/messages/{id}/report` (2026-10-24; Apple guideline 1.2) — any member with chat
  enabled reports another member's message → 204 (idempotent per reporter and message; own messages
  → 422; another family's id → 404). The report goes **only to the family's admins** (never to staff
  — chats stay family-only): a push `family_chat_report` ("Sam reported a message in the family
  chat", no message text; `data { type, message_id }`; normal preference and quiet-hours rules,
  preference key `family_chat_report`, default on, admins only) and the
  message gains `"reported": true` for admins (other members always see `false`). Admins then delete
  the message or turn chat off for that member (existing tools). Rate limit 10 reports/hour per member.
- `GET /family/chat/unread` → `{ "data": { "unread": 3 } }` (cheap; the app polls it for the tab badge).
- The app polls `after` every ~4 s while the chat screen is open and the unread count every ~60 s
  otherwise (plus a push). Real-time sockets may replace polling later without changing these shapes.

**Retention:** messages older than the family's `policy.chat.retention_days` are deleted for good
(hourly job): **free 7 days, Family plan 365 days** (tier settings). After a downgrade the shorter
period applies from the next run (the app warns the admin on the paywall/chat screen). Account
deletion erases that user's messages; removing a member keeps their messages as "Former member"
until retention removes them; deleting the family deletes the chat. `/me/export` includes the
user's own messages that still exist.

**Members and policy:** Member gains `chat_enabled` (bool, default true); a family admin turns it
off/on for any member via `PATCH /family/members/{member}` (`chat_enabled`). Policy gains
`"chat": { "retention_days": 7 }`. A member with chat off neither reads nor posts (403
`chat_disabled`) and gets no chat pushes.

**Push:** new notification type `family_chat` (preference key, default on; quiet hours apply; at
most 1 push per member per 2 minutes, collapsed). The push **never contains message text** (it goes
through Expo/Apple/Google): title "Brainy Clan", body "Sam sent a message" (localized), `data`
`{ "type": "family_chat", "message_id": 812 }`.

### Legal, consent and privacy (B9)
Drafts of the privacy policy and terms are served by the backend and **must be reviewed by a lawyer
before store submission** (the pages show a "Draft" banner until `LEGAL_DRAFT=false`).
- `GET /legal` (public, `?lang=fr` optional) → `{ "data": { "terms": { "version": "2026-10-16", "url": "https://brainyclan.com/terms",
  "summary": "What changed in this version" | null }, "privacy": { ... same } } }`. Versions are opaque
  strings (currently dates); `summary` is a short localized "what changed" text for re-acceptance. Pages at `/terms`
  and `/privacy` on the website host (HTML, `?lang=fr` for French; the old `/legal/terms` and `/legal/privacy` redirect there).
- `POST /auth/register` and adult `POST /auth/social/{provider}` sign-ups require `accept_terms: true`
  (422 on `accept_terms` otherwise); the server records a consent row (user, type `terms`+`privacy`,
  versions, time, IP hash). Joining by invite as an adult with email (`/auth/join` with email) also
  needs `accept_terms: true`.
- `GET /family/invites/preview?birth_year=2017&birth_month=3` (admin) → `{ "data": { "age_band": "child",
  "is_child": true, "parental_consent_required": true, "email_login_allowed": false } }` — lets the
  invite form show the parental-consent checkbox only when needed (the app never computes bands).
- **Parental consent:** `POST /family/invites` for a nickname/birth date in a band with `is_child`
  requires `parental_consent: true` (422 on `parental_consent` otherwise) — the admin confirms they are
  the parent/guardian (or have their permission) and agrees to the child's data being processed as
  described in the privacy policy. Recorded as a consent row (type `parental`, admin, child invite →
  member once joined, policy version, time).
- `GET /me` gains `legal: { "needs_acceptance": false, "terms_version": "<current>", "privacy_version": "<current>",
  "accepted_terms_version": "<user's last accepted>" | null, "accepted_privacy_version": ... | null }`
  — `needs_acceptance` is true for non-child users whose accepted versions differ from the current
  ones (always false for children). `POST /me/legal-acceptance` (auth) — Body: `terms_version, privacy_version` (must equal the
  current ones) → 204. Children never see this (their parent consented).
- **Account deletion web page** (Google Play requirement): public HTML page `/account/delete` (en/fr)
  explaining how to delete the account in the app (Profile → Privacy → Delete account; children:
  ask the family admin) and a request form (email of the account) for people who no longer have
  the app. A submitted request is stored (`account_deletion_requests`, rate-limited, honeypot) and
  emails a one-time confirmation link to that address if it belongs to an account; confirming runs
  the same deletion as `DELETE /me` (owner with other members → the page explains to delete the
  family in the app or contact support). Never reveals whether an email exists.
- `GET /me/export` (auth; any non-child user — children get 403, their parent exports via support)
  → `{ "data": { "exported_at": "...", "user": {...}, "memberships": [...], "point_entries": [...],
  "sessions": [...], "goals": [...], "chat_messages": [{ "id", "family_id", "member_id", "body", "created_at" }],
  "consents": [...], "devices": [...] } }` (`chat_messages`: the user's own not-deleted messages; devices without tokens,
  social accounts without tokens) — right of access (PIPEDA / Quebec Law 25 / GDPR-style).
  Rate-limited: 1 per hour → 429.

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
- 2026-10-16 — Member gains `has_own_login`; purchase flow notes; `POST /family/subscription/sync`.
- 2026-10-16 — Rewarded ads: app-generated `reward_ref` in AdMob SSV custom data (`<reward_ref>:<session_id>`), SSV webhook under `/api/v1/webhooks/admob-ssv`, 409 `ad_reward_pending`.
- 2026-10-16 — B9 legal: `GET /legal`, terms/privacy pages, `accept_terms` on sign-ups, `parental_consent` on child invites, `me.legal` + `POST /me/legal-acceptance`, `GET /me/export`.
- 2026-10-16 — Policy gains `ad_request` (non_personalized, under_age_of_consent, max_ad_content_rating).
- 2026-10-16 — SSV: `ssv_signature_invalid` (400), 200 body with `outcome`; ad second chance needs `reward_ref` (422); push `data` for `family_activity` / `daily_reminder`.
- 2026-10-16 — Legal clarifications: `GET /legal` `summary`; `GET /family/invites/preview`; `me.legal` current + accepted versions; `/me/export` shape and who may export.
- 2026-10-16 — Account deletion web page `/account/delete` with emailed confirmation (Play requirement).
- 2026-10-16 — B9 details: `POST /me/legal-acceptance` → 403 for children, 422 on a stale version; `/me/export` also has `social_accounts` and per-membership `notification_preferences` and is sent as an attachment (`no-store`); social sign-up checks `accept_terms` after birth/age checks; child invites check `parental_consent` before the member limit; webhooks rate-limited (600/min/IP).
- 2026-10-17 — Login throttled per email as well as per IP; documented request size limits.
- 2026-10-17 — `GET /play/sessions/current`; a new session abandons older unfinished ones; idle sessions auto-abandoned after 24 h.
- 2026-10-17 — Content locale: items in the member's `locale`, English only where no translation exists (no duplicates across languages). No shape change.
- 2026-10-22 — Sign-in lockout (429 `login_locked` + `retry_after`), `POST /auth/password/forgot` + web page `/password/reset`. Production hosts: website `https://brainyclan.com` (legal pages, support, account deletion, password reset), API `https://api.brainyclan.com/api/v1`; support email `support@brainyclan.com`.
- 2026-10-22 — Avatars: the Clan set, 22 species × (kid, teen, mom, dad, grandma, grandpa) = 132 keys (`tiger`, `tiger-mom`, …); existing keys unchanged.
- 2026-10-22 — Legal pages moved to `/terms` and `/privacy` on the website host; `GET /legal` URLs point there (`https://brainyclan.com/terms`), the old `/legal/terms` and `/legal/privacy` answer 301. No shape change.
- 2026-10-23 — Theme "worlds": new theme keys `jungle`, `space`, `neon`; member `accessibility.sound_effects` (null | bool) and policy `accessibility_defaults.sound_effects` (child true, others false).
- 2026-10-23 — Worlds phase 2: keys `sea`, `dino`, `sticker`, `lofi`, `nature`, `midnight`; `GET /me/worlds` (unlock catalog: free / points / streak / family_plan); 403 `world_locked`; Member `theme_unlocked`.
- 2026-10-23 — 8 new categories (logic-riddles, language-swap, inventions-technology, our-planet, art-music, food-world, sports-games, life-skills) and category icon keys `bulb, language, rocket, earth, palette, restaurant, football, shield`. No shape change.
- 2026-10-23 — Brain games: types `memory_match`, `pattern_sequence`, `nonogram`, `spot_difference` (server-generated), category `brain-games` (icon `sparkles`).
- 2026-10-23 — Family chat: `/family/chat/*` endpoints, Message object, Member `chat_enabled`, Policy `chat.retention_days` (free 7 / Family 365), push type `family_chat` (no message text), error `chat_disabled`.
- 2026-10-24 — Family chat clarifications (backend built): callers without an active membership get 404 on `/family/chat/*` (not 409); body length counted in Unicode code points; CRLF → LF; "Former member" `nickname` localized with `avatar` null; `before`+`after` or `limit` > 100 → 422; read marker capped at the newest id; notification preferences `types.family_chat`; `/me/export` gains `chat_messages`; Member `chat_enabled` and Policy `chat` in the shared-object examples. Privacy policy version bumped to `2026-10-23` (re-acceptance).
- 2026-10-24 — B10: `GET /pricing`, website subscribe flow with Paddle (merchant of record), `GET /family/web-checkout/availability`, Paddle webhook, `store: paddle`, `POST /family/subscription/manage-link`, referral codes (`POST /family/referral-code`, `/r/{code}`, offer codes), new error codes.
- 2026-10-24 — B11 (additive, monitoring only): `GET /health/deep` with `X-Health-Token` (MySQL, Redis, scheduler heartbeat, Horizon, failed jobs last hour; 200/503). `GET /health` unchanged.
- 2026-10-24 — B10 clarifications (backend built, no shape change): `POST /family/subscription/manage-link` → 404 `not_found` when the family has no subscription, 503 `web_checkout_unavailable` when Paddle isn't configured or reachable (portal links are temporary — open right away); `GET /family/web-checkout/availability` → 422 for a `storefront` that isn't 3 letters, `link_allowed: false` without one; `POST /family/referral-code` → a malformed code is also 422 `referral_code_invalid`; `GET /family/subscription` shows the entitled plan (store or web) first. Website: the emailed checkout link creates one Paddle transaction — opening it again within 30 min returns to the same checkout; without a Paddle hosted checkout the link goes to `/subscribe/pay` (Paddle.js). Terms and privacy versions bumped to `2026-10-24` (re-acceptance).
- 2026-10-24 — Chat report: `POST /family/chat/messages/{id}/report` (to family admins only), Message `reported` (admins), push/preference `family_chat_report`.
- 2026-10-24 — Chat report clarifications (backend built, no shape change): reporting an already deleted message → 204 no-op; a message past retention → 404; own message → 422 `validation_failed` (`errors.message`); the push goes to active admins with chat on, except the reporter; deleting a message (tombstone) clears its reports (`reported` false); reports are deleted with the message (retention, erasure, family deletion) and with the reporter's account; `types.family_chat_report` is in every member's preferences (only admins receive it). Message examples show `reported`.
