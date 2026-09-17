#!/usr/bin/env sh
set -eu

cd /var/www/html

mkdir -p \
storage/framework/cache/data \
storage/framework/sessions \
storage/framework/views \
storage/logs \
bootstrap/cache \
/run/nginx \
/var/log/supervisor

echo "Running Laravel migrations..."
php artisan migrate --force

echo "Building Laravel production caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Starting Canovia with Nginx + PHP-FPM on port 10000"
exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
