<?php

namespace App\Services;

use App\Models\Comic;
use App\Models\ComicView;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserLibrary;
use App\Models\UserComicProgress;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Get overall platform metrics
     */
    public function getPlatformMetrics(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        return [
            'total_users' => User::count(),
            'new_users' => User::where('created_at', '>', $startDate)->count(),
            'total_comics' => Comic::where('is_visible', true)->count(),
            'total_revenue' => Payment::where('status', 'succeeded')->sum('amount'),
            'revenue_period' => Payment::where('status', 'succeeded')
                ->where('paid_at', '>', $startDate)
                ->sum('amount'),
            'total_purchases' => Payment::where('status', 'succeeded')->count(),
            'purchases_period' => Payment::where('status', 'succeeded')
                ->where('paid_at', '>', $startDate)
                ->count(),
            'total_views' => ComicView::count(),
            'views_period' => ComicView::where('viewed_at', '>', $startDate)->count(),
            'active_readers' => UserComicProgress::where('last_read_at', '>', $startDate)
                ->distinct('user_id')
                ->count(),
        ];
    }

    /**
     * Get revenue analytics
     */
    public function getRevenueAnalytics(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        // Daily revenue for the period
        $dailyRevenue = Payment::select(
                DB::raw('DATE(paid_at) as date'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('COUNT(*) as transactions')
            )
            ->where('status', 'succeeded')
            ->where('paid_at', '>', $startDate)
            ->groupBy(DB::raw('DATE(paid_at)'))
            ->orderBy('date')
            ->get();

        // Top earning comics
        $topEarningComics = Comic::select('comics.*')
            ->join('payments', 'comics.id', '=', 'payments.comic_id')
            ->where('payments.status', 'succeeded')
            ->where('payments.paid_at', '>', $startDate)
            ->groupBy('comics.id')
            ->orderByRaw('SUM(payments.amount) DESC')
            ->limit(10)
            ->get()
            ->map(function ($comic) use ($startDate) {
                $revenue = $comic->payments()
                    ->where('status', 'succeeded')
                    ->where('paid_at', '>', $startDate)
                    ->sum('amount');
                $comic->period_revenue = $revenue;
                return $comic;
            });

        return [
            'daily_revenue' => $dailyRevenue,
            'top_earning_comics' => $topEarningComics,
            'average_transaction_value' => Payment::where('status', 'succeeded')
                ->where('paid_at', '>', $startDate)
                ->avg('amount') ?? 0,
        ];
    }

    /**
     * Get user engagement analytics
     */
    public function getUserEngagementAnalytics(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        // Reading completion rates
        $completionStats = UserComicProgress::select(
                DB::raw('COUNT(*) as total_reading_sessions'),
                DB::raw('SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_sessions'),
                DB::raw('AVG(progress_percentage) as average_progress'),
                DB::raw('AVG(reading_time_minutes) as average_reading_time')
            )
            ->where('last_read_at', '>', $startDate)
            ->first();

        // Most active users
        $activeUsers = User::select('users.*')
            ->join('user_comic_progress', 'users.id', '=', 'user_comic_progress.user_id')
            ->where('user_comic_progress.last_read_at', '>', $startDate)
            ->groupBy('users.id')
            ->orderByRaw('COUNT(user_comic_progress.id) DESC')
            ->limit(10)
            ->get()
            ->map(function ($user) use ($startDate) {
                $user->reading_sessions = $user->comicProgress()
                    ->where('last_read_at', '>', $startDate)
                    ->count();
                $user->total_reading_time = $user->comicProgress()
                    ->where('last_read_at', '>', $startDate)
                    ->sum('reading_time_minutes');
                return $user;
            });

        // Genre popularity
        $genrePopularity = Comic::select('genre')
            ->join('comic_views', 'comics.id', '=', 'comic_views.comic_id')
            ->where('comic_views.viewed_at', '>', $startDate)
            ->whereNotNull('genre')
            ->groupBy('genre')
            ->orderByRaw('COUNT(comic_views.id) DESC')
            ->limit(10)
            ->get()
            ->map(function ($item) use ($startDate) {
                $item->view_count = ComicView::join('comics', 'comic_views.comic_id', '=', 'comics.id')
                    ->where('comics.genre', $item->genre)
                    ->where('comic_views.viewed_at', '>', $startDate)
                    ->count();
                return $item;
            });

        return [
            'completion_stats' => $completionStats,
            'active_users' => $activeUsers,
            'genre_popularity' => $genrePopularity,
            'reading_completion_rate' => $completionStats->total_reading_sessions > 0
                ? ($completionStats->completed_sessions / $completionStats->total_reading_sessions) * 100
                : 0,
            'average_session_duration' => $completionStats->average_reading_time ?? 0,
            'daily_active_users' => [], // Placeholder for daily active users data
        ];
    }

    /**
     * Get comic performance analytics
     */
    public function getComicPerformanceAnalytics(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        // Most viewed comics
        $mostViewed = ComicView::getPopularComics(10, $days);

        // Trending comics
        $trending = ComicView::getTrendingComics(10);

        // Best rated comics
        $bestRated = Comic::whereHas('libraryEntries', function ($query) {
                $query->whereNotNull('rating');
            })
            ->where('total_ratings', '>=', 3) // At least 3 ratings
            ->orderBy('average_rating', 'desc')
            ->limit(10)
            ->get();

        // Comics with most purchases
        $mostPurchased = Comic::select('comics.*')
            ->join('payments', 'comics.id', '=', 'payments.comic_id')
            ->where('payments.status', 'succeeded')
            ->where('payments.paid_at', '>', $startDate)
            ->groupBy('comics.id')
            ->orderByRaw('COUNT(payments.id) DESC')
            ->limit(10)
            ->get()
            ->map(function ($comic) use ($startDate) {
                $comic->period_purchases = $comic->payments()
                    ->where('status', 'succeeded')
                    ->where('paid_at', '>', $startDate)
                    ->count();
                return $comic;
            });

        return [
            'most_viewed' => $mostViewed,
            'trending' => $trending,
            'best_rated' => $bestRated,
            'most_purchased' => $mostPurchased,
        ];
    }

    /**
     * Get conversion analytics (views to purchases)
     */
    public function getConversionAnalytics(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $comicsWithMetrics = Comic::select('comics.*')
            ->leftJoin('comic_views', function ($join) use ($startDate) {
                $join->on('comics.id', '=', 'comic_views.comic_id')
                     ->where('comic_views.viewed_at', '>', $startDate);
            })
            ->leftJoin('payments', function ($join) use ($startDate) {
                $join->on('comics.id', '=', 'payments.comic_id')
                     ->where('payments.status', 'succeeded')
                     ->where('payments.paid_at', '>', $startDate);
            })
            ->where('comics.is_visible', true)
            ->where('comics.is_free', false) // Only paid comics for conversion
            ->groupBy('comics.id')
            ->selectRaw('
                comics.*,
                COUNT(DISTINCT comic_views.id) as period_views,
                COUNT(DISTINCT payments.id) as period_purchases
            ')
            ->having('period_views', '>', 0)
            ->get()
            ->map(function ($comic) {
                $comic->conversion_rate = $comic->period_views > 0 
                    ? ($comic->period_purchases / $comic->period_views) * 100 
                    : 0;
                return $comic;
            })
            ->sortByDesc('conversion_rate');

        $overallViews = ComicView::join('comics', 'comic_views.comic_id', '=', 'comics.id')
            ->where('comics.is_free', false)
            ->where('comic_views.viewed_at', '>', $startDate)
            ->count();

        $overallPurchases = Payment::join('comics', 'payments.comic_id', '=', 'comics.id')
            ->where('comics.is_free', false)
            ->where('payments.status', 'succeeded')
            ->where('payments.paid_at', '>', $startDate)
            ->count();

        return [
            'comics_with_metrics' => $comicsWithMetrics->take(20),
            'overall_conversion_rate' => $overallViews > 0 ? ($overallPurchases / $overallViews) * 100 : 0,
            'total_views' => $overallViews,
            'total_purchases' => $overallPurchases,
        ];
    }

    /**
     * Generate comprehensive analytics report with export capabilities
     */
    public function generateComprehensiveReport(array $options = []): array
    {
        $days = $options['days'] ?? 30;
        
        return [
            'summary' => [
                'period' => $days . ' days',
                'generated_at' => now()->toISOString(),
                'platform_metrics' => $this->getPlatformMetrics($days),
            ],
            'revenue_analytics' => $this->getRevenueAnalytics($days),
            'user_engagement' => $this->getUserEngagementAnalytics($days),
            'comic_performance' => $this->getComicPerformanceAnalytics($days),
            'conversion_metrics' => $this->getConversionAnalytics($days),
            'realtime_metrics' => $this->getRealtimeMetrics(),
        ];
    }

    /**
     * Export report to CSV format
     */
    public function exportReportToCsv(array $data, string $filename = null): string
    {
        $filename = $filename ?? 'analytics_report_' . now()->format('Y_m_d_H_i_s') . '.csv';
        $filePath = storage_path('app/public/exports/' . $filename);
        
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        
        $csvData = [];
        $csvData[] = ['Section', 'Metric', 'Value', 'Date'];
        
        // Platform metrics
        foreach ($data['summary']['platform_metrics'] as $key => $value) {
            $csvData[] = ['Platform', ucwords(str_replace('_', ' ', $key)), $value, $data['summary']['generated_at']];
        }
        
        // Revenue data
        if (isset($data['revenue_analytics']['daily_revenue'])) {
            foreach ($data['revenue_analytics']['daily_revenue'] as $row) {
                $csvData[] = ['Revenue', 'Daily Revenue', $row->revenue, $row->date];
                $csvData[] = ['Revenue', 'Daily Transactions', $row->transactions, $row->date];
            }
        }
        
        $handle = fopen($filePath, 'w');
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        
        return $filePath;
    }

    /**
     * Export report to JSON format
     */
    public function exportReportToJson(array $data, string $filename = null): string
    {
        $filename = $filename ?? 'analytics_report_' . now()->format('Y_m_d_H_i_s') . '.json';
        $filePath = storage_path('app/public/exports/' . $filename);
        
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
        
        return $filePath;
    }

    /**
     * Export report to PDF format (requires dompdf or similar)
     */
    public function exportReportToPdf(array $data, string $filename = null): string
    {
        $filename = $filename ?? 'analytics_report_' . now()->format('Y_m_d_H_i_s') . '.pdf';
        $filePath = storage_path('app/public/exports/' . $filename);
        
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        
        // Generate HTML content for the PDF
        $html = view('reports.analytics', compact('data'))->render();
        
        // This would require a PDF library like dompdf or wkhtmltopdf
        // For now, we'll create a simple text file as placeholder
        file_put_contents(str_replace('.pdf', '.html', $filePath), $html);
        
        return str_replace('.pdf', '.html', $filePath);
    }

    /**
     * Get real-time metrics for dashboard
     */
    public function getRealtimeMetrics(): array
    {
        $now = now();
        
        return [
            'online_users' => $this->getOnlineUsersCount(),
            'active_reading_sessions' => $this->getActiveReadingSessionsCount(),
            'revenue_today' => Payment::where('status', 'succeeded')
                ->whereDate('paid_at', $now->toDateString())
                ->sum('amount'),
            'new_users_today' => User::whereDate('created_at', $now->toDateString())->count(),
            'views_last_hour' => ComicView::where('viewed_at', '>=', $now->subHour())->count(),
            'last_updated' => $now->toISOString(),
        ];
    }

    /**
     * Get subscription-specific analytics
     */
    public function getSubscriptionAnalytics(): array
    {
        $totalUsers = User::count();
        $subscribedUsers = User::where('subscription_status', 'active')->count();
        $trialUsers = User::where('subscription_status', 'trial')->count();
        $canceledUsers = User::where('subscription_status', 'canceled')->count();
        
        $subscriptionRevenue = Payment::where('status', 'succeeded')
            ->where('type', 'subscription')
            ->sum('amount');
        
        $monthlySubscriptionRevenue = Payment::where('status', 'succeeded')
            ->where('type', 'subscription')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');
        
        return [
            'total_subscribers' => $subscribedUsers,
            'trial_users' => $trialUsers,
            'canceled_users' => $canceledUsers,
            'conversion_rate' => $totalUsers > 0 ? ($subscribedUsers / $totalUsers) * 100 : 0,
            'churn_rate' => ($subscribedUsers + $canceledUsers) > 0 
                ? ($canceledUsers / ($subscribedUsers + $canceledUsers)) * 100 : 0,
            'subscription_revenue' => $subscriptionRevenue,
            'monthly_subscription_revenue' => $monthlySubscriptionRevenue,
            'average_revenue_per_user' => $subscribedUsers > 0 ? $subscriptionRevenue / $subscribedUsers : 0,
        ];
    }

    /**
     * Get reading behavior analytics
     */
    public function getReadingBehaviorAnalytics(int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        $readingPatterns = UserComicProgress::select(
                DB::raw('HOUR(last_read_at) as hour'),
                DB::raw('COUNT(*) as sessions'),
                DB::raw('AVG(reading_time_minutes) as avg_duration')
            )
            ->where('last_read_at', '>', $startDate)
            ->groupBy(DB::raw('HOUR(last_read_at)'))
            ->orderBy('hour')
            ->get();
        
        $deviceStats = UserComicProgress::select(
                DB::raw('device_type'),
                DB::raw('COUNT(*) as sessions'),
                DB::raw('AVG(reading_time_minutes) as avg_duration')
            )
            ->where('last_read_at', '>', $startDate)
            ->whereNotNull('device_type')
            ->groupBy('device_type')
            ->get();
        
        return [
            'reading_by_hour' => $readingPatterns,
            'device_usage' => $deviceStats,
            'average_session_length' => UserComicProgress::where('last_read_at', '>', $startDate)
                ->avg('reading_time_minutes') ?? 0,
            'total_reading_time' => UserComicProgress::where('last_read_at', '>', $startDate)
                ->sum('reading_time_minutes'),
        ];
    }

    private function getOnlineUsersCount(): int
    {
        // Users with activity in the last 15 minutes
        return User::whereHas('comicProgress', function ($query) {
            $query->where('last_read_at', '>=', now()->subMinutes(15));
        })->count();
    }

    private function getActiveReadingSessionsCount(): int
    {
        // Reading sessions updated in the last 30 minutes
        return UserComicProgress::where('last_read_at', '>=', now()->subMinutes(30))
            ->distinct('user_id')
            ->count();
    }
}
