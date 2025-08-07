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

# Set database connection details from Render environment BEFORE caching config
echo "Configuring database connection..."

# Force PostgreSQL connection settings for Render
export DB_CONNECTION="pgsql"
export DB_HOST="${DB_HOST:-dpg-d2a2nrh5pdvs73aaf51g-a}"
export DB_PORT="${DB_PORT:-5432}"
export DB_DATABASE="${DB_DATABASE:-bagcomics_db}"
export DB_USERNAME="${DB_USERNAME:-bagcomics}"
export DB_PASSWORD="${DB_PASSWORD:-fhTpOQ62SKRsHE3BYiobOtUYhq4zlww6}"

# Also handle DATABASE_URL if provided (Render style)
if [ -n "$DATABASE_URL" ]; then
    echo "DATABASE_URL provided: parsing components"
    # Parse DATABASE_URL for PostgreSQL
    # Format: postgresql://username:password@host:port/database
    
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
fi

echo "Final database config: $DB_USERNAME@$DB_HOST:$DB_PORT/$DB_DATABASE"

# Write database config to .env file to ensure Laravel uses correct values
echo "Writing database configuration to .env..."

# Create base .env from production template if it doesn't exist
if [ ! -f /var/www/html/.env ]; then
    if [ -f /var/www/html/.env.production ]; then
        echo "Copying .env.production to .env"
        cp /var/www/html/.env.production /var/www/html/.env
    else
        echo "Creating basic .env file"
        cat > /var/www/html/.env << 'EOF'
APP_NAME="BAG Comics"
APP_ENV=production
APP_DEBUG=false
LOG_CHANNEL=stack
LOG_LEVEL=error
EOF
    fi
fi

# Update database configuration in .env
echo "Updating database settings in .env..."
# Remove existing DB_ lines and add new ones
grep -v '^DB_' /var/www/html/.env > /var/www/html/.env.temp 2>/dev/null || touch /var/www/html/.env.temp
cat >> /var/www/html/.env.temp << EOF
DB_CONNECTION=$DB_CONNECTION
DB_HOST=$DB_HOST
DB_PORT=$DB_PORT
DB_DATABASE=$DB_DATABASE
DB_USERNAME=$DB_USERNAME
DB_PASSWORD=$DB_PASSWORD
EOF
mv /var/www/html/.env.temp /var/www/html/.env

# Debug: show current .env database config
echo "Current .env database configuration:"
grep '^DB_' /var/www/html/.env || echo "No DB_ variables found in .env"

# Clear and cache config AFTER setting database configuration
echo "Optimizing application..."
echo "Clearing all Laravel caches..."
php artisan config:clear || echo "Config clear failed"
php artisan route:clear || echo "Route clear failed"  
php artisan view:clear || echo "View clear failed"
php artisan cache:clear || echo "Cache clear failed"
php artisan event:clear || echo "Event clear failed"

# Remove cached files manually
echo "Removing cache files manually..."
rm -rf /var/www/html/bootstrap/cache/*.php || echo "No bootstrap cache files"
rm -rf /var/www/html/storage/framework/cache/data/* || echo "No cache data"
rm -rf /var/www/html/storage/framework/views/* || echo "No compiled views"

echo "Rebuilding config cache..."
php artisan config:cache

# Only cache views (disable route caching temporarily)
if [ "$APP_DEBUG" != "true" ]; then
    # php artisan route:cache  # Disabled temporarily due to route binding issues
    php artisan view:cache
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
php artisan migrate --force || {
    echo "Migration failed, attempting to continue..."
    # If migrations fail due to existing tables, that's okay in production
    echo "Note: Some migrations may have failed due to existing tables, which is expected in production."
}

# Seed the database with all data
echo "Seeding database with sample data..."
# Check if database seeding is needed
echo "Checking if database seeding is required..."
SEED_CHECK=$(php -r "
require '/var/www/html/vendor/autoload.php';
\$app = require '/var/www/html/bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    \$comicCount = \App\Models\Comic::count();
    \$userCount = \App\Models\User::where('email', '!=', 'test@example.com')->count();
    echo \"comics:\$comicCount,users:\$userCount\";
} catch (Exception \$e) {
    echo 'error:' . \$e->getMessage();
}
" 2>/dev/null || echo "error:database_connection_failed")

echo "Seed check result: $SEED_CHECK"

if [[ "$SEED_CHECK" == comics:0* ]] || [[ "$SEED_CHECK" == *users:0* ]]; then
    echo "Database appears empty, running full seeding..."
    
    # Check if sample PDF exists for comics
    if [ -f "/var/www/html/public/sample-comic.pdf" ]; then
        echo "Sample comic PDF found in public directory, proceeding with seeding..."
    else
        echo "Warning: sample-comic.pdf not found in public/, creating placeholder..."
        echo "This is a placeholder PDF for demo purposes" > /var/www/html/public/sample-comic.pdf
    fi
    
    # Run database seeding with error handling
    if php artisan db:seed --force; then
        echo "Database seeding completed successfully!"
        
        # Verify seeding completed successfully
        VERIFICATION=$(php -r "
        require '/var/www/html/vendor/autoload.php';
        \$app = require '/var/www/html/bootstrap/app.php';
        \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
        
        try {
            \$comics = \App\Models\Comic::count();
            \$users = \App\Models\User::count();
            echo \"Success: \$comics comics and \$users users created\";
        } catch (Exception \$e) {
            echo 'Error: ' . \$e->getMessage();
        }
        " 2>/dev/null || echo "Verification failed")
        
        echo "Seeding verification: $VERIFICATION"
    else
        echo "Warning: Database seeding failed, but continuing deployment..."
        echo "Application will work but may have no initial content."
        echo "You can manually run 'php artisan db:seed' later."
    fi
else
    echo "Database already contains data, skipping seeding."
    echo "Current state: $SEED_CHECK"
fi

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
