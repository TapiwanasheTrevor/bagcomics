<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comic;
use App\Models\ComicView;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserComicProgress;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    /**
     * Get platform overview analytics.
     */
    public function overview(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|string|in:7d,30d,90d,1y',
            'timezone' => 'nullable|string'
        ]);

        $period = $request->get('period', '30d');
        $timezone = $request->get('timezone', 'UTC');

        $analytics = $this->analyticsService->getPlatformOverview($period, $timezone);

        return response()->json($analytics);
    }

    /**
     * Get user engagement metrics.
     */
    public function userEngagement(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|string|in:7d,30d,90d,1y',
            'metric' => 'nullable|string|in:active_users,reading_time,completion_rate,retention'
        ]);

        $period = $request->get('period', '30d');
        $metric = $request->get('metric', 'active_users');

        $engagement = $this->analyticsService->getUserEngagement($period, $metric);

        return response()->json($engagement);
    }

    /**
     * Get content performance analytics.
     */
    public function contentPerformance(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|string|in:7d,30d,90d,1y',
            'sort_by' => 'nullable|string|in:views,readers,rating,revenue',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $period = $request->get('period', '30d');
        $sortBy = $request->get('sort_by', 'views');
        $limit = $request->get('limit', 20);

        $performance = $this->analyticsService->getContentPerformance($period, $sortBy, $limit);

        return response()->json($performance);
    }

    /**
     * Get revenue analytics.
     */
    public function revenue(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|string|in:7d,30d,90d,1y',
            'breakdown' => 'nullable|string|in:daily,weekly,monthly'
        ]);

        $period = $request->get('period', '30d');
        $breakdown = $request->get('breakdown', 'daily');

        $revenue = $this->analyticsService->getRevenueAnalytics($period, $breakdown);

        return response()->json($revenue);
    }

    /**
     * Get reading behavior analytics.
     */
    public function readingBehavior(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|string|in:7d,30d,90d,1y',
            'user_id' => 'nullable|exists:users,id'
        ]);

        $period = $request->get('period', '30d');
        $userId = $request->get('user_id');

        // If user_id is provided, ensure user can only access their own data or is admin
        if ($userId && $userId != auth()->id() && !auth()->user()->can('access-admin')) {
            return response()->json([
                'code' => 'ACCESS_DENIED',
                'message' => 'You can only access your own reading behavior data'
            ], 403);
        }

        $behavior = $this->analyticsService->getReadingBehavior($period, $userId);

        return response()->json($behavior);
    }

    /**
     * Get genre popularity analytics.
     */
    public function genrePopularity(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|string|in:7d,30d,90d,1y',
            'metric' => 'nullable|string|in:views,purchases,ratings'
        ]);

        $period = $request->get('period', '30d');
        $metric = $request->get('metric', 'views');

        $popularity = $this->analyticsService->getGenrePopularity($period, $metric);

        return response()->json($popularity);
    }

    /**
     * Get search analytics.
     */
    public function searchAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|string|in:7d,30d,90d,1y',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $period = $request->get('period', '30d');
        $limit = $request->get('limit', 50);

        $searchData = $this->analyticsService->getSearchAnalytics($period, $limit);

        return response()->json($searchData);
    }

    /**
     * Get user retention analytics.
     */
    public function userRetention(Request $request): JsonResponse
    {
        $request->validate([
            'cohort_period' => 'nullable|string|in:daily,weekly,monthly',
            'retention_period' => 'nullable|integer|min:1|max:52'
        ]);

        $cohortPeriod = $request->get('cohort_period', 'weekly');
        $retentionPeriod = $request->get('retention_period', 12);

        $retention = $this->analyticsService->getUserRetention($cohortPeriod, $retentionPeriod);

        return response()->json($retention);
    }

    /**
     * Get conversion funnel analytics.
     */
    public function conversionFunnel(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|string|in:7d,30d,90d,1y',
            'funnel_type' => 'nullable|string|in:purchase,reading,engagement'
        ]);

        $period = $request->get('period', '30d');
        $funnelType = $request->get('funnel_type', 'purchase');

        $funnel = $this->analyticsService->getConversionFunnel($period, $funnelType);

        return response()->json($funnel);
    }

    /**
     * Export analytics data.
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:overview,revenue,content,users,reading',
            'period' => 'nullable|string|in:7d,30d,90d,1y',
            'format' => 'nullable|string|in:csv,xlsx,json'
        ]);

        $type = $request->type;
        $period = $request->get('period', '30d');
        $format = $request->get('format', 'csv');

        try {
            $exportData = $this->analyticsService->exportAnalytics($type, $period, $format);

            return response()->json([
                'download_url' => $exportData['url'],
                'filename' => $exportData['filename'],
                'expires_at' => $exportData['expires_at']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 'EXPORT_FAILED',
                'message' => 'Failed to export analytics data',
                'details' => ['error' => $e->getMessage()]
            ], 400);
        }
    }
}