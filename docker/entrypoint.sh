#!/bin/sh

set -e

echo "🚀 Starting Laravel..."

# The storage volume is mounted over the image's /var/www/storage, so the
# build-time chown is masked and root-run artisan commands (below) create
# root-owned files in it. Re-assert ownership every boot so the php-fpm
# workers (www-data) can write logs, cache and sessions — otherwise the app
# 500s intermittently and the errors can't even be logged.
chown -R www-data:www-data storage bootstrap/cache

php artisan migrate --force

php artisan optimize

exec php-fpm