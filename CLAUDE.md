# Brainy Clan - Backend

Brainy Clan is a family learning / quiz mobile app. This repository is its backend:
a **Laravel 13** REST API for the mobile app plus a **Filament 5** (Livewire 4) admin panel.

Design doc: <link>

The design doc holds the full architecture and data design. Read it before larger changes.

## Stack

PHP 8.5, Laravel 13, Filament 5, MySQL 8.4, Redis, Laravel Horizon (queues), Sanctum (API
tokens), Pest (tests), Pint (code style), Larastan (static analysis), Laravel Boost (MCP +
AI guidelines). Everything runs in Docker (see `docker-compose.yml`).

## Running commands - always through Docker

There is **no PHP, Composer or MySQL client on the Windows host**. Run every PHP-side command
inside the `app` container, from the repository root:

```sh
docker compose exec app php artisan <command>
docker compose exec app composer <command>
docker compose exec app php artisan test            # or: docker compose exec app ./vendor/bin/pest
docker compose exec app ./vendor/bin/pint           # code style (use --test to only check)
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G
```

The stack must be running: `docker compose --profile workers up -d`. Inside containers the
hosts are `mysql`, `redis` and `mailpit` (not 127.0.0.1). After changing jobs or queue
code, restart the workers: `docker compose restart horizon`.

## Architecture rules

- **Modules.** Domain code lives in `app/Modules/<Name>/` with the sub-folders
  `Models`, `Actions`, `Events`, `Listeners`, `Http`, `Policies`.
  Modules: `Identity`, `Family`, `Content`, `ChallengeTypes`, `Play`, `Scoring`, `Goals`,
  `Policy`, `Billing`, `Notifications`. (Not created yet; add them as features are built.)
- **Thin controllers.** Controllers validate (Form Requests), call one Action class and
  return an API Resource. Business logic lives in Action classes, not in controllers,
  models or Filament resources (Filament calls the same Actions).
- **Points are an append-only ledger.** Never update or delete point entries; balances are
  derived from the ledger. Corrections are new compensating entries.
- **The server is the referee.** The mobile app never decides correctness, scores, rewards
  or limits; it submits answers/events and the server evaluates them.
- **Rules via a PolicyResolver.** Family/child rules and limits are looked up through the
  `PolicyResolver` (Policy module), never hard-coded in Actions or controllers.
- **API.** All mobile endpoints live under `/api/v1`, authenticated with Sanctum tokens.
  (`HasApiTokens` still has to be added to the User model when the Identity module is built.)
- Write Pest tests for Actions and API endpoints; keep Pint and Larastan clean.

Laravel Boost adds its own guidelines below (`php artisan boost:install` / `boost:update`).
