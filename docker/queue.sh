#!/bin/sh

set -e

until php artisan about >/dev/null 2>&1
do
    echo "Waiting Laravel..."
    sleep 2
done

exec php artisan queue:work \
    --sleep=3 \
    --tries=3 \
    --timeout=90