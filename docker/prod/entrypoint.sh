#!/bin/sh
set -e

cd /app

# Wait for the database; the whole stack starts at once
if [ -n "$DB_HOST" ]; then
    echo "Waiting for the database ($DB_HOST:${DB_PORT:-5432})…"
    for i in $(seq 1 60); do
        if php -r "exit(@fsockopen(getenv('DB_HOST'), (int)(getenv('DB_PORT') ?: 5432)) ? 0 : 1);"; then
            break
        fi
        sleep 2
    done
fi

php artisan migrate --force --no-interaction

# Cheap self-repair on boot: reconcile the denormalised message counters.
php artisan webhook:recount || true

# Create the admin on first boot when a password was supplied
if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
    php artisan webhook:admin "$ADMIN_EMAIL" --password="$ADMIN_PASSWORD" || true
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
