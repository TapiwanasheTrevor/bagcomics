#!/bin/bash

# Script to update production environment variables for bagcomics.shop

echo "Production Environment Update Script for bagcomics.shop"
echo "========================================================"
echo ""
echo "This script will update your production environment variables."
echo ""

# Update the main environment variables
echo "Key environment variables to update in your production deployment:"
echo ""
echo "1. APP_URL=https://www.bagcomics.shop"
echo "2. ASSET_URL=https://www.bagcomics.shop"
echo "3. SESSION_DOMAIN=.bagcomics.shop"
echo "4. SANCTUM_STATEFUL_DOMAINS=www.bagcomics.shop,bagcomics.shop"
echo "5. SPA_URL=https://www.bagcomics.shop"
echo "6. FRONTEND_URL=https://www.bagcomics.shop"
echo ""
echo "CORS Configuration (already updated in config/cors.php):"
echo "- Allowed origins include both www.bagcomics.shop and bagcomics.shop"
echo "- Credentials are supported"
echo "- Paths include api/*, sanctum/csrf-cookie, build/*, storage/*"
echo ""

# If running locally for testing
if [ "$1" == "local" ]; then
    echo "Testing locally..."
    cp .env.production .env.production.backup
    echo "Backup created: .env.production.backup"
    
    # Clear caches
    php artisan config:clear
    php artisan cache:clear
    php artisan route:clear
    php artisan view:clear
    
    # Rebuild assets
    npm run build
    
    echo "Local testing environment updated!"
fi

echo ""
echo "Next steps:"
echo "1. Update these environment variables in your production deployment (Render, etc.)"
echo "2. Clear all caches in production: php artisan config:clear cache:clear route:clear view:clear"
echo "3. Rebuild assets if needed: npm run build"
echo "4. Restart your application"
echo ""
echo "Note: Make sure your DNS is properly configured:"
echo "- www.bagcomics.shop should point to your application"
echo "- bagcomics.shop should redirect to www.bagcomics.shop (or vice versa)"