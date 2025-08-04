#!/bin/bash

# Exit on any error
set -e

echo "Starting BAG Comics deployment..."

# Ensure storage directories exist
echo "Creating storage directories..."
mkdir -p /var/www/html/storage/app/public
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Run composer scripts that were skipped during build
echo "Running composer post-install scripts..."
composer run-script post-autoload-dump || echo "Composer scripts completed with warnings"

# Wait a moment for any file system operations
sleep 2

# Clear and cache config
echo "Optimizing application..."
php artisan config:clear
php artisan config:cache

# Only cache routes and views if not in debug mode
if [ "$APP_DEBUG" != "true" ]; then
    php artisan route:cache
    php artisan view:cache
fi

# Wait for database to be ready (if using PostgreSQL)
if [ "$DB_CONNECTION" = "pgsql" ] && [ -n "$DB_HOST" ] && [ "$DB_HOST" != "127.0.0.1" ] && [ "$DB_HOST" != "localhost" ]; then
    echo "Waiting for PostgreSQL to be ready..."
    timeout=60
    while ! PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" -c '\q' 2>/dev/null; do
        timeout=$((timeout - 1))
        if [ $timeout -eq 0 ]; then
            echo "Timeout waiting for PostgreSQL"
            exit 1
        fi
        echo "PostgreSQL is unavailable - sleeping"
        sleep 1
    done
    echo "PostgreSQL is ready!"
fi

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Seed the database if needed
echo "Seeding database..."
php artisan db:seed --class=CmsContentSeeder --force || echo "CMS seeding failed or already completed"

echo "Creating admin users..."
php artisan db:seed --class=AdminUserSeeder --force || echo "Admin user seeding failed or already completed"

# Create storage link
echo "Creating storage link..."
php artisan storage:link || echo "Storage link already exists"

# Set proper permissions
echo "Setting permissions..."
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/public
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache
chmod -R 755 /var/www/html/public

# Ensure specific directories exist and have correct permissions
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/app/public
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

echo "BAG Comics is ready!"

# Execute the main command
exec "$@"
