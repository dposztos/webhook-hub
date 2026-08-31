# syntax=docker/dockerfile:1

# 1) Frontend build
FROM node:22-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
# The UI catalogs. resources/js/i18n.js globs ../../lang/*.json at build time,
# so without this the bundle ships with no translations at all and every label
# renders as its own key ("tree.promptRootGroup"). Nothing fails while building;
# the check below is what turns that silence into a broken build.
COPY lang ./lang
RUN npm run build \
    && grep -q 'Groups and URLs' public/build/assets/*.js \
    && grep -q 'Csoportok' public/build/assets/*.js \
    || { echo 'the language catalogs did not reach the bundle — is lang/ in the build context?' >&2; exit 1; }

# 2) PHP dependencies
FROM composer:2 AS vendor
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-req=php
COPY . .
RUN composer dump-autoload --optimize --no-dev

# 3) Runtime: nginx + php-fpm in one container, under supervisord
FROM php:8.4-fpm-alpine

# python3 is here for the script action; it is inert unless WEBHOOK_SCRIPTS_ENABLED
# is turned on. See docker/prod/Dockerfile.scripts to add Python libraries.
RUN apk add --no-cache nginx supervisor postgresql-dev icu-dev libzip-dev tzdata python3 py3-pip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-install -j$(nproc) pdo_pgsql pcntl intl zip bcmath opcache \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/*

ENV TZ=Europe/Budapest

COPY docker/prod/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/prod/nginx.conf /etc/nginx/nginx.conf
COPY docker/prod/supervisord.conf /etc/supervisord.conf
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /app
COPY --from=vendor /build /app
COPY --from=assets /build/public/build /app/public/build

# Separate mkdirs: Alpine's /bin/sh does not understand {a,b} expansion.
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
