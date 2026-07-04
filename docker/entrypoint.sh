#!/bin/sh
set -e

echo "🚀 Starting Laravel..."

composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

php artisan key:generate --force || true

php artisan migrate --force

php artisan optimize

exec php-fpm