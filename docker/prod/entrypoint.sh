#!/bin/sh
# Brainy Clan production entrypoint: caches config/routes/views/events from the runtime
# environment (.env.prod via env_file) for this container, then runs the command
# (php-fpm, horizon or schedule:work). Migrations are run explicitly on deploy (docs/DEPLOY.md).
set -e

if [ "${APP_ENV:-production}" = "production" ]; then
    if [ "${APP_DEBUG:-false}" = "true" ]; then
        echo "Refusing to start: APP_DEBUG=true in production." >&2
        exit 1
    fi
    php artisan optimize --quiet
fi

exec "$@"
