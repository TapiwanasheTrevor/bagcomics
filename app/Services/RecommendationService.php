<?php

namespace App\Services;

use App\Models\User;
use App\Models\Comic;
use App\Models\UserRecommendation;
use App\Models\UserLibrary;
use App\Models\ReadingProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RecommendationService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const MIN_INTERACTIONS = 3; // Minimum interactions for collaborative filtering
    private const MAX_RECOMMENDATIONS = 50;

    /**
     * Generate personalized recommendations for a user
     */
    public function generateRecommendations(User $user, int $limit = 10): Collection
    {
        $cacheKey = "recommendations.user.{$user->id}.limit.{$limit}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $limit) {
            Log::info("Generating recommendations for user {$user->id}");
            
            // Get user's reading history and preferences
            $userProfile = $this->buildUserProfile($user);
            
            // Generate different types of recommendations
            $recommendations = collect();
            
            // 1. Collaborative Filtering (35% weight)
            $collaborative = $this->getCollaborativeRecommendations($user, $userProfile);
            $recommendations = $recommendations->merge($collaborative->take(intval($limit * 0.35)));
            
            // 2. Content-Based Filtering (30% weight) 
            $contentBased = $this->getContentBasedRecommendations($user, $userProfile);
            $recommendations = $recommendations->merge($contentBased->take(intval($limit * 0.30)));
            
            // 3. Trending/Popular (20% weight)
            $trending = $this->getTrendingRecommendations($user, $userProfile);
            $recommendations = $recommendations->merge($trending->take(intval($limit * 0.20)));
            
            // 4. New Releases (15% weight)
            $newReleases = $this->getNewReleaseRecommendations($user, $userProfile);
            $recommendations = $recommendations->merge($newReleases->take(intval($limit * 0.15)));
            
            // Remove duplicates and sort by score
            $finalRecommendations = $this->deduplicateAndRank($recommendations, $limit);
            
            // Store recommendations in database
            $this->storeRecommendations($user, $finalRecommendations);
            
            return $finalRecommendations;
        });
    }

    /**
     * Build comprehensive user profile for recommendations
     */
    private function buildUserProfile(User $user): array
    {
        $cacheKey = "user.profile.{$user->id}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            // Get user's library and reading progress
            $library = $user->library()->with(['comic', 'progress'])->get();
            $readComics = $library->filter(fn($entry) => $entry->progress?->progress_percentage > 10);
            
            // Extract preferences
            $genres = $readComics->pluck('comic.genre')->filter()->countBy();
            $authors = $readComics->pluck('comic.author')->filter()->countBy();
            $publishers = $readComics->pluck('comic.publisher')->filter()->countBy();
            $tags = $readComics->pluck('comic.tags')->flatten()->filter()->countBy();
            
            // Calculate reading patterns
            $avgRating = $library->whereNotNull('rating')->avg('rating') ?? 3.5;
            $avgReadingTime = $readComics->avg('total_reading_time') ?? 1800; // 30 minutes default
            $preferredLength = $this->calculatePreferredLength($readComics);
            $activityLevel = $this->calculateActivityLevel($user);
            
            return [
                'user_id' => $user->id,
                'favorite_genres' => $genres->sortByDesc(fn($count) => $count)->keys()->take(5)->toArray(),
                'favorite_authors' => $authors->sortByDesc(fn($count) => $count)->keys()->take(5)->toArray(),
                'favorite_publishers' => $publishers->sortByDesc(fn($count) => $count)->keys()->take(3)->toArray(),
                'favorite_tags' => $tags->sortByDesc(fn($count) => $count)->keys()->take(10)->toArray(),
                'avg_rating' => $avgRating,
                'avg_reading_time' => $avgReadingTime,
                'preferred_length' => $preferredLength,
                'activity_level' => $activityLevel,
                'total_comics_read' => $readComics->count(),
                'total_comics_in_library' => $library->count(),
                'reading_recency' => $this->calculateReadingRecency($user),
                'genre_diversity' => $genres->count(),
                'completion_rate' => $this->calculateCompletionRate($library)
            ];
        });
    }

    /**
     * Get collaborative filtering recommendations
     */
    private function getCollaborativeRecommendations(User $user, array $userProfile): Collection
    {
        if ($userProfile['total_comics_read'] < self::MIN_INTERACTIONS) {
            return collect();
        }

        // Find similar users based on reading history
        $similarUsers = $this->findSimilarUsers($user, $userProfile);
        
        if ($similarUsers->isEmpty()) {
            return collect();
        }

        // Get comics that similar users enjoyed but current user hasn't read
        $recommendations = collect();
        
        foreach ($similarUsers as $similarUser) {
            $theirComics = UserLibrary::where('user_id', $similarUser['user_id'])
                ->whereHas('progress', fn($q) => $q->where('progress_percentage', '>', 50))
                ->where('rating', '>=', 4)
                ->with('comic')
                ->get();
                
            foreach ($theirComics as $entry) {
                if (!$this->userHasComic($user->id, $entry->comic_id)) {
                    $score = $this->calculateCollaborativeScore(
                        $entry,
                        $similarUser['similarity'],
                        $userProfile
                    );
                    
                    $recommendations->push([
                        'comic' => $entry->comic,
                        'score' => $score,
                        'type' => 'collaborative_filtering',
                        'reasons' => ['collaborative_filtering', 'similar_readers']
                    ]);
                }
            }
        }

        return $recommendations->sortByDesc('score');
    }

    /**
     * Get content-based recommendations
     */
    private function getContentBasedRecommendations(User $user, array $userProfile): Collection
    {
        $recommendations = collect();
        
        // Genre-based recommendations
        foreach ($userProfile['favorite_genres'] as $index => $genre) {
            $weight = 1 - ($index * 0.15); // Decreasing weight for less preferred genres
            
            $genreComics = Comic::where('genre', $genre)
                ->where('is_visible', true)
                ->where('average_rating', '>=', $userProfile['avg_rating'] - 0.5)
                ->whereNotIn('id', $this->getUserComicIds($user->id))
                ->orderByDesc('average_rating')
                ->take(5)
                ->get();
                
            foreach ($genreComics as $comic) {
                $score = $this->calculateContentScore($comic, $userProfile, $weight);
                $recommendations->push([
                    'comic' => $comic,
                    'score' => $score,
                    'type' => 'content_based',
                    'reasons' => ['similar_genre', 'highly_rated']
                ]);
            }
        }
        
        // Author-based recommendations
        foreach ($userProfile['favorite_authors'] as $index => $author) {
            $weight = 1 - ($index * 0.2);
            
            $authorComics = Comic::where('author', $author)
                ->where('is_visible', true)
                ->whereNotIn('id', $this->getUserComicIds($user->id))
                ->orderByDesc('average_rating')
                ->take(3)
                ->get();
                
            foreach ($authorComics as $comic) {
                $score = $this->calculateContentScore($comic, $userProfile, $weight);
                $recommendations->push([
                    'comic' => $comic,
                    'score' => $score,
                    'type' => 'content_based',
                    'reasons' => ['same_author', 'highly_rated']
                ]);
            }
        }

        return $recommendations->sortByDesc('score');
    }

    /**
     * Get trending/popular recommendations
     */
    private function getTrendingRecommendations(User $user, array $userProfile): Collection
    {
        // Get trending comics from the last 30 days
        $trending = Comic::select('comics.*')
            ->selectRaw('COUNT(user_library.id) as recent_additions')
            ->selectRaw('AVG(user_library.rating) as recent_avg_rating')
            ->leftJoin('user_library', 'comics.id', '=', 'user_library.comic_id')
            ->where('comics.is_visible', true)
            ->where(function($q) {
                $q->where('user_library.created_at', '>=', now()->subDays(30))
                  ->orWhereNull('user_library.created_at');
            })
            ->whereNotIn('comics.id', $this->getUserComicIds($user->id))
            ->groupBy('comics.id')
            ->havingRaw('recent_additions > 5') // At least 5 new readers
            ->orderByDesc('recent_additions')
            ->orderByDesc('recent_avg_rating')
            ->take(15)
            ->get();

        return $trending->map(function ($comic) use ($userProfile) {
            $score = $this->calculateTrendingScore($comic, $userProfile);
            return [
                'comic' => $comic,
                'score' => $score,
                'type' => 'trending',
                'reasons' => ['popular_now', 'highly_rated']
            ];
        })->sortByDesc('score');
    }

    /**
     * Get new release recommendations
     */
    private function getNewReleaseRecommendations(User $user, array $userProfile): Collection
    {
        $newReleases = Comic::where('is_visible', true)
            ->where('published_at', '>=', now()->subDays(14)) // Last 2 weeks
            ->whereIn('genre', $userProfile['favorite_genres'])
            ->whereNotIn('id', $this->getUserComicIds($user->id))
            ->orderByDesc('published_at')
            ->orderByDesc('average_rating')
            ->take(10)
            ->get();

        return $newReleases->map(function ($comic) use ($userProfile) {
            $score = $this->calculateNewReleaseScore($comic, $userProfile);
            return [
                'comic' => $comic,
                'score' => $score,
                'type' => 'new_release',
                'reasons' => ['new_release', 'similar_genre']
            ];
        })->sortByDesc('score');
    }

    /**
     * Find users with similar reading patterns
     */
    private function findSimilarUsers(User $user, array $userProfile): Collection
    {
        $userComics = $this->getUserComicIds($user->id);
        
        if (count($userComics) < self::MIN_INTERACTIONS) {
            return collect();
        }

        // Find users who have read similar comics
        $similarUsers = DB::table('user_library as ul1')
            ->select('ul1.user_id')
            ->selectRaw('COUNT(*) as common_comics')
            ->selectRaw('AVG(ABS(ul1.rating - ul2.rating)) as rating_similarity')
            ->join('user_library as ul2', function($join) use ($user) {
                $join->on('ul1.comic_id', '=', 'ul2.comic_id')
                     ->where('ul2.user_id', $user->id);
            })
            ->where('ul1.user_id', '!=', $user->id)
            ->whereNotNull('ul1.rating')
            ->whereNotNull('ul2.rating')
            ->groupBy('ul1.user_id')
            ->having('common_comics', '>=', max(2, intval(count($userComics) * 0.1)))
            ->orderByDesc('common_comics')
            ->orderBy('rating_similarity')
            ->take(20)
            ->get();

        // Calculate similarity scores
        return $similarUsers->map(function ($similarUser) use ($userComics) {
            $similarity = $this->calculateUserSimilarity(
                $userComics,
                $this->getUserComicIds($similarUser->user_id),
                $similarUser->rating_similarity
            );
            
            return [
                'user_id' => $similarUser->user_id,
                'similarity' => $similarity,
                'common_comics' => $similarUser->common_comics
            ];
        })->sortByDesc('similarity');
    }

    /**
     * Calculate similarity between two users
     */
    private function calculateUserSimilarity(array $userComics, array $otherUserComics, float $ratingDiff): float
    {
        $intersection = array_intersect($userComics, $otherUserComics);
        $union = array_unique(array_merge($userComics, $otherUserComics));
        
        $jaccardSimilarity = count($intersection) / count($union);
        $ratingCompatibility = 1 - min($ratingDiff / 5, 1); // Normalize to 0-1
        
        return ($jaccardSimilarity * 0.7) + ($ratingCompatibility * 0.3);
    }

    /**
     * Calculate various scoring functions
     */
    private function calculateCollaborativeScore($entry, float $similarity, array $userProfile): float
    {
        $baseScore = ($entry->rating / 5) * $similarity;
        
        // Boost for genre match
        if (in_array($entry->comic->genre, $userProfile['favorite_genres'])) {
            $baseScore *= 1.2;
        }
        
        // Boost for high ratings
        if ($entry->rating >= 4.5) {
            $baseScore *= 1.1;
        }
        
        return min($baseScore, 1.0);
    }

    private function calculateContentScore(Comic $comic, array $userProfile, float $weight): float
    {
        $score = 0;
        
        // Rating component (40%)
        $score += ($comic->average_rating / 5) * 0.4;
        
        // Genre match (30%)
        if (in_array($comic->genre, $userProfile['favorite_genres'])) {
            $genreIndex = array_search($comic->genre, $userProfile['favorite_genres']);
            $genreScore = 1 - ($genreIndex * 0.15);
            $score += $genreScore * 0.3;
        }
        
        // Author match (20%)
        if (in_array($comic->author, $userProfile['favorite_authors'])) {
            $score += 0.2;
        }
        
        // Popularity component (10%)
        $popularityScore = min($comic->total_readers / 1000, 1); // Normalize
        $score += $popularityScore * 0.1;
        
        return min($score * $weight, 1.0);
    }

    private function calculateTrendingScore(Comic $comic, array $userProfile): float
    {
        $score = 0;
        
        // Recent popularity (50%)
        $recentAdditions = $comic->recent_additions ?? 0;
        $popularityScore = min($recentAdditions / 50, 1);
        $score += $popularityScore * 0.5;
        
        // Rating (30%)
        $score += ($comic->average_rating / 5) * 0.3;
        
        // Genre match (20%)
        if (in_array($comic->genre, $userProfile['favorite_genres'])) {
            $score += 0.2;
        }
        
        return $score;
    }

    private function calculateNewReleaseScore(Comic $comic, array $userProfile): float
    {
        $score = 0;
        
        // Recency (40%)
        $daysSincePublished = now()->diffInDays($comic->published_at);
        $recencyScore = max(0, 1 - ($daysSincePublished / 14)); // 2 week window
        $score += $recencyScore * 0.4;
        
        // Rating (30%)
        $score += ($comic->average_rating / 5) * 0.3;
        
        // Genre preference (30%)
        if (in_array($comic->genre, $userProfile['favorite_genres'])) {
            $genreIndex = array_search($comic->genre, $userProfile['favorite_genres']);
            $genreScore = 1 - ($genreIndex * 0.1);
            $score += $genreScore * 0.3;
        }
        
        return $score;
    }

    /**
     * Helper functions
     */
    private function getUserComicIds(int $userId): array
    {
        return Cache::remember("user.comics.{$userId}", 3600, function () use ($userId) {
            return UserLibrary::where('user_id', $userId)->pluck('comic_id')->toArray();
        });
    }

    private function userHasComic(int $userId, int $comicId): bool
    {
        return in_array($comicId, $this->getUserComicIds($userId));
    }

    private function calculatePreferredLength(Collection $readComics): string
    {
        $avgPages = $readComics->avg('comic.page_count') ?? 50;
        return match (true) {
            $avgPages < 20 => 'short',
            $avgPages < 50 => 'medium',
            default => 'long'
        };
    }

    private function calculateActivityLevel(User $user): string
    {
        $recentActivity = UserLibrary::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
            
        return match (true) {
            $recentActivity >= 10 => 'high',
            $recentActivity >= 3 => 'medium',
            default => 'low'
        };
    }

    private function calculateReadingRecency(User $user): float
    {
        $lastRead = ReadingProgress::where('user_id', $user->id)
            ->orderByDesc('last_read_at')
            ->value('last_read_at');
            
        if (!$lastRead) return 0;
        
        $daysSinceLastRead = now()->diffInDays($lastRead);
        return max(0, 1 - ($daysSinceLastRead / 30)); // 30 day window
    }

    private function calculateCompletionRate(Collection $library): float
    {
        if ($library->isEmpty()) return 0.5;
        
        $completed = $library->filter(function ($entry) {
            return $entry->progress && $entry->progress->is_completed;
        })->count();
        
        return $completed / $library->count();
    }

    /**
     * Remove duplicates and rank final recommendations
     */
    private function deduplicateAndRank(Collection $recommendations, int $limit): Collection
    {
        return $recommendations
            ->unique(fn($rec) => $rec['comic']->id)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * Store recommendations in database
     */
    private function storeRecommendations(User $user, Collection $recommendations): void
    {
        // Clear old recommendations
        UserRecommendation::where('user_id', $user->id)
            ->where('recommended_at', '<', now()->subDays(7))
            ->delete();

        // Store new recommendations
        foreach ($recommendations as $rec) {
            UserRecommendation::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'comic_id' => $rec['comic']->id,
                ],
                [
                    'recommendation_type' => $rec['type'],
                    'score' => $rec['score'],
                    'reasons' => $rec['reasons'],
                    'recommended_at' => now(),
                    'expires_at' => now()->addDays(7),
                ]
            );
        }
    }

    /**
     * Get stored recommendations for user
     */
    public function getStoredRecommendations(User $user, int $limit = 10): Collection
    {
        return UserRecommendation::where('user_id', $user->id)
            ->active()
            ->with('comic')
            ->orderByDesc('score')
            ->orderByDesc('recommended_at')
            ->take($limit)
            ->get();
    }

    /**
     * Track recommendation interaction
     */
    public function trackInteraction(User $user, Comic $comic, string $action): void
    {
        $recommendation = UserRecommendation::where('user_id', $user->id)
            ->where('comic_id', $comic->id)
            ->first();
            
        if ($recommendation) {
            match ($action) {
                'clicked' => $recommendation->markAsClicked(),
                'dismissed' => $recommendation->dismiss(),
                default => null
            };
        }
        
        // Clear cache to refresh recommendations
        Cache::forget("recommendations.user.{$user->id}");
    }
}