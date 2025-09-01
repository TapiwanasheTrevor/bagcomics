<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'nullable|string|max:255',
            'genre' => 'nullable|string',
            'tags' => 'nullable|string',
            'sort' => 'nullable|in:relevance,newest,rating,popularity,title',
            'is_free' => 'nullable|boolean',
            'min_rating' => 'nullable|numeric|min:0|max:5',
            'max_pages' => 'nullable|integer|min:1',
            'max_readers' => 'nullable|integer|min:0',
            'days' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0'
        ]);

        $query = Comic::where('is_visible', true);

        // Search query
        if ($request->filled('query')) {
            $searchTerm = $request->query;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('author', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Genre filter
        if ($request->filled('genre')) {
            $query->where('genre', $request->genre);
        }

        // Tags filter
        if ($request->filled('tags')) {
            $tags = explode(',', $request->tags);
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', trim($tag));
            }
        }

        // Free comics filter
        if ($request->has('is_free')) {
            $query->where('is_free', $request->boolean('is_free'));
        }

        // Minimum rating filter
        if ($request->filled('min_rating')) {
            $query->where('average_rating', '>=', $request->min_rating);
        }

        // Maximum pages filter
        if ($request->filled('max_pages')) {
            $query->where('page_count', '<=', $request->max_pages);
        }

        // Maximum readers filter (for hidden gems)
        if ($request->filled('max_readers')) {
            $query->where('total_readers', '<=', $request->max_readers);
        }

        // Recent comics filter (last N days)
        if ($request->filled('days')) {
            $query->where('published_at', '>=', now()->subDays($request->days));
        }

        // Sorting
        switch ($request->get('sort', 'relevance')) {
            case 'newest':
                $query->orderBy('published_at', 'desc');
                break;
            case 'rating':
                $query->orderBy('average_rating', 'desc')
                      ->orderBy('total_ratings', 'desc');
                break;
            case 'popularity':
                $query->orderBy('total_readers', 'desc')
                      ->orderBy('total_ratings', 'desc');
                break;
            case 'title':
                $query->orderBy('title', 'asc');
                break;
            case 'relevance':
            default:
                if ($request->filled('query')) {
                    // Order by relevance (title match first, then author, then description)
                    $searchTerm = $request->query;
                    $query->orderByRaw("
                        CASE 
                            WHEN title LIKE ? THEN 1
                            WHEN author LIKE ? THEN 2
                            WHEN description LIKE ? THEN 3
                            ELSE 4
                        END
                    ", ["%{$searchTerm}%", "%{$searchTerm}%", "%{$searchTerm}%"]);
                }
                $query->orderBy('average_rating', 'desc');
                break;
        }

        // Pagination
        $limit = $request->get('limit', 24);
        $offset = $request->get('offset', 0);

        $comics = $query->skip($offset)
            ->take($limit)
            ->get()
            ->map(function ($comic) {
                return [
                    'id' => $comic->id,
                    'slug' => $comic->slug,
                    'title' => $comic->title,
                    'author' => $comic->author,
                    'genre' => $comic->genre,
                    'description' => $comic->description,
                    'cover_image_url' => $comic->getCoverImageUrl(),
                    'average_rating' => round($comic->average_rating, 1),
                    'total_ratings' => $comic->total_ratings ?? 0,
                    'total_readers' => $comic->total_readers ?? 0,
                    'page_count' => $comic->page_count,
                    'is_free' => $comic->is_free,
                    'price' => $comic->price,
                    'published_at' => $comic->published_at->format('Y-m-d'),
                    'tags' => $comic->tags ?? []
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $comics,
            'total' => $comics->count(),
            'query_params' => $request->all()
        ]);
    }

    public function autocomplete(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2|max:100'
        ]);

        $searchTerm = $request->query;
        $cacheKey = "autocomplete.{$searchTerm}";

        $suggestions = Cache::remember($cacheKey, 300, function () use ($searchTerm) {
            $comics = Comic::where('is_visible', true)
                ->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', "{$searchTerm}%")
                      ->orWhere('author', 'LIKE', "{$searchTerm}%");
                })
                ->orderBy('total_readers', 'desc')
                ->take(10)
                ->get(['id', 'title', 'author', 'genre', 'cover_image_url']);

            return $comics->map(function ($comic) {
                return [
                    'id' => $comic->id,
                    'title' => $comic->title,
                    'author' => $comic->author,
                    'genre' => $comic->genre,
                    'cover_image_url' => $comic->getCoverImageUrl(),
                    'type' => 'comic'
                ];
            });
        });

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions
        ]);
    }

    public function getTags(Request $request): JsonResponse
    {
        $cacheKey = 'popular.tags';
        
        $tags = Cache::remember($cacheKey, 3600, function () {
            $allTags = Comic::where('is_visible', true)
                ->whereNotNull('tags')
                ->pluck('tags')
                ->flatten()
                ->filter()
                ->countBy()
                ->sortByDesc(function ($count) {
                    return $count;
                })
                ->take(50)
                ->map(function ($count, $tag) {
                    return [
                        'tag' => $tag,
                        'count' => $count
                    ];
                })
                ->values();

            return $allTags;
        });

        return response()->json([
            'success' => true,
            'tags' => $tags
        ]);
    }

    public function getGenres(Request $request): JsonResponse
    {
        $cacheKey = 'available.genres';
        
        $genres = Cache::remember($cacheKey, 3600, function () {
            return Comic::where('is_visible', true)
                ->whereNotNull('genre')
                ->distinct()
                ->pluck('genre')
                ->sort()
                ->values();
        });

        return response()->json([
            'success' => true,
            'genres' => $genres
        ]);
    }
}