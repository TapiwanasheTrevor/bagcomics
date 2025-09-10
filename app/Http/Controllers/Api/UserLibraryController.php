<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comic;
use App\Models\UserLibrary;
use App\Models\UserPreferences;
use App\Services\LibrarySyncService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class UserLibraryController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->library()
            ->with(['comic' => function ($query) {
                $query->where('is_visible', true);
            }]);

        // Apply filters
        if ($request->filled('access_type')) {
            $query->where('access_type', $request->access_type);
        }

        if ($request->filled('is_favorite')) {
            $query->where('is_favorite', $request->boolean('is_favorite'));
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        $library = $query->paginate($request->get('per_page', 12));

        return response()->json([
            'data' => $library->items(),
            'pagination' => [
                'current_page' => $library->currentPage(),
                'last_page' => $library->lastPage(),
                'per_page' => $library->perPage(),
                'total' => $library->total(),
            ]
        ]);
    }

    public function addToLibrary(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'access_type' => ['required', Rule::in(['free', 'purchased', 'subscription'])],
            'purchase_price' => 'nullable|numeric|min:0',
        ]);

        if (!$comic->is_visible) {
            return response()->json(['message' => 'Comic not found'], 404);
        }

        $libraryEntry = UserLibrary::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'comic_id' => $comic->id,
            ],
            [
                'access_type' => $request->access_type,
                'purchase_price' => $request->purchase_price,
                'purchased_at' => $request->access_type === 'purchased' ? now() : null,
            ]
        );

        // Increment reader count if this is a new addition
        if ($libraryEntry->wasRecentlyCreated) {
            $comic->incrementReaderCount();
        }

        return response()->json([
            'message' => 'Comic added to library successfully',
            'library_entry' => $libraryEntry->load('comic')
        ]);
    }

    public function removeFromLibrary(Request $request, Comic $comic): JsonResponse
    {
        $libraryEntry = $request->user()->library()
            ->where('comic_id', $comic->id)
            ->first();

        if (!$libraryEntry) {
            return response()->json(['message' => 'Comic not found in library'], 404);
        }

        $libraryEntry->delete();

        return response()->json(['message' => 'Comic removed from library successfully']);
    }

    public function toggleFavorite(Request $request, Comic $comic): JsonResponse
    {
        $libraryEntry = $request->user()->library()
            ->where('comic_id', $comic->id)
            ->first();

        if (!$libraryEntry) {
            // If comic is not in library, add it first with appropriate access type
            $accessType = 'free'; // Default to free
            
            // Check if comic is free or if user has purchased it
            if ($comic->is_free) {
                $accessType = 'free';
            } else {
                // Check if user has purchased this comic
                $purchase = $request->user()->purchases()
                    ->where('comic_id', $comic->id)
                    ->where('status', 'completed')
                    ->first();
                
                if ($purchase) {
                    $accessType = 'purchased';
                }
            }

            // Create library entry
            $libraryEntry = UserLibrary::create([
                'user_id' => $request->user()->id,
                'comic_id' => $comic->id,
                'access_type' => $accessType,
                'is_favorite' => true, // Set as favorite since user is trying to favorite it
                'purchase_price' => $comic->price ?? 0,
                'purchased_at' => $accessType === 'purchased' ? now() : null,
            ]);

            // Increment reader count for new addition
            $comic->incrementReaderCount();

            return response()->json([
                'message' => 'Comic added to library and favorited',
                'is_favorite' => true,
                'newly_added' => true
            ]);
        }

        $libraryEntry->is_favorite = !$libraryEntry->is_favorite;
        $libraryEntry->save();

        return response()->json([
            'message' => 'Favorite status updated successfully',
            'is_favorite' => $libraryEntry->is_favorite,
            'newly_added' => false
        ]);
    }

    public function setRating(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000',
        ]);

        $libraryEntry = $request->user()->library()
            ->where('comic_id', $comic->id)
            ->first();

        if (!$libraryEntry) {
            return response()->json(['message' => 'Comic not found in library'], 404);
        }

        $libraryEntry->setRating($request->rating, $request->review);

        // Update comic's average rating
        $comic->updateRating();

        return response()->json([
            'message' => 'Rating updated successfully',
            'rating' => $libraryEntry->rating,
            'review' => $libraryEntry->review
        ]);
    }

    public function favorites(Request $request): JsonResponse
    {
        $favorites = $request->user()->library()
            ->where('is_favorite', true)
            ->with(['comic' => function ($query) {
                $query->where('is_visible', true);
            }])
            ->paginate($request->get('per_page', 12));

        return response()->json([
            'data' => $favorites->items(),
            'pagination' => [
                'current_page' => $favorites->currentPage(),
                'last_page' => $favorites->lastPage(),
                'per_page' => $favorites->perPage(),
                'total' => $favorites->total(),
            ]
        ]);
    }

    public function recentlyAdded(Request $request): JsonResponse
    {
        $recent = $request->user()->library()
            ->with(['comic' => function ($query) {
                $query->where('is_visible', true);
            }])
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();

        return response()->json($recent);
    }

    public function statistics(Request $request): JsonResponse
    {
        $statistics = $request->user()->getReadingStatistics();
        return response()->json($statistics);
    }

    public function analytics(Request $request): JsonResponse
    {
        $analytics = $request->user()->getLibraryAnalytics();
        return response()->json($analytics);
    }

    public function advancedFilter(Request $request): JsonResponse
    {
        $request->validate([
            'genre' => 'nullable|string',
            'publisher' => 'nullable|string',
            'rating_min' => 'nullable|integer|min:1|max:5',
            'rating_max' => 'nullable|integer|min:1|max:5',
            'completion_status' => 'nullable|in:unread,reading,completed',
            'reading_time_min' => 'nullable|integer|min:0',
            'reading_time_max' => 'nullable|integer|min:0',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'sort_by' => 'nullable|in:last_read,rating,progress,reading_time,date_added',
            'sort_direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = $request->user()->library()
            ->with(['comic', 'progress']);

        // Apply filters
        if ($request->filled('genre')) {
            $query->byGenre($request->genre);
        }

        if ($request->filled('publisher')) {
            $query->byPublisher($request->publisher);
        }

        if ($request->filled('rating_min') && $request->filled('rating_max')) {
            $query->byRatingRange($request->rating_min, $request->rating_max);
        } elseif ($request->filled('rating_min')) {
            $query->where('rating', '>=', $request->rating_min);
        } elseif ($request->filled('rating_max')) {
            $query->where('rating', '<=', $request->rating_max);
        }

        if ($request->filled('completion_status')) {
            $query->byCompletionStatus($request->completion_status);
        }

        if ($request->filled('reading_time_min')) {
            $maxTime = $request->filled('reading_time_max') ? $request->reading_time_max : null;
            $query->byReadingTime($request->reading_time_min, $maxTime);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->byDateRange(
                Carbon::parse($request->date_from),
                Carbon::parse($request->date_to)
            );
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'date_added');
        $sortDirection = $request->get('sort_direction', 'desc');

        switch ($sortBy) {
            case 'last_read':
                $query->orderByLastRead($sortDirection);
                break;
            case 'rating':
                $query->orderByRating($sortDirection);
                break;
            case 'progress':
                $query->orderByProgress($sortDirection);
                break;
            case 'reading_time':
                $query->orderByReadingTime($sortDirection);
                break;
            default:
                $query->orderBy('created_at', $sortDirection);
        }

        $library = $query->paginate($request->get('per_page', 12));

        return response()->json([
            'data' => $library->items(),
            'pagination' => [
                'current_page' => $library->currentPage(),
                'last_page' => $library->lastPage(),
                'per_page' => $library->perPage(),
                'total' => $library->total(),
            ],
            'filters_applied' => $request->only([
                'genre', 'publisher', 'rating_min', 'rating_max', 
                'completion_status', 'reading_time_min', 'reading_time_max',
                'date_from', 'date_to'
            ]),
        ]);
    }

    public function updateReadingTime(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'reading_time_seconds' => 'required|integer|min:1',
        ]);

        $libraryEntry = $request->user()->library()
            ->where('comic_id', $comic->id)
            ->first();

        if (!$libraryEntry) {
            return response()->json(['message' => 'Comic not found in library'], 404);
        }

        $libraryEntry->addReadingTime($request->reading_time_seconds);
        $libraryEntry->updateLastAccessed();

        return response()->json([
            'message' => 'Reading time updated successfully',
            'total_reading_time' => $libraryEntry->total_reading_time,
            'formatted_time' => $libraryEntry->getReadingTimeFormatted(),
        ]);
    }

    public function updateProgress(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'completion_percentage' => 'required|numeric|min:0|max:100',
        ]);

        $libraryEntry = $request->user()->library()
            ->where('comic_id', $comic->id)
            ->first();

        if (!$libraryEntry) {
            return response()->json(['message' => 'Comic not found in library'], 404);
        }

        $libraryEntry->updateCompletionPercentage($request->completion_percentage);
        $libraryEntry->updateLastAccessed();

        return response()->json([
            'message' => 'Progress updated successfully',
            'completion_percentage' => $libraryEntry->completion_percentage,
            'is_completed' => $libraryEntry->isCompleted(),
        ]);
    }

    public function syncLibrary(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => 'required|string|max:255',
            'last_sync' => 'nullable|date',
            'sync_data' => 'nullable|array',
        ]);

        $syncService = app(LibrarySyncService::class);
        $user = $request->user();
        $lastSync = $request->last_sync ? Carbon::parse($request->last_sync) : null;

        if ($request->has('sync_data')) {
            // Upload sync data from device
            $result = $syncService->syncUserLibrary(
                $user, 
                $request->sync_data, 
                $request->device_id
            );
            
            return response()->json([
                'message' => 'Library synced successfully',
                'sync_result' => $result,
            ]);
        } else {
            // Download sync data to device
            $syncData = $syncService->getUserSyncData($user, $lastSync);
            
            return response()->json([
                'message' => 'Sync data retrieved successfully',
                'sync_data' => $syncData,
                'needs_sync' => $syncService->needsSync($user, $lastSync),
            ]);
        }
    }

    public function readingHabits(Request $request): JsonResponse
    {
        $habits = $request->user()->getReadingHabitsAnalysis();
        return response()->json($habits);
    }

    public function libraryHealth(Request $request): JsonResponse
    {
        $health = $request->user()->getLibraryHealthMetrics();
        return response()->json($health);
    }

    public function readingGoals(Request $request): JsonResponse
    {
        $goals = $request->user()->getReadingGoals();
        return response()->json($goals);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $request->validate([
            'reading_view_mode' => 'nullable|in:single,continuous,dual',
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

        $preferences = $request->user()->getPreferences();
        $preferences->updatePreferences($request->only([
            'reading_view_mode', 'reading_direction', 'reading_zoom_level',
            'auto_hide_controls', 'control_hide_delay', 'theme',
            'reduce_motion', 'high_contrast', 'email_notifications',
            'new_releases_notifications', 'reading_reminders'
        ]));

        return response()->json([
            'message' => 'Preferences updated successfully',
            'preferences' => $preferences->fresh(),
        ]);
    }

    public function getPreferences(Request $request): JsonResponse
    {
        $preferences = $request->user()->getPreferences();
        
        return response()->json([
            'preferences' => $preferences,
            'reading_preferences' => $preferences->getReadingPreferences(),
            'accessibility_preferences' => $preferences->getAccessibilityPreferences(),
            'notification_preferences' => $preferences->getNotificationPreferences(),
        ]);
    }

    public function resetPreferences(Request $request): JsonResponse
    {
        $user = $request->user();
        $preferences = $user->preferences;
        
        if (!$preferences) {
            $preferences = $user->preferences()->create(UserPreferences::getDefaults());
        } else {
            $preferences->resetToDefaults();
        }

        return response()->json([
            'message' => 'Preferences reset to defaults successfully',
            'preferences' => $preferences->fresh(),
        ]);
    }

    public function exportLibrary(Request $request): JsonResponse
    {
        $request->validate([
            'format' => 'nullable|in:json,csv',
            'include_progress' => 'nullable|in:true,false,1,0',
            'include_reviews' => 'nullable|in:true,false,1,0',
        ]);

        $format = $request->get('format', 'json');
        $includeProgress = $request->boolean('include_progress');
        $includeReviews = $request->boolean('include_reviews');

        $query = $request->user()->library()->with('comic');
        
        if ($includeProgress) {
            $query->with('progress');
        }

        $library = $query->get();

        $exportData = $library->map(function ($entry) use ($includeProgress, $includeReviews) {
            $data = [
                'comic_title' => $entry->comic->title,
                'comic_author' => $entry->comic->author,
                'comic_publisher' => $entry->comic->publisher,
                'comic_genre' => $entry->comic->genre,
                'access_type' => $entry->access_type,
                'purchase_price' => $entry->purchase_price,
                'purchased_at' => $entry->purchased_at?->toDateString(),
                'is_favorite' => $entry->is_favorite,
                'rating' => $entry->rating,
                'completion_percentage' => $entry->completion_percentage,
                'total_reading_time_minutes' => round(($entry->total_reading_time ?? 0) / 60),
                'last_accessed_at' => $entry->last_accessed_at?->toDateString(),
                'added_to_library_at' => $entry->created_at->toDateString(),
            ];

            if ($includeReviews && $entry->review) {
                $data['review'] = $entry->review;
            }

            if ($includeProgress && $entry->progress) {
                $data['current_page'] = $entry->progress->current_page;
                $data['total_pages'] = $entry->progress->total_pages;
                $data['last_read_at'] = $entry->progress->last_read_at?->toDateString();
            }

            return $data;
        });

        if ($format === 'csv') {
            $filename = 'library_export_' . now()->format('Y-m-d_H-i-s') . '.csv';
            
            return response()->json([
                'message' => 'Library exported successfully',
                'format' => 'csv',
                'filename' => $filename,
                'data' => $exportData,
                'download_url' => route('api.library.download-export', ['filename' => $filename]),
            ]);
        }

        return response()->json([
            'message' => 'Library exported successfully',
            'format' => 'json',
            'data' => $exportData,
            'total_comics' => $exportData->count(),
            'exported_at' => now()->toISOString(),
        ]);
    }
}
