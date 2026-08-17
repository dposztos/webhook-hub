#!/bin/sh
set -e

cd /app

# Megvárjuk az adatbázist (a stack egyszerre indul)
if [ -n "$DB_HOST" ]; then
    echo "Várakozás az adatbázisra ($DB_HOST:${DB_PORT:-5432})…"
    for i in $(seq 1 60); do
        if php -r "exit(@fsockopen(getenv('DB_HOST'), (int)(getenv('DB_PORT') ?: 5432)) ? 0 : 1);"; then
            break
        fi
        sleep 2
    done
fi

php artisan migrate --force --no-interaction

# Olcsó önjavítás induláskor: a denormalizált üzenetszámlálók egyeztetése.
php artisan webhook:recount || true

# Az első indításkor létrehozzuk az admint, ha meg van adva jelszó
if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
    php artisan webhook:admin "$ADMIN_EMAIL" --password="$ADMIN_PASSWORD" || true
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
