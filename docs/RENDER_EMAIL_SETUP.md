# Email Setup for Render Deployment

## Quick Setup Guide

### Option 1: Mailgun (Recommended - 5,000 free emails/month)

1. **Sign up at Mailgun**
   - Go to https://signup.mailgun.com/new/signup
   - Create account (no credit card required for sandbox)

2. **Get credentials**
   - Dashboard → Sending → Domain settings
   - Copy SMTP credentials

3. **Add to Render Environment Variables**
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.mailgun.org
   MAIL_PORT=587
   MAIL_USERNAME=postmaster@sandbox[...].mailgun.org
   MAIL_PASSWORD=your-password-here
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=noreply@bagcomics.com
   MAIL_FROM_NAME=BAG Comics
   ```

### Option 2: SendGrid (100 free emails/day)

1. **Sign up at SendGrid**
   - Go to https://signup.sendgrid.com
   - Create account and verify email

2. **Create API Key**
   - Settings → API Keys → Create API Key
   - Choose "Full Access"

3. **Add to Render Environment Variables**
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.sendgrid.net
   MAIL_PORT=587
   MAIL_USERNAME=apikey
   MAIL_PASSWORD=SG.xxxxxxxxxxxx (your API key)
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=noreply@bagcomics.com
   MAIL_FROM_NAME=BAG Comics
   ```

### Option 3: Gmail SMTP (Not recommended for production)
For testing only - limited to 500 emails/day:

1. **Enable 2-factor authentication** on your Gmail account

2. **Generate App Password**
   - Go to https://myaccount.google.com/apppasswords
   - Create app password for "Mail"

3. **Add to Render Environment Variables**
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=your-email@gmail.com
   MAIL_PASSWORD=your-app-password
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=your-email@gmail.com
   MAIL_FROM_NAME=BAG Comics
   ```

## Setting Environment Variables on Render

1. Go to your Render Dashboard
2. Select your BAG Comics service
3. Go to "Environment" tab
4. Add each variable:
   - Click "Add Environment Variable"
   - Enter key and value
   - Save changes

## Testing Email Configuration

After deployment, test your email setup:

```bash
# SSH into Render service or use Render Shell
php artisan tinker

# Test email
Mail::raw('Test email from BAG Comics', function ($message) {
    $message->to('your-email@example.com')
            ->subject('Test Email');
});
```

## Monitoring & Troubleshooting

### Check Email Logs
```bash
# View Laravel logs
tail -f storage/logs/laravel.log | grep -i mail
```

### Common Issues & Solutions

1. **"Connection could not be established"**
   - Check MAIL_HOST and MAIL_PORT
   - Verify firewall allows outbound SMTP

2. **"Authentication failed"**
   - Double-check MAIL_USERNAME and MAIL_PASSWORD
   - For SendGrid, username must be "apikey"

3. **"Emails not being received"**
   - Check spam/junk folders
   - Verify MAIL_FROM_ADDRESS is valid
   - Consider setting up domain verification

4. **Rate limiting errors**
   - Check service limits
   - Implement queue throttling:
   ```php
   // In config/queue.php
   'connections' => [
       'database' => [
           'driver' => 'database',
           'table' => 'jobs',
           'queue' => 'default',
           'retry_after' => 90,
           'after_commit' => false,
           'rate_limit' => [
               'key' => 'notifications',
               'allow' => 10, // 10 emails
               'every' => 60, // per minute
           ],
       ],
   ],
   ```

## Production Checklist

- [ ] Email service account created
- [ ] SMTP credentials obtained
- [ ] Environment variables added to Render
- [ ] Test email sent successfully
- [ ] Domain verification configured (optional)
- [ ] Queue worker running on Render
- [ ] Email logs monitored

## Queue Worker on Render

Add a background worker service for processing email queues:

1. **Create new Background Worker** on Render
2. **Set Build Command**: `composer install && npm install && npm run build`
3. **Set Start Command**: `php artisan queue:work --sleep=3 --tries=3`
4. **Copy all environment variables** from main service

## Costs Overview

| Service | Free Tier | Paid Plans |
|---------|-----------|------------|
| Mailgun | 5,000 emails/month | $35/mo for 50k emails |
| SendGrid | 100 emails/day | $19.95/mo for 50k emails |
| Postmark | 100 emails/month | $15/mo for 10k emails |
| Amazon SES | None | $0.10 per 1,000 emails |
| Gmail SMTP | 500 emails/day | Not for production |

## Recommended Setup for BAG Comics

For a production comic platform, we recommend:

1. **Start with Mailgun** (free tier)
2. **Verify your domain** for better deliverability
3. **Monitor usage** and upgrade as needed
4. **Set up queue worker** for background processing
5. **Enable email tracking** to monitor open rates

## Need Help?

- Mailgun Support: https://help.mailgun.com
- SendGrid Docs: https://docs.sendgrid.com
- Laravel Mail Docs: https://laravel.com/docs/mail
- Render Support: https://render.com/docs