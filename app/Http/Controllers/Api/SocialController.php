<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Comic;
use App\Models\ReadingList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SocialController extends Controller
{
    public function followUser(Request $request, User $user): JsonResponse
    {
        $currentUser = Auth::user();
        
        if ($currentUser->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot follow yourself'
            ], 400);
        }

        $currentUser->follow($user);

        return response()->json([
            'success' => true,
            'message' => 'User followed successfully'
        ]);
    }

    public function unfollowUser(Request $request, User $user): JsonResponse
    {
        $currentUser = Auth::user();
        $currentUser->unfollow($user);

        return response()->json([
            'success' => true,
            'message' => 'User unfollowed successfully'
        ]);
    }

    public function getUserFollowers(Request $request, User $user): JsonResponse
    {
        $followers = $user->followers()
            ->select('users.id', 'users.name', 'users.email')
            ->withCount(['comics as library_size', 'readingLists as lists_count'])
            ->orderBy('user_follows.created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'followers' => $followers->items(),
                'total' => $followers->total(),
                'current_page' => $followers->currentPage(),
                'last_page' => $followers->lastPage()
            ]
        ]);
    }

    public function getUserFollowing(Request $request, User $user): JsonResponse
    {
        $following = $user->following()
            ->select('users.id', 'users.name', 'users.email')
            ->withCount(['comics as library_size', 'readingLists as lists_count'])
            ->orderBy('user_follows.created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'following' => $following->items(),
                'total' => $following->total(),
                'current_page' => $following->currentPage(),
                'last_page' => $following->lastPage()
            ]
        ]);
    }

    public function getActivityFeed(Request $request): JsonResponse
    {
        $user = Auth::user();
        $limit = $request->get('limit', 20);
        
        // Get activity from users the current user follows
        $followingIds = $user->following()->pluck('users.id');
        
        // Include self for own activity
        $followingIds->push($user->id);
        
        $cacheKey = "activity.feed.{$user->id}.{$limit}";
        
        $activities = Cache::remember($cacheKey, 300, function () use ($followingIds, $limit) {
            $activities = collect();
            
            // Reading list activities
            $listActivities = \App\Models\ReadingListActivity::whereIn('user_id', $followingIds)
                ->with(['user:id,name', 'readingList:id,name,slug', 'comic:id,title,slug'])
                ->whereIn('action', ['created', 'comic_added', 'liked', 'followed'])
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get()
                ->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'type' => 'list_activity',
                        'action' => $activity->action,
                        'user' => [
                            'id' => $activity->user->id,
                            'name' => $activity->user->name
                        ],
                        'list' => [
                            'id' => $activity->readingList->id,
                            'name' => $activity->readingList->name,
                            'slug' => $activity->readingList->slug
                        ],
                        'comic' => $activity->comic ? [
                            'id' => $activity->comic->id,
                            'title' => $activity->comic->title,
                            'slug' => $activity->comic->slug
                        ] : null,
                        'created_at' => $activity->created_at
                    ];
                });
            
            $activities = $activities->merge($listActivities);
            
            // Library additions
            $libraryAdditions = \App\Models\UserLibrary::whereIn('user_id', $followingIds)
                ->with(['user:id,name', 'comic:id,title,slug,cover_image_url'])
                ->where('created_at', '>=', now()->subDays(7))
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'type' => 'library_addition',
                        'action' => 'added_to_library',
                        'user' => [
                            'id' => $item->user->id,
                            'name' => $item->user->name
                        ],
                        'comic' => [
                            'id' => $item->comic->id,
                            'title' => $item->comic->title,
                            'slug' => $item->comic->slug,
                            'cover_image_url' => $item->comic->getCoverImageUrl()
                        ],
                        'created_at' => $item->created_at
                    ];
                });
            
            $activities = $activities->merge($libraryAdditions);
            
            // Reviews
            $reviews = \App\Models\Review::whereIn('user_id', $followingIds)
                ->with(['user:id,name', 'comic:id,title,slug'])
                ->where('created_at', '>=', now()->subDays(7))
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get()
                ->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'type' => 'review',
                        'action' => 'wrote_review',
                        'user' => [
                            'id' => $review->user->id,
                            'name' => $review->user->name
                        ],
                        'comic' => [
                            'id' => $review->comic->id,
                            'title' => $review->comic->title,
                            'slug' => $review->comic->slug
                        ],
                        'content' => Str::limit($review->content, 150),
                        'rating' => $review->rating,
                        'created_at' => $review->created_at
                    ];
                });
            
            $activities = $activities->merge($reviews);
            
            // Sort by date and limit
            return $activities->sortByDesc('created_at')->take($limit)->values();
        });

        return response()->json([
            'success' => true,
            'data' => [
                'activities' => $activities,
                'total' => $activities->count()
            ]
        ]);
    }

    public function getUserProfile(Request $request, User $user): JsonResponse
    {
        $currentUser = Auth::user();
        
        $profile = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'joined_at' => $user->created_at,
            'stats' => [
                'library_size' => $user->library()->count(),
                'lists_count' => $user->readingLists()->public()->count(),
                'followers_count' => $user->followers()->count(),
                'following_count' => $user->following()->count(),
                'reviews_count' => $user->reviews()->count(),
                'average_rating' => round($user->library()->whereNotNull('rating')->avg('rating') ?? 0, 1)
            ],
            'is_following' => $currentUser ? $currentUser->isFollowing($user) : false,
            'is_self' => $currentUser && $currentUser->id === $user->id
        ];
        
        // Get public lists
        $profile['public_lists'] = $user->readingLists()
            ->public()
            ->withCount('comics')
            ->orderBy('updated_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($list) {
                return [
                    'id' => $list->id,
                    'name' => $list->name,
                    'slug' => $list->slug,
                    'description' => $list->description,
                    'comics_count' => $list->comics_count,
                    'followers_count' => $list->followers_count
                ];
            });
        
        // Get recent activity
        $profile['recent_activity'] = $this->getUserRecentActivity($user, 10);
        
        // Get favorite genres
        $profile['favorite_genres'] = $user->library()
            ->join('comics', 'user_library.comic_id', '=', 'comics.id')
            ->whereNotNull('comics.genre')
            ->selectRaw('comics.genre, COUNT(*) as count')
            ->groupBy('comics.genre')
            ->orderByDesc('count')
            ->take(5)
            ->pluck('genre');

        return response()->json([
            'success' => true,
            'data' => [
                'profile' => $profile
            ]
        ]);
    }

    public function getSuggestedUsers(Request $request): JsonResponse
    {
        $user = Auth::user();
        $limit = $request->get('limit', 10);
        
        $cacheKey = "suggested.users.{$user->id}.{$limit}";
        
        $suggestions = Cache::remember($cacheKey, 3600, function () use ($user, $limit) {
            $followingIds = $user->following()->pluck('users.id');
            $followingIds->push($user->id); // Exclude self
            
            // Find users with similar reading preferences
            $similarUsers = User::whereNotIn('id', $followingIds)
                ->select('users.*')
                ->selectRaw('COUNT(DISTINCT ul2.comic_id) as common_comics')
                ->join('user_library as ul1', 'ul1.user_id', '=', 'users.id')
                ->join('user_library as ul2', function ($join) use ($user) {
                    $join->on('ul2.comic_id', '=', 'ul1.comic_id')
                        ->where('ul2.user_id', '=', $user->id);
                })
                ->groupBy('users.id')
                ->having('common_comics', '>=', 3)
                ->orderByDesc('common_comics')
                ->take($limit)
                ->get()
                ->map(function ($suggestedUser) use ($user) {
                    return [
                        'id' => $suggestedUser->id,
                        'name' => $suggestedUser->name,
                        'email' => $suggestedUser->email,
                        'common_comics' => $suggestedUser->common_comics,
                        'library_size' => $suggestedUser->library()->count(),
                        'lists_count' => $suggestedUser->readingLists()->public()->count(),
                        'reason' => "Reads similar comics ({$suggestedUser->common_comics} in common)"
                    ];
                });
            
            return $similarUsers;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'suggestions' => $suggestions,
                'total' => $suggestions->count()
            ]
        ]);
    }

    private function getUserRecentActivity(User $user, int $limit): array
    {
        $activities = [];
        
        // Recent reviews
        $recentReviews = $user->reviews()
            ->with('comic:id,title,slug')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($review) {
                return [
                    'type' => 'review',
                    'comic' => [
                        'id' => $review->comic->id,
                        'title' => $review->comic->title,
                        'slug' => $review->comic->slug
                    ],
                    'rating' => $review->rating,
                    'created_at' => $review->created_at
                ];
            });
        
        foreach ($recentReviews as $review) {
            $activities[] = $review;
        }
        
        // Recent list creations
        $recentLists = $user->readingLists()
            ->public()
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($list) {
                return [
                    'type' => 'list_created',
                    'list' => [
                        'id' => $list->id,
                        'name' => $list->name,
                        'slug' => $list->slug
                    ],
                    'created_at' => $list->created_at
                ];
            });
        
        foreach ($recentLists as $list) {
            $activities[] = $list;
        }
        
        // Sort by date and limit
        usort($activities, function ($a, $b) {
            return $b['created_at']->timestamp - $a['created_at']->timestamp;
        });
        
        return array_slice($activities, 0, $limit);
    }
}