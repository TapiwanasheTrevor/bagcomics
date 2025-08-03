<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private AnalyticsService $analyticsService
    ) {}

    /**
     * Get platform metrics
     */
    public function platformMetrics(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $metrics = $this->analyticsService->getPlatformMetrics($days);

        return response()->json($metrics);
    }

    /**
     * Get revenue analytics
     */
    public function revenueAnalytics(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $analytics = $this->analyticsService->getRevenueAnalytics($days);

        return response()->json($analytics);
    }

    /**
     * Get user engagement analytics
     */
    public function userEngagement(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $analytics = $this->analyticsService->getUserEngagementAnalytics($days);

        return response()->json($analytics);
    }

    /**
     * Get comic performance analytics
     */
    public function comicPerformance(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $analytics = $this->analyticsService->getComicPerformanceAnalytics($days);

        return response()->json($analytics);
    }

    /**
     * Get conversion analytics
     */
    public function conversionAnalytics(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $analytics = $this->analyticsService->getConversionAnalytics($days);

        return response()->json($analytics);
    }

    /**
     * Get popular comics
     */
    public function popularComics(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $days = $request->get('days', 30);
        
        $comics = \App\Models\ComicView::getPopularComics($limit, $days);

        return response()->json($comics);
    }

    /**
     * Get trending comics
     */
    public function trendingComics(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        
        $comics = \App\Models\ComicView::getTrendingComics($limit);

        return response()->json($comics);
    }
}
