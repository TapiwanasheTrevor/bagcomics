<?php

namespace App\Services;

use App\Models\CmsContent;
use App\Models\CmsAnalytic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CmsAnalyticsService
{
    /**
     * Track a content event
     */
    public function trackEvent(CmsContent $content, string $eventType, array $metadata = []): CmsAnalytic
    {
        return $content->trackEvent($eventType, $metadata);
    }

    /**
     * Get content performance metrics
     */
    public function getContentPerformance(CmsContent $content, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        $analytics = $content->analytics()
            ->where('occurred_at', '>=', $startDate)
            ->get();
        
        $views = $analytics->where('event_type', 'view')->count();
        $edits = $analytics->where('event_type', 'edit')->count();
        $publishes = $analytics->where('event_type', 'version_published')->count();
        
        // Group by day for trend analysis
        $dailyViews = $analytics
            ->where('event_type', 'view')
            ->groupBy(function ($item) {
                return $item->occurred_at->format('Y-m-d');
            })
            ->map->count();
        
        return [
            'content_id' => $content->id,
            'content_key' => $content->key,
            'period_days' => $days,
            'total_views' => $views,
            'total_edits' => $edits,
            'total_publishes' => $publishes,
            'daily_views' => $dailyViews,
            'average_daily_views' => $views / $days,
        ];
    }

    /**
     * Get platform-wide analytics
     */
    public function getPlatformAnalytics(int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        $analytics = CmsAnalytic::where('occurred_at', '>=', $startDate)->get();
        
        $eventCounts = $analytics->groupBy('event_type')->map->count();
        
        // Most viewed content
        $mostViewed = $analytics
            ->where('event_type', 'view')
            ->groupBy('cms_content_id')
            ->map->count()
            ->sortDesc()
            ->take(10);
        
        // Most edited content
        $mostEdited = $analytics
            ->where('event_type', 'edit')
            ->groupBy('cms_content_id')
            ->map->count()
            ->sortDesc()
            ->take(10);
        
        // Daily activity
        $dailyActivity = $analytics
            ->groupBy(function ($item) {
                return $item->occurred_at->format('Y-m-d');
            })
            ->map->count();
        
        return [
            'period_days' => $days,
            'total_events' => $analytics->count(),
            'event_counts' => $eventCounts,
            'most_viewed_content' => $this->enrichContentIds($mostViewed),
            'most_edited_content' => $this->enrichContentIds($mostEdited),
            'daily_activity' => $dailyActivity,
            'average_daily_activity' => $analytics->count() / $days,
        ];
    }

    /**
     * Get content engagement metrics
     */
    public function getEngagementMetrics(int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        // Get unique content pieces that had activity
        $activeContent = CmsAnalytic::where('occurred_at', '>=', $startDate)
            ->distinct('cms_content_id')
            ->count('cms_content_id');
        
        $totalContent = CmsContent::count();
        
        // Get sections with most activity
        $sectionActivity = DB::table('cms_analytics')
            ->join('cms_contents', 'cms_analytics.cms_content_id', '=', 'cms_contents.id')
            ->where('cms_analytics.occurred_at', '>=', $startDate)
            ->select('cms_contents.section', DB::raw('count(*) as activity_count'))
            ->groupBy('cms_contents.section')
            ->orderBy('activity_count', 'desc')
            ->get();
        
        return [
            'period_days' => $days,
            'active_content_pieces' => $activeContent,
            'total_content_pieces' => $totalContent,
            'engagement_rate' => $totalContent > 0 ? ($activeContent / $totalContent) * 100 : 0,
            'section_activity' => $sectionActivity,
        ];
    }

    /**
     * Get user activity analytics
     */
    public function getUserActivity(int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        // Get edit activity by users
        $userEdits = DB::table('cms_analytics')
            ->join('cms_content_versions', function ($join) {
                $join->on('cms_analytics.cms_content_id', '=', 'cms_content_versions.cms_content_id')
                     ->where('cms_analytics.event_type', '=', 'version_created');
            })
            ->join('users', 'cms_content_versions.created_by', '=', 'users.id')
            ->where('cms_analytics.occurred_at', '>=', $startDate)
            ->select('users.name', 'users.email', DB::raw('count(*) as edit_count'))
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderBy('edit_count', 'desc')
            ->get();
        
        return [
            'period_days' => $days,
            'user_edits' => $userEdits,
            'total_editors' => $userEdits->count(),
            'total_edits' => $userEdits->sum('edit_count'),
        ];
    }

    /**
     * Generate analytics report
     */
    public function generateReport(int $days = 30): array
    {
        return [
            'report_generated_at' => now(),
            'period_days' => $days,
            'platform_analytics' => $this->getPlatformAnalytics($days),
            'engagement_metrics' => $this->getEngagementMetrics($days),
            'user_activity' => $this->getUserActivity($days),
        ];
    }

    /**
     * Get trending content
     */
    public function getTrendingContent(int $days = 7, int $limit = 10): Collection
    {
        $startDate = now()->subDays($days);
        
        $trending = DB::table('cms_analytics')
            ->join('cms_contents', 'cms_analytics.cms_content_id', '=', 'cms_contents.id')
            ->where('cms_analytics.occurred_at', '>=', $startDate)
            ->where('cms_analytics.event_type', 'view')
            ->select(
                'cms_contents.*',
                DB::raw('count(*) as view_count'),
                DB::raw('count(*) / ' . $days . ' as daily_average')
            )
            ->groupBy('cms_contents.id')
            ->orderBy('view_count', 'desc')
            ->limit($limit)
            ->get();
        
        return $trending;
    }

    /**
     * Clean old analytics data
     */
    public function cleanOldAnalytics(int $keepDays = 365): int
    {
        $cutoffDate = now()->subDays($keepDays);
        
        return CmsAnalytic::where('occurred_at', '<', $cutoffDate)->delete();
    }

    /**
     * Enrich content IDs with content information
     */
    protected function enrichContentIds(Collection $contentIds): Collection
    {
        $contentData = CmsContent::whereIn('id', $contentIds->keys())
            ->get()
            ->keyBy('id');
        
        return $contentIds->map(function ($count, $contentId) use ($contentData) {
            $content = $contentData->get($contentId);
            
            return [
                'content_id' => $contentId,
                'content_key' => $content?->key,
                'content_title' => $content?->title,
                'content_section' => $content?->section,
                'count' => $count,
            ];
        });
    }
}