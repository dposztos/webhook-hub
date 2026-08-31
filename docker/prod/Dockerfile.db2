# syntax=docker/dockerfile:1

# Webhook Hub with Python + an IBM i (AS/400) ODBC driver, for script actions
# that query Db2 for i.
#
#   docker build -f docker/prod/Dockerfile.db2 -t webhook-hub:db2 .
#
# It is a separate image on purpose. The default image is Alpine, and IBM ships
# the i Access ODBC driver as a glibc binary, so this variant runs on Debian.
# IBM builds it for amd64, i386 and ppc64el only — there is no arm64 driver.

# 1) Frontend build
FROM node:22-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# 2) PHP dependencies
FROM composer:2 AS vendor
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-req=php
COPY . .
RUN composer dump-autoload --optimize --no-dev

# 3) Runtime: nginx + php-fpm under supervisord, on glibc
FROM php:8.4-fpm-bookworm

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx supervisor tzdata ca-certificates curl \
        libpq5 libicu72 libzip4 \
        python3 python3-pyodbc \
        unixodbc odbcinst libodbc2 libodbcinst2 \
    && apt-get install -y --no-install-recommends libpq-dev libicu-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pcntl intl zip bcmath opcache \
    && apt-get purge -y --auto-remove libpq-dev libicu-dev libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# IBM's public package server for the i Access ODBC driver. It is unsigned,
# hence [trusted=yes]; the repository is IBM's own and is served over HTTPS.
RUN echo 'deb [trusted=yes] https://public.dhe.ibm.com/software/ibmi/products/odbc/debs 1.1.0 main' \
        > /etc/apt/sources.list.d/ibmi-acs.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends ibm-iaccess \
    && rm -rf /var/lib/apt/lists/* \
    # Fail the build rather than ship an image whose driver is not registered.
    && odbcinst -q -d | grep -q 'IBM i Access ODBC Driver'

ENV TZ=Europe/Budapest

COPY docker/prod/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/prod/nginx.conf /etc/nginx/nginx.conf
COPY docker/prod/supervisord.conf /etc/supervisord.conf
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /app
COPY --from=vendor /build /app
COPY --from=assets /build/public/build /app/public/build

RUN rm -rf /app/docker/dev /app/php \
    && mkdir -p storage/framework/cache/data \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/framework/views \
    && mkdir -p storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
