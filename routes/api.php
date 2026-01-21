<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ComicSearchController;
use App\Http\Controllers\Api\ReadingProgressController;
use App\Http\Controllers\Api\ComicController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AnalyticsController;

// Apply rate limiting to all API routes
Route::middleware(['api.rate_limit:120,1'])->group(function () {
    
    // Public routes (higher rate limit for authenticated users)
    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('auth:web');

    // Comics API Routes
    Route::prefix('comics')->group(function () {
        Route::get('/', [ComicController::class, 'index']);
        Route::get('/featured', [ComicController::class, 'featured']);
        Route::get('/new-releases', [ComicController::class, 'newReleases']);
        Route::get('/genres', [ComicController::class, 'genres']);
        Route::get('/tags', [ComicController::class, 'tags']);
        Route::get('/{comic}', [ComicController::class, 'show']);
        Route::post('/{comic}/track-view', [ComicController::class, 'trackView']);
    });

    // Comic Search API Routes
    Route::prefix('search')->group(function () {
        Route::get('/comics', [ComicSearchController::class, 'search']);
        Route::get('/suggestions', [ComicSearchController::class, 'suggestions']);
        Route::get('/autocomplete', [ComicSearchController::class, 'autocomplete']);
        Route::get('/filter-options', [ComicSearchController::class, 'filterOptions']);
        Route::get('/popular-terms', [ComicSearchController::class, 'popularTerms']);
        Route::get('/analytics', [ComicSearchController::class, 'analytics'])->middleware('auth:sanctum');
    });

    // Public Social Sharing API Routes
    Route::prefix('social')->group(function () {
        Route::post('/comics/{comic}/share', [App\Http\Controllers\Api\SocialSharingController::class, 'shareComic'])->middleware('optional.auth');
        Route::get('/comics/{comic}/metadata', [App\Http\Controllers\Api\SocialSharingController::class, 'getSharingMetadata']);
        Route::get('/comics/{comic}/stats', [App\Http\Controllers\Api\SocialSharingController::class, 'getComicSharingStats']);
    });

    // Authenticated routes (stricter rate limiting)
    Route::middleware(['auth:web', 'api.rate_limit:300,1'])->group(function () {
        
        // Reading Progress API Routes
        Route::prefix('comics/{comic}/progress')->group(function () {
            Route::get('/', [ReadingProgressController::class, 'getProgress']);
            Route::post('/update', [ReadingProgressController::class, 'updateProgress']);
            Route::post('/session/start', [ReadingProgressController::class, 'startSession']);
            Route::post('/session/end', [ReadingProgressController::class, 'endSession']);
            Route::post('/session/pause', [ReadingProgressController::class, 'addPauseTime']);
            Route::post('/bookmarks', [ReadingProgressController::class, 'addBookmark']);
            Route::delete('/bookmarks', [ReadingProgressController::class, 'removeBookmark']);
            Route::get('/bookmarks', [ReadingProgressController::class, 'getBookmarks']);
            Route::post('/preferences', [ReadingProgressController::class, 'updatePreferences']);
            Route::post('/sync-bookmarks', [ReadingProgressController::class, 'synchronizeBookmarks']);
        });

        // User Reading Statistics
        Route::get('/user/reading-statistics', [ReadingProgressController::class, 'getUserStatistics']);

        // Payment API Routes
        Route::prefix('payments')->group(function () {
            Route::post('/comics/{comic:slug}/intent', [PaymentController::class, 'createPaymentIntent']);
            Route::post('/confirm', [PaymentController::class, 'confirmPayment']);
            Route::post('/process', [PaymentController::class, 'processPayment']);
            Route::get('/history', [PaymentController::class, 'history']);
            Route::get('/{payment}', [PaymentController::class, 'show']);
            Route::post('/{payment}/refund', [PaymentController::class, 'requestRefund']);
            Route::get('/statistics/user', [PaymentController::class, 'statistics']);
        });

        // Analytics API Routes (User-specific)
        Route::prefix('analytics')->group(function () {
            Route::get('/reading-behavior', [AnalyticsController::class, 'readingBehavior']);
        });

        // Social Sharing API Routes (User-specific)
        Route::prefix('social')->group(function () {
            Route::get('/history', [App\Http\Controllers\Api\SocialSharingController::class, 'getSharingHistory']);
            Route::get('/platforms', [App\Http\Controllers\Api\SocialSharingController::class, 'getAvailablePlatforms']);
            Route::post('/connect', [App\Http\Controllers\Api\SocialSharingController::class, 'connectSocialAccount']);
            Route::delete('/disconnect', [App\Http\Controllers\Api\SocialSharingController::class, 'disconnectSocialAccount']);
        });

        // Achievements API Routes
        Route::prefix('achievements')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\AchievementsController::class, 'getUserAchievements']);
            Route::get('/stats', [App\Http\Controllers\Api\AchievementsController::class, 'getReadingStats']);
            Route::get('/types', [App\Http\Controllers\Api\AchievementsController::class, 'getAchievementTypes']);
        });

        // Enhanced User Library API Routes
        Route::prefix('library')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\UserLibraryController::class, 'index']);
            Route::get('/statistics', [App\Http\Controllers\Api\UserLibraryController::class, 'statistics']);
            Route::get('/analytics', [App\Http\Controllers\Api\UserLibraryController::class, 'analytics']);
            Route::get('/filter', [App\Http\Controllers\Api\UserLibraryController::class, 'advancedFilter']);
            Route::get('/favorites', [App\Http\Controllers\Api\UserLibraryController::class, 'favorites']);
            Route::get('/recent', [App\Http\Controllers\Api\UserLibraryController::class, 'recentlyAdded']);
            Route::post('/sync', [App\Http\Controllers\Api\UserLibraryController::class, 'syncLibrary']);
            
            // Enhanced analytics and insights
            Route::get('/reading-habits', [App\Http\Controllers\Api\UserLibraryController::class, 'readingHabits']);
            Route::get('/health', [App\Http\Controllers\Api\UserLibraryController::class, 'libraryHealth']);
            Route::get('/goals', [App\Http\Controllers\Api\UserLibraryController::class, 'readingGoals']);
            Route::get('/export', [App\Http\Controllers\Api\UserLibraryController::class, 'exportLibrary']);
            
            // User preferences management
            Route::get('/preferences', [App\Http\Controllers\Api\UserLibraryController::class, 'getPreferences']);
            Route::post('/preferences', [App\Http\Controllers\Api\UserLibraryController::class, 'updatePreferences']);
            Route::post('/preferences/reset', [App\Http\Controllers\Api\UserLibraryController::class, 'resetPreferences']);
            
            Route::prefix('comics/{comic:slug}')->group(function () {
                Route::post('/add', [App\Http\Controllers\Api\UserLibraryController::class, 'addToLibrary']);
                Route::delete('/remove', [App\Http\Controllers\Api\UserLibraryController::class, 'removeFromLibrary']);
                Route::post('/favorite', [App\Http\Controllers\Api\UserLibraryController::class, 'toggleFavorite']);
                Route::post('/rating', [App\Http\Controllers\Api\UserLibraryController::class, 'setRating']);
                Route::post('/reading-time', [App\Http\Controllers\Api\UserLibraryController::class, 'updateReadingTime']);
                Route::post('/progress', [App\Http\Controllers\Api\UserLibraryController::class, 'updateProgress']);
            });
        });

        // User Preferences API Routes
        Route::prefix('preferences')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\UserPreferencesController::class, 'getPreferences']);
            Route::put('/', [App\Http\Controllers\Api\UserPreferencesController::class, 'updatePreferences']);
            Route::post('/reset', [App\Http\Controllers\Api\UserPreferencesController::class, 'resetPreferences']);
            Route::get('/notifications', [App\Http\Controllers\Api\UserPreferencesController::class, 'getNotificationPreferences']);
            Route::put('/notifications', [App\Http\Controllers\Api\UserPreferencesController::class, 'updateNotificationPreferences']);
        });

        // Reviews API Routes
        Route::prefix('reviews')->group(function () {
            Route::get('/comics/{comic}', [App\Http\Controllers\Api\ReviewController::class, 'index']);
            Route::post('/comics/{comic}', [App\Http\Controllers\Api\ReviewController::class, 'store']);
            Route::get('/{review}', [App\Http\Controllers\Api\ReviewController::class, 'show']);
            Route::put('/{review}', [App\Http\Controllers\Api\ReviewController::class, 'update']);
            Route::delete('/{review}', [App\Http\Controllers\Api\ReviewController::class, 'destroy']);
            Route::post('/{review}/vote', [App\Http\Controllers\Api\ReviewController::class, 'vote']);
            Route::delete('/{review}/vote', [App\Http\Controllers\Api\ReviewController::class, 'removeVote']);
            Route::post('/{review}/report', [App\Http\Controllers\Api\ReviewController::class, 'report']);
        });

        // Recommendation API Routes
        Route::prefix('recommendations')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\RecommendationController::class, 'getRecommendations']);
            Route::get('/trending', [App\Http\Controllers\Api\RecommendationController::class, 'getTrending']);
            Route::get('/comics/{comic}/similar', [App\Http\Controllers\Api\RecommendationController::class, 'getSimilarComics']);
            Route::post('/track', [App\Http\Controllers\Api\RecommendationController::class, 'trackInteraction']);
            Route::get('/stats', [App\Http\Controllers\Api\RecommendationController::class, 'getStats']);
        });

        // Gamification API Routes
        Route::prefix('gamification')->group(function () {
            Route::get('/streaks', [App\Http\Controllers\Api\GamificationController::class, 'getStreaks']);
            Route::get('/goals', [App\Http\Controllers\Api\GamificationController::class, 'getGoals']);
            Route::post('/goals', [App\Http\Controllers\Api\GamificationController::class, 'createGoal']);
            Route::put('/goals/{goal}', [App\Http\Controllers\Api\GamificationController::class, 'updateGoal']);
            Route::delete('/goals/{goal}', [App\Http\Controllers\Api\GamificationController::class, 'deleteGoal']);
            Route::get('/goals/recommended', [App\Http\Controllers\Api\GamificationController::class, 'getRecommendedGoals']);
            Route::get('/stats', [App\Http\Controllers\Api\GamificationController::class, 'getStats']);
            Route::post('/track-activity', [App\Http\Controllers\Api\GamificationController::class, 'trackActivity']);
            Route::get('/types', [App\Http\Controllers\Api\GamificationController::class, 'getGoalTypes']);
        });

        // Reading Lists API Routes
        Route::prefix('lists')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\ReadingListController::class, 'index']);
            Route::get('/public', [App\Http\Controllers\Api\ReadingListController::class, 'publicLists']);
            Route::post('/', [App\Http\Controllers\Api\ReadingListController::class, 'create']);
            Route::get('/{list}', [App\Http\Controllers\Api\ReadingListController::class, 'show']);
            Route::put('/{list}', [App\Http\Controllers\Api\ReadingListController::class, 'update']);
            Route::delete('/{list}', [App\Http\Controllers\Api\ReadingListController::class, 'delete']);
            Route::post('/{list}/comics/{comic}', [App\Http\Controllers\Api\ReadingListController::class, 'addComic']);
            Route::delete('/{list}/comics/{comic}', [App\Http\Controllers\Api\ReadingListController::class, 'removeComic']);
            Route::post('/{list}/reorder', [App\Http\Controllers\Api\ReadingListController::class, 'reorderComics']);
            Route::post('/{list}/follow', [App\Http\Controllers\Api\ReadingListController::class, 'follow']);
            Route::delete('/{list}/follow', [App\Http\Controllers\Api\ReadingListController::class, 'unfollow']);
            Route::post('/{list}/like', [App\Http\Controllers\Api\ReadingListController::class, 'like']);
            Route::delete('/{list}/like', [App\Http\Controllers\Api\ReadingListController::class, 'unlike']);
            Route::post('/{list}/duplicate', [App\Http\Controllers\Api\ReadingListController::class, 'duplicate']);
        });

        // Social API Routes
        Route::prefix('social')->group(function () {
            Route::post('/users/{user}/follow', [App\Http\Controllers\Api\SocialController::class, 'followUser']);
            Route::delete('/users/{user}/follow', [App\Http\Controllers\Api\SocialController::class, 'unfollowUser']);
            Route::get('/users/{user}/followers', [App\Http\Controllers\Api\SocialController::class, 'getUserFollowers']);
            Route::get('/users/{user}/following', [App\Http\Controllers\Api\SocialController::class, 'getUserFollowing']);
            Route::get('/users/{user}/profile', [App\Http\Controllers\Api\SocialController::class, 'getUserProfile']);
            Route::get('/feed', [App\Http\Controllers\Api\SocialController::class, 'getActivityFeed']);
            Route::get('/suggestions', [App\Http\Controllers\Api\SocialController::class, 'getSuggestedUsers']);
        });

        // Achievement API Routes
        Route::prefix('achievements')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\AchievementController::class, 'getUserAchievements']);
            Route::get('/all', [App\Http\Controllers\Api\AchievementController::class, 'getAllAchievements']);
            Route::get('/unseen', [App\Http\Controllers\Api\AchievementController::class, 'getUnseenAchievements']);
            Route::post('/check', [App\Http\Controllers\Api\AchievementController::class, 'checkAchievements']);
            Route::post('/{achievement}/seen', [App\Http\Controllers\Api\AchievementController::class, 'markAchievementSeen']);
            Route::get('/categories', [App\Http\Controllers\Api\AchievementController::class, 'getAchievementCategories']);
        });
    });

    // Search and Discovery API Routes (Public)
    Route::prefix('comics')->group(function () {
        Route::get('/search', [App\Http\Controllers\Api\SearchController::class, 'search']);
        Route::get('/autocomplete', [App\Http\Controllers\Api\SearchController::class, 'autocomplete']);
        Route::get('/tags', [App\Http\Controllers\Api\SearchController::class, 'getTags']);
        Route::get('/genres', [App\Http\Controllers\Api\SearchController::class, 'getGenres']);
    });

    // Admin routes (more restrictive rate limiting)
    Route::middleware(['auth:web', 'can:access-admin', 'api.rate_limit:200,1'])->prefix('admin')->group(function () {
        
        // Platform Analytics (Admin only)
        Route::prefix('analytics')->group(function () {
            Route::get('/overview', [AnalyticsController::class, 'overview']);
            Route::get('/user-engagement', [AnalyticsController::class, 'userEngagement']);
            Route::get('/content-performance', [AnalyticsController::class, 'contentPerformance']);
            Route::get('/revenue', [AnalyticsController::class, 'revenue']);
            Route::get('/genre-popularity', [AnalyticsController::class, 'genrePopularity']);
            Route::get('/search-analytics', [AnalyticsController::class, 'searchAnalytics']);
            Route::get('/user-retention', [AnalyticsController::class, 'userRetention']);
            Route::get('/conversion-funnel', [AnalyticsController::class, 'conversionFunnel']);
            Route::post('/export', [AnalyticsController::class, 'export']);
        });
        
        // Payment Analytics
        Route::prefix('payments')->group(function () {
            Route::get('/analytics', [App\Http\Controllers\Api\Admin\PaymentAnalyticsController::class, 'analytics']);
            Route::get('/revenue', [App\Http\Controllers\Api\Admin\PaymentAnalyticsController::class, 'revenue']);
            Route::get('/transactions', [App\Http\Controllers\Api\Admin\PaymentAnalyticsController::class, 'transactions']);
            Route::get('/refunds', [App\Http\Controllers\Api\Admin\PaymentAnalyticsController::class, 'refunds']);
            Route::get('/subscriptions', [App\Http\Controllers\Api\Admin\PaymentAnalyticsController::class, 'subscriptions']);
            Route::get('/reports', [App\Http\Controllers\Api\Admin\PaymentAnalyticsController::class, 'reports']);
        });

        // Review Moderation
        Route::prefix('reviews')->group(function () {
            Route::get('/pending', [App\Http\Controllers\Api\Admin\ReviewModerationController::class, 'pending']);
            Route::get('/reported', [App\Http\Controllers\Api\Admin\ReviewModerationController::class, 'reported']);
            Route::post('/{review}/approve', [App\Http\Controllers\Api\Admin\ReviewModerationController::class, 'approve']);
            Route::post('/{review}/reject', [App\Http\Controllers\Api\Admin\ReviewModerationController::class, 'reject']);
            Route::post('/{review}/flag', [App\Http\Controllers\Api\Admin\ReviewModerationController::class, 'flag']);
            Route::delete('/{review}', [App\Http\Controllers\Api\Admin\ReviewModerationController::class, 'delete']);
            Route::get('/statistics', [App\Http\Controllers\Api\Admin\ReviewModerationController::class, 'statistics']);
        });
        
        // Comic Notification Admin Routes
        Route::prefix('notifications')->group(function () {
            Route::get('/statistics', [App\Http\Controllers\Api\Admin\ComicNotificationController::class, 'getStatistics']);
            Route::get('/recipients', [App\Http\Controllers\Api\Admin\ComicNotificationController::class, 'getRecipients']);
            Route::post('/test', [App\Http\Controllers\Api\Admin\ComicNotificationController::class, 'sendTestNotification']);
            Route::post('/comics/{comic}/trigger', [App\Http\Controllers\Api\Admin\ComicNotificationController::class, 'triggerNotifications']);
        });

        // CMS Admin API Routes
        Route::prefix('cms')->group(function () {
            // Content management
            Route::get('/content', [App\Http\Controllers\Api\Admin\CmsContentController::class, 'index']);
            Route::post('/content', [App\Http\Controllers\Api\Admin\CmsContentController::class, 'store']);
            Route::get('/content/{key}', [App\Http\Controllers\Api\Admin\CmsContentController::class, 'show']);
            Route::put('/content/{key}', [App\Http\Controllers\Api\Admin\CmsContentController::class, 'update']);
            Route::delete('/content/{key}', [App\Http\Controllers\Api\Admin\CmsContentController::class, 'destroy']);
            
            // Content workflow
            Route::post('/content/{key}/publish', [App\Http\Controllers\Api\Admin\CmsContentController::class, 'publish']);
            Route::post('/content/{key}/archive', [App\Http\Controllers\Api\Admin\CmsContentController::class, 'archive']);
            Route::post('/content/{key}/schedule', [App\Http\Controllers\Api\Admin\CmsContentController::class, 'schedule']);
            Route::post('/content/{key}/revert', [App\Http\Controllers\Api\Admin\CmsContentController::class, 'revertVersion']);
            Route::post('/content/bulk-status', [App\Http\Controllers\Api\Admin\CmsContentController::class, 'bulkUpdateStatus']);
            
            // Versioning
            Route::get('/content/{key}/versions', [App\Http\Controllers\Api\Admin\CmsContentController::class, 'versions']);
            Route::post('/content/{key}/versions/compare', [App\Http\Controllers\Api\Admin\CmsContentController::class, 'compareVersions']);
            
            // Analytics
            Route::get('/content/{key}/analytics', [App\Http\Controllers\Api\Admin\CmsContentController::class, 'analytics']);
            Route::get('/analytics/platform', [App\Http\Controllers\Api\Admin\CmsContentController::class, 'platformAnalytics']);
            Route::get('/analytics/engagement', [App\Http\Controllers\Api\Admin\CmsContentController::class, 'engagementMetrics']);
            Route::get('/analytics/users', [App\Http\Controllers\Api\Admin\CmsContentController::class, 'userActivity']);
            Route::get('/analytics/report', [App\Http\Controllers\Api\Admin\CmsContentController::class, 'analyticsReport']);
            Route::get('/analytics/trending', [App\Http\Controllers\Api\Admin\CmsContentController::class, 'trendingContent']);
            
            // Media management
            Route::get('/media', [App\Http\Controllers\Api\Admin\CmsMediaController::class, 'index']);
            Route::post('/media', [App\Http\Controllers\Api\Admin\CmsMediaController::class, 'store']);
            Route::get('/media/{asset}', [App\Http\Controllers\Api\Admin\CmsMediaController::class, 'show']);
            Route::put('/media/{asset}', [App\Http\Controllers\Api\Admin\CmsMediaController::class, 'update']);
            Route::delete('/media/{asset}', [App\Http\Controllers\Api\Admin\CmsMediaController::class, 'destroy']);
            Route::get('/media-stats', [App\Http\Controllers\Api\Admin\CmsMediaController::class, 'stats']);
            Route::post('/media/bulk-delete', [App\Http\Controllers\Api\Admin\CmsMediaController::class, 'bulkDelete']);
            
            // Workflow management
            Route::get('/workflow/dashboard', [App\Http\Controllers\Api\Admin\CmsWorkflowController::class, 'dashboard']);
            Route::get('/workflow/status/{status}', [App\Http\Controllers\Api\Admin\CmsWorkflowController::class, 'contentByStatus']);
            Route::get('/workflow/ready-to-publish', [App\Http\Controllers\Api\Admin\CmsWorkflowController::class, 'readyToPublish']);
            Route::post('/workflow/process-scheduled', [App\Http\Controllers\Api\Admin\CmsWorkflowController::class, 'processScheduled']);
            Route::get('/workflow/{key}/history', [App\Http\Controllers\Api\Admin\CmsWorkflowController::class, 'workflowHistory']);
            Route::post('/workflow/{key}/approve', [App\Http\Controllers\Api\Admin\CmsWorkflowController::class, 'approveContent']);
            Route::post('/workflow/{key}/reject', [App\Http\Controllers\Api\Admin\CmsWorkflowController::class, 'rejectContent']);
            
            // Version workflow
            Route::post('/workflow/{key}/versions', [App\Http\Controllers\Api\Admin\CmsWorkflowController::class, 'createVersion']);
            Route::post('/workflow/versions/{version}/publish', [App\Http\Controllers\Api\Admin\CmsWorkflowController::class, 'publishVersion']);
            Route::post('/workflow/versions/{version}/schedule', [App\Http\Controllers\Api\Admin\CmsWorkflowController::class, 'scheduleVersion']);
        });

        // Comic Upload Admin Routes (Cloudinary)
        Route::prefix('comics')->group(function () {
            Route::post('/create-from-directory', [App\Http\Controllers\Api\Admin\ComicUploadController::class, 'createFromDirectory']);
            Route::post('/{comic}/pages', [App\Http\Controllers\Api\Admin\ComicUploadController::class, 'uploadPages']);
            Route::post('/{comic}/cover', [App\Http\Controllers\Api\Admin\ComicUploadController::class, 'uploadCover']);
            Route::post('/{comic}/reorder-pages', [App\Http\Controllers\Api\Admin\ComicUploadController::class, 'reorderPages']);
            Route::delete('/{comic}/pages', [App\Http\Controllers\Api\Admin\ComicUploadController::class, 'deletePages']);
            Route::delete('/{comic}', [App\Http\Controllers\Api\Admin\ComicUploadController::class, 'deleteComic']);
        });
    });

}); // End of main rate limiting group

