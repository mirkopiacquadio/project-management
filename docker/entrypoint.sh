#!/bin/sh

set -e

echo "🚀 Starting Laravel..."

php artisan migrate --force

php artisan optimize

exec php-fpm