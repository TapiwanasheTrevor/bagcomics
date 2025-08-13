<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comic;
use App\Services\ComicNotificationService;
use App\Jobs\SendNewComicNotifications;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ComicNotificationController extends Controller
{
    public function __construct(
        private ComicNotificationService $notificationService
    ) {}

    /**
     * Manually trigger notifications for a specific comic
     */
    public function triggerNotifications(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'force' => 'boolean'
        ]);

        $force = $request->boolean('force', false);

        // Check if comic is suitable for notifications
        if (!$force && (!$comic->is_visible || !$comic->published_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Comic must be visible and published to send notifications. Use force=true to override.',
                'comic' => [
                    'id' => $comic->id,
                    'title' => $comic->title,
                    'is_visible' => $comic->is_visible,
                    'published_at' => $comic->published_at
                ]
            ], 400);
        }

        // Dispatch notification job
        SendNewComicNotifications::dispatch($comic);

        return response()->json([
            'success' => true,
            'message' => 'Notifications have been queued for sending.',
            'comic' => [
                'id' => $comic->id,
                'title' => $comic->title,
                'slug' => $comic->slug
            ]
        ]);
    }

    /**
     * Get notification statistics
     */
    public function getStatistics(): JsonResponse
    {
        $stats = $this->notificationService->getNotificationStatistics();

        return response()->json([
            'success' => true,
            'statistics' => $stats
        ]);
    }

    /**
     * Get notification recipients
     */
    public function getRecipients(): JsonResponse
    {
        $recipients = $this->notificationService->getNotificationRecipients();

        return response()->json([
            'success' => true,
            'recipients_count' => $recipients->count(),
            'recipients' => $recipients->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'preferences' => $user->getPreferences()->getNotificationPreferences()
                ];
            })
        ]);
    }

    /**
     * Send a test notification to a specific user
     */
    public function sendTestNotification(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'comic_id' => 'required|exists:comics,id'
        ]);

        $user = \App\Models\User::find($request->user_id);
        $comic = Comic::find($request->comic_id);

        $success = $this->notificationService->sendTestNotification($user, $comic);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Test notification sent successfully.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ],
                'comic' => [
                    'id' => $comic->id,
                    'title' => $comic->title
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to send test notification. Check user preferences or logs.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'preferences' => $user->getPreferences()->getNotificationPreferences()
            ]
        ], 400);
    }
}