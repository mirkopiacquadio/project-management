#!/bin/sh

set -e

echo "🚀 Starting Laravel..."

if [ ! -d vendor ]; then
    echo "📦 Installing Composer dependencies..."
    composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader
fi

php artisan package:discover --ansi

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec php-fpm