# Render Environment Variables Configuration

## Required Environment Variables for Render Deployment

Copy and paste these into your Render service environment variables section:

### Application Settings
```
APP_NAME=BAG Comics
APP_ENV=production
APP_KEY=base64:nVb4U6m2ibg2Hcxah3zRuO+yGHc5gIPMKn06exhHOrc=
APP_DEBUG=false
APP_URL=https://bagcomics.onrender.com
```

### Database Configuration (Option 1: Individual Variables)
```
DB_CONNECTION=pgsql
DB_HOST=dpg-d2a2nrh5pdvs73aaf51g-a
DB_PORT=5432
DB_DATABASE=bagcomics_db
DB_USERNAME=bagcomics
DB_PASSWORD=fhTpOQ62SKRsHE3BYiobOtUYhq4zlww6
```

### Database Configuration (Option 2: Single URL - Choose This One)
```
DATABASE_URL=postgresql://bagcomics:fhTpOQ62SKRsHE3BYiobOtUYhq4zlww6@dpg-d2a2nrh5pdvs73aaf51g-a/bagcomics_db
```

### Cache and Session Settings
```
CACHE_DRIVER=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
BROADCAST_DRIVER=log
```

### Security Settings
```
FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=bagcomics.onrender.com
SANCTUM_STATEFUL_DOMAINS=bagcomics.onrender.com
```

### File Storage
```
FILESYSTEM_DISK=public
PERSISTENT_STORAGE_PATH=/var/www/html/storage/app/public
```

### Mail Configuration (Optional - Update with your mail service)
```
MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=hello@bagcomics.com
MAIL_FROM_NAME=BAG Comics
```

### Stripe Configuration (Add your actual Stripe keys)
```
STRIPE_KEY=pk_live_your_stripe_publishable_key
STRIPE_SECRET=sk_live_your_stripe_secret_key
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret
```

### Additional Settings
```
LOG_CHANNEL=stack
LOG_LEVEL=error
SESSION_LIFETIME=120
```

## Database URLs Provided by Render

### Internal Database URL (for application)
```
postgresql://bagcomics:fhTpOQ62SKRsHE3BYiobOtUYhq4zlww6@dpg-d2a2nrh5pdvs73aaf51g-a/bagcomics_db
```

### External Database URL (for external connections/tools)
```
postgresql://bagcomics:fhTpOQ62SKRsHE3BYiobOtUYhq4zlww6@dpg-d2a2nrh5pdvs73aaf51g-a.oregon-postgres.render.com/bagcomics_db
```

## Deployment Steps

1. **Set Environment Variables**: Copy the variables above into your Render service settings
2. **Deploy**: Push code to GitHub to trigger deployment
3. **Run Migrations**: After successful deployment, run: `php artisan migrate --force`
4. **Seed Admin User**: Run: `php artisan db:seed --class=AdminUserSeeder`
5. **Initialize Production**: Run: `php artisan bagcomics:init-production`

## Admin Access After Deployment

- **URL**: https://bagcomics.onrender.com/admin
- **Email**: admin@bagcomics.com
- **Password**: admin123

## Notes

- Use the **Internal Database URL** for your application's `DATABASE_URL`
- The entrypoint script will automatically parse the DATABASE_URL and set individual DB_* variables
- Persistent disk storage is mounted at `/var/www/html/storage/app/public` (10GB)
- All uploaded comics will persist between deployments