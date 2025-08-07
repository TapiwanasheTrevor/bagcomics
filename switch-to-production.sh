#!/bin/bash

echo "🚀 Switching to PRODUCTION environment..."

# Copy production environment file to .env
cp .env.production .env

# Clear caches
echo "Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

echo "✅ Switched to PRODUCTION environment successfully!"
echo "📍 Database: Render PostgreSQL"
echo "🌐 URL: https://bagcomics.onrender.com"
echo "🔧 Debug: disabled"
echo "📧 Mail: SMTP"
echo "🔑 Sessions: database driver"

echo ""
echo "⚠️  WARNING: You are now connected to PRODUCTION database!"
echo "Be careful with migrations and data changes."
echo ""
echo "To deploy changes to production:"
echo "1. Test thoroughly in local first"
echo "2. git add . && git commit -m 'your message'"
echo "3. git push origin main"