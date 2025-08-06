# BAG Comics - Production Deployment Guide

## Overview

This guide covers deploying BAG Comics to production on Render with PostgreSQL database and persistent file storage.

## Prerequisites

- Git repository access
- Render account
- Stripe account for payments
- Basic knowledge of Docker and PostgreSQL

## Production Architecture

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Render Web    │────│  PostgreSQL DB   │    │  Persistent     │
│   Service       │    │  (Render)        │    │  Disk Storage   │
│   (Docker)      │    │                  │    │  (10GB)         │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

## Step-by-Step Deployment

### 1. Prepare Your Repository

Ensure your code is pushed to the main branch with all the latest changes:

```bash
git add .
git commit -m "Production ready deployment"
git push origin main
```

### 2. Set Up Render Services

#### Create PostgreSQL Database

1. Go to Render Dashboard
2. Click "New" → "PostgreSQL"
3. Configure:
   - **Name**: `bagcomics-db`
   - **Database**: `bagcomics`  
   - **User**: `bagcomics_user`
   - **Plan**: `Starter` (or higher for production)

#### Create Web Service

1. Click "New" → "Web Service"
2. Connect your Git repository
3. Configure:
   - **Name**: `bagcomics`
   - **Runtime**: `Docker`
   - **Branch**: `main`
   - **Plan**: `Starter` (or higher)

### 3. Configure Environment Variables

In your Render web service, set these environment variables:

#### Application Settings
```bash
APP_NAME="BAG Comics"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:YOUR_GENERATED_APP_KEY_HERE
APP_URL=https://your-app.onrender.com
```

#### Database Configuration
```bash
DB_CONNECTION=pgsql
DB_HOST=[Auto-filled from database service]
DB_PORT=[Auto-filled from database service]  
DB_DATABASE=[Auto-filled from database service]
DB_USERNAME=[Auto-filled from database service]
DB_PASSWORD=[Auto-filled from database service]
```

#### Security Settings
```bash
FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=".your-app.onrender.com"
```

#### Cache and Session
```bash
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

#### File Storage
```bash
FILESYSTEM_DISK=public
PERSISTENT_STORAGE_PATH=/var/www/html/storage/app/public
```

#### Stripe Configuration
```bash
STRIPE_KEY=pk_live_your_stripe_publishable_key
STRIPE_SECRET=sk_live_your_stripe_secret_key
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret
VITE_STRIPE_PUBLISHABLE_KEY=pk_live_your_stripe_publishable_key
```

### 4. Configure Persistent Storage

Add to your `render.yaml`:

```yaml
services:
  - type: web
    name: bagcomics
    disk:
      name: bagcomics-storage
      mountPath: /var/www/html/storage/app/public
      sizeGB: 10
```

### 5. Deploy and Initialize

#### First Deployment
```bash
# Render will automatically build and deploy from your Dockerfile
# Monitor the deployment in Render Dashboard
```

#### Initialize Production Environment
```bash
# SSH into your Render service (if available) or use the initialization command:
php artisan bagcomics:init-production
```

### 6. Post-Deployment Tasks

#### Verify Services
```bash
# Check application health
curl https://your-app.onrender.com/health

# Check database connectivity  
php artisan tinker
> DB::connection()->getPdo();
```

#### Create Admin User
```bash
php artisan db:seed --class=AdminUserSeeder
```

#### Warm Up Caches
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Domain Configuration

### Custom Domain Setup

1. In Render Dashboard → Your Service → Settings
2. Add your custom domain
3. Configure DNS:
   ```
   Type: CNAME
   Name: @ (or your subdomain)
   Value: your-app.onrender.com
   ```

### SSL Certificate

Render automatically provides SSL certificates for custom domains.

## Monitoring and Maintenance

### Health Checks

Render automatically monitors your service health via the `/health` endpoint.

### Log Monitoring

```bash
# View application logs in Render Dashboard
# Or programmatically:
php artisan log:monitor
```

### Database Backups

Render automatically backs up PostgreSQL databases. For additional backups:

```bash
# Manual backup
php artisan backup:create

# Automated backup schedule (add to cron or GitHub Actions)
php artisan schedule:run
```

### Performance Monitoring

```bash
# Check performance metrics
php artisan analytics:performance-report

