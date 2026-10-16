# syntax=docker/dockerfile:1
#
# Brainy Clan backend - PRODUCTION images (docker-compose.prod.yml, docs/DEPLOY.md).
# Build context = repository root:
#
#   docker build -f docker/prod/Dockerfile --target prod -t brainy-clan-app .   # php-fpm / horizon / scheduler
#   docker build -f docker/prod/Dockerfile --target web  -t brainy-clan-web .   # Caddy + public/ assets
#
# Stages: base (PHP + extensions) -> vendor (composer install --no-dev, optimized autoloader,
# Filament assets) -> prod (non-root php-fpm, OPcache without timestamp checks) and web (Caddy).

# PHP_IMAGE: the official image. Only for a local test build behind a TLS-inspecting antivirus
# (PECL downloads fail) pass the dev image, which already has the extensions:
#   --build-arg PHP_IMAGE=brainy-clan-backend-php
ARG PHP_IMAGE=php:8.5-fpm

FROM ${PHP_IMAGE} AS base

USER root

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl unzip \
    && rm -rf /var/lib/apt/lists/*

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath exif gd intl pcntl pdo_mysql redis zip

WORKDIR /var/www/html

# ---------------------------------------------------------------------------------------------
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1

# Dependencies first (cached while composer.lock is unchanged).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .
# Optimized autoloader + package discovery + Filament assets into public/ (post-autoload-dump).
RUN rm -rf tests docker/php/extra-ca .env bootstrap/cache/*.php storage/logs/* \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache \
    && composer dump-autoload --no-dev --optimize \
    && php artisan filament:assets

# ---------------------------------------------------------------------------------------------
FROM base AS prod

COPY docker/prod/php.ini /usr/local/etc/php/conf.d/zz-brainy-clan.ini
COPY docker/prod/php-fpm.conf /usr/local/etc/php-fpm.d/zzz-brainy-clan.conf

RUN (getent group brainy || groupadd --gid 1000 brainy) \
    && (id brainy || useradd --uid 1000 --gid brainy --create-home --shell /usr/sbin/nologin brainy)

COPY --from=vendor --chown=brainy:brainy /var/www/html /var/www/html
COPY --chmod=0755 docker/prod/entrypoint.sh /usr/local/bin/brainy-entrypoint

USER brainy

# Caches config/routes/views per container and refuses APP_DEBUG=true (docker/prod/entrypoint.sh).
ENTRYPOINT ["brainy-entrypoint"]

EXPOSE 9000

# php-fpm answers on 9000. horizon/scheduler override this in docker-compose.prod.yml.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);'

CMD ["php-fpm"]

# ---------------------------------------------------------------------------------------------
FROM caddy:2-alpine AS web

COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile
COPY --from=vendor /var/www/html/public /var/www/html/public
