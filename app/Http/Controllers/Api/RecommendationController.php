<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comic;
use App\Models\User;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RecommendationController extends Controller
{
    public function __construct(
        private RecommendationService $recommendationService
    ) {}

    /**
     * Get personalized recommendations for the authenticated user
     */
    public function getRecommendations(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'type' => 'nullable|in:all,collaborative,content,trending,new_releases',
            'refresh' => 'nullable|boolean'
        ]);

        try {
            $user = $request->user();
            $limit = $request->get('limit', 12);
            $type = $request->get('type', 'all');
            $refresh = $request->boolean('refresh');

            // Clear cache if refresh requested
            if ($refresh) {
                Cache::forget("recommendations.user.{$user->id}.limit.{$limit}");
                Cache::forget("user.profile.{$user->id}");
            }

            // Generate fresh recommendations
            $recommendations = $this->recommendationService->generateRecommendations($user, $limit * 2);

            // Filter by type if specified
            if ($type !== 'all') {
                $recommendations = $recommendations->filter(fn($rec) => $rec['type'] === $type);
            }

            // Format response
            $formattedRecommendations = $recommendations->take($limit)->map(function ($rec) {
                return [
                    'comic' => [
                        'id' => $rec['comic']->id,
                        'slug' => $rec['comic']->slug,
                        'title' => $rec['comic']->title,
                        'author' => $rec['comic']->author,
                        'genre' => $rec['comic']->genre,
                        'description' => $rec['comic']->description,
                        'cover_image_url' => $rec['comic']->getCoverImageUrl(),
                        'average_rating' => round($rec['comic']->average_rating, 1),
                        'total_ratings' => $rec['comic']->total_ratings ?? 0,
                        'page_count' => $rec['comic']->page_count,
                        'is_free' => $rec['comic']->is_free,
                        'price' => $rec['comic']->price,
                        'reading_time_estimate' => $rec['comic']->reading_time_estimate,
                        'published_at' => $rec['comic']->published_at->format('Y-m-d'),
                        'tags' => $rec['comic']->tags,
                    ],
                    'recommendation_score' => round($rec['score'], 3),
                    'recommendation_type' => $rec['type'],
                    'reasons' => $this->formatReasons($rec['reasons']),
                    'confidence' => $this->getConfidenceLevel($rec['score'])
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'recommendations' => $formattedRecommendations,
                    'total' => $formattedRecommendations->count(),
                    'user_profile_stats' => $this->getUserProfileStats($user),
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get recommendations', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate recommendations',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get trending comics across the platform
     */
    public function getTrending(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'timeframe' => 'nullable|in:day,week,month,all_time'
        ]);

        $limit = $request->get('limit', 20);
        $timeframe = $request->get('timeframe', 'week');

        $cacheKey = "trending.comics.{$timeframe}.limit.{$limit}";

        $trending = Cache::remember($cacheKey, 1800, function () use ($timeframe, $limit) { // 30 minutes
            $query = Comic::select('comics.*')
                ->selectRaw('COUNT(user_libraries.id) as recent_additions')
                ->selectRaw('AVG(user_libraries.rating) as recent_avg_rating')
                ->leftJoin('user_libraries', 'comics.id', '=', 'user_libraries.comic_id')
                ->where('comics.is_visible', true);

            // Apply timeframe filter
            match ($timeframe) {
                'day' => $query->where('user_libraries.created_at', '>=', now()->subDay()),
                'week' => $query->where('user_libraries.created_at', '>=', now()->subWeek()),
                'month' => $query->where('user_libraries.created_at', '>=', now()->subMonth()),
                default => null // all_time - no filter
            };

            return $query->groupBy('comics.id')
                ->havingRaw('COUNT(user_libraries.id) > 0')
                ->orderByRaw('COUNT(user_libraries.id) DESC')
                ->orderByRaw('AVG(user_libraries.rating) DESC')
                ->orderByDesc('comics.average_rating')
                ->take($limit)
                ->get()
                ->map(function ($comic) {
                    $comic->trending_score = $this->calculateTrendingScore(
                        $comic->recent_additions ?? 0,
                        $comic->recent_avg_rating ?? $comic->average_rating,
                        $comic->average_rating,
                        $comic->total_readers
                    );
                    return $comic;
                });
        });

        return response()->json([
            'success' => true,
            'data' => [
                'trending_comics' => $trending->values(),
                'timeframe' => $timeframe,
                'total' => $trending->count(),
                'generated_at' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Get similar comics to a specific comic
     */
    public function getSimilarComics(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:20'
        ]);

        $limit = $request->get('limit', 6);
        $cacheKey = "similar.comics.{$comic->id}.limit.{$limit}";

        $similar = Cache::remember($cacheKey, 3600, function () use ($comic, $limit) {
            // Get similar comics based on genre, author, tags, and ratings
            $query = Comic::where('is_visible', true)
                ->where('id', '!=', $comic->id);

            // Score by similarity factors
            $similarComics = collect();

            // Same genre comics
            if ($comic->genre) {
                $genreComics = $query->where('genre', $comic->genre)
                    ->orderByDesc('average_rating')
                    ->take($limit * 2)
                    ->get()
                    ->map(function ($c) use ($comic) {
                        $c->similarity_score = $this->calculateSimilarityScore($c, $comic);
                        return $c;
                    });
                $similarComics = $similarComics->merge($genreComics);
            }

            // Same author comics
            if ($comic->author) {
                $authorComics = Comic::where('author', $comic->author)
                    ->where('is_visible', true)
                    ->where('id', '!=', $comic->id)
                    ->orderByDesc('average_rating')
                    ->take(5)
                    ->get()
                    ->map(function ($c) use ($comic) {
                        $c->similarity_score = $this->calculateSimilarityScore($c, $comic) + 0.3; // Boost for same author
                        return $c;
                    });
                $similarComics = $similarComics->merge($authorComics);
            }

            // Tag-based similarity
            if ($comic->tags && count($comic->tags) > 0) {
                $tagComics = Comic::where('is_visible', true)
                    ->where('id', '!=', $comic->id)
                    ->whereJsonLength('tags', '>', 0)
                    ->get()
                    ->filter(function ($c) use ($comic) {
                        $intersection = array_intersect($c->tags ?? [], $comic->tags ?? []);
                        return count($intersection) > 0;
                    })
                    ->map(function ($c) use ($comic) {
                        $c->similarity_score = $this->calculateSimilarityScore($c, $comic);
                        return $c;
                    });
                $similarComics = $similarComics->merge($tagComics);
            }

            return $similarComics
                ->unique('id')
                ->sortByDesc('similarity_score')
                ->take($limit)
                ->values();
        });

        return response()->json([
            'success' => true,
            'data' => [
                'similar_comics' => $similar,
                'source_comic' => [
                    'id' => $comic->id,
                    'title' => $comic->title,
                    'author' => $comic->author,
                    'genre' => $comic->genre
                ],
                'total' => $similar->count()
            ]
        ]);
    }

    /**
     * Track user interaction with recommendations
     */
    public function trackInteraction(Request $request): JsonResponse
    {
        $request->validate([
            'comic_id' => 'required|exists:comics,id',
            'action' => 'required|in:clicked,dismissed,added_to_library,started_reading',
            'recommendation_type' => 'nullable|string'
        ]);

        try {
            $user = $request->user();
            $comic = Comic::findOrFail($request->comic_id);
            $action = $request->action;

            $this->recommendationService->trackInteraction($user, $comic, $action);

            // Log interaction for analytics
            Log::info('Recommendation interaction tracked', [
                'user_id' => $user->id,
                'comic_id' => $comic->id,
                'action' => $action,
                'recommendation_type' => $request->recommendation_type
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Interaction tracked successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to track recommendation interaction', [
                'user_id' => $request->user()?->id,
                'comic_id' => $request->comic_id,
                'action' => $request->action,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to track interaction'
            ], 500);
        }
    }

    /**
     * Get recommendation statistics for analytics
     */
    public function getStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $cacheKey = "recommendation.stats.{$user->id}";

        $stats = Cache::remember($cacheKey, 3600, function () use ($user) {
            return [
                'total_recommendations_generated' => $user->recommendations()->count(),
                'recommendations_clicked' => $user->recommendations()->whereNotNull('clicked_at')->count(),
                'recommendations_dismissed' => $user->recommendations()->where('is_dismissed', true)->count(),
                'click_through_rate' => $this->calculateCTR($user),
                'favorite_recommendation_types' => $this->getFavoriteRecommendationTypes($user),
                'recommendation_accuracy' => $this->calculateAccuracy($user)
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    // Helper methods
    private function formatReasons(array $reasons): array
    {
        return collect($reasons)->map(function ($reason) {
            return match ($reason) {
                'similar_genre' => 'Similar to genres you enjoy',
                'same_author' => 'From an author you\'ve read',
                'highly_rated' => 'Highly rated by other readers',
                'popular_now' => 'Trending among readers',
                'collaborative_filtering' => 'Readers like you also enjoyed',
                'new_release' => 'New release in your favorite genre',
                'continue_series' => 'Next in a series you\'ve read',
                'similar_readers' => 'Popular with similar readers',
                'reading_pattern' => 'Matches your reading preferences',
                default => ucfirst(str_replace('_', ' ', $reason))
            };
        })->toArray();
    }

    private function getConfidenceLevel(float $score): string
    {
        return match (true) {
            $score >= 0.9 => 'very_high',
            $score >= 0.8 => 'high',
            $score >= 0.6 => 'medium',
            default => 'low'
        };
    }

    private function getUserProfileStats(User $user): array
    {
        return [
            'total_comics_in_library' => $user->library()->count(),
            'comics_completed' => $user->library()->whereHas('progress', function ($q) {
                $q->where('is_completed', true);
            })->count(),
            'average_rating_given' => round($user->library()->whereNotNull('rating')->avg('rating') ?? 0, 1),
            'favorite_genres' => $user->library()
                ->with('comic:id,genre')
                ->get()
                ->pluck('comic.genre')
                ->filter()
                ->countBy()
                ->sortByDesc(fn($count) => $count)
                ->keys()
                ->take(3)
                ->toArray()
        ];
    }

    private function calculateTrendingScore(int $recentAdditions, float $recentAvgRating, float $overallRating, int $totalReaders): float
    {
        $popularityScore = min($recentAdditions / 10, 1); // Normalize to max of 1
        $qualityScore = ($recentAvgRating + $overallRating) / 10; // 0-1 scale
        $reachScore = min($totalReaders / 1000, 1); // Normalize

        return ($popularityScore * 0.5) + ($qualityScore * 0.3) + ($reachScore * 0.2);
    }

    private function calculateSimilarityScore(Comic $comic1, Comic $comic2): float
    {
        $score = 0;

        // Genre match (40%)
        if ($comic1->genre === $comic2->genre) {
            $score += 0.4;
        }

        // Author match (30%)
        if ($comic1->author === $comic2->author) {
            $score += 0.3;
        }

        // Rating similarity (20%)
        $ratingDiff = abs($comic1->average_rating - $comic2->average_rating);
        $ratingScore = max(0, 1 - ($ratingDiff / 5));
        $score += $ratingScore * 0.2;

        // Tag similarity (10%)
        if ($comic1->tags && $comic2->tags) {
            $intersection = array_intersect($comic1->tags, $comic2->tags);
            $union = array_unique(array_merge($comic1->tags, $comic2->tags));
            $tagScore = count($intersection) / count($union);
            $score += $tagScore * 0.1;
        }

        return $score;
    }

    private function calculateCTR(User $user): float
    {
        $total = $user->recommendations()->count();
        $clicked = $user->recommendations()->whereNotNull('clicked_at')->count();

        return $total > 0 ? round(($clicked / $total) * 100, 1) : 0;
    }

    private function getFavoriteRecommendationTypes(User $user): array
    {
        return $user->recommendations()
            ->whereNotNull('clicked_at')
            ->get()
            ->groupBy('recommendation_type')
            ->map(fn($group) => $group->count())
            ->sortByDesc(fn($count) => $count)
            ->take(3)
            ->toArray();
    }

    private function calculateAccuracy(User $user): float
    {
        // Calculate accuracy based on clicked recommendations that were added to library
        $clickedRecs = $user->recommendations()->whereNotNull('clicked_at')->get();
        if ($clickedRecs->isEmpty()) return 0;

        $accurate = $clickedRecs->filter(function ($rec) use ($user) {
            return $user->library()->where('comic_id', $rec->comic_id)->exists();
        })->count();

        return round(($accurate / $clickedRecs->count()) * 100, 1);
    }
}