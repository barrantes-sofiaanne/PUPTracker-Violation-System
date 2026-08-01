#!/bin/bash

# Run Laravel migrations
echo "Running database migrations..."
php artisan migrate --force

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Start the Laravel application
echo "Starting Laravel application..."
php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
