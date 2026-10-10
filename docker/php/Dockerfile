# syntax=docker/dockerfile:1
#
# Brainy Clan backend - development PHP image (PHP-FPM + CLI tools).
# Used by the app, horizon and scheduler services in docker-compose.yml.

FROM php:8.5-fpm

# UID/GID of the user that runs PHP inside the container. On Windows (Docker Desktop)
# bind-mounted files are writable by everyone, so the defaults are fine. On Linux/WSL
# they are set to your own uid/gid so files created by artisan/composer belong to you.
ARG UID=1000
ARG GID=1000

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_HOME=/home/brainy/.composer \
    COMPOSER_CACHE_DIR=/home/brainy/.composer/cache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        unzip \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions. OPcache is always compiled into PHP 8.5, so it is not installed here;
# it is configured in php.ini instead. posix and pdo_sqlite (used by the test suite)
# ship with the official image.
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        pcntl \
        pdo_mysql \
        redis \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/zz-brainy-clan.ini
COPY php-fpm.conf /usr/local/etc/php-fpm.d/zzz-brainy-clan.conf

# Unprivileged user that runs php-fpm, artisan, composer, horizon and the scheduler.
RUN groupadd --non-unique --gid "${GID}" brainy \
    && useradd --non-unique --uid "${UID}" --gid "${GID}" --create-home --shell /bin/bash brainy \
    && mkdir -p "${COMPOSER_CACHE_DIR}" /var/www/html \
    && chown -R brainy:brainy /home/brainy /var/www/html

USER brainy

# The project is bind-mounted from the host, so its owner differs from this user.
RUN git config --global --add safe.directory '*'

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
