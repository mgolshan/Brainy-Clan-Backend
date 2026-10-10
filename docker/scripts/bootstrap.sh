#!/usr/bin/env bash
#
# Brainy Clan backend - one-time bootstrap. Runs INSIDE the app container:
#
#   docker compose run --rm app bash docker/scripts/bootstrap.sh
#
# (scripts/setup.ps1 and scripts/setup.sh call it for you.)
#
# Installs Composer dependencies, writes .env, adds the project packages (Filament,
# Horizon, Sanctum, Boost, IDE helper, Larastan, Pest), runs their installers and the
# migrations, and creates a local admin user. Safe to re-run: steps that are already
# done are skipped.

set -euo pipefail

cd /var/www/html

# Never let installers stop and wait for input.
export COMPOSER_NO_INTERACTION=1
export PEST_NO_SUPPORT=true

ADMIN_EMAIL="admin@brainyclan.test"
ADMIN_PASSWORD="password"

heading() {
    printf '\n\033[1;36m==> %s\033[0m\n' "$*"
}

info() {
    printf '    %s\n' "$*"
}

warn() {
    printf '\033[1;33m    WARNING: %s\033[0m\n' "$*" >&2
}

# set_env KEY VALUE [FILE]
# Replaces an active "KEY=" line, otherwise the first commented "# KEY=" line,
# otherwise appends "KEY=VALUE". VALUE is written verbatim (include quotes yourself).
set_env() {
    local key="$1" value="$2" file="${3:-.env}" tmp

    tmp="$(mktemp)"

    if grep -qE "^${key}=" "$file"; then
        KEY="$key" VALUE="$value" awk '
            BEGIN { key = ENVIRON["KEY"]; val = ENVIRON["VALUE"] }
            index($0, key "=") == 1 { print key "=" val; next }
            { print }
        ' "$file" > "$tmp"
    elif grep -qE "^#[[:space:]]*${key}=" "$file"; then
        KEY="$key" VALUE="$value" awk '
            BEGIN { key = ENVIRON["KEY"]; val = ENVIRON["VALUE"]; done = 0 }
            !done && $0 ~ ("^#[[:space:]]*" key "=") { print key "=" val; done = 1; next }
            { print }
        ' "$file" > "$tmp"
    else
        cat "$file" > "$tmp"
        # Make sure the appended line starts on a new line.
        if [ -s "$tmp" ] && [ "$(tail -c 1 "$tmp" | od -An -c | tr -d ' ')" != '\n' ]; then
            printf '\n' >> "$tmp"
        fi
        printf '%s=%s\n' "$key" "$value" >> "$tmp"
    fi

    # Write through the existing file (keeps permissions; plays well with bind mounts).
    cat "$tmp" > "$file"
    rm -f "$tmp"
}

# Is a package listed in composer.json? has_package <name> [require|require-dev]
has_package() {
    local package="$1" section="${2:-}"

    # shellcheck disable=SC2016 # PHP code, expanded by PHP not bash
    PACKAGE="$package" SECTION="$section" php -r '
        $json = json_decode(file_get_contents("composer.json"), true);
        $sections = getenv("SECTION") !== "" ? [getenv("SECTION")] : ["require", "require-dev"];
        foreach ($sections as $section) {
            if (isset($json[$section][getenv("PACKAGE")])) {
                exit(0);
            }
        }
        exit(1);
    '
}

# composer_require [--dev] <package[:constraint]>...
# Requires only the packages that are not in composer.json yet.
composer_require() {
    local dev_flag=() missing=() spec name

    if [ "${1:-}" = "--dev" ]; then
        dev_flag=(--dev)
        shift
    fi

    for spec in "$@"; do
        name="${spec%%:*}"
        if has_package "$name"; then
            info "$name is already required, skipping."
        else
            missing+=("$spec")
        fi
    done

    if [ "${#missing[@]}" -gt 0 ]; then
        composer require "${dev_flag[@]}" "${missing[@]}" --with-all-dependencies --no-interaction --no-progress
    fi
}

# --------------------------------------------------------------------------------------

heading "Environment file (.env)"

if [ ! -f .env ]; then
    cp .env.example .env
    info "Created .env from .env.example."
else
    info ".env already exists, updating the Docker settings in it."
fi

set_env APP_NAME '"Brainy Clan"'
set_env APP_URL http://localhost:8080
set_env DB_CONNECTION mysql
set_env DB_HOST mysql
set_env DB_PORT 3306
set_env DB_DATABASE brainy_clan
set_env DB_USERNAME brainy
set_env DB_PASSWORD secret
set_env REDIS_CLIENT phpredis
set_env REDIS_HOST redis
set_env REDIS_PORT 6379
set_env QUEUE_CONNECTION redis
set_env CACHE_STORE redis
set_env SESSION_DRIVER redis
set_env MAIL_MAILER smtp
set_env MAIL_HOST mailpit
set_env MAIL_PORT 1025

