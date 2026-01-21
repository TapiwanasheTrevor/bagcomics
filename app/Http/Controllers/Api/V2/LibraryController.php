<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Comic;
use App\Models\UserLibrary;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LibraryController extends Controller
{
    /**
     * Get user's bookmarked comics
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        $library = UserLibrary::where('user_id', $user->id)
            ->with(['comic.pages'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $library->map(function ($entry) use ($user) {
                $comic = $entry->comic;
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
                    'pages' => $comic->getPageUrls(),
                    'likesCount' => $comic->likes_count ?? 0,
                    'isLiked' => $comic->isLikedByUser($user),
                    'isBookmarked' => true,
                    'isFree' => $comic->is_free,
                    'addedAt' => $entry->created_at->toISOString(),
                    'userRating' => $entry->rating,
                ];
            })
        ]);
    }

    /**
     * Add comic to library (bookmark)
     */
    public function store(Comic $comic): JsonResponse
    {
        $user = Auth::user();

        $library = UserLibrary::firstOrCreate(
            ['user_id' => $user->id, 'comic_id' => $comic->id],
            ['access_type' => $comic->is_free ? 'free' : 'reading']
        );

        return response()->json([
            'message' => 'Added to library',
            'isBookmarked' => true,
        ], 201);
    }

    /**
     * Remove comic from library
     */
    public function destroy(Comic $comic): JsonResponse
    {
        $user = Auth::user();

        UserLibrary::where('user_id', $user->id)
            ->where('comic_id', $comic->id)
            ->delete();

        return response()->json([
            'message' => 'Removed from library',
            'isBookmarked' => false,
        ]);
    }

    /**
     * Update reading progress
     */
    public function updateProgress(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'current_page' => 'required|integer|min:1',
            'total_pages' => 'integer|min:1',
        ]);

        $user = Auth::user();

        // Get or create progress record
        $progress = $comic->userProgress()
            ->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'current_page' => $request->current_page,
                    'total_pages' => $request->total_pages ?? $comic->page_count,
                    'last_read_at' => now(),
                ]
            );

        // Ensure comic is in library
        UserLibrary::firstOrCreate(
            ['user_id' => $user->id, 'comic_id' => $comic->id],
            ['access_type' => 'reading']
        );

        return response()->json([
            'currentPage' => $progress->current_page,
            'totalPages' => $progress->total_pages,
            'percentage' => $progress->getProgressPercentage(),
        ]);
    }
}
