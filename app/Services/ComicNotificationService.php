<?php

namespace App\Services;

use App\Models\Comic;
use App\Models\User;
use App\Notifications\NewComicReleased;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class ComicNotificationService
{
    /**
     * Send new comic release notifications to subscribed users
     */
    public function sendNewComicNotifications(Comic $comic): int
    {
        // Get all users who want new release notifications
        $users = User::whereHas('preferences', function ($query) {
            $query->where('email_notifications', true)
                  ->where('new_releases_notifications', true);
        })->get();

        if ($users->isEmpty()) {
            Log::info('No users subscribed to new comic notifications');
            return 0;
        }

        // Send notifications
        Notification::send($users, new NewComicReleased($comic));

        Log::info('New comic notifications sent', [
            'comic_id' => $comic->id,
            'comic_title' => $comic->title,
            'recipients_count' => $users->count()
        ]);

        return $users->count();
    }

    /**
     * Send notification to a specific user
     */
    public function sendNewComicNotificationToUser(User $user, Comic $comic): bool
    {
        $preferences = $user->getPreferences();
        
        if (!$preferences->wantsNewReleaseNotifications()) {
            return false;
        }

        $user->notify(new NewComicReleased($comic));

        Log::info('New comic notification sent to user', [
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'comic_title' => $comic->title
        ]);

        return true;
    }

    /**
     * Get statistics on notification preferences
     */
    public function getNotificationStatistics(): array
    {
        $totalUsers = User::count();
        $emailEnabledUsers = User::whereHas('preferences', function ($query) {
            $query->where('email_notifications', true);
        })->count();
        
        $newReleasesSubscribers = User::whereHas('preferences', function ($query) {
            $query->where('email_notifications', true)
                  ->where('new_releases_notifications', true);
        })->count();

        return [
            'total_users' => $totalUsers,
            'email_enabled_users' => $emailEnabledUsers,
            'new_releases_subscribers' => $newReleasesSubscribers,
            'subscription_rate' => $totalUsers > 0 ? round(($newReleasesSubscribers / $totalUsers) * 100, 2) : 0
        ];
    }

    /**
     * Send a test notification to verify the system works
     */
    public function sendTestNotification(User $user, Comic $comic): bool
    {
        try {
            $user->notify(new NewComicReleased($comic));
            
            Log::info('Test notification sent successfully', [
                'user_id' => $user->id,
                'comic_id' => $comic->id
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send test notification', [
                'user_id' => $user->id,
                'comic_id' => $comic->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Get users who should receive new comic notifications
     */
    public function getNotificationRecipients(): \Illuminate\Database\Eloquent\Collection
    {
        return User::whereHas('preferences', function ($query) {
            $query->where('email_notifications', true)
                  ->where('new_releases_notifications', true);
        })->get();
    }

    /**
     * Update user notification preferences
     */
    public function updateUserNotificationPreferences(User $user, array $preferences): bool
    {
        try {
            $userPrefs = $user->getPreferences();
            
            $validPreferences = array_intersect_key($preferences, [
                'email_notifications' => true,
                'new_releases_notifications' => true,
                'reading_reminders' => true,
            ]);
            
            $userPrefs->updatePreferences($validPreferences);
            
            Log::info('User notification preferences updated', [
                'user_id' => $user->id,
                'preferences' => $validPreferences
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update user notification preferences', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
}