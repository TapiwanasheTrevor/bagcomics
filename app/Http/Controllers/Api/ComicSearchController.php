<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ComicSearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ComicSearchController extends Controller
{
    protected ComicSearchService $searchService;

    public function __construct(ComicSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Perform advanced comic search
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'query' => 'nullable|string|max:255',
                'filters' => 'nullable|array',
                'filters.genre' => 'nullable|array',
                'filters.genre.*' => 'string|max:100',
                'filters.author' => 'nullable|array',
                'filters.author.*' => 'string|max:255',
                'filters.publisher' => 'nullable|array',
                'filters.publisher.*' => 'string|max:255',
                'filters.price_min' => 'nullable|numeric|min:0',
                'filters.price_max' => 'nullable|numeric|min:0',
                'filters.is_free' => 'nullable|boolean',
                'filters.has_mature_content' => 'nullable|boolean',
                'filters.year_min' => 'nullable|integer|min:1900|max:' . date('Y'),
                'filters.year_max' => 'nullable|integer|min:1900|max:' . date('Y'),
                'filters.min_rating' => 'nullable|numeric|min:0|max:5',
                'filters.language' => 'nullable|array',
                'filters.language.*' => 'string|max:10',
                'filters.tags' => 'nullable|array',
                'filters.tags.*' => 'string|max:50',
                'filters.series_id' => 'nullable|integer|exists:comic_series,id',
                'filters.is_new_release' => 'nullable|boolean',
                'filters.max_reading_time' => 'nullable|integer|min:1',
                'sort' => 'nullable|string|in:relevance,title_asc,title_desc,author_asc,author_desc,publication_year_asc,publication_year_desc,rating_desc,rating_asc,popularity_desc,popularity_asc,price_asc,price_desc,newest,oldest,recent_views',
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1',
            ]);

            $results = $this->searchService->search($validated);

            return response()->json([
                'success' => true,
                'data' => $results->items(),
                'pagination' => [
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'from' => $results->firstItem(),
                    'to' => $results->lastItem(),
                ],
                'search_info' => [
                    'query' => $validated['query'] ?? '',
                    'filters_applied' => !empty($validated['filters']),
                    'sort' => $validated['sort'] ?? 'relevance',
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Comic search failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Search failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Get search suggestions
     */
    public function suggestions(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'query' => 'required|string|min:1|max:255',
                'limit' => 'nullable|integer|min:1|max:20',
            ]);

            $suggestions = $this->searchService->getSuggestions(
                $validated['query'],
                $validated['limit'] ?? 10
            );

            return response()->json([
                'success' => true,
                'data' => $suggestions,
                'query' => $validated['query'],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Search suggestions failed', [
                'error' => $e->getMessage(),
                'query' => $request->input('query'),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get suggestions.',
            ], 500);
        }
    }

    /**
     * Get autocomplete suggestions with categories
     */
    public function autocomplete(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'query' => 'required|string|min:1|max:255',
                'limit' => 'nullable|integer|min:1|max:20',
            ]);

            $suggestions = $this->searchService->getAutocompleteSuggestions(
                $validated['query'],
                $validated['limit'] ?? 10
            );

            return response()->json([
                'success' => true,
                'data' => $suggestions,
                'query' => $validated['query'],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Autocomplete failed', [
                'error' => $e->getMessage(),
                'query' => $request->input('query'),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get autocomplete suggestions.',
            ], 500);
        }
    }

    /**
     * Get filter options for the search interface
     */
    public function filterOptions(): JsonResponse
    {
        try {
            $options = $this->searchService->getFilterOptions();

            return response()->json([
                'success' => true,
                'data' => $options,
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to get filter options', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get filter options.',
            ], 500);
        }
    }

    /**
     * Get popular search terms
     */
    public function popularTerms(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'limit' => 'nullable|integer|min:1|max:50',
            ]);

            $terms = $this->searchService->getPopularSearchTerms(
                $validated['limit'] ?? 10
            );

            return response()->json([
                'success' => true,
                'data' => $terms,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Failed to get popular terms', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get popular terms.',
            ], 500);
        }
    }

    /**
     * Get search analytics (for admin use)
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            // This would typically require admin authentication
            $validated = $request->validate([
                'period' => 'nullable|string|in:day,week,month,year',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            // For now, return basic statistics
            $analytics = [
                'total_searches' => 0, // Would come from search logs
                'popular_queries' => $this->searchService->getPopularSearchTerms(20),
                'filter_usage' => [
                    'genre' => 0,
                    'author' => 0,
                    'publisher' => 0,
                    'price_range' => 0,
                    'rating' => 0,
                ],
                'search_trends' => [], // Would show search volume over time
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics,
                'period' => $validated['period'] ?? 'month',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Failed to get search analytics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get search analytics.',
            ], 500);
        }
    }
}