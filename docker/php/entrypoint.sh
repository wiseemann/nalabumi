#!/bin/sh
set -e

echo "Waiting for database..."
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; do
    sleep 2
done
echo "Database ready."

php artisan migrate --force
php artisan config:cache || echo "Warning: config:cache failed (non-fatal)"
php artisan route:cache || echo "Warning: route:cache failed (non-fatal)"
php artisan view:cache || echo "Warning: view:cache failed (non-fatal)"

if [ $# -gt 0 ]; then
    exec "$@"
fi

echo "Starting PHP-FPM..."
exec php-fpm
