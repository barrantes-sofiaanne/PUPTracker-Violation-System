#!/bin/bash

set -e

echo "Waiting for database to be ready..."
while ! nc -z db 3306; do
  sleep 1
done

echo "Database is ready!"

# Run migrations
echo "Running database migrations..."
php artisan migrate --force

echo "Starting PHP-FPM..."
php-fpm
