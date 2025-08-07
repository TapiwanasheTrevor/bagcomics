# 🔧 Environment Management Guide

This guide explains how to manage different environments (local, production) for BAG Comics to prevent configuration conflicts.

## 📁 Environment Files

### Available Environment Files:
- `.env` - **Active environment** (never commit this)
- `.env.local` - Local development configuration
- `.env.production` - Production configuration template
- `.env.example` - Laravel default example

## 🔄 Quick Environment Switching

### Switch to Local Development:
```bash
# Using the script (recommended)
./switch-to-local.sh

# Or manually
cp .env.local .env
php artisan config:clear
```

### Switch to Production Configuration:
```bash
# Using the script (recommended)  
./switch-to-production.sh

# Or manually
cp .env.production .env
php artisan config:clear
```

## 🏠 Local Environment (`.env.local`)

**Use for:** Local development, testing, debugging

**Key Settings:**
- Database: `localhost:5432/bagcomics`
- URL: `http://localhost:8000`
- Debug: `true`
- Sessions: File-based
- Mail: Log driver
- HTTPS: Disabled

**Requirements:**
- Local PostgreSQL server running
- PHP development server or XAMPP

## 🚀 Production Environment (`.env.production`)

**Use for:** Render.com deployment (reference only)

**Key Settings:**
- Database: Render PostgreSQL
- URL: `https://bagcomics.onrender.com`
- Debug: `false` 
- Sessions: Database-based
- Mail: SMTP
- HTTPS: Enforced

**Note:** Production uses Render environment variables, not this file.

## ⚠️ Important Rules

### ❌ DON'T:
- Commit `.env` file (it's in `.gitignore`)
- Use production credentials locally
- Change environments without clearing caches
- Test migrations on production directly

### ✅ DO:
- Use switching scripts when changing environments
- Clear caches after environment changes
- Test locally before production deployment
- Keep environment files updated

## 🔧 Common Commands

### Check Current Environment:
```bash
php artisan env
# or
grep "APP_ENV" .env
```

### Clear All Caches (after environment change):
```bash
php artisan config:clear
php artisan route:clear  
php artisan view:clear
php artisan cache:clear
```

### Test Database Connection:
```bash
php artisan migrate:status
```

## 🐛 Troubleshooting

### "Database connection refused":
1. Check if you're in the right environment
2. Verify database credentials match environment
3. Ensure database server is running (local) or accessible (production)

### "Filament admin not accessible":
1. Switch to correct environment: `./switch-to-local.sh`
2. Clear caches: `php artisan config:clear`
3. Check database connection
4. Verify user has admin privileges

### "Session issues":
1. Different session drivers between environments
2. Clear sessions: `php artisan session:flush` (local only)
3. Check session configuration matches environment

## 🚀 Deployment Workflow

### Local Development:
1. `./switch-to-local.sh`
2. Make your changes
3. Test thoroughly
4. Commit and push

### Production Deployment:
1. Push changes to GitHub
2. Render automatically deploys
3. Uses environment variables (not `.env.production`)
4. Monitor deployment logs

## 🔐 Environment Variables in Render

Production uses Render's environment variables dashboard, not `.env.production`. 

To update production settings:
1. Go to Render dashboard
2. Select your web service  
3. Go to "Environment" tab
4. Update variables
5. Service redeploys automatically

## 📊 Environment Comparison

| Setting | Local | Production |
|---------|-------|------------|
| Database | localhost | Render PostgreSQL |
| Sessions | File | Database |
| Debug | Enabled | Disabled |
| HTTPS | Disabled | Enforced |
| Mail | Log | SMTP |
| Cache | Database | Database |
| Assets | Local | CDN-ready |

---

## 🆘 Emergency Recovery

If you accidentally broke an environment:

### Restore Local:
```bash
git checkout .env.local
cp .env.local .env
php artisan config:clear
```

### Fix Production:
1. Check Render environment variables
2. Redeploy from GitHub
3. Or use Render's manual deploy

---

**Remember:** Always test locally first, then deploy to production! 🚀