# Monitor database performance
php artisan db:analyze-performance
```

## Scaling Considerations

### Horizontal Scaling
- Upgrade to Render's higher plans for more CPU/memory
- Consider multiple web service instances for high traffic

### Database Optimization
- Monitor database performance metrics
- Consider upgrading PostgreSQL plan for more connections/storage
- Implement read replicas if needed

### File Storage
- Monitor disk usage in Render Dashboard
- Increase persistent disk size as needed
- Consider CDN for static assets

## Troubleshooting

### Common Issues

#### 1. File Upload Issues
```bash
# Check disk space
df -h /var/www/html/storage

# Check permissions
ls -la /var/www/html/storage/app/public

# Fix permissions
php artisan storage:fix-permissions
```

#### 2. Database Connection Issues
```bash
# Test database connection
php artisan db:test-connection

# Check environment variables
php artisan config:show database
```

#### 3. Cache Issues
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Performance Issues

#### Slow Page Load
```bash
# Check database query performance
php artisan db:monitor-slow-queries

# Analyze cache hit rates  
php artisan cache:stats

# Check memory usage
php artisan system:memory-usage
```

#### High Database Load
```bash
# Optimize database
php artisan db:optimize

# Update statistics tables
php artisan db:update-statistics

# Analyze table performance
php artisan db:analyze-tables
```

## Security Checklist

### Pre-Production Security Audit

- [ ] All environment variables properly set
- [ ] HTTPS enforced
- [ ] Secure cookies enabled
- [ ] CSRF protection active
- [ ] Rate limiting configured
- [ ] File upload validation enabled
- [ ] SQL injection protection verified
- [ ] XSS protection implemented

### Ongoing Security

```bash
# Regular security scan
php artisan security:scan

# Update dependencies
composer update --with-all-dependencies

# Check for vulnerabilities
php artisan security:check-vulnerabilities
```

## Backup and Recovery

### Database Backup Strategy

```bash
# Daily automated backup
0 2 * * * php artisan backup:database

# Weekly full backup  
0 3 * * 0 php artisan backup:full
```

### File Backup Strategy

```bash
# Backup uploaded files
php artisan backup:files --disk=public

# Sync to external storage
php artisan backup:sync-to-cloud
```

### Recovery Procedures

```bash
# Restore database from backup
php artisan backup:restore-database backup_file.sql

# Restore files from backup
php artisan backup:restore-files backup_files.tar.gz
```

## Deployment Automation

### GitHub Actions CI/CD

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Render

on:
  push:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Run Tests
        run: |
          composer install
          php artisan test
          
  deploy:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - name: Deploy to Render
        run: |
          curl -X POST "${{ secrets.RENDER_DEPLOY_HOOK_URL }}"
```

### Rolling Deployments

1. Deploy to staging environment first
2. Run full test suite
3. Deploy to production with zero downtime
4. Monitor health metrics post-deployment

## Support and Maintenance

### Regular Maintenance Tasks

#### Daily
- [ ] Monitor error logs
- [ ] Check system health metrics
- [ ] Verify backup completion

#### Weekly  
- [ ] Review performance metrics
- [ ] Update security patches
- [ ] Clean up log files
- [ ] Check disk usage

#### Monthly
- [ ] Review and optimize database queries
- [ ] Update dependencies
- [ ] Performance audit
- [ ] Security audit
- [ ] Review and update documentation

### Emergency Procedures

#### Service Downtime
1. Check Render service status
2. Review recent deployments
3. Check error logs
4. Rollback if necessary
5. Contact Render support if needed

#### Database Issues
1. Check PostgreSQL service status
2. Review slow query logs
3. Check database connections
4. Consider read-only mode if needed
5. Restore from backup if corrupted

### Contact and Support

- **Application Issues**: Check logs and health endpoints
- **Render Platform Issues**: Render Support Portal
- **Database Issues**: Monitor PostgreSQL metrics
- **Security Concerns**: Follow incident response plan

## Performance Benchmarks

### Expected Performance Metrics

- **Homepage Load Time**: < 1.5 seconds
- **API Response Time**: < 500ms
- **Search Queries**: < 800ms
- **File Upload Processing**: < 5 seconds
- **Database Query Time**: < 200ms average

### Monitoring Thresholds

- **Error Rate**: < 1%
- **Response Time 95th Percentile**: < 2 seconds
- **Database Connections**: < 80% of limit
- **Disk Usage**: < 85% of allocated space
- **Memory Usage**: < 90% of allocated memory

This completes the production deployment guide for BAG Comics on Render with PostgreSQL and persistent storage.