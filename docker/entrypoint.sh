#!/bin/bash

# Exit on any error
set -e

echo "Starting BAG Comics deployment..."

# ============================================
# PHASE 1: Create directories (no Laravel needed)
# ============================================
echo "Creating storage directories..."
mkdir -p /var/www/html/storage/app/public/comics
mkdir -p /var/www/html/storage/app/public/covers
mkdir -p /var/www/html/storage/app/public/images/original
mkdir -p /var/www/html/storage/app/public/images/thumbnail
mkdir -p /var/www/html/storage/app/public/images/small
mkdir -p /var/www/html/storage/app/public/images/medium
mkdir -p /var/www/html/storage/app/public/images/large
mkdir -p /var/www/html/storage/app/public/exports
mkdir -p /var/www/html/storage/framework/cache/data
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

    chown -R www-data:www-data "$PERSISTENT_STORAGE_PATH"
    chmod -R 775 "$PERSISTENT_STORAGE_PATH"

    echo "Persistent storage structure created successfully"
fi

# ============================================
# PHASE 2: Configure .env BEFORE any Laravel commands
# ============================================
echo "Configuring environment..."

# Set database connection details from Render environment
export DB_CONNECTION="pgsql"
export DB_HOST="${DB_HOST:-dpg-d2a2nrh5pdvs73aaf51g-a}"
export DB_PORT="${DB_PORT:-5432}"
export DB_DATABASE="${DB_DATABASE:-bagcomics_db}"
export DB_USERNAME="${DB_USERNAME:-bagcomics}"
export DB_PASSWORD="${DB_PASSWORD:-fhTpOQ62SKRsHE3BYiobOtUYhq4zlww6}"

# Handle DATABASE_URL if provided (Render style)
if [ -n "$DATABASE_URL" ]; then
    echo "DATABASE_URL provided: parsing components"
    DB_URL_NO_PREFIX="${DATABASE_URL#postgresql://}"
    DB_CREDENTIALS="${DB_URL_NO_PREFIX%%@*}"
    export DB_USERNAME="${DB_CREDENTIALS%%:*}"
    export DB_PASSWORD="${DB_CREDENTIALS#*:}"
    DB_HOST_PORT_DB="${DB_URL_NO_PREFIX#*@}"
    DB_HOST_PORT="${DB_HOST_PORT_DB%%/*}"
    export DB_HOST="${DB_HOST_PORT%%:*}"
    if [[ "$DB_HOST_PORT" == *":"* ]]; then
        export DB_PORT="${DB_HOST_PORT#*:}"
    else
        export DB_PORT="5432"
    fi
    export DB_DATABASE="${DB_HOST_PORT_DB##*/}"
fi

echo "Database config: $DB_USERNAME@$DB_HOST:$DB_PORT/$DB_DATABASE"

# Create complete .env file with all required variables
echo "Creating complete .env file..."
cat > /var/www/html/.env << EOF
APP_NAME="${APP_NAME:-BAG Comics}"
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY:-base64:nVb4U6m2ibg2Hcxah3zRuO+yGHc5gIPMKn06exhHOrc=}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-https://bagcomics.onrender.com}

LOG_CHANNEL=${LOG_CHANNEL:-stack}
LOG_LEVEL=${LOG_LEVEL:-error}

DB_CONNECTION=${DB_CONNECTION}
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}

SESSION_DRIVER=${SESSION_DRIVER:-database}
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=${SESSION_SECURE_COOKIE:-true}
SESSION_DOMAIN=${SESSION_DOMAIN:-.bagcomics.shop}

CACHE_STORE=${CACHE_STORE:-database}
QUEUE_CONNECTION=${QUEUE_CONNECTION:-database}

FILESYSTEM_DISK=${FILESYSTEM_DISK:-public}
FILE_STORAGE_DISK=${FILE_STORAGE_DISK:-public}

MAIL_MAILER=${MAIL_MAILER:-log}

STRIPE_KEY=${STRIPE_KEY:-}
STRIPE_SECRET=${STRIPE_SECRET:-}
STRIPE_WEBHOOK_SECRET=${STRIPE_WEBHOOK_SECRET:-}

AUTH_MODEL=${AUTH_MODEL:-App\\Models\\User}
FORCE_HTTPS=${FORCE_HTTPS:-true}
EOF

echo "Environment file created. APP_KEY is set: $(grep -q 'APP_KEY=base64:' /var/www/html/.env && echo 'YES' || echo 'NO')"

# ============================================
# PHASE 3: Clear old caches (file operations only)
# ============================================
echo "Clearing cached files..."
rm -rf /var/www/html/bootstrap/cache/*.php 2>/dev/null || true
rm -rf /var/www/html/storage/framework/cache/data/* 2>/dev/null || true
rm -rf /var/www/html/storage/framework/views/*.php 2>/dev/null || true

# ============================================
# PHASE 4: Run Laravel optimization commands
# ============================================
echo "Optimizing Laravel..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache

if [ "$APP_DEBUG" != "true" ]; then
    php artisan view:cache || echo "View cache failed (non-critical)"
fi

# ============================================
# PHASE 5: Wait for database and run migrations
# ============================================
if [ "$DB_CONNECTION" = "pgsql" ] && [ -n "$DB_HOST" ] && [ "$DB_HOST" != "127.0.0.1" ] && [ "$DB_HOST" != "localhost" ]; then
    echo "Waiting for PostgreSQL to be ready..."
    timeout_count=30

    while [ $timeout_count -gt 0 ]; do
        if php -r "
            \$conn = @pg_connect('host=$DB_HOST port=$DB_PORT dbname=$DB_DATABASE user=$DB_USERNAME password=$DB_PASSWORD connect_timeout=5');
            exit(\$conn ? 0 : 1);
        " 2>/dev/null; then
            echo "PostgreSQL is ready!"
            break
        fi

        timeout_count=$((timeout_count - 1))
        if [ $timeout_count -eq 0 ]; then
            echo "PostgreSQL connection timeout, continuing anyway..."
            break
        fi

        echo "Waiting for PostgreSQL... (${timeout_count}s remaining)"
        sleep 2
    done
fi

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force || echo "Migration completed (some may have been skipped)"

# ============================================
# PHASE 6: Seed database if empty
# ============================================
echo "Checking if database seeding is required..."
COMIC_COUNT=$(php artisan tinker --execute="echo App\Models\Comic::count();" 2>/dev/null | tail -1 || echo "0")

if [ "$COMIC_COUNT" = "0" ] || [ -z "$COMIC_COUNT" ]; then
    echo "Database appears empty, running seeding..."
    php artisan db:seed --force || echo "Seeding completed with warnings"
else
    echo "Database has $COMIC_COUNT comics, skipping seeding."
fi

# ============================================
# PHASE 7: Final setup
# ============================================
echo "Creating storage link..."
php artisan storage:link 2>/dev/null || echo "Storage link already exists"

echo "Setting final permissions..."
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/public
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache
chmod -R 755 /var/www/html/public

echo "BAG Comics is ready!"

# Execute the main command
exec "$@"
