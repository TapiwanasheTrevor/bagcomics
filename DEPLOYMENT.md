# BAG Comics - Render Deployment Guide

This guide will help you deploy your BAG Comics Laravel application to Render using Docker.

## Prerequisites

1. A GitHub repository with your code
2. A Render account (free tier available)
3. Your Stripe API keys (for production)

## Deployment Steps

### 1. Prepare Your Repository

Make sure all the Docker configuration files are committed to your repository:
- `Dockerfile`
- `docker/apache.conf`
- `docker/entrypoint.sh`
- `render.yaml`
- `.dockerignore`

### 2. Deploy to Render

#### Option A: Using render.yaml (Recommended)

1. Push your code to GitHub
2. Go to [Render Dashboard](https://dashboard.render.com)
3. Click "New" → "Blueprint"
4. Connect your GitHub repository
5. Render will automatically detect the `render.yaml` file and configure your service

#### Option B: Manual Setup

1. Go to [Render Dashboard](https://dashboard.render.com)
2. Click "New" → "Web Service"
3. Connect your GitHub repository
4. Configure the service:
   - **Name**: `bagcomics`
   - **Environment**: `Docker`
   - **Region**: Choose your preferred region
   - **Branch**: `main` (or your default branch)
   - **Dockerfile Path**: `./Dockerfile`

### 3. Environment Variables

Set these environment variables in Render:

#### Required Variables:
```
APP_NAME=BAG Comics
APP_ENV=production
APP_DEBUG=false
APP_KEY=[Will be auto-generated]
APP_URL=[Will be auto-set by Render]
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
LOG_LEVEL=error
```

#### Stripe Configuration:
**Important**: You must replace these placeholders with your actual Stripe keys:
```
STRIPE_KEY=pk_test_... (your Stripe publishable key)
STRIPE_SECRET=sk_test_... (your Stripe secret key)
STRIPE_WEBHOOK_SECRET=whsec_... (your webhook secret)
VITE_STRIPE_PUBLISHABLE_KEY=pk_test_... (same as STRIPE_KEY)
```

**To get your Stripe keys:**
1. Go to [Stripe Dashboard](https://dashboard.stripe.com)
2. Navigate to Developers → API keys
3. Copy your publishable and secret keys
4. For webhooks: Go to Developers → Webhooks → Add endpoint

### 4. Post-Deployment

After successful deployment:

1. **Access your app**: Your app will be available at `https://your-app-name.onrender.com`

2. **Create admin user**: SSH into your container or use Render's shell to create an admin user:
   ```bash
   php artisan make:filament-user
   ```

3. **Access admin panel**: Go to `https://your-app-name.onrender.com/admin`

4. **Configure CMS content**: Use the admin panel to customize your frontend content

## Important Notes

### Database
- Uses SQLite for simplicity on Render's free tier
- Database file is stored in the container (data will persist between deployments)
- For production, consider upgrading to PostgreSQL

### File Storage
- Uses local storage (files stored in container)
- For production, consider using AWS S3 or similar cloud storage

### SSL/HTTPS
- Render automatically provides SSL certificates
- Your app will be accessible via HTTPS

### Custom Domain
- You can add a custom domain in Render's dashboard
- Update `APP_URL` environment variable when using custom domain

## Troubleshooting

### Build Failures
- Check the build logs in Render dashboard
- Ensure all dependencies are properly specified
- Verify Docker configuration

### Runtime Issues
- Check application logs in Render dashboard
- Verify environment variables are set correctly
- Ensure database migrations ran successfully

### Performance
- Free tier has limitations (sleeps after 15 minutes of inactivity)
- Consider upgrading to paid plan for production use

## Scaling Considerations

For production deployment:

1. **Database**: Migrate to PostgreSQL
2. **File Storage**: Use AWS S3 or similar
3. **Caching**: Add Redis for better performance
4. **Monitoring**: Set up application monitoring
5. **Backups**: Implement database backup strategy

## Support

If you encounter issues:
1. Check Render's documentation
2. Review application logs
3. Verify environment configuration
4. Test locally with Docker first
