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

# Python libraries the scripts import, declared in requirements.txt next to
# them. Installed into a virtualenv rather than the system interpreter, which
# Debian marks externally managed, and only when the file has changed since the
# last start — a restart with no edit costs nothing.
#
# A failure here must not keep the app down: webhooks still need capturing, and
# the rule editor shows what pip said.
if [ "$WEBHOOK_SCRIPTS_REQUIREMENTS" = "true" ]; then
    requirements="${WEBHOOK_SCRIPTS_DIR:-/app/scripts}/requirements.txt"
    venv="${WEBHOOK_SCRIPTS_VENV:-/app/storage/pyenv}"

    if [ -f "$requirements" ]; then
        want=$(sha256sum "$requirements" | cut -d' ' -f1)
        have=$(cat "$venv/.requirements-hash" 2>/dev/null || echo none)

        if [ "$want" != "$have" ]; then
            echo "Installing $requirements…"
            rm -f "$venv/.requirements-error"

            if [ ! -x "$venv/bin/python3" ]; then
                # --system-site-packages so pyodbc and the rest of the image
                # stay importable from inside the virtualenv.
                python3 -m venv --system-site-packages "$venv" 2>&1 || true
            fi

            if [ -x "$venv/bin/pip" ] && out=$("$venv/bin/pip" install --no-cache-dir -r "$requirements" 2>&1); then
                echo "$want" > "$venv/.requirements-hash"
                echo "Python requirements installed."
            else
                # Keep the lines that say what went wrong. pip's normal chatter
                # ("Requirement already satisfied…") would otherwise be the first
                # thing shown in the editor, which reads like a success.
                out="${out:-python3 -m venv failed}"

                if printf '%s\n' "$out" | grep -qiE '^(ERROR|WARNING)'; then
                    printf '%s\n' "$out" | grep -iE '^(ERROR|WARNING)' | tail -10 > "$venv/.requirements-error"
                else
                    printf '%s\n' "$out" | tail -10 > "$venv/.requirements-error"
                fi

                # Whatever was installed earlier stays usable, so this is not
                # necessarily a fall back to the system interpreter.
                echo "WARNING: installing $requirements failed; scripts keep running with the libraries already installed." >&2
                cat "$venv/.requirements-error" >&2 2>/dev/null || true
            fi
        fi
    else
        echo "WEBHOOK_SCRIPTS_REQUIREMENTS is on, but $requirements does not exist." >&2
    fi
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
