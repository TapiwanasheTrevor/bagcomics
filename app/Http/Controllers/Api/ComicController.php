<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ComicResource;
use App\Http\Resources\Api\ComicCollection;
use App\Http\Requests\Api\ComicFilterRequest;
use App\Models\Comic;
use App\Models\ComicView;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ComicController extends Controller
{
    public function index(ComicFilterRequest $request)
    {
        $query = Comic::query()
            ->where('is_visible', true)
            ->with([
                'approvedReviews' => function ($query) {
                    $query->select('comic_id', 'rating');
                },
                'userProgress' => function ($query) use ($request) {
                    if ($request->user()) {
                        $query->where('user_id', $request->user()->id);
                    }
                }
            ]);

        // Apply filters
        if ($request->filled('genre')) {
            $query->where('genre', $request->genre);
        }

        if ($request->filled('language')) {
            $query->where('language', $request->language);
        }

        if ($request->filled('is_free')) {
            $query->where('is_free', $request->boolean('is_free'));
        }

        if ($request->filled('has_mature_content')) {
            $query->where('has_mature_content', $request->boolean('has_mature_content'));
        }

        if ($request->filled('tags')) {
            $tags = explode(',', $request->tags);
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', trim($tag));
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('author', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply sorting - support both sort_by and sort parameters
        $sortBy = $request->get('sort_by', $request->get('sort', 'published_at'));
        $sortOrder = $request->get('sort_order', 'desc');

        // Map common sort aliases
        $sortMapping = [
            'rating' => 'average_rating',
            'readers' => 'total_readers',
            'created_at' => 'published_at',
        ];

        if (isset($sortMapping[$sortBy])) {
            $sortBy = $sortMapping[$sortBy];
        }

        $allowedSorts = ['title', 'published_at', 'average_rating', 'total_readers', 'page_count'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Support both per_page and limit parameters
        $perPage = $request->get('per_page', $request->get('limit', 12));
        $comics = $query->paginate($perPage);

        return response()->json([
            'data' => ComicResource::collection($comics->items()),
            'pagination' => [
                'current_page' => $comics->currentPage(),
                'last_page' => $comics->lastPage(),
                'per_page' => $comics->perPage(),
                'total' => $comics->total(),
            ]
        ]);
    }

    public function show(Comic $comic, Request $request)
    {
        if (!$comic->is_visible) {
            return response()->json(['message' => 'Comic not found'], 404);
        }

        $comic->load([
            'approvedReviews' => function ($query) {
                $query->select('comic_id', 'rating');
            },
            'userProgress' => function ($query) use ($request) {
                if ($request->user()) {
                    $query->where('user_id', $request->user()->id);
                }
            }
        ]);

        return response()->json((new ComicResource($comic))->toArray($request));
    }

    public function featured(Request $request)
    {
        $featured = Comic::query()
            ->where('is_visible', true)
            ->with(['approvedReviews' => function ($query) {
                $query->select('comic_id', 'rating');
            }])
            ->orderBy('total_readers', 'desc')
            ->limit(12) // Get more to filter by actual ratings
            ->get()
            ->filter(function ($comic) {
                return $comic->getCalculatedAverageRating() >= 4.0;
            })
            ->take(6);

        return response()->json(ComicResource::collection($featured)->toArray($request));
    }

    public function newReleases(Request $request)
    {
        $newReleases = Comic::query()
            ->where('is_visible', true)
            ->where('published_at', '>=', now()->subDays(30))
            ->with(['approvedReviews' => function ($query) {
                $query->select('comic_id', 'rating');
            }])
            ->orderBy('published_at', 'desc')
            ->limit(8)
            ->get();

        return response()->json(ComicResource::collection($newReleases)->toArray($request));
    }

    public function genres(): JsonResponse
    {
        $genres = Comic::query()
            ->where('is_visible', true)
            ->whereNotNull('genre')
            ->distinct()
            ->pluck('genre')
            ->sort()
            ->values();

        return response()->json($genres);
    }

    public function tags(): JsonResponse
    {
        $allTags = Comic::query()
            ->where('is_visible', true)
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->sort()
            ->values();

        return response()->json($allTags);
    }

    public function trackView(Request $request, Comic $comic): JsonResponse
    {
        if (!$comic->is_visible) {
            return response()->json(['message' => 'Comic not found'], 404);
        }

        // Record the view
        ComicView::recordView($comic, $request->user(), $request->ip(), session()->getId());

        return response()->json(['message' => 'View tracked successfully']);
    }
}
