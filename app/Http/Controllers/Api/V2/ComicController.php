<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Comic;
use App\Models\ComicLike;
use App\Models\ComicComment;
use App\Models\UserLibrary;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ComicController extends Controller
{
    /**
     * List all visible comics with optional filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = Comic::visible()
            ->withCount(['likes', 'approvedComments as comments_count', 'pages as page_count_relation']);

        // Genre filter
        if ($genre = $request->get('genre')) {
            $query->where('genre', $genre);
        }

        // Free filter
        if ($request->boolean('is_free')) {
            $query->where('is_free', true);
        }

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('author', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        // Sorting
        $sort = $request->get('sort', 'created_at');
        $direction = $request->get('direction', 'desc');

        match ($sort) {
            'rating' => $query->orderByDesc('average_rating'),
            'popular' => $query->orderByDesc('total_readers'),
            'title' => $query->orderBy('title', $direction),
            default => $query->orderBy('created_at', 'desc'),
        };

        $limit = min($request->get('limit', 20), 50);
        $comics = $query->paginate($limit);

        $user = Auth::user();

        return response()->json([
            'data' => $comics->map(fn($comic) => $this->transformComic($comic, $user)),
            'meta' => [
                'current_page' => $comics->currentPage(),
                'total' => $comics->total(),
                'per_page' => $comics->perPage(),
                'last_page' => $comics->lastPage(),
            ]
        ]);
    }

    /**
     * Get featured/trending comics
     */
    public function featured(): JsonResponse
    {
        $comics = Comic::visible()
            ->withCount(['pages as page_count_relation'])
            ->orderByDesc('average_rating')
            ->orderByDesc('total_readers')
            ->limit(6)
            ->get();

        $user = Auth::user();

        return response()->json([
            'data' => $comics->map(fn($comic) => $this->transformComic($comic, $user))
        ]);
    }

    /**
     * Get recently added comics
     */
    public function recent(): JsonResponse
    {
        $comics = Comic::visible()
            ->withCount(['pages as page_count_relation'])
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        $user = Auth::user();

        return response()->json([
            'data' => $comics->map(fn($comic) => $this->transformComic($comic, $user))
        ]);
    }

    /**
     * Get single comic with pages
     */
    public function show(Comic $comic): JsonResponse
    {
        if (!$comic->is_visible) {
            abort(404);
        }

        $comic->load(['pages', 'approvedComments.user']);
        $user = Auth::user();

        $data = $this->transformComic($comic, $user);
        $data['commentsCount'] = $comic->approvedComments->count();

        // Only include page URLs if comic is free or user has access
        $hasAccess = $comic->is_free || ($user && $user->hasAccessToComic($comic));
        $data['hasAccess'] = $hasAccess;

        if ($hasAccess) {
            $data['pages'] = $comic->getPageUrls();
        } else {
            // Return preview (first 2 pages) for paid comics
            $allPages = $comic->getPageUrls();
            $data['pages'] = array_slice($allPages, 0, 2);
            $data['previewOnly'] = true;
            $data['totalPages'] = count($allPages);
        }

        // User progress if authenticated
        if ($user) {
            $progress = $comic->getProgressForUser($user);
            if ($progress) {
                $data['userProgress'] = [
                    'currentPage' => $progress->current_page,
                    'totalPages' => $progress->total_pages ?? $comic->page_count,
                    'percentage' => $progress->getProgressPercentage(),
                ];
            }
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Get pages for a comic
     */
    public function pages(Comic $comic): JsonResponse
    {
        if (!$comic->is_visible) {
            abort(404);
        }

        $user = Auth::user();
        $hasAccess = $comic->is_free || ($user && $user->hasAccessToComic($comic));

        if (!$hasAccess) {
            return response()->json([
                'error' => 'Purchase required',
                'code' => 'ACCESS_DENIED',
                'isFree' => $comic->is_free,
                'price' => $comic->price,
            ], 403);
        }

        return response()->json([
            'data' => $comic->getPageUrls()
        ]);
    }

    /**
     * Get all genres
     */
    public function genres(): JsonResponse
    {
        $genres = Comic::visible()
            ->select('genre')
            ->distinct()
            ->whereNotNull('genre')
            ->pluck('genre')
            ->filter()
            ->values();

        return response()->json(['data' => $genres]);
    }

    /**
     * Toggle like on a comic
     */
    public function toggleLike(Comic $comic): JsonResponse
    {
        $user = Auth::user();

        $like = ComicLike::where('user_id', $user->id)
            ->where('comic_id', $comic->id)
            ->first();

        if ($like) {
            $like->delete();
            $isLiked = false;
        } else {
            ComicLike::create([
                'user_id' => $user->id,
                'comic_id' => $comic->id,
            ]);
            $isLiked = true;
        }

        return response()->json([
            'isLiked' => $isLiked,
            'likesCount' => $comic->fresh()->likes_count,
        ]);
    }

    /**
     * Rate a comic
     */
    public function rate(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $user = Auth::user();

        // Update or create library entry with rating
        $library = UserLibrary::updateOrCreate(
            ['user_id' => $user->id, 'comic_id' => $comic->id],
            ['rating' => $request->rating]
        );

        // Recalculate average rating
        $comic->updateRating();

        return response()->json([
            'rating' => $request->rating,
            'averageRating' => $comic->fresh()->average_rating,
        ]);
    }

    /**
     * Get comments for a comic
     */
    public function getComments(Comic $comic): JsonResponse
    {
        $comments = $comic->approvedComments()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => $comments->map(fn($comment) => [
                'id' => (string) $comment->id,
                'user' => $comment->user->name,
                'text' => $comment->content,
                'date' => $comment->created_at->toISOString(),
                'isSpoiler' => $comment->is_spoiler,
            ]),
            'meta' => [
                'current_page' => $comments->currentPage(),
                'total' => $comments->total(),
            ]
        ]);
    }

    /**
     * Add a comment
     */
    public function addComment(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|min:1|max:1000',
            'is_spoiler' => 'boolean',
        ]);

        $user = Auth::user();

        $comment = ComicComment::create([
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'content' => $request->content,
            'is_spoiler' => $request->boolean('is_spoiler'),
        ]);

        return response()->json([
            'data' => [
                'id' => (string) $comment->id,
                'user' => $user->name,
                'text' => $comment->content,
                'date' => $comment->created_at->toISOString(),
                'isSpoiler' => $comment->is_spoiler,
            ]
        ], 201);
    }

    /**
     * Transform comic for API response
     */
    private function transformComic(Comic $comic, $user = null): array
    {
        $genres = $comic->genre ? [$comic->genre] : [];
        if ($comic->tags) {
            $genres = array_merge($genres, $comic->getTagsArray());
        }

        return [
            'id' => (string) $comic->id,
            'slug' => $comic->slug,
            'title' => $comic->title,
            'author' => $comic->author ?? 'Unknown',
            'description' => $comic->description ?? '',
            'coverImage' => $comic->cover_image_url,
            'genre' => array_unique($genres),
            'rating' => (float) ($comic->average_rating ?? 0),
            'totalChapters' => $comic->page_count ?? 0,
            'episodes' => $comic->issue_number ?? 1,
            'likesCount' => $comic->likes_count ?? 0,
            'isLiked' => $user ? $comic->isLikedByUser($user) : false,
            'isBookmarked' => $user ? $comic->isBookmarkedByUser($user) : false,
            'isFree' => $comic->is_free,
            'price' => $comic->price,
        ];
    }
}
