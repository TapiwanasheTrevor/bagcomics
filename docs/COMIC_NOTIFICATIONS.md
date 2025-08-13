# Comic Notification System

## Overview

The Comic Notification System automatically sends email notifications to users when new comics are uploaded by administrators. Users can opt-in/opt-out of these notifications during registration and through their account preferences.

## Features

✅ **User Registration Opt-in**: Users can choose to receive notifications during signup  
✅ **Automatic Notifications**: Notifications are sent automatically when admins upload new comics  
✅ **User Preferences**: Users can manage their notification settings anytime  
✅ **Beautiful Email Templates**: Rich HTML emails with comic details and branding  
✅ **Queue Support**: Notifications are processed in the background  
✅ **Admin Tools**: Admins can test and manually trigger notifications  
✅ **Comprehensive Logging**: All notification activities are logged  

## How It Works

### 1. User Registration
When users register, they can opt-in to:
- **Email notifications**: General email communications
- **New comic notifications**: Specific notifications for new comic releases

### 2. Automatic Triggers
Notifications are automatically sent when:
- A new comic is created with `is_visible = true` and `published_at` is set
- An existing comic is updated to become visible (`is_visible` changed from false to true)
- A deleted comic is restored

### 3. Email Content
Each notification email includes:
- Comic title and author
- Comic description (if available)
- Genre and pricing information
- Cover image (if available)
- Direct link to read the comic
- BAG Comics branding

## User Flow

### During Registration
1. User fills out registration form
2. Communication preferences section shows:
   - ✅ Email notifications (checked by default)
   - ✅ New comic notifications (checked by default, requires email notifications)
3. User can uncheck either option
4. Preferences are saved with their account

### Managing Preferences
Users can update their notification preferences:
- Via API: `PUT /api/preferences/notifications`
- Through account settings dashboard
- By clicking unsubscribe links in emails (future enhancement)

## Admin Features

### Automatic Processing
- Notifications are automatically queued when comics are published
- Background jobs handle the actual email sending
- Failed notifications are retried up to 3 times

### Manual Controls
Admins can:
- View notification statistics: `GET /api/admin/notifications/statistics`
- See subscriber list: `GET /api/admin/notifications/recipients`
- Manually trigger notifications: `POST /api/admin/notifications/comics/{comic}/trigger`
- Send test notifications: `POST /api/admin/notifications/test`

### Command Line Tools
```bash
# Test the notification system
php artisan comic:test-notifications

# Test with specific comic and user
php artisan comic:test-notifications --comic-id=1 --user-email=user@example.com

# Process notification queue
php artisan queue:work
```

## API Endpoints

### User Endpoints
```
GET    /api/preferences/notifications          - Get notification preferences
PUT    /api/preferences/notifications          - Update notification preferences
```

### Admin Endpoints (requires admin access)
```
GET    /api/admin/notifications/statistics     - Get notification statistics
GET    /api/admin/notifications/recipients     - Get list of subscribers
POST   /api/admin/notifications/test           - Send test notification
POST   /api/admin/notifications/comics/{comic}/trigger - Manually trigger notifications
```

## Configuration

### Environment Variables
```env
# Mail configuration
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=noreply@bagcomics.com
MAIL_FROM_NAME="BAG Comics"

# Queue configuration (for background processing)
QUEUE_CONNECTION=database
```

### Queue Setup
```bash
# Create queue tables
php artisan queue:table
php artisan migrate

# Run queue worker
php artisan queue:work
```

## Database Schema

### User Preferences
```sql
user_preferences:
- email_notifications (boolean)
- new_releases_notifications (boolean)
- reading_reminders (boolean)
```

### Notifications Table
Laravel's built-in notifications table tracks all sent notifications.

## Monitoring

### Logs
All notification activities are logged with details:
- `storage/logs/laravel.log`
- Search for: "New comic notifications", "Test notification", etc.

### Statistics
The system tracks:
- Total users
- Users with email notifications enabled
- Users subscribed to new releases
- Subscription rate percentage

### Queue Monitoring
Monitor background jobs:
```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

## Testing

### Automated Testing
```bash
# Run the test command
php artisan comic:test-notifications
```

### Manual Testing
1. Register a new user with notifications enabled
2. Create or publish a new comic as admin
3. Check that notification job is queued
4. Process the queue: `php artisan queue:work`
5. Verify email was sent (check logs or email service)

### Test Scenarios
- ✅ User with notifications enabled receives email
- ✅ User with notifications disabled does not receive email
- ✅ Only visible and published comics trigger notifications
- ✅ Failed notifications are retried
- ✅ Email content renders correctly with comic details

## Troubleshooting

### Common Issues

1. **Notifications not sending**
   - Check user has `email_notifications = true` and `new_releases_notifications = true`
   - Verify comic is `is_visible = true` and has `published_at` set
   - Ensure queue worker is running: `php artisan queue:work`

2. **Email not delivered**
   - Check mail configuration in `.env`
   - Verify mail service credentials
   - Check `failed_jobs` table for failed attempts

3. **Queue not processing**
   - Run `php artisan queue:work` manually
   - Check queue connection in `config/queue.php`
   - Verify database queue tables exist

### Debug Commands
```bash
# Check notification statistics
curl -H "Authorization: Bearer {admin-token}" http://localhost/api/admin/notifications/statistics

# Check queue status
php artisan queue:monitor

# Clear failed jobs
php artisan queue:flush
```

## Future Enhancements

- [ ] Unsubscribe links in emails
- [ ] Digest notifications (weekly/monthly summaries)
- [ ] Push notifications for mobile apps
- [ ] Customizable notification templates
- [ ] A/B testing for email content
- [ ] Analytics on email open rates and click-through rates