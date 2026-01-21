# BAG Comics - Production Deployment Guide

## Environment Configuration for Render

### Required Environment Variables

Set these environment variables in your Render dashboard:

```bash
# Application Settings
APP_NAME="BagComics"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://bagcomics.onrender.com
ASSET_URL=https://bagcomics.onrender.com

# Database (Auto-configured by Render PostgreSQL add-on)
DB_CONNECTION=pgsql
DB_HOST=your-postgres-host.oregon-postgres.render.com
DB_PORT=5432
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

# Session Configuration
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=bagcomics.onrender.com

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_FROM_ADDRESS="noreply@bagcomics.com"
MAIL_FROM_NAME="BagComics"

# Security
SANCTUM_STATEFUL_DOMAINS=bagcomics.onrender.com
SPA_URL=https://bagcomics.onrender.com
FRONTEND_URL=https://bagcomics.onrender.com
LIVEWIRE_SECURE=true
FORCE_HTTPS=true

# Search (Use database driver for production)
SCOUT_DRIVER=database

# Cache & Queue
CACHE_STORE=database
QUEUE_CONNECTION=database

# Stripe (Replace with your actual Stripe keys)
STRIPE_KEY=pk_test_your_stripe_publishable_key_here
STRIPE_SECRET=sk_test_your_stripe_secret_key_here
VITE_STRIPE_PUBLISHABLE_KEY=pk_test_your_stripe_publishable_key_here

# Authentication Model
AUTH_MODEL=App\\Models\\User
```

## Post-Deployment Steps

### 1. Create Admin User

Visit the following URL to create an admin user (replace with your email):
```
https://bagcomics.onrender.com/make-admin/your-email@domain.com
```

### 2. Access Admin Dashboard

Once you have admin privileges, you can access the admin dashboard at:
```
https://bagcomics.onrender.com/admin
```

### 3. Clear Caches (if needed)

If you need to clear caches in production, you can run:
```bash
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

Then re-cache for performance:
```bash
php artisan config:cache
php artisan route:cache
```

## Production Features

✅ **Filament Admin Dashboard** - Full admin interface with analytics widgets
✅ **PDF Comic Reader** - Enhanced PDF viewer with progress tracking  
✅ **Payment Processing** - Stripe integration for comic purchases
✅ **User Authentication** - Complete user management system
✅ **Responsive Design** - Mobile-optimized interface
✅ **Progressive Web App** - PWA features for mobile installation

## Security Features

- HTTPS enforcement
- Secure session cookies
- CSRF protection
- SQL injection protection
- XSS protection
- Content Security Policy headers
- Trusted proxy configuration

## Database

- **PostgreSQL** on Render
- **Migrations** run automatically on deploy
- **Session storage** in database
- **Cache storage** in database for better performance

## File Storage

- Comics stored in `/public/storage`
- Cover images in `/public/images`
- PDF files protected with access control

## Performance Optimizations

- Configuration caching
- Route caching
- View compilation
- Asset versioning
- Database query optimization
- Lazy loading for comic grids

## Monitoring & Logs

- Laravel logs available in Render dashboard
- Application metrics tracking
- Error reporting and debugging (disabled in production)
- Performance monitoring through built-in analytics

---

**Note**: Remember to update Stripe keys to live keys when ready for actual payments!