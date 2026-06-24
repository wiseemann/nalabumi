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

echo "Starting services..."
exec supervisord -c /etc/supervisor/supervisord.conf
