FROM php:8.4-cli-alpine

RUN apk add --no-cache postgresql-dev icu-dev oniguruma-dev libzip-dev git bash python3 \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-install -j$(nproc) pdo_pgsql pcntl intl zip bcmath opcache \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

WORKDIR /app
