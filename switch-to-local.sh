#!/bin/bash

echo "🔄 Switching to LOCAL environment..."

# Copy local environment file to .env
cp .env.local .env

# Clear caches
echo "Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

echo "✅ Switched to LOCAL environment successfully!"
echo "📍 Database: localhost:5432/bagcomics"
echo "🌐 URL: http://localhost:8000"
echo "🔧 Debug: enabled"
echo "📧 Mail: log driver"
echo "🔑 Sessions: file driver"

echo ""
echo "Next steps:"
echo "1. Make sure your local PostgreSQL is running"
echo "2. Run: php artisan serve"
echo "3. Visit: http://localhost:8000"