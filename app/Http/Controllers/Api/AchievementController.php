<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AchievementController extends Controller
{
    public function __construct(
        private AchievementService $achievementService
    ) {}

    public function getUserAchievements(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $achievements = $this->achievementService->getUserAchievements($user);

            return response()->json([
                'success' => true,
                'data' => $achievements
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load achievements',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function getUnseenAchievements(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $unseenAchievements = $this->achievementService->getUnseenAchievements($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'achievements' => $unseenAchievements->map(function ($userAchievement) {
                        return [
                            'id' => $userAchievement->achievement->id,
                            'key' => $userAchievement->achievement->key,
                            'name' => $userAchievement->achievement->name,
                            'description' => $userAchievement->achievement->description,
                            'category' => $userAchievement->achievement->category,
                            'type' => $userAchievement->achievement->type,
                            'icon' => $userAchievement->achievement->icon,
                            'color' => $userAchievement->achievement->color,
                            'rarity' => $userAchievement->achievement->rarity,
                            'rarity_display' => $userAchievement->achievement->rarity_display_name,
                            'rarity_color' => $userAchievement->achievement->rarity_color,
                            'points' => $userAchievement->achievement->points,
                            'unlocked_at' => $userAchievement->unlocked_at
                        ];
                    }),
                    'count' => $unseenAchievements->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load unseen achievements'
            ], 500);
        }
    }

    public function markAchievementSeen(Request $request, Achievement $achievement): JsonResponse
    {
        try {
            $user = Auth::user();
            $this->achievementService->markAchievementAsSeen($user, $achievement);

            return response()->json([
                'success' => true,
                'message' => 'Achievement marked as seen'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark achievement as seen'
            ], 500);
        }
    }

    public function checkAchievements(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $newAchievements = $this->achievementService->checkAndUnlockAchievements($user, 'manual_check');

            return response()->json([
                'success' => true,
                'data' => [
                    'new_achievements' => $newAchievements->map(function ($userAchievement) {
                        return [
                            'id' => $userAchievement->achievement->id,
                            'key' => $userAchievement->achievement->key,
                            'name' => $userAchievement->achievement->name,
                            'description' => $userAchievement->achievement->description,
                            'category' => $userAchievement->achievement->category,
                            'type' => $userAchievement->achievement->type,
                            'icon' => $userAchievement->achievement->icon,
                            'color' => $userAchievement->achievement->color,
                            'rarity' => $userAchievement->achievement->rarity,
                            'rarity_display' => $userAchievement->achievement->rarity_display_name,
                            'rarity_color' => $userAchievement->achievement->rarity_color,
                            'points' => $userAchievement->achievement->points,
                            'unlocked_at' => $userAchievement->unlocked_at
                        ];
                    }),
                    'count' => $newAchievements->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check achievements'
            ], 500);
        }
    }

    public function getAchievementCategories(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'categories' => Achievement::getCategories(),
                'rarities' => Achievement::getRarities(),
                'types' => Achievement::getTypes()
            ]
        ]);
    }

    public function getAllAchievements(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'category' => 'nullable|string',
                'rarity' => 'nullable|string',
                'unlocked' => 'nullable|boolean'
            ]);

            $user = Auth::user();
            $query = Achievement::active()->visible();

            if ($request->filled('category')) {
                $query->byCategory($request->category);
            }

            if ($request->filled('rarity')) {
                $query->byRarity($request->rarity);
            }

            $achievements = $query->orderBy('unlock_order')->get();

            // Get user's unlocked achievements
            $unlockedIds = $user->achievements()->pluck('achievement_id')->toArray();

            // Filter by unlocked status if requested
            if ($request->has('unlocked')) {
                if ($request->boolean('unlocked')) {
                    $achievements = $achievements->whereIn('id', $unlockedIds);
                } else {
                    $achievements = $achievements->whereNotIn('id', $unlockedIds);
                }
            }

            $formattedAchievements = $achievements->map(function ($achievement) use ($user, $unlockedIds) {
                $isUnlocked = in_array($achievement->id, $unlockedIds);
                
                $data = [
                    'id' => $achievement->id,
                    'key' => $achievement->key,
                    'name' => $achievement->name,
                    'description' => $achievement->description,
                    'category' => $achievement->category,
                    'type' => $achievement->type,
                    'icon' => $achievement->icon,
                    'color' => $achievement->color,
                    'rarity' => $achievement->rarity,
                    'rarity_display' => $achievement->rarity_display_name,
                    'rarity_color' => $achievement->rarity_color,
                    'points' => $achievement->points,
                    'requirements' => $achievement->requirements,
                    'unlock_order' => $achievement->unlock_order,
                    'is_unlocked' => $isUnlocked
                ];

                if ($isUnlocked) {
                    $userAchievement = $user->userAchievements()
                        ->where('achievement_id', $achievement->id)
                        ->first();
                    
                    if ($userAchievement) {
                        $data['unlocked_at'] = $userAchievement->unlocked_at;
                        $data['is_seen'] = $userAchievement->is_seen;
                    }
                } else {
                    $data['progress'] = $this->achievementService->calculateProgress($user, $achievement);
                }

                return $data;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'achievements' => $formattedAchievements->values(),
                    'total' => $formattedAchievements->count(),
                    'filters' => [
                        'category' => $request->category,
                        'rarity' => $request->rarity,
                        'unlocked' => $request->unlocked
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load achievements'
            ], 500);
        }
    }
}