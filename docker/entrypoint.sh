#!/bin/bash

# Exit on any error
set -e

echo "Starting BAG Comics deployment..."

# Wait for database to be ready (if using external DB)
echo "Checking database connection..."

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Clear and cache config
echo "Optimizing application..."
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Seed the database if needed
echo "Seeding database..."
php artisan db:seed --class=CmsContentSeeder --force

# Create storage link
echo "Creating storage link..."
php artisan storage:link

# Set proper permissions
echo "Setting permissions..."
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/database

echo "BAG Comics is ready!"

# Execute the main command
exec "$@"
