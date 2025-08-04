<?php

namespace App\Services;

use App\Models\Comic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Scout\Builder as ScoutBuilder;

class ComicSearchService
{
    /**
     * Perform advanced search with filters
     */
    public function search(array $params = []): LengthAwarePaginator
    {
        $query = $params['query'] ?? '';
        $filters = $params['filters'] ?? [];
        $sort = $params['sort'] ?? 'relevance';
        $perPage = $params['per_page'] ?? 20;
        $page = $params['page'] ?? 1;

        // Use Scout for text search if query is provided
        if (!empty($query)) {
            return $this->searchWithScout($query, $filters, $sort, $perPage, $page);
        }

        // Use Eloquent for filtering without text search
        return $this->searchWithEloquent($filters, $sort, $perPage, $page);
    }

    /**
     * Search using Laravel Scout (Meilisearch)
     */
    protected function searchWithScout(string $query, array $filters, string $sort, int $perPage, int $page): LengthAwarePaginator
    {
        $scoutQuery = Comic::search($query);

        // Apply filters
        $scoutQuery = $this->applyScoutFilters($scoutQuery, $filters);

        // Apply sorting
        $scoutQuery = $this->applyScoutSorting($scoutQuery, $sort);

        // Execute search with pagination
        return $scoutQuery->paginate($perPage, 'page', $page);
    }

