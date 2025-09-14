<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAnalytics;
use App\Models\Comic;
use App\Models\UserActivity;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function recordReadingActivity(User $user, Comic $comic, array $data = []): void
    {
        $today = Carbon::today();
        
        // Record comics read
        UserAnalytics::recordMetric(
            $user->id,
            'reading',
            'comics_read',
            1,
            [
                'comic' => [
                    'id' => $comic->id,
                    'title' => $comic->title,
                    'slug' => $comic->slug,
                    'cover_image_url' => $comic->cover_image_url,
                    'genre' => $comic->genre,
                    'author' => $comic->author,
                ]
            ],
            $today
        );

        // Record reading time if provided
        if (isset($data['reading_time_minutes'])) {
            UserAnalytics::recordMetric(
                $user->id,
                'reading',
                'reading_time_minutes',
                $data['reading_time_minutes'],
                ['comic_id' => $comic->id],
                $today
            );
        }

        // Record pages read if provided
        if (isset($data['pages_read'])) {
            UserAnalytics::recordMetric(
                $user->id,
                'reading',
                'pages_read',
                $data['pages_read'],
                ['comic_id' => $comic->id],
                $today
            );
        }

        // Record reading session
        UserAnalytics::recordMetric(
            $user->id,
            'reading',
            'reading_sessions',
            1,
            [
                'session_data' => $data,
                'comic_id' => $comic->id
            ],
            $today
        );

        // Clear relevant caches
        $this->clearUserAnalyticsCache($user->id);
    }

    public function recordEngagementActivity(User $user, string $activityType, array $data = []): void
    {
        $today = Carbon::today();
        
        $validActivities = [
            'rating_given', 'review_written', 'list_created', 
            'share_made', 'comment_posted', 'bookmark_added'
        ];

        if (!in_array($activityType, $validActivities)) {
            return;
        }

        UserAnalytics::recordMetric(
            $user->id,
            'engagement',
            $activityType,
            1,
            $data,
            $today
        );

        $this->clearUserAnalyticsCache($user->id);
    }

    public function recordDiscoveryActivity(User $user, string $discoveryType, array $data = []): void
    {
        $today = Carbon::today();
        
        $validDiscoveries = [
            'genre_explored', 'author_discovered', 'recommendation_followed',
            'trending_comic_read', 'search_performed', 'filter_applied'
        ];

        if (!in_array($discoveryType, $validDiscoveries)) {
            return;
        }

        UserAnalytics::recordMetric(
            $user->id,
            'discovery',
            $discoveryType,
            1,
            $data,
            $today
        );

        $this->clearUserAnalyticsCache($user->id);
    }

    public function recordSocialActivity(User $user, string $socialType, array $data = []): void
    {
        $today = Carbon::today();
        
        $validSocial = [
            'user_followed', 'list_shared', 'activity_liked',
            'comment_replied', 'profile_viewed'
        ];

        if (!in_array($socialType, $validSocial)) {
            return;
        }

        UserAnalytics::recordMetric(
            $user->id,
            'social',
            $socialType,
            1,
            $data,
            $today
        );

        $this->clearUserAnalyticsCache($user->id);
    }

    public function getComprehensiveAnalytics(int $userId, int $days = 30): array
    {
        $cacheKey = "user_analytics_{$userId}_{$days}";
        
        return Cache::remember($cacheKey, 3600, function () use ($userId, $days) {
            return [
                'reading_stats' => UserAnalytics::getReadingStats($userId, $days),
                'engagement_stats' => UserAnalytics::getEngagementStats($userId, $days),
                'discovery_stats' => UserAnalytics::getDiscoveryStats($userId, $days),
                'streak_stats' => UserAnalytics::getStreakStats($userId),
                'daily_activity' => UserAnalytics::getDailyActivity($userId, $days),
                'top_comics' => UserAnalytics::getTopComics($userId, 10),
                'reading_patterns' => $this->getReadingPatterns($userId, $days),
                'progress_trends' => $this->getProgressTrends($userId, $days),
            ];
        });
    }

    public function getReadingPatterns(int $userId, int $days = 30): array
    {
        $analytics = UserAnalytics::forUser($userId)
            ->forMetric('reading')
            ->recent($days)
            ->get();

        // Time of day analysis
        $hourlyData = $analytics->groupBy(function ($item) {
            return $item->created_at->hour;
        })->map->count()->toArray();

        // Day of week analysis
        $dailyData = $analytics->groupBy(function ($item) {
            return $item->created_at->dayOfWeek;
        })->map->count()->toArray();

        // Genre preferences
        $genreData = $analytics->where('metric_name', 'comics_read')
            ->flatMap(function ($item) {
                return collect($item->additional_data)->get('comic.genre', []);
            })
            ->groupBy(function ($genre) {
                return $genre;
            })
            ->map->count()
            ->sortDesc()
            ->toArray();

        // Average session length
        $sessionLengths = $analytics->where('metric_name', 'reading_time_minutes')
            ->pluck('value')
            ->filter()
            ->toArray();

        $avgSessionLength = count($sessionLengths) > 0 ? array_sum($sessionLengths) / count($sessionLengths) : 0;

        return [
            'peak_reading_hours' => $hourlyData,
            'peak_reading_days' => $dailyData,
            'favorite_genres' => $genreData,
            'average_session_length' => round($avgSessionLength, 2),
            'total_sessions' => $analytics->where('metric_name', 'reading_sessions')->sum('value'),
            'consistency_score' => $this->calculateConsistencyScore($userId, $days)
        ];
    }

    public function getProgressTrends(int $userId, int $days = 30): array
    {
        $weeklyData = [];
        $currentDate = Carbon::now();
        
        for ($week = 0; $week < 4; $week++) {
            $weekStart = $currentDate->copy()->subWeeks($week + 1)->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();
            
            $weekStats = UserAnalytics::forUser($userId)
                ->forDateRange($weekStart, $weekEnd)
                ->get();
            
            $weeklyData[] = [
                'week' => $weekStart->format('M j') . ' - ' . $weekEnd->format('M j'),
                'comics_read' => $weekStats->where('metric_name', 'comics_read')->sum('value'),
                'reading_time' => $weekStats->where('metric_name', 'reading_time_minutes')->sum('value'),
                'engagement_actions' => $weekStats->where('metric_type', 'engagement')->count()
            ];
        }

        return array_reverse($weeklyData);
    }

    private function calculateConsistencyScore(int $userId, int $days = 30): float
    {
        $dailyActivity = UserAnalytics::getDailyActivity($userId, $days);
        
        $activeDays = collect($dailyActivity)->filter(function ($day) {
            return $day['comics_read'] > 0 || $day['reading_time'] > 0;
        })->count();
        
        return round(($activeDays / $days) * 100, 1);
    }

    public function generateMonthlyReport(int $userId, ?int $month = null, ?int $year = null): array
    {
        $month = $month ?? Carbon::now()->month;
        $year = $year ?? Carbon::now()->year;
        
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $daysInMonth = $startDate->daysInMonth;
        
        $cacheKey = "monthly_report_{$userId}_{$year}_{$month}";
        
        return Cache::remember($cacheKey, 7200, function () use ($userId, $startDate, $endDate, $daysInMonth) {
            $monthlyStats = UserAnalytics::forUser($userId)
                ->forDateRange($startDate, $endDate)
                ->get();

            $readingStats = [
                'total_comics' => $monthlyStats->where('metric_name', 'comics_read')->sum('value'),
                'total_time' => $monthlyStats->where('metric_name', 'reading_time_minutes')->sum('value'),
                'total_pages' => $monthlyStats->where('metric_name', 'pages_read')->sum('value'),
                'reading_sessions' => $monthlyStats->where('metric_name', 'reading_sessions')->sum('value'),
            ];

            $engagementStats = [
                'ratings_given' => $monthlyStats->where('metric_name', 'rating_given')->sum('value'),
                'reviews_written' => $monthlyStats->where('metric_name', 'review_written')->sum('value'),
                'lists_created' => $monthlyStats->where('metric_name', 'list_created')->sum('value'),
                'social_interactions' => $monthlyStats->where('metric_type', 'social')->sum('value'),
            ];

            // Get achievements unlocked this month
            $achievements = DB::table('user_achievements')
                ->join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
                ->where('user_achievements.user_id', $userId)
                ->whereBetween('user_achievements.unlocked_at', [$startDate, $endDate])
                ->select('achievements.name', 'achievements.points', 'user_achievements.unlocked_at')
                ->orderBy('user_achievements.unlocked_at', 'desc')
                ->get();

            // Calculate streaks at end of month
            $streakStats = UserAnalytics::getStreakStats($userId);

            // Daily breakdown
            $dailyBreakdown = UserAnalytics::getDailyActivity($userId, $daysInMonth);

            return [
                'period' => [
                    'month' => $startDate->format('F'),
                    'year' => $startDate->year,
                    'days_in_month' => $daysInMonth
                ],
                'reading' => $readingStats,
                'engagement' => $engagementStats,
                'achievements' => $achievements->toArray(),
                'streaks' => $streakStats,
                'daily_activity' => $dailyBreakdown,
                'summary' => [
                    'most_active_day' => $this->getMostActiveDay($dailyBreakdown),
                    'consistency_score' => $this->calculateConsistencyScore($userId, $daysInMonth),
                    'total_points_earned' => $achievements->sum('points'),
                    'improvement_areas' => $this->getImprovementAreas($monthlyStats)
                ]
            ];
        });
    }

    private function getMostActiveDay(array $dailyActivity): ?array
    {
        $mostActive = collect($dailyActivity)->sortByDesc(function ($day) {
            return $day['comics_read'] + ($day['reading_time'] / 60);
        })->first();

        return $mostActive;
    }

    private function getImprovementAreas(mixed $monthlyStats): array
    {
        $areas = [];
        
        $totalReadingTime = $monthlyStats->where('metric_name', 'reading_time_minutes')->sum('value');
        $totalEngagement = $monthlyStats->where('metric_type', 'engagement')->sum('value');
        $totalDiscovery = $monthlyStats->where('metric_type', 'discovery')->sum('value');
        
        if ($totalReadingTime < 300) { // Less than 5 hours per month
            $areas[] = [
                'area' => 'reading_time',
                'suggestion' => 'Try to read for at least 10-15 minutes daily',
                'current_score' => $totalReadingTime
            ];
        }
        
        if ($totalEngagement < 10) {
            $areas[] = [
                'area' => 'engagement',
                'suggestion' => 'Rate comics and write reviews to engage more with the community',
                'current_score' => $totalEngagement
            ];
        }
        
        if ($totalDiscovery < 5) {
            $areas[] = [
                'area' => 'discovery',
                'suggestion' => 'Explore new genres and discover trending comics',
                'current_score' => $totalDiscovery
            ];
        }
        
        return $areas;
    }

    public function clearUserAnalyticsCache(int $userId): void
    {
        $patterns = [
            "user_analytics_{$userId}_*",
            "monthly_report_{$userId}_*",
            "reading_insights_{$userId}_*"
        ];
        
        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    public function aggregateWeeklyData(): void
    {
        $lastWeek = Carbon::now()->subWeek();
        $startOfWeek = $lastWeek->startOfWeek();
        $endOfWeek = $lastWeek->endOfWeek();

        // Get all users who had activity last week
        $activeUsers = UserAnalytics::whereBetween('date', [$startOfWeek, $endOfWeek])
            ->distinct('user_id')
            ->pluck('user_id');

        foreach ($activeUsers as $userId) {
            $weeklyStats = UserAnalytics::forUser($userId)
                ->forDateRange($startOfWeek, $endOfWeek)
                ->get();

            // Aggregate reading metrics
            $totalComics = $weeklyStats->where('metric_name', 'comics_read')->sum('value');
            $totalTime = $weeklyStats->where('metric_name', 'reading_time_minutes')->sum('value');
            $totalPages = $weeklyStats->where('metric_name', 'pages_read')->sum('value');

            // Create weekly aggregates
            UserAnalytics::recordMetric(
                $userId,
                'reading',
                'comics_read',
                $totalComics,
                ['aggregated_from' => 'weekly'],
                $startOfWeek,
                'weekly'
            );

            UserAnalytics::recordMetric(
                $userId,
                'reading',
                'reading_time_minutes',
                $totalTime,
                ['aggregated_from' => 'weekly'],
                $startOfWeek,
                'weekly'
            );

            UserAnalytics::recordMetric(
                $userId,
                'reading',
                'pages_read',
                $totalPages,
                ['aggregated_from' => 'weekly'],
                $startOfWeek,
                'weekly'
            );

            // Clear cache for this user
            $this->clearUserAnalyticsCache($userId);
        }
    }

    public function aggregateMonthlyData(): void
    {
        $lastMonth = Carbon::now()->subMonth();
        $startOfMonth = $lastMonth->startOfMonth();
        $endOfMonth = $lastMonth->endOfMonth();

        // Similar to weekly aggregation but for monthly data
        $activeUsers = UserAnalytics::whereBetween('date', [$startOfMonth, $endOfMonth])
            ->distinct('user_id')
            ->pluck('user_id');

        foreach ($activeUsers as $userId) {
            $monthlyStats = UserAnalytics::forUser($userId)
                ->forDateRange($startOfMonth, $endOfMonth)
                ->get();

            // Create monthly aggregates similar to weekly
            $totalComics = $monthlyStats->where('metric_name', 'comics_read')->sum('value');
            $totalTime = $monthlyStats->where('metric_name', 'reading_time_minutes')->sum('value');
            
            UserAnalytics::recordMetric(
                $userId,
                'reading',
                'comics_read',
                $totalComics,
                ['aggregated_from' => 'monthly'],
                $startOfMonth,
                'monthly'
            );

            $this->clearUserAnalyticsCache($userId);
        }
    }

    public function getPlatformMetrics(int $days = 30): array
    {
        $cacheKey = "platform_metrics_{$days}";
        
        return Cache::remember($cacheKey, 1800, function () use ($days) {
            $periodStart = Carbon::now()->subDays($days);
            
            // Total users count
            $totalUsers = User::count();
            
            // New users in the period
            $newUsers = User::where('created_at', '>=', $periodStart)->count();
            
            // Total revenue from all payments
            $totalRevenue = \App\Models\Payment::where('status', 'succeeded')
                ->sum('amount');
            
            // Revenue in the specified period
            $revenuePeriod = \App\Models\Payment::where('status', 'succeeded')
                ->where('created_at', '>=', $periodStart)
                ->sum('amount');
            
            // Total comics count (published and visible)
            $totalComics = Comic::where('is_visible', true)
                ->whereNotNull('published_at')
                ->count();
            
            // Total purchases count
            $totalPurchases = \App\Models\Payment::where('status', 'succeeded')
                ->count();
            
            // Purchases in the period
            $purchasesPeriod = \App\Models\Payment::where('status', 'succeeded')
                ->where('created_at', '>=', $periodStart)
                ->count();
            
            // Total comic views
            $totalViews = \App\Models\ComicView::count();
            
            // Views in the period
            $viewsPeriod = \App\Models\ComicView::where('created_at', '>=', $periodStart)
                ->count();
            
            // Active readers (users who read comics in the period)
            $activeReaders = UserAnalytics::where('metric_name', 'comics_read')
                ->where('date', '>=', $periodStart)
                ->distinct('user_id')
                ->count('user_id');
            
            return [
                'total_users' => $totalUsers,
                'new_users' => $newUsers,
                'total_revenue' => $totalRevenue,
                'revenue_period' => $revenuePeriod,
                'total_comics' => $totalComics,
                'total_purchases' => $totalPurchases,
                'purchases_period' => $purchasesPeriod,
                'total_views' => $totalViews,
                'views_period' => $viewsPeriod,
                'active_readers' => $activeReaders,
            ];
        });
    }

    public function getRevenueAnalytics(int $days = 30): array
    {
        $cacheKey = "revenue_analytics_{$days}";
        
        return Cache::remember($cacheKey, 1800, function () use ($days) {
            $startDate = Carbon::now()->subDays($days);
            $endDate = Carbon::now();
            
            // Get daily revenue data
            $dailyRevenue = \App\Models\Payment::where('status', 'succeeded')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('DATE(created_at) as date')
                ->selectRaw('SUM(amount) as revenue')
                ->selectRaw('COUNT(*) as transactions')
                ->groupBy('date')
                ->orderBy('date')
                ->get();
            
            // Fill in missing dates with zero values
            $dateRange = [];
            for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
                $dateRange[$date->format('Y-m-d')] = (object)[
                    'date' => $date->format('Y-m-d'),
                    'revenue' => 0,
                    'transactions' => 0
                ];
            }
            
            // Merge actual data with date range
            foreach ($dailyRevenue as $day) {
                $dateRange[$day->date] = $day;
            }
            
            // Calculate summary statistics
            $totalRevenue = $dailyRevenue->sum('revenue');
            $totalTransactions = $dailyRevenue->sum('transactions');
            $avgDailyRevenue = $totalRevenue / max($days, 1);
            $avgTransactionValue = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;
            
            // Get revenue by payment type
            $revenueByType = \App\Models\Payment::where('status', 'succeeded')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('payment_type')
                ->selectRaw('SUM(amount) as revenue')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('payment_type')
                ->get();
            
            // Get top comics by revenue
            $topComics = \App\Models\Payment::where('status', 'succeeded')
                ->whereBetween('payments.created_at', [$startDate, $endDate])
                ->whereNotNull('comic_id')
                ->join('comics', 'payments.comic_id', '=', 'comics.id')
                ->selectRaw('comics.id, comics.title, comics.slug')
                ->selectRaw('SUM(payments.amount) as revenue')
                ->selectRaw('COUNT(payments.id) as purchases')
                ->groupBy('comics.id', 'comics.title', 'comics.slug')
                ->orderByDesc('revenue')
                ->limit(10)
                ->get();
            
            return [
                'daily_revenue' => array_values($dateRange),
                'summary' => [
                    'total_revenue' => $totalRevenue,
                    'total_transactions' => $totalTransactions,
                    'average_daily_revenue' => round($avgDailyRevenue, 2),
                    'average_transaction_value' => round($avgTransactionValue, 2),
                ],
                'revenue_by_type' => $revenueByType,
                'top_comics' => $topComics,
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'days' => $days,
                ],
            ];
        });
    }

    public function getUserEngagementAnalytics(int $days = 30): array
    {
        $cacheKey = "user_engagement_analytics_{$days}";
        
        return Cache::remember($cacheKey, 1800, function () use ($days) {
            $startDate = Carbon::now()->subDays($days);
            
            // Get genre popularity based on comic views
            $genrePopularity = \App\Models\ComicView::whereBetween('comic_views.created_at', [$startDate, Carbon::now()])
                ->join('comics', 'comic_views.comic_id', '=', 'comics.id')
                ->selectRaw('comics.genre')
                ->selectRaw('COUNT(comic_views.id) as view_count')
                ->selectRaw('COUNT(DISTINCT comic_views.user_id) as unique_viewers')
                ->whereNotNull('comics.genre')
                ->groupBy('comics.genre')
                ->orderByDesc('view_count')
                ->limit(15)
                ->get();
            
            // Process genre data to handle JSON arrays
            $processedGenres = [];
            foreach ($genrePopularity as $genreData) {
                // Handle genre as array or string
                $genres = is_string($genreData->genre) ? 
                    (json_decode($genreData->genre, true) ?? [$genreData->genre]) : 
                    [$genreData->genre];
                    
                foreach ($genres as $genre) {
                    if (!isset($processedGenres[$genre])) {
                        $processedGenres[$genre] = (object)[
                            'genre' => $genre,
                            'view_count' => 0,
                            'unique_viewers' => 0
                        ];
                    }
                    $processedGenres[$genre]->view_count += $genreData->view_count;
                    $processedGenres[$genre]->unique_viewers += $genreData->unique_viewers;
                }
            }
            
            // Sort and limit processed genres
            usort($processedGenres, function($a, $b) {
                return $b->view_count - $a->view_count;
            });
            $processedGenres = array_slice($processedGenres, 0, 10);
            
            // Get user activity metrics
            $userActivity = User::whereBetween('created_at', [$startDate, Carbon::now()])
                ->selectRaw('DATE(created_at) as date')
                ->selectRaw('COUNT(*) as new_users')
                ->groupBy('date')
                ->orderBy('date')
                ->get();
            
            // Get engagement metrics
            $totalViews = \App\Models\ComicView::whereBetween('created_at', [$startDate, Carbon::now()])->count();
            $uniqueViewers = \App\Models\ComicView::whereBetween('created_at', [$startDate, Carbon::now()])
                ->distinct('user_id')
                ->whereNotNull('user_id')
                ->count('user_id');
            
            // Get review engagement
            $totalReviews = \App\Models\ComicReview::whereBetween('created_at', [$startDate, Carbon::now()])->count();
            $activeReviewers = \App\Models\ComicReview::whereBetween('created_at', [$startDate, Carbon::now()])
                ->distinct('user_id')
                ->count('user_id');
            
            // Get bookmark engagement
            $totalBookmarks = \App\Models\ComicBookmark::whereBetween('created_at', [$startDate, Carbon::now()])->count();
            
            return [
                'genre_popularity' => array_values($processedGenres),
                'user_activity' => $userActivity,
                'engagement_metrics' => [
                    'total_views' => $totalViews,
                    'unique_viewers' => $uniqueViewers,
                    'total_reviews' => $totalReviews,
                    'active_reviewers' => $activeReviewers,
                    'total_bookmarks' => $totalBookmarks,
                    'avg_views_per_user' => $uniqueViewers > 0 ? round($totalViews / $uniqueViewers, 2) : 0,
                ],
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => Carbon::now()->format('Y-m-d'),
                    'days' => $days,
                ],
            ];
        });
    }

    public function getComicPerformanceAnalytics(int $days = 30): array
    {
        $cacheKey = "comic_performance_analytics_{$days}";
        
        return Cache::remember($cacheKey, 1800, function () use ($days) {
            $startDate = Carbon::now()->subDays($days);
            
            // Get most viewed comics with full model data
            $mostViewedIds = \App\Models\ComicView::whereBetween('comic_views.created_at', [$startDate, Carbon::now()])
                ->join('comics', 'comic_views.comic_id', '=', 'comics.id')
                ->selectRaw('comics.id')
                ->selectRaw('COUNT(comic_views.id) as view_count')
                ->groupBy('comics.id')
                ->orderByDesc('view_count')
                ->limit(10)
                ->pluck('comics.id');
            
            // Ensure we have a collection even if empty
            if ($mostViewedIds->isEmpty()) {
                $mostViewed = collect();
            } else {
                $mostViewed = Comic::whereIn('id', $mostViewedIds)->get()->map(function($comic) use ($days) {
                    // Add a method stub for getViewsInPeriod
                    $comic->period_views = \App\Models\ComicView::where('comic_id', $comic->id)
                        ->whereBetween('created_at', [Carbon::now()->subDays($days), Carbon::now()])
                        ->count();
                    return $comic;
                });
            }
            
            // Get most purchased comics with full model data
            $mostPurchasedData = \App\Models\Payment::where('status', 'succeeded')
                ->whereBetween('payments.created_at', [$startDate, Carbon::now()])
                ->whereNotNull('comic_id')
                ->selectRaw('comic_id')
                ->selectRaw('COUNT(*) as purchase_count')
                ->groupBy('comic_id')
                ->orderByDesc('purchase_count')
                ->limit(10)
                ->get();
            
            $mostPurchasedIds = $mostPurchasedData->pluck('comic_id');
            $purchaseCountMap = $mostPurchasedData->pluck('purchase_count', 'comic_id');
            
            // Ensure we have a collection even if empty
            if ($mostPurchasedIds->isEmpty()) {
                $mostPurchased = collect();
            } else {
                $mostPurchased = Comic::whereIn('id', $mostPurchasedIds)->get()->map(function($comic) use ($purchaseCountMap) {
                    $comic->period_purchases = $purchaseCountMap[$comic->id] ?? 0;
                    return $comic;
                });
            }
            
            // Get best rated comics with full model data - ensure collection
            $bestRated = Comic::where('average_rating', '>', 0)
                ->where('total_ratings', '>=', 5) // Minimum 5 ratings
                ->orderByDesc('average_rating')
                ->orderByDesc('total_ratings')
                ->limit(10)
                ->get();
            
            if ($bestRated->isEmpty()) {
                $bestRated = collect();
            }
            
            // Get recently added comics - ensure collection
            $recentlyAdded = Comic::where('is_visible', true)
                ->whereNotNull('published_at')
                ->orderByDesc('published_at')
                ->limit(10)
                ->get();
                
            if ($recentlyAdded->isEmpty()) {
                $recentlyAdded = collect();
            }
            
            // Calculate overall performance metrics
            $totalComics = Comic::where('is_visible', true)->whereNotNull('published_at')->count();
            $comicsWithViews = \App\Models\ComicView::whereBetween('created_at', [$startDate, Carbon::now()])
                ->distinct('comic_id')
                ->count('comic_id');
            $comicsWithPurchases = \App\Models\Payment::where('status', 'succeeded')
                ->whereBetween('created_at', [$startDate, Carbon::now()])
                ->whereNotNull('comic_id')
                ->distinct('comic_id')
                ->count('comic_id');
            
            return [
                'most_viewed' => $mostViewed,
                'most_purchased' => $mostPurchased,
                'best_rated' => $bestRated,
                'recently_added' => $recentlyAdded,
                'performance_metrics' => [
                    'total_comics' => $totalComics,
                    'comics_with_views' => $comicsWithViews,
                    'comics_with_purchases' => $comicsWithPurchases,
                    'view_rate' => $totalComics > 0 ? round(($comicsWithViews / $totalComics) * 100, 2) : 0,
                    'purchase_rate' => $totalComics > 0 ? round(($comicsWithPurchases / $totalComics) * 100, 2) : 0,
                ],
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => Carbon::now()->format('Y-m-d'),
                    'days' => $days,
                ],
            ];
        });
    }
}