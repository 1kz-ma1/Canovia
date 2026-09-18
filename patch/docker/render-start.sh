#!/usr/bin/env sh
set -eu

cd /var/www/html

PORT="${PORT:-10000}"
case "$PORT" in
    ''|*[!0-9]*) echo "PORT must be an integer" >&2; exit 1 ;;
esac
if [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then
    echo "PORT must be between 1 and 65535" >&2
    exit 1
fi
sed "s/listen 10000 /listen ${PORT} /; s/listen \[::\]:10000 /listen [::]:${PORT} /" \
    docker/nginx.conf > /etc/nginx/nginx.conf
nginx -t
php-fpm -t

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

# CLI cache generation runs as root; FPM workers run as www-data.
chown -R www-data:www-data storage bootstrap/cache

echo "Starting Canovia with Nginx + PHP-FPM on port ${PORT}"
exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
