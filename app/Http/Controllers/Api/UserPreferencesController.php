<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ComicNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserPreferencesController extends Controller
{
    public function __construct(
        private ComicNotificationService $notificationService
    ) {}

    /**
     * Get user preferences
     */
    public function getPreferences(): JsonResponse
    {
        $user = Auth::user();
        $preferences = $user->getPreferences();

        return response()->json([
            'success' => true,
            'preferences' => [
                'reading' => $preferences->getReadingPreferences(),
                'accessibility' => $preferences->getAccessibilityPreferences(),
                'notifications' => $preferences->getNotificationPreferences()
            ]
        ]);
    }

    /**
     * Update user preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $request->validate([
            'reading_view_mode' => 'nullable|in:single,double,scroll',
            'reading_direction' => 'nullable|in:ltr,rtl',
            'reading_zoom_level' => 'nullable|numeric|min:0.5|max:3.0',
            'auto_hide_controls' => 'nullable|boolean',
            'control_hide_delay' => 'nullable|integer|min:1000|max:10000',
            'theme' => 'nullable|in:light,dark,auto',
            'reduce_motion' => 'nullable|boolean',
            'high_contrast' => 'nullable|boolean',
            'email_notifications' => 'nullable|boolean',
            'new_releases_notifications' => 'nullable|boolean',
            'reading_reminders' => 'nullable|boolean',
        ]);

        $user = Auth::user();
        $preferences = $user->getPreferences();

        // Get only the preferences that were provided in the request
        $updateData = array_filter($request->only([
            'reading_view_mode',
            'reading_direction', 
            'reading_zoom_level',
            'auto_hide_controls',
            'control_hide_delay',
            'theme',
            'reduce_motion',
            'high_contrast',
            'email_notifications',
            'new_releases_notifications',
            'reading_reminders',
        ]), function($value) {
            return $value !== null;
        });

        // Update preferences
        $success = $this->notificationService->updateUserNotificationPreferences($user, $updateData);

        if ($success) {
            // Reload preferences
            $preferences = $user->refresh()->getPreferences();

            return response()->json([
                'success' => true,
                'message' => 'Preferences updated successfully.',
                'preferences' => [
                    'reading' => $preferences->getReadingPreferences(),
                    'accessibility' => $preferences->getAccessibilityPreferences(),
                    'notifications' => $preferences->getNotificationPreferences()
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to update preferences.'
        ], 500);
    }

    /**
     * Reset preferences to defaults
     */
    public function resetPreferences(): JsonResponse
    {
        $user = Auth::user();
        $preferences = $user->getPreferences();
        
        $preferences->resetToDefaults();

        return response()->json([
            'success' => true,
            'message' => 'Preferences reset to defaults.',
            'preferences' => [
                'reading' => $preferences->getReadingPreferences(),
                'accessibility' => $preferences->getAccessibilityPreferences(),
                'notifications' => $preferences->getNotificationPreferences()
            ]
        ]);
    }

    /**
     * Get notification preferences only
     */
    public function getNotificationPreferences(): JsonResponse
    {
        $user = Auth::user();
        $preferences = $user->getPreferences();

        return response()->json([
            'success' => true,
            'notifications' => $preferences->getNotificationPreferences(),
            'can_receive_notifications' => $preferences->wantsEmailNotifications(),
            'can_receive_new_releases' => $preferences->wantsNewReleaseNotifications()
        ]);
    }

    /**
     * Update notification preferences only
     */
    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $request->validate([
            'email_notifications' => 'required|boolean',
            'new_releases_notifications' => 'required|boolean',
            'reading_reminders' => 'required|boolean',
        ]);

        $user = Auth::user();
        $updateData = $request->only([
            'email_notifications',
            'new_releases_notifications', 
            'reading_reminders'
        ]);

        $success = $this->notificationService->updateUserNotificationPreferences($user, $updateData);

        if ($success) {
            $preferences = $user->refresh()->getPreferences();
            
            return response()->json([
                'success' => true,
                'message' => 'Notification preferences updated successfully.',
                'notifications' => $preferences->getNotificationPreferences(),
                'can_receive_notifications' => $preferences->wantsEmailNotifications(),
                'can_receive_new_releases' => $preferences->wantsNewReleaseNotifications()
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to update notification preferences.'
        ], 500);
    }
}