<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReadingList;
use App\Models\Comic;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ReadingListController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $lists = $user->readingLists()
            ->withCount('comics')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($list) use ($user) {
                return $this->formatList($list, $user);
            });

        return response()->json([
            'success' => true,
            'data' => [
                'lists' => $lists,
                'total' => $lists->count()
            ]
        ]);
    }

    public function publicLists(Request $request): JsonResponse
    {
        $request->validate([
            'sort' => 'nullable|in:popular,newest,featured',
            'tag' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:50',
            'offset' => 'nullable|integer|min:0'
        ]);

        $query = ReadingList::public()
            ->with(['user:id,name,email', 'comics' => function ($q) {
                $q->take(4);
            }])
            ->withCount('comics');

        // Filter by tag
        if ($request->filled('tag')) {
            $query->whereJsonContains('tags', $request->tag);
        }

        // Sorting
        switch ($request->get('sort', 'popular')) {
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'featured':
                $query->where('is_featured', true)->orderBy('updated_at', 'desc');
                break;
            case 'popular':
            default:
                $query->popular();
                break;
        }

        $limit = $request->get('limit', 20);
        $offset = $request->get('offset', 0);

        $lists = $query->skip($offset)
            ->take($limit)
            ->get()
            ->map(function ($list) {
                return $this->formatPublicList($list);
            });

        return response()->json([
            'success' => true,
            'data' => [
                'lists' => $lists,
                'total' => $lists->count()
            ]
        ]);
    }

    public function show(Request $request, ReadingList $list): JsonResponse
    {
        $user = Auth::user();
        
        // Check if list is private and user doesn't have access
        if (!$list->is_public && (!$user || $list->user_id !== $user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'This list is private'
            ], 403);
        }

        $list->load(['user:id,name,email', 'comics', 'activities' => function ($q) {
            $q->latest()->take(10);
        }]);

        return response()->json([
            'success' => true,
            'data' => [
                'list' => $this->formatDetailedList($list, $user)
            ]
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'nullable|boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        
        $list = ReadingList::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'slug' => Str::slug($request->name . '-' . Str::random(6)),
            'description' => $request->description,
            'is_public' => $request->get('is_public', true),
            'tags' => $request->tags ?? []
        ]);

        // Log activity
        $list->activities()->create([
            'user_id' => $user->id,
            'action' => 'created'
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'list' => $this->formatList($list, $user)
            ],
            'message' => 'Reading list created successfully'
        ], 201);
    }

    public function update(Request $request, ReadingList $list): JsonResponse
    {
        $user = Auth::user();
        
        if (!$list->canBeEditedBy($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'sometimes|boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $list->update($request->only(['name', 'description', 'is_public', 'tags']));

        // Log activity
        $list->activities()->create([
            'user_id' => $user->id,
            'action' => 'updated'
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'list' => $this->formatList($list, $user)
            ],
            'message' => 'Reading list updated successfully'
        ]);
    }

    public function delete(Request $request, ReadingList $list): JsonResponse
    {
        $user = Auth::user();
        
        if (!$list->canBeEditedBy($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $list->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reading list deleted successfully'
        ]);
    }

    public function addComic(Request $request, ReadingList $list, Comic $comic): JsonResponse
    {
        $user = Auth::user();
        
        if (!$list->canBeEditedBy($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Check if comic already in list
        if ($list->comics()->where('comic_id', $comic->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Comic already in list'
            ], 400);
        }

        $notes = $request->get('notes');
        $list->addComic($comic, $notes);

        return response()->json([
            'success' => true,
            'message' => 'Comic added to list'
        ]);
    }

    public function removeComic(Request $request, ReadingList $list, Comic $comic): JsonResponse
    {
        $user = Auth::user();
        
        if (!$list->canBeEditedBy($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $list->removeComic($comic);

        return response()->json([
            'success' => true,
            'message' => 'Comic removed from list'
        ]);
    }

    public function reorderComics(Request $request, ReadingList $list): JsonResponse
    {
        $user = Auth::user();
        
        if (!$list->canBeEditedBy($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'comic_id' => 'required|exists:comics,id',
            'position' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $comic = Comic::findOrFail($request->comic_id);
        $list->moveComic($comic, $request->position);

        return response()->json([
            'success' => true,
            'message' => 'Comic order updated'
        ]);
    }

    public function follow(Request $request, ReadingList $list): JsonResponse
    {
        $user = Auth::user();
        $list->follow($user);

        return response()->json([
            'success' => true,
            'message' => 'Now following list'
        ]);
    }

    public function unfollow(Request $request, ReadingList $list): JsonResponse
    {
        $user = Auth::user();
        $list->unfollow($user);

        return response()->json([
            'success' => true,
            'message' => 'Unfollowed list'
        ]);
    }

    public function like(Request $request, ReadingList $list): JsonResponse
    {
        $user = Auth::user();
        $list->like($user);

        return response()->json([
            'success' => true,
            'message' => 'List liked'
        ]);
    }

    public function unlike(Request $request, ReadingList $list): JsonResponse
    {
        $user = Auth::user();
        $list->unlike($user);

        return response()->json([
            'success' => true,
            'message' => 'List unliked'
        ]);
    }

    public function duplicate(Request $request, ReadingList $list): JsonResponse
    {
        $user = Auth::user();
        
        $newName = $request->get('name', $list->name . ' (Copy)');
        $newList = $list->duplicate($user, $newName);

        return response()->json([
            'success' => true,
            'data' => [
                'list' => $this->formatList($newList, $user)
            ],
            'message' => 'List duplicated successfully'
        ], 201);
    }

    // Helper methods
    private function formatList(ReadingList $list, ?User $user): array
    {
        return [
            'id' => $list->id,
            'name' => $list->name,
            'slug' => $list->slug,
            'description' => $list->description,
            'is_public' => $list->is_public,
            'is_featured' => $list->is_featured,
            'cover_image_url' => $list->cover_image_url,
            'tags' => $list->tags ?? [],
            'comics_count' => $list->comics_count,
            'followers_count' => $list->followers_count,
            'likes_count' => $list->likes_count,
            'created_at' => $list->created_at,
            'updated_at' => $list->updated_at,
            'is_owner' => $user && $list->user_id === $user->id,
            'is_following' => $user && $list->isFollowedBy($user),
            'is_liked' => $user && $list->isLikedBy($user)
        ];
    }

    private function formatPublicList(ReadingList $list): array
    {
        return [
            'id' => $list->id,
            'name' => $list->name,
            'slug' => $list->slug,
            'description' => $list->description,
            'cover_image_url' => $list->cover_image_url,
            'tags' => $list->tags ?? [],
            'comics_count' => $list->comics_count,
            'followers_count' => $list->followers_count,
            'likes_count' => $list->likes_count,
            'is_featured' => $list->is_featured,
            'user' => [
                'id' => $list->user->id,
                'name' => $list->user->name
            ],
            'preview_comics' => $list->comics->take(4)->map(function ($comic) {
                return [
                    'id' => $comic->id,
                    'title' => $comic->title,
                    'cover_image_url' => $comic->getCoverImageUrl()
                ];
            }),
            'created_at' => $list->created_at
        ];
    }

    private function formatDetailedList(ReadingList $list, ?User $user): array
    {
        return [
            'id' => $list->id,
            'name' => $list->name,
            'slug' => $list->slug,
            'description' => $list->description,
            'is_public' => $list->is_public,
            'is_featured' => $list->is_featured,
            'cover_image_url' => $list->cover_image_url,
            'tags' => $list->tags ?? [],
            'comics_count' => $list->comics_count,
            'followers_count' => $list->followers_count,
            'likes_count' => $list->likes_count,
            'share_url' => $list->getShareUrl(),
            'user' => [
                'id' => $list->user->id,
                'name' => $list->user->name
            ],
            'comics' => $list->comics->map(function ($comic) {
                return [
                    'id' => $comic->id,
                    'slug' => $comic->slug,
                    'title' => $comic->title,
                    'author' => $comic->author,
                    'genre' => $comic->genre,
                    'cover_image_url' => $comic->getCoverImageUrl(),
                    'average_rating' => $comic->average_rating,
                    'position' => $comic->pivot->position,
                    'notes' => $comic->pivot->notes,
                    'added_at' => $comic->pivot->added_at
                ];
            }),
            'recent_activity' => $list->activities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'action' => $activity->action,
                    'action_description' => $activity->action_description,
                    'user' => [
                        'id' => $activity->user->id,
                        'name' => $activity->user->name
                    ],
                    'comic' => $activity->comic ? [
                        'id' => $activity->comic->id,
                        'title' => $activity->comic->title
                    ] : null,
                    'created_at' => $activity->created_at
                ];
            }),
            'is_owner' => $user && $list->user_id === $user->id,
            'is_following' => $user && $list->isFollowedBy($user),
            'is_liked' => $user && $list->isLikedBy($user),
            'created_at' => $list->created_at,
            'updated_at' => $list->updated_at
        ];
    }
}