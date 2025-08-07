#!/bin/bash

# Exit on any error
set -e

echo "Starting BAG Comics deployment..."

# Ensure storage directories exist
echo "Creating storage directories..."
mkdir -p /var/www/html/storage/app/public/comics
mkdir -p /var/www/html/storage/app/public/covers
mkdir -p /var/www/html/storage/app/public/images/original
mkdir -p /var/www/html/storage/app/public/images/thumbnail
mkdir -p /var/www/html/storage/app/public/images/small
mkdir -p /var/www/html/storage/app/public/images/medium
mkdir -p /var/www/html/storage/app/public/images/large
mkdir -p /var/www/html/storage/app/public/exports
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

# If persistent storage is mounted, ensure structure exists there too
if [ -n "$PERSISTENT_STORAGE_PATH" ] && [ -d "$PERSISTENT_STORAGE_PATH" ]; then
    echo "Setting up persistent storage structure at $PERSISTENT_STORAGE_PATH..."
    mkdir -p "$PERSISTENT_STORAGE_PATH/comics"
    mkdir -p "$PERSISTENT_STORAGE_PATH/covers"
    mkdir -p "$PERSISTENT_STORAGE_PATH/images/original"
    mkdir -p "$PERSISTENT_STORAGE_PATH/images/thumbnail"
    mkdir -p "$PERSISTENT_STORAGE_PATH/images/small"
    mkdir -p "$PERSISTENT_STORAGE_PATH/images/medium"
    mkdir -p "$PERSISTENT_STORAGE_PATH/images/large"
    mkdir -p "$PERSISTENT_STORAGE_PATH/exports"
    
    # Set permissions for persistent storage
    chown -R www-data:www-data "$PERSISTENT_STORAGE_PATH"
    chmod -R 775 "$PERSISTENT_STORAGE_PATH"
    
    echo "Persistent storage structure created successfully"
fi

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

# Set database connection details from Render environment
echo "Configuring database connection..."

# Check if DATABASE_URL is provided (Render style)
if [ -n "$DATABASE_URL" ]; then
    echo "Using DATABASE_URL for connection"
    # Parse DATABASE_URL for PostgreSQL
    # Format: postgresql://username:password@host:port/database
    export DB_CONNECTION="pgsql"
    
    # Extract components from DATABASE_URL
    # Remove postgresql:// prefix
    DB_URL_NO_PREFIX="${DATABASE_URL#postgresql://}"
    
    # Extract username:password
    DB_CREDENTIALS="${DB_URL_NO_PREFIX%%@*}"
    export DB_USERNAME="${DB_CREDENTIALS%%:*}"
    export DB_PASSWORD="${DB_CREDENTIALS#*:}"
    
    # Extract host:port/database
    DB_HOST_PORT_DB="${DB_URL_NO_PREFIX#*@}"
    
    # Extract host and port
    DB_HOST_PORT="${DB_HOST_PORT_DB%%/*}"
    export DB_HOST="${DB_HOST_PORT%%:*}"
    
    # Check if port is specified or use default
    if [[ "$DB_HOST_PORT" == *":"* ]]; then
        export DB_PORT="${DB_HOST_PORT#*:}"
    else
        export DB_PORT="5432"
    fi
    
    # Extract database name
    export DB_DATABASE="${DB_HOST_PORT_DB##*/}"
    
    echo "Database config: $DB_USERNAME@$DB_HOST:$DB_PORT/$DB_DATABASE"
else
    # Use individual environment variables if DATABASE_URL not provided
    export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
    export DB_HOST="${DB_HOST:-dpg-d2a2nrh5pdvs73aaf51g-a}"
    export DB_PORT="${DB_PORT:-5432}"
    export DB_DATABASE="${DB_DATABASE:-bagcomics_db}"
    export DB_USERNAME="${DB_USERNAME:-bagcomics}"
    export DB_PASSWORD="${DB_PASSWORD:-fhTpOQ62SKRsHE3BYiobOtUYhq4zlww6}"
    echo "Using individual DB env vars: $DB_USERNAME@$DB_HOST:$DB_PORT/$DB_DATABASE"
fi

# Wait for database to be ready (if using PostgreSQL)
if [ "$DB_CONNECTION" = "pgsql" ] && [ -n "$DB_HOST" ] && [ "$DB_HOST" != "127.0.0.1" ] && [ "$DB_HOST" != "localhost" ]; then
    echo "Waiting for PostgreSQL to be ready..."
    
    # For Render, try shorter timeout and different connection approach
    if [[ "$DB_HOST" == dpg-* ]]; then
        echo "Detected Render PostgreSQL host, using optimized connection strategy"
        timeout=30
        
        # Test connection with minimal timeout
        while [ $timeout -gt 0 ]; do
            if timeout 5 PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -c 'SELECT 1;' >/dev/null 2>&1; then
                echo "PostgreSQL is ready!"
                break
            fi
            
            timeout=$((timeout - 1))
            if [ $timeout -eq 0 ]; then
                echo "PostgreSQL connection timeout, but continuing deployment..."
                echo "Connection will be attempted during Laravel initialization"
                break
            fi
            
            echo "PostgreSQL is unavailable - sleeping (${timeout}s remaining)"
            sleep 2
        done
    else
        # Standard connection check for other environments
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
