#!/usr/bin/env sh
set -eu

cd /var/www/html

mkdir -p \
storage/framework/cache/data \
storage/framework/sessions \
storage/framework/views \
storage/logs \
bootstrap/cache

echo "Running Laravel migrations..."
php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

PORT="${PORT:-10000}"

echo "Starting Canovia on 0.0.0.0:${PORT}"
exec php artisan serve --host=0.0.0.0 --port="${PORT}"