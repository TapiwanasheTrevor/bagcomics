<?php

namespace App\Console\Commands;

use App\Models\Comic;
use App\Models\User;
use App\Services\ComicNotificationService;
use Illuminate\Console\Command;

class TestComicNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'comic:test-notifications 
                            {--comic-id= : ID of the comic to test with}
                            {--user-email= : Email of the user to test with}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the comic notification system by sending a test email';

    /**
     * Execute the console command.
     */
    public function handle(ComicNotificationService $notificationService)
    {
        $this->info('🧪 Testing Comic Notification System...');
        $this->newLine();

        // Get or create test comic
        $comicId = $this->option('comic-id');
        if ($comicId) {
            $comic = Comic::find($comicId);
            if (!$comic) {
                $this->error("Comic with ID {$comicId} not found!");
                return 1;
            }
        } else {
            $comic = Comic::where('is_visible', true)->first();
            if (!$comic) {
                $this->error('No visible comics found for testing!');
                return 1;
            }
        }

        $this->info("📚 Using comic: {$comic->title} (ID: {$comic->id})");

        // Get test user
        $userEmail = $this->option('user-email');
        if ($userEmail) {
            $user = User::where('email', $userEmail)->first();
            if (!$user) {
                $this->error("User with email {$userEmail} not found!");
                return 1;
            }
        } else {
            // Find a user with notifications enabled
            $user = User::whereHas('preferences', function ($query) {
                $query->where('email_notifications', true)
                      ->where('new_releases_notifications', true);
            })->first();

            if (!$user) {
                $this->error('No users with notification preferences enabled found!');
                $this->info('Tip: Register a user and enable notifications in their preferences.');
                return 1;
            }
        }

        $this->info("👤 Using user: {$user->name} ({$user->email})");
        $this->newLine();

        // Check user preferences
        $preferences = $user->getPreferences();
        $this->info('📋 User Notification Preferences:');
        $this->info("   Email notifications: " . ($preferences->email_notifications ? '✅' : '❌'));
        $this->info("   New release notifications: " . ($preferences->new_releases_notifications ? '✅' : '❌'));
        $this->newLine();

        if (!$preferences->wantsNewReleaseNotifications()) {
            $this->warn('⚠️  User does not have new release notifications enabled!');
            $this->warn('The notification will not be sent due to user preferences.');
            return 1;
        }

        // Send test notification
        $this->info('📧 Sending test notification...');
        $success = $notificationService->sendTestNotification($user, $comic);

        if ($success) {
            $this->info('✅ Test notification sent successfully!');
            $this->info("📬 Check the email inbox for: {$user->email}");
        } else {
            $this->error('❌ Failed to send test notification!');
            $this->error('Check the logs for more details.');
            return 1;
        }

        $this->newLine();

        // Show notification statistics
        $stats = $notificationService->getNotificationStatistics();
        $this->info('📊 Notification Statistics:');
        $this->info("   Total users: {$stats['total_users']}");
        $this->info("   Email enabled users: {$stats['email_enabled_users']}");
        $this->info("   New release subscribers: {$stats['new_releases_subscribers']}");
        $this->info("   Subscription rate: {$stats['subscription_rate']}%");

        $this->newLine();
        $this->info('🎉 Test completed successfully!');

        return 0;
    }
}
