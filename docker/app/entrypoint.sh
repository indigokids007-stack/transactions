#!/bin/sh

# Everything a clean clone needs before the application can serve a request. The
# repository ships no `.env` and no `vendor/`, so without this a freshly built
# container reaches `artisan` with nothing to autoload and exits.
#
# Every step is skipped when it has already been done, so restarting the container
# costs a migration check and nothing else.

set -e

cd /app

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

exec docker-php-entrypoint "$@"
