#!/bin/sh
# Entrypoint of the production application image (docker/php/Dockerfile, `php-fpm` stage).
#
# Only the web-serving container (`php-fpm`) prepares the application: it applies pending database
# migrations and warms the framework caches, then hands over to PHP-FPM. The queue worker and the
# scheduler run the same image with their own command and start straight away — the Compose file
# makes them wait for `php-fpm` to be healthy, i.e. for this script to have finished.
set -e

if [ "$1" = "php-fpm" ]; then
    if [ -z "$APP_KEY" ]; then
        echo "APP_KEY is not set. Generate one with:" >&2
        echo "  docker compose run --rm --no-deps --entrypoint php php-fpm artisan key:generate --show" >&2
        echo "and put it into .env (docs/deployment/README.md, \"Installation\")." >&2
        exit 1
    fi

    # --isolated: a lock in the cache store, so a second starting container cannot migrate at the same time.
    php artisan migrate --force --isolated

    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

exec "$@"
