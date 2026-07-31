#!/bin/sh

# Everything a clean clone needs before the application can serve a request. The
# repository ships no `.env` and no `vendor/`, so without this a freshly built
# container reaches `artisan` with nothing to autoload and exits.
#
# Every step is skipped when it has already been done, so restarting the container
# costs a migration check and nothing else.
#
# Exactly one service does this. The others share the same image and the same
# working directory, so a second container running `composer install` and `migrate`
# alongside the first would be racing it for no gain; they set APP_BOOTSTRAP=0 and
# wait for the dependencies to appear instead.

set -e

cd /app

WAIT_FOR_BOOTSTRAP_SECONDS=600

if [ "${APP_BOOTSTRAP:-1}" = "1" ]; then
    if [ ! -f .env ]; then
        echo "entrypoint: creating .env from .env.example"
        cp .env.example .env
    fi

    if [ ! -f vendor/autoload.php ]; then
        echo "entrypoint: installing composer dependencies"
        composer install --no-interaction --prefer-dist --no-progress
    fi

    if grep -q '^APP_KEY=$' .env; then
        echo "entrypoint: generating the application key"
        php artisan key:generate --force
    fi

    echo "entrypoint: running migrations"
    php artisan migrate --force
else
    echo "entrypoint: waiting for the bootstrapping container to install dependencies"

    waited=0

    while [ ! -f vendor/autoload.php ] || [ ! -f .env ]; do
        if [ "$waited" -ge "$WAIT_FOR_BOOTSTRAP_SECONDS" ]; then
            echo "entrypoint: dependencies did not appear after ${WAIT_FOR_BOOTSTRAP_SECONDS}s" >&2
            exit 1
        fi

        waited=$((waited + 2))
        sleep 2
    done
fi

exec docker-php-entrypoint "$@"