    /**
     * Search using Eloquent query builder
     */
    protected function searchWithEloquent(array $filters, string $sort, int $perPage, int $page): LengthAwarePaginator
    {
        $query = Comic::query()
            ->where('is_visible', true)
            ->whereNotNull('published_at');

        // Apply filters
        $query = $this->applyEloquentFilters($query, $filters);

        // Apply sorting
        $query = $this->applyEloquentSorting($query, $sort);

        // Execute query with pagination
        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Apply filters to Scout query
     */
    protected function applyScoutFilters(ScoutBuilder $query, array $filters): ScoutBuilder
    {
        $meilisearchFilters = [];

        // Genre filter
        if (!empty($filters['genre'])) {
            if (is_array($filters['genre'])) {
                $genreFilters = array_map(fn($genre) => "genre = '{$genre}'", $filters['genre']);
                $meilisearchFilters[] = '(' . implode(' OR ', $genreFilters) . ')';
            } else {
                $meilisearchFilters[] = "genre = '{$filters['genre']}'";
            }
        }

        // Author filter
        if (!empty($filters['author'])) {
            if (is_array($filters['author'])) {
                $authorFilters = array_map(fn($author) => "author = '{$author}'", $filters['author']);
                $meilisearchFilters[] = '(' . implode(' OR ', $authorFilters) . ')';
            } else {
                $meilisearchFilters[] = "author = '{$filters['author']}'";
            }
        }

        // Publisher filter
        if (!empty($filters['publisher'])) {
            if (is_array($filters['publisher'])) {
                $publisherFilters = array_map(fn($publisher) => "publisher = '{$publisher}'", $filters['publisher']);
                $meilisearchFilters[] = '(' . implode(' OR ', $publisherFilters) . ')';
            } else {
                $meilisearchFilters[] = "publisher = '{$filters['publisher']}'";
            }
        }

        // Price range filter
        if (!empty($filters['price_min']) || !empty($filters['price_max'])) {
            $priceFilters = [];
            if (!empty($filters['price_min'])) {
                $priceFilters[] = "price >= {$filters['price_min']}";
            }
            if (!empty($filters['price_max'])) {
                $priceFilters[] = "price <= {$filters['price_max']}";
            }
            if (!empty($priceFilters)) {
                $meilisearchFilters[] = '(' . implode(' AND ', $priceFilters) . ')';
            }
        }

        // Free comics filter
        if (isset($filters['is_free']) && $filters['is_free'] !== '') {
            $meilisearchFilters[] = "is_free = " . ($filters['is_free'] ? 'true' : 'false');
        }

        // Mature content filter
        if (isset($filters['has_mature_content']) && $filters['has_mature_content'] !== '') {
            $meilisearchFilters[] = "has_mature_content = " . ($filters['has_mature_content'] ? 'true' : 'false');
        }

        // Publication year range
        if (!empty($filters['year_min']) || !empty($filters['year_max'])) {
            $yearFilters = [];
            if (!empty($filters['year_min'])) {
                $yearFilters[] = "publication_year >= {$filters['year_min']}";
            }
            if (!empty($filters['year_max'])) {
                $yearFilters[] = "publication_year <= {$filters['year_max']}";
            }
            if (!empty($yearFilters)) {
                $meilisearchFilters[] = '(' . implode(' AND ', $yearFilters) . ')';
            }
        }

        // Rating filter
        if (!empty($filters['min_rating'])) {
            $meilisearchFilters[] = "average_rating >= {$filters['min_rating']}";
        }

        // Language filter
        if (!empty($filters['language'])) {
            if (is_array($filters['language'])) {
                $languageFilters = array_map(fn($lang) => "language = '{$lang}'", $filters['language']);
                $meilisearchFilters[] = '(' . implode(' OR ', $languageFilters) . ')';
            } else {
                $meilisearchFilters[] = "language = '{$filters['language']}'";
            }
        }

        // Tags filter
        if (!empty($filters['tags'])) {
            $tags = is_array($filters['tags']) ? $filters['tags'] : [$filters['tags']];
            $tagFilters = [];
            foreach ($tags as $tag) {
                $tagFilters[] = "tags = '{$tag}'";
            }
            if (!empty($tagFilters)) {
                $meilisearchFilters[] = '(' . implode(' OR ', $tagFilters) . ')';
            }
        }

        // Series filter
        if (!empty($filters['series_id'])) {
            $meilisearchFilters[] = "series_id = {$filters['series_id']}";
        }

        // New releases filter
        if (isset($filters['is_new_release']) && $filters['is_new_release']) {
            $meilisearchFilters[] = "is_new_release = true";
        }

        // Reading time filter
        if (!empty($filters['max_reading_time'])) {
            $meilisearchFilters[] = "reading_time_estimate <= {$filters['max_reading_time']}";
        }

        // Apply all filters
        if (!empty($meilisearchFilters)) {
            $query->where(implode(' AND ', $meilisearchFilters));
        }

        return $query;
    }

    /**
     * Apply filters to Eloquent query
     */
    protected function applyEloquentFilters(Builder $query, array $filters): Builder
    {
        // Genre filter
        if (!empty($filters['genre'])) {
            if (is_array($filters['genre'])) {
                $query->whereIn('genre', $filters['genre']);
            } else {
                $query->where('genre', $filters['genre']);
            }
        }

        // Author filter
        if (!empty($filters['author'])) {
            if (is_array($filters['author'])) {
                $query->whereIn('author', $filters['author']);
            } else {
                $query->where('author', $filters['author']);
            }
        }

        // Publisher filter
        if (!empty($filters['publisher'])) {
            if (is_array($filters['publisher'])) {
                $query->whereIn('publisher', $filters['publisher']);
            } else {
                $query->where('publisher', $filters['publisher']);
            }
        }

        // Price range filter
        if (!empty($filters['price_min'])) {
            $query->where('price', '>=', $filters['price_min']);
        }
        if (!empty($filters['price_max'])) {
            $query->where('price', '<=', $filters['price_max']);
        }

        // Free comics filter
        if (isset($filters['is_free']) && $filters['is_free'] !== '') {
            $query->where('is_free', (bool) $filters['is_free']);
        }

        // Mature content filter
        if (isset($filters['has_mature_content']) && $filters['has_mature_content'] !== '') {
            $query->where('has_mature_content', (bool) $filters['has_mature_content']);
        }

        // Publication year range
        if (!empty($filters['year_min'])) {
            $query->where('publication_year', '>=', $filters['year_min']);
        }
        if (!empty($filters['year_max'])) {
            $query->where('publication_year', '<=', $filters['year_max']);
        }

        // Rating filter
        if (!empty($filters['min_rating'])) {
            $query->where('average_rating', '>=', $filters['min_rating']);
        }

        // Language filter
        if (!empty($filters['language'])) {
            if (is_array($filters['language'])) {
                $query->whereIn('language', $filters['language']);
            } else {
                $query->where('language', $filters['language']);
            }
        }

        // Tags filter (JSON contains)
        if (!empty($filters['tags'])) {
            $tags = is_array($filters['tags']) ? $filters['tags'] : [$filters['tags']];
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        // Series filter
        if (!empty($filters['series_id'])) {
            $query->where('series_id', $filters['series_id']);
        }

        // New releases filter (published within last 30 days)
        if (isset($filters['is_new_release']) && $filters['is_new_release']) {
            $query->where('published_at', '>', now()->subDays(30));
        }

        // Reading time filter
        if (!empty($filters['max_reading_time'])) {
            $query->where('page_count', '<=', $filters['max_reading_time'] / 2); // Assuming 2 minutes per page
        }

        return $query;
    }

    /**
     * Apply sorting to Scout query
     */
    protected function applyScoutSorting(ScoutBuilder $query, string $sort): ScoutBuilder
    {
        switch ($sort) {
            case 'title_asc':
                return $query->orderBy('title', 'asc');
            case 'title_desc':
                return $query->orderBy('title', 'desc');
            case 'author_asc':
                return $query->orderBy('author', 'asc');
            case 'author_desc':
                return $query->orderBy('author', 'desc');
            case 'publication_year_asc':
                return $query->orderBy('publication_year', 'asc');
            case 'publication_year_desc':
                return $query->orderBy('publication_year', 'desc');
            case 'rating_desc':
                return $query->orderBy('average_rating', 'desc');
            case 'rating_asc':
                return $query->orderBy('average_rating', 'asc');
            case 'popularity_desc':
                return $query->orderBy('total_readers', 'desc');
            case 'popularity_asc':
                return $query->orderBy('total_readers', 'asc');
            case 'price_asc':
                return $query->orderBy('price', 'asc');
            case 'price_desc':
                return $query->orderBy('price', 'desc');
            case 'newest':
                return $query->orderBy('published_at', 'desc');
            case 'oldest':
                return $query->orderBy('published_at', 'asc');
            case 'recent_views':
                return $query->orderBy('recent_views', 'desc');
            case 'relevance':
            default:
                // Default Scout relevance sorting
                return $query;
        }
    }

    /**
     * Apply sorting to Eloquent query
     */
    protected function applyEloquentSorting(Builder $query, string $sort): Builder
    {
        switch ($sort) {
            case 'title_asc':
                return $query->orderBy('title', 'asc');
            case 'title_desc':
                return $query->orderBy('title', 'desc');
            case 'author_asc':
                return $query->orderBy('author', 'asc');
            case 'author_desc':
                return $query->orderBy('author', 'desc');
            case 'publication_year_asc':
                return $query->orderBy('publication_year', 'asc');
            case 'publication_year_desc':
                return $query->orderBy('publication_year', 'desc');
            case 'rating_desc':
                return $query->orderBy('average_rating', 'desc');
            case 'rating_asc':
                return $query->orderBy('average_rating', 'asc');
            case 'popularity_desc':
                return $query->orderBy('total_readers', 'desc');
            case 'popularity_asc':
                return $query->orderBy('total_readers', 'asc');
            case 'price_asc':
                return $query->orderBy('price', 'asc');
            case 'price_desc':
                return $query->orderBy('price', 'desc');
            case 'newest':
                return $query->orderBy('published_at', 'desc');
            case 'oldest':
                return $query->orderBy('published_at', 'asc');
            case 'recent_views':
                return $query->orderByDesc('view_count');
            case 'relevance':
            default:
                // Default sorting by rating and popularity
                return $query->orderByDesc('average_rating')
                            ->orderByDesc('total_readers');
        }
    }

    /**
     * Get search suggestions based on partial query
     */
    public function getSuggestions(string $query, int $limit = 10): array
    {
        if (strlen($query) < 2) {
            return [];
        }

        // Get title suggestions (use LIKE for SQLite compatibility)
        $likeOperator = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';
        
        $titleSuggestions = Comic::where('is_visible', true)
            ->where('title', $likeOperator, "%{$query}%")
            ->orderBy('total_readers', 'desc')
            ->limit($limit)
            ->pluck('title')
            ->toArray();

        // Get author suggestions
        $authorSuggestions = Comic::where('is_visible', true)
            ->where('author', $likeOperator, "%{$query}%")
            ->orderBy('total_readers', 'desc')
            ->limit($limit)
            ->pluck('author')
            ->unique()
            ->values()
            ->toArray();

        // Get publisher suggestions
        $publisherSuggestions = Comic::where('is_visible', true)
            ->where('publisher', $likeOperator, "%{$query}%")
            ->orderBy('total_readers', 'desc')
            ->limit($limit)
            ->pluck('publisher')
            ->unique()
            ->values()
            ->toArray();

        // Combine and deduplicate suggestions
        $allSuggestions = array_merge($titleSuggestions, $authorSuggestions, $publisherSuggestions);
        $uniqueSuggestions = array_unique($allSuggestions);

        return array_slice($uniqueSuggestions, 0, $limit);
    }

    /**
     * Get autocomplete suggestions with categories
     */
    public function getAutocompleteSuggestions(string $query, int $limit = 10): array
    {
        if (strlen($query) < 2) {
            return [];
        }

        $suggestions = [
            'titles' => [],
            'authors' => [],
            'publishers' => [],
            'series' => [],
        ];

        // Use appropriate LIKE operator based on database
        $likeOperator = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';

        // Title suggestions
        $suggestions['titles'] = Comic::where('is_visible', true)
            ->where('title', $likeOperator, "%{$query}%")
            ->orderBy('total_readers', 'desc')
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'cover_image_path'])
            ->toArray();

        // Author suggestions
        $suggestions['authors'] = Comic::where('is_visible', true)
            ->where('author', $likeOperator, "%{$query}%")
            ->select('author')
            ->distinct()
            ->orderBy('author')
            ->limit($limit)
            ->pluck('author')
            ->toArray();

        // Publisher suggestions
        $suggestions['publishers'] = Comic::where('is_visible', true)
            ->where('publisher', $likeOperator, "%{$query}%")
            ->select('publisher')
            ->distinct()
            ->orderBy('publisher')
            ->limit($limit)
            ->pluck('publisher')
            ->toArray();

        // Series suggestions
        $suggestions['series'] = Comic::join('comic_series', 'comics.series_id', '=', 'comic_series.id')
            ->where('comics.is_visible', true)
            ->where('comic_series.name', $likeOperator, "%{$query}%")
            ->select('comic_series.id', 'comic_series.name', 'comic_series.slug')
            ->distinct()
            ->orderBy('comic_series.name')
            ->limit($limit)
            ->get()
            ->toArray();

        return $suggestions;
    }

