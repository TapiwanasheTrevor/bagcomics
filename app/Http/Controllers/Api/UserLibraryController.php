<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comic;
use App\Models\UserLibrary;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class UserLibraryController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->library()
            ->with(['comic' => function ($query) {
                $query->where('is_visible', true);
            }]);

        // Apply filters
        if ($request->filled('access_type')) {
            $query->where('access_type', $request->access_type);
        }

        if ($request->filled('is_favorite')) {
            $query->where('is_favorite', $request->boolean('is_favorite'));
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        $library = $query->paginate($request->get('per_page', 12));

        return response()->json([
            'data' => $library->items(),
            'pagination' => [
                'current_page' => $library->currentPage(),
                'last_page' => $library->lastPage(),
                'per_page' => $library->perPage(),
                'total' => $library->total(),
            ]
        ]);
    }

    public function addToLibrary(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'access_type' => ['required', Rule::in(['free', 'purchased', 'subscription'])],
            'purchase_price' => 'nullable|numeric|min:0',
        ]);

        if (!$comic->is_visible) {
            return response()->json(['message' => 'Comic not found'], 404);
        }

        $libraryEntry = UserLibrary::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'comic_id' => $comic->id,
            ],
            [
                'access_type' => $request->access_type,
                'purchase_price' => $request->purchase_price,
                'purchased_at' => $request->access_type === 'purchased' ? now() : null,
            ]
        );

        // Increment reader count if this is a new addition
        if ($libraryEntry->wasRecentlyCreated) {
            $comic->incrementReaderCount();
        }

        return response()->json([
            'message' => 'Comic added to library successfully',
            'library_entry' => $libraryEntry->load('comic')
        ]);
    }

    public function removeFromLibrary(Request $request, Comic $comic): JsonResponse
    {
        $libraryEntry = $request->user()->library()
            ->where('comic_id', $comic->id)
            ->first();

        if (!$libraryEntry) {
            return response()->json(['message' => 'Comic not found in library'], 404);
        }

        $libraryEntry->delete();

        return response()->json(['message' => 'Comic removed from library successfully']);
    }

    public function toggleFavorite(Request $request, Comic $comic): JsonResponse
    {
        $libraryEntry = $request->user()->library()
            ->where('comic_id', $comic->id)
            ->first();

        if (!$libraryEntry) {
            return response()->json(['message' => 'Comic not found in library'], 404);
        }

        $libraryEntry->is_favorite = !$libraryEntry->is_favorite;
        $libraryEntry->save();

        return response()->json([
            'message' => 'Favorite status updated successfully',
            'is_favorite' => $libraryEntry->is_favorite
        ]);
    }

    public function setRating(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000',
        ]);

        $libraryEntry = $request->user()->library()
            ->where('comic_id', $comic->id)
            ->first();

        if (!$libraryEntry) {
            return response()->json(['message' => 'Comic not found in library'], 404);
        }

        $libraryEntry->setRating($request->rating, $request->review);

        // Update comic's average rating
        $comic->updateRating();

        return response()->json([
            'message' => 'Rating updated successfully',
            'rating' => $libraryEntry->rating,
            'review' => $libraryEntry->review
        ]);
    }

    public function favorites(Request $request): JsonResponse
    {
        $favorites = $request->user()->library()
            ->where('is_favorite', true)
            ->with(['comic' => function ($query) {
                $query->where('is_visible', true);
            }])
            ->paginate($request->get('per_page', 12));

        return response()->json([
            'data' => $favorites->items(),
            'pagination' => [
                'current_page' => $favorites->currentPage(),
                'last_page' => $favorites->lastPage(),
                'per_page' => $favorites->perPage(),
                'total' => $favorites->total(),
            ]
        ]);
    }

    public function recentlyAdded(Request $request): JsonResponse
    {
        $recent = $request->user()->library()
            ->with(['comic' => function ($query) {
                $query->where('is_visible', true);
            }])
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();

        return response()->json($recent);
    }
}
