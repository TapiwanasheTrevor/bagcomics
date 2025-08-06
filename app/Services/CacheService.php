<?php

namespace App\Services;

use App\Models\Comic;
use App\Models\User;
use App\Models\ComicReview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CacheService
{
    const CACHE_TTL_SHORT = 300;     // 5 minutes
    const CACHE_TTL_MEDIUM = 1800;   // 30 minutes
    const CACHE_TTL_LONG = 3600;     // 1 hour
    const CACHE_TTL_DAILY = 86400;   // 24 hours

    /**
     * Cache popular comics with Redis
     */
    public function getPopularComics(int $limit = 10): \Illuminate\Support\Collection
    {
        return Cache::remember(
            "popular_comics_{$limit}",
            self::CACHE_TTL_MEDIUM,
            function () use ($limit) {
                Log::info('Cache miss: popular_comics', ['limit' => $limit]);
                
                return Comic::select(['id', 'title', 'author', 'genre', 'cover_image_path', 'view_count', 'average_rating'])
                    ->where('is_visible', true)
                    ->orderBy('view_count', 'desc')
                    ->limit($limit)
                    ->get();
            }
        );
    }

    /**
     * Cache trending comics based on recent activity
     */
    public function getTrendingComics(int $limit = 10): \Illuminate\Support\Collection
    {
        return Cache::remember(
            "trending_comics_{$limit}",
            self::CACHE_TTL_SHORT,
            function () use ($limit) {
                Log::info('Cache miss: trending_comics', ['limit' => $limit]);
                
                return Comic::select(['comics.id', 'comics.title', 'comics.author', 'comics.genre', 'comics.cover_image_path'])
                    ->join('comic_views', 'comics.id', '=', 'comic_views.comic_id')
                    ->where('comics.is_visible', true)
                    ->where('comic_views.viewed_at', '>=', now()->subDays(7))
                    ->groupBy(['comics.id', 'comics.title', 'comics.author', 'comics.genre', 'comics.cover_image_path'])
                    ->orderByRaw('COUNT(comic_views.id) DESC')
                    ->limit($limit)
                    ->get();
            }
        );
    }

    /**
     * Cache new releases
     */
    public function getNewReleases(int $limit = 10): \Illuminate\Support\Collection
    {
        return Cache::remember(
            "new_releases_{$limit}",
            self::CACHE_TTL_LONG,
            function () use ($limit) {
                Log::info('Cache miss: new_releases', ['limit' => $limit]);
                
                return Comic::select(['id', 'title', 'author', 'genre', 'cover_image_path', 'published_at'])
                    ->where('is_visible', true)
                    ->whereNotNull('published_at')
                    ->orderBy('published_at', 'desc')
                    ->limit($limit)
                    ->get();
            }
        );
    }

    /**
     * Cache comic details with related data
     */
    public function getComicDetails(int $comicId): ?Comic
    {
        return Cache::remember(
            "comic_details_{$comicId}",
            self::CACHE_TTL_MEDIUM,
            function () use ($comicId) {
                Log::info('Cache miss: comic_details', ['comic_id' => $comicId]);
                
                return Comic::with([
                    'series',
                    'reviews' => function ($query) {
                        $query->where('is_approved', true)
                            ->orderBy('created_at', 'desc')
                            ->limit(10);
                    },
                    'reviews.user:id,name'
                ])->find($comicId);
            }
        );
    }

    /**
     * Cache user library with pagination support
     */
    public function getUserLibrary(int $userId, int $page = 1, int $perPage = 20): array
    {
        $cacheKey = "user_library_{$userId}_page_{$page}_per_{$perPage}";
        
        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SHORT,
            function () use ($userId, $page, $perPage) {
                Log::info('Cache miss: user_library', [
                    'user_id' => $userId,
                    'page' => $page,
                    'per_page' => $perPage
                ]);
                
                $offset = ($page - 1) * $perPage;
                
                $comics = DB::table('user_libraries')
                    ->join('comics', 'user_libraries.comic_id', '=', 'comics.id')
                    ->where('user_libraries.user_id', $userId)
                    ->where('comics.is_visible', true)
                    ->select([
                        'comics.id',
                        'comics.title',
                        'comics.author',
                        'comics.genre',
                        'comics.cover_image_path',
                        'user_libraries.created_at as purchased_at',
                        'user_libraries.rating',
                        'user_libraries.is_favorite'
                    ])
                    ->orderBy('user_libraries.created_at', 'desc')
                    ->offset($offset)
                    ->limit($perPage)
                    ->get();
                
                $total = DB::table('user_libraries')
                    ->join('comics', 'user_libraries.comic_id', '=', 'comics.id')
                    ->where('user_libraries.user_id', $userId)
                    ->where('comics.is_visible', true)
                    ->count();
                
                return [
                    'comics' => $comics,
                    'total' => $total,
                    'has_more' => ($offset + $perPage) < $total
                ];
            }
        );
    }

    /**
     * Cache comic recommendations for a user
     */
    public function getRecommendationsForUser(int $userId, int $limit = 10): \Illuminate\Support\Collection
    {
        return Cache::remember(
            "recommendations_user_{$userId}_limit_{$limit}",
            self::CACHE_TTL_LONG,
            function () use ($userId, $limit) {
                Log::info('Cache miss: user_recommendations', [
                    'user_id' => $userId,
                    'limit' => $limit
                ]);
                
                // Get user's favorite genres
                $userGenres = DB::table('user_libraries')
                    ->join('comics', 'user_libraries.comic_id', '=', 'comics.id')
                    ->where('user_libraries.user_id', $userId)
                    ->whereNotNull('comics.genre')
                    ->select('comics.genre')
                    ->groupBy('comics.genre')
                    ->orderByRaw('COUNT(*) DESC')
                    ->limit(3)
                    ->pluck('genre');
                
                if ($userGenres->isEmpty()) {
                    // Fallback to popular comics if no preferences
                    return $this->getPopularComics($limit);
                }
                
                return Comic::select(['id', 'title', 'author', 'genre', 'cover_image_path', 'average_rating'])
                    ->where('is_visible', true)
                    ->whereIn('genre', $userGenres)
                    ->whereNotIn('id', function ($query) use ($userId) {
                        $query->select('comic_id')
                            ->from('user_libraries')
                            ->where('user_id', $userId);
                    })
                    ->orderBy('average_rating', 'desc')
                    ->orderBy('view_count', 'desc')
                    ->limit($limit)
                    ->get();
            }
        );
    }

    /**
     * Cache genre statistics
     */
    public function getGenreStats(): \Illuminate\Support\Collection
    {
        return Cache::remember(
            'genre_stats',
            self::CACHE_TTL_DAILY,
            function () {
                Log::info('Cache miss: genre_stats');
                
                return Comic::select('genre', DB::raw('COUNT(*) as count'), DB::raw('AVG(average_rating) as avg_rating'))
                    ->where('is_visible', true)
                    ->whereNotNull('genre')
                    ->groupBy('genre')
                    ->orderBy('count', 'desc')
                    ->get();
            }
        );
    }

    /**
     * Cache search results with Redis
     */
    public function searchComics(string $query, array $filters = [], int $limit = 20): array
    {
        $filterHash = md5(serialize($filters));
        $cacheKey = "search_" . md5($query) . "_{$filterHash}_limit_{$limit}";
        
        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SHORT,
            function () use ($query, $filters, $limit) {
                Log::info('Cache miss: search_comics', [
                    'query' => $query,
                    'filters' => $filters,
                    'limit' => $limit
                ]);
                
                $searchQuery = Comic::select([
                        'id', 'title', 'author', 'genre', 'description',
                        'cover_image_path', 'average_rating', 'view_count'
                    ])
                    ->where('is_visible', true)
                    ->where(function ($q) use ($query) {
                        $q->where('title', 'LIKE', "%{$query}%")
                          ->orWhere('author', 'LIKE', "%{$query}%")
                          ->orWhere('description', 'LIKE', "%{$query}%")
                          ->orWhere('tags', 'LIKE', "%{$query}%");
                    });
                
                // Apply filters
                if (isset($filters['genre']) && $filters['genre']) {
                    $searchQuery->where('genre', $filters['genre']);
                }
                
                if (isset($filters['is_free'])) {
                    $searchQuery->where('is_free', $filters['is_free']);
                }
                
                if (isset($filters['min_rating']) && $filters['min_rating']) {
                    $searchQuery->where('average_rating', '>=', $filters['min_rating']);
                }
                
                $results = $searchQuery->orderByRaw("
                    CASE 
                        WHEN title LIKE ? THEN 1
                        WHEN author LIKE ? THEN 2
                        ELSE 3
                    END
                ", ["%{$query}%", "%{$query}%"])
                ->orderBy('average_rating', 'desc')
                ->limit($limit)
                ->get();
                
                return [
                    'comics' => $results,
                    'total' => $results->count(),
                    'query' => $query,
                    'filters' => $filters
                ];
            }
        );
    }

    /**
     * Invalidate related caches when a comic is updated
     */
    public function invalidateComicCaches(int $comicId): void
    {
        $comic = Comic::find($comicId);
        if (!$comic) return;
        
        Log::info('Invalidating comic caches', ['comic_id' => $comicId]);
        
        // Clear specific comic cache
        Cache::forget("comic_details_{$comicId}");
        
        // Clear listing caches that might include this comic
        $this->clearListingCaches();
        
        // Clear genre stats if genre changed
        Cache::forget('genre_stats');
        
        // Clear search caches (this is aggressive but ensures consistency)
        $this->clearSearchCaches();
    }

    /**
     * Invalidate user-specific caches
     */
    public function invalidateUserCaches(int $userId): void
    {
        Log::info('Invalidating user caches', ['user_id' => $userId]);
        
        // Clear user library caches (all pages)
        $cacheKeys = Cache::getRedis()->keys("*user_library_{$userId}_*");
        if (!empty($cacheKeys)) {
            Cache::getRedis()->del($cacheKeys);
        }
        
        // Clear user recommendations
        Cache::forget("recommendations_user_{$userId}_limit_*");
    }

    /**
     * Clear all listing caches
     */
    public function clearListingCaches(): void
    {
        Log::info('Clearing listing caches');
        
        Cache::forget('popular_comics_*');
        Cache::forget('trending_comics_*');
        Cache::forget('new_releases_*');
    }

    /**
     * Clear search caches
     */
    public function clearSearchCaches(): void
    {
        Log::info('Clearing search caches');
        
        $searchKeys = Cache::getRedis()->keys('*search_*');
        if (!empty($searchKeys)) {
            Cache::getRedis()->del($searchKeys);
        }
    }

    /**
     * Warm up essential caches
     */
    public function warmupCaches(): void
    {
        Log::info('Warming up caches');
        
        // Warm up popular content
        $this->getPopularComics(10);
        $this->getPopularComics(20);
        $this->getTrendingComics(10);
        $this->getNewReleases(10);
        $this->getGenreStats();
        
        // Warm up top comics details
        $popularComics = Comic::orderBy('view_count', 'desc')->limit(5)->pluck('id');
        foreach ($popularComics as $comicId) {
            $this->getComicDetails($comicId);
        }
        
        Log::info('Cache warmup completed');
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        try {
            $redis = Cache::getRedis();
            $info = $redis->info();
            
            return [
                'redis_version' => $info['redis_version'] ?? 'unknown',
                'used_memory' => $info['used_memory_human'] ?? 'unknown',
                'connected_clients' => $info['connected_clients'] ?? 0,
                'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => $this->calculateHitRate($info),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get cache stats', ['error' => $e->getMessage()]);
            
            return [
                'error' => 'Cache stats unavailable',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate cache hit rate
     */
    private function calculateHitRate(array $info): string
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        if ($total === 0) {
            return '0%';
        }
        
        return number_format(($hits / $total) * 100, 2) . '%';
    }

    /**
     * Clear all caches (use with caution in production)
     */
    public function clearAllCaches(): void
    {
        Log::warning('Clearing all caches');
        Cache::flush();
    }
}