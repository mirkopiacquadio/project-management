#!/bin/sh

set -e

echo "🚀 Starting Queue..."

if [ ! -d vendor ]; then
    composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader
fi

php artisan package:discover --ansi

exec php artisan queue:work \
    --sleep=3 \
    --tries=3 \
    --timeout=90