    /**
     * Get filter options for the search interface
     */
    public function getFilterOptions(): array
    {
        return [
            'genres' => Comic::where('is_visible', true)
                ->whereNotNull('genre')
                ->select('genre')
                ->distinct()
                ->orderBy('genre')
                ->pluck('genre')
                ->toArray(),

            'authors' => Comic::where('is_visible', true)
                ->whereNotNull('author')
                ->select('author')
                ->distinct()
                ->orderBy('author')
                ->limit(100) // Limit to prevent too many options
                ->pluck('author')
                ->toArray(),

            'publishers' => Comic::where('is_visible', true)
                ->whereNotNull('publisher')
                ->select('publisher')
                ->distinct()
                ->orderBy('publisher')
                ->limit(100)
                ->pluck('publisher')
                ->toArray(),

            'languages' => Comic::where('is_visible', true)
                ->whereNotNull('language')
                ->select('language')
                ->distinct()
                ->orderBy('language')
                ->pluck('language')
                ->toArray(),

            'publication_years' => [
                'min' => Comic::where('is_visible', true)->min('publication_year'),
                'max' => Comic::where('is_visible', true)->max('publication_year'),
            ],

            'price_range' => [
                'min' => Comic::where('is_visible', true)->where('price', '>', 0)->min('price'),
                'max' => Comic::where('is_visible', true)->max('price'),
            ],

            'tags' => Comic::where('is_visible', true)
                ->whereNotNull('tags')
                ->get()
                ->pluck('tags')
                ->flatten()
                ->unique()
                ->sort()
                ->values()
                ->toArray(),
        ];
    }

    /**
     * Get popular search terms
     */
    public function getPopularSearchTerms(int $limit = 10): array
    {
        // This would typically come from a search analytics table
        // For now, return popular genres and authors
        $popularGenres = Comic::where('is_visible', true)
            ->select('genre')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('genre')
            ->orderByDesc('count')
            ->limit($limit)
            ->pluck('genre')
            ->toArray();

        $popularAuthors = Comic::where('is_visible', true)
            ->select('author')
            ->selectRaw('SUM(total_readers) as total_readers')
            ->groupBy('author')
            ->orderByDesc('total_readers')
            ->limit($limit)
            ->pluck('author')
            ->toArray();

        return array_merge($popularGenres, $popularAuthors);
    }
}