# --------------------------------------------------------------------------------------

heading "Composer install (Laravel skeleton dependencies)"

composer install --no-interaction --no-progress

if grep -qE '^APP_KEY=.+' .env; then
    info "APP_KEY already set."
else
    php artisan key:generate --force --no-interaction
fi

# Make sure no stale cached config hides the new .env values.
php artisan config:clear --no-interaction > /dev/null

# --------------------------------------------------------------------------------------

heading "Composer require: Filament 5, Horizon"

composer_require "filament/filament:^5.0" laravel/horizon

heading "Composer require (dev): Boost, IDE helper, Larastan"

composer_require --dev laravel/boost barryvdh/laravel-ide-helper larastan/larastan

heading "Composer require (dev): Pest"

if has_package pestphp/pest; then
    info "pestphp/pest is already required, skipping."
else
    # Pest brings the PHPUnit version it needs. The skeleton pins phpunit/phpunit
    # directly, which can block newer Pest majors, so the direct pin is dropped first
    # (this is the approach documented by Pest/Laravel).
    if has_package phpunit/phpunit require-dev; then
        composer remove --dev phpunit/phpunit --no-update --no-interaction
    fi
    composer require --dev pestphp/pest pestphp/pest-plugin-laravel --with-all-dependencies --no-interaction --no-progress
fi

if [ ! -f tests/Pest.php ]; then
    # --init only creates files that do not exist yet (tests/Pest.php here); the
    # skeleton's existing tests and phpunit.xml are left untouched.
    php vendor/bin/pest --init < /dev/null
else
    info "tests/Pest.php already exists, skipping pest --init."
fi

# --------------------------------------------------------------------------------------

heading "Sanctum + API routes (install:api)"

if [ -f routes/api.php ] && has_package laravel/sanctum; then
    info "routes/api.php and laravel/sanctum already present, skipping."
else
    # Requires laravel/sanctum itself, publishes its migration and routes/api.php,
    # and registers the API routes in bootstrap/app.php.
    php artisan install:api --without-migration-prompt --no-interaction
fi

heading "Horizon (horizon:install)"

if [ -f app/Providers/HorizonServiceProvider.php ]; then
    info "Horizon is already installed, skipping."
else
    php artisan horizon:install --no-interaction
fi

heading "Filament admin panel (filament:install --panels)"

if [ -f app/Providers/Filament/AdminPanelProvider.php ]; then
    info "Filament admin panel already exists, skipping."
else
    # With --no-interaction the panel ID prompt takes its default: "admin".
    php artisan filament:install --panels --no-interaction
fi

# --------------------------------------------------------------------------------------

heading "Database migrations"

php artisan migrate --force --no-interaction

heading "Seed data (admin user, age bands, settings, categories, content, demo family)"

# Every seeder is idempotent, so re-running the bootstrap is safe. The staff account
# ($ADMIN_EMAIL / $ADMIN_PASSWORD) comes from database/seeders/AdminUserSeeder.php.
php artisan db:seed --force --no-interaction

# --------------------------------------------------------------------------------------

heading "IDE helper files (PhpStorm code completion)"

php artisan ide-helper:generate --no-interaction || warn "ide-helper:generate failed."
php artisan ide-helper:meta --no-interaction || warn "ide-helper:meta failed."
# -N / --nowrite: write _ide_helper_models.php instead of touching the model files.
php artisan ide-helper:models -N --no-interaction || warn "ide-helper:models failed."

# --------------------------------------------------------------------------------------

heading "Config cache (big speed-up on Windows bind mounts)"

# Re-run after editing .env or config/*.php (or run `php artisan config:clear`).
# Tests are unaffected: phpunit.xml points APP_CONFIG_CACHE elsewhere.
php artisan config:cache --no-interaction

# --------------------------------------------------------------------------------------

heading "Done"

cat <<EOF

    API             http://localhost:8080          (health check: /up)
    Admin panel     http://localhost:8080/admin    $ADMIN_EMAIL / $ADMIN_PASSWORD
    Horizon         http://localhost:8080/horizon  (needs: docker compose --profile workers up -d)
    Mailpit         http://localhost:8026
    MySQL (host)    127.0.0.1:3307                 brainy / secret, database brainy_clan
    Redis (host)    127.0.0.1:6380

EOF
