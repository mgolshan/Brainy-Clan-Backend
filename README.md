# Brainy Clan - Backend

REST API and admin panel for **Brainy Clan**, a family learning and quiz mobile app.

- Laravel 13 on PHP 8.5, Filament 5 admin panel (Livewire 4)
- MySQL 8.4, Redis, Laravel Horizon for queues, Sanctum for API tokens
- Pest, Laravel Pint, Larastan, Laravel Boost (MCP server + AI guidelines)
- Everything runs in Docker. No PHP, Composer or MySQL needs to be installed on Windows.

Project rules and architecture notes for AI assistants and developers: [CLAUDE.md](CLAUDE.md).

## Prerequisites

- Windows 11 with **Docker Desktop** using the **WSL 2 backend** (Settings > General >
  "Use the WSL 2 based engine"). Docker Desktop must be running.
- Free host ports: 8080 (web), 3307 (MySQL), 6380 (Redis), 8025 and 1025 (Mailpit).
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
4. runs `php artisan boost:install` interactively. When it asks which agents or editors to
   configure, select **Claude Code** (and PhpStorm / Junie if it is listed and you use it),
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

## URLs and credentials

| What | Where | Login |
| --- | --- | --- |
| API | http://localhost:8080 (health check: http://localhost:8080/up) | Sanctum tokens |
| Admin panel (Filament) | http://localhost:8080/admin | `admin@brainyclan.test` / `password` |
| Horizon dashboard | http://localhost:8080/horizon | open in the local environment |
| Mailpit (caught e-mails) | http://localhost:8025 | - |
| MySQL from the host | `127.0.0.1:3307`, database `brainy_clan` | `brainy` / `secret` (root: `root`) |
| Redis from the host | `127.0.0.1:6380` | - |

Inside the containers the services are reached by name: `mysql:3306`, `redis:6379`,
`mailpit:1025`. That is what `.env` uses.

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