// ============================================
// V2 API Routes - New Image-Based Frontend
// ============================================
Route::prefix('v2')->group(function () {

    // Public routes
    Route::get('/comics', [App\Http\Controllers\Api\V2\ComicController::class, 'index']);
    Route::get('/comics/featured', [App\Http\Controllers\Api\V2\ComicController::class, 'featured']);
    Route::get('/comics/recent', [App\Http\Controllers\Api\V2\ComicController::class, 'recent']);
    Route::get('/genres', [App\Http\Controllers\Api\V2\ComicController::class, 'genres']);
    Route::get('/comics/{comic:slug}', [App\Http\Controllers\Api\V2\ComicController::class, 'show']);
    Route::get('/comics/{comic:slug}/pages', [App\Http\Controllers\Api\V2\ComicController::class, 'pages']);
    Route::get('/comics/{comic:slug}/comments', [App\Http\Controllers\Api\V2\ComicController::class, 'getComments']);

    // Auth routes
    Route::post('/auth/login', [App\Http\Controllers\Api\V2\AuthController::class, 'login']);
    Route::post('/auth/register', [App\Http\Controllers\Api\V2\AuthController::class, 'register']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('/auth/logout', [App\Http\Controllers\Api\V2\AuthController::class, 'logout']);
        Route::get('/auth/user', [App\Http\Controllers\Api\V2\AuthController::class, 'user']);
        Route::post('/auth/refresh', [App\Http\Controllers\Api\V2\AuthController::class, 'refresh']);

        // Library (bookmarks)
        Route::get('/library', [App\Http\Controllers\Api\V2\LibraryController::class, 'index']);
        Route::post('/library/{comic:slug}', [App\Http\Controllers\Api\V2\LibraryController::class, 'store']);
        Route::delete('/library/{comic:slug}', [App\Http\Controllers\Api\V2\LibraryController::class, 'destroy']);
        Route::patch('/library/{comic:slug}/progress', [App\Http\Controllers\Api\V2\LibraryController::class, 'updateProgress']);

        // Engagement
        Route::post('/comics/{comic:slug}/like', [App\Http\Controllers\Api\V2\ComicController::class, 'toggleLike']);
        Route::post('/comics/{comic:slug}/rate', [App\Http\Controllers\Api\V2\ComicController::class, 'rate']);
        Route::post('/comics/{comic:slug}/comments', [App\Http\Controllers\Api\V2\ComicController::class, 'addComment']);
    });
});

