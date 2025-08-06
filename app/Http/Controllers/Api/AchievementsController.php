<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AchievementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AchievementsController extends Controller
{
    private AchievementService $achievementService;

    public function __construct(AchievementService $achievementService)
    {
        $this->achievementService = $achievementService;
    }

    /**
     * Get user's achievements
     */
    public function getUserAchievements(): JsonResponse
    {
        $user = Auth::user();

        try {
            $achievements = $this->achievementService->getUserAchievements($user);

            return response()->json([
                'success' => true,
                'achievements' => $achievements,
                'total_achievements' => $achievements->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get achievements: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's reading statistics
     */
    public function getReadingStats(): JsonResponse
    {
        $user = Auth::user();

        try {
            $stats = $this->achievementService->getUserReadingStats($user);

            return response()->json([
                'success' => true,
                'reading_stats' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get reading stats: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available achievement types and their descriptions
     */
    public function getAchievementTypes(): JsonResponse
    {
        try {
            $achievementTypes = [];
            
            foreach (AchievementService::ACHIEVEMENT_TYPES as $type => $title) {
                $achievementTypes[$type] = [
                    'title' => $title,
                    'description' => $this->getAchievementDescription($type),
                    'icon' => $this->getAchievementIcon($type),
                ];
            }

            $milestones = AchievementService::MILESTONE_THRESHOLDS;

            return response()->json([
                'success' => true,
                'achievement_types' => $achievementTypes,
                'milestone_thresholds' => $milestones,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get achievement types: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get achievement description
     */
    private function getAchievementDescription(string $type): string
    {
        return match ($type) {
            'first_comic' => 'Complete your first comic',
            'comic_completed' => 'Complete a comic',
            'series_completed' => 'Complete an entire comic series',
            'genre_explorer' => 'Read comics from different genres',
            'speed_reader' => 'Complete a comic in one sitting',
            'collector' => 'Build your comic library',
            'reviewer' => 'Write reviews for comics',
            'social_sharer' => 'Share comics on social media',
            'milestone_reader' => 'Reach reading milestones',
            'binge_reader' => 'Read multiple comics in one day',
            default => 'Achievement description',
        };
    }

    /**
     * Get achievement icon
     */
    private function getAchievementIcon(string $type): string
    {
        return match ($type) {
            'first_comic' => '🎉',
            'comic_completed' => '✅',
            'series_completed' => '🏆',
            'genre_explorer' => '🌟',
            'speed_reader' => '⚡',
            'collector' => '📚',
            'reviewer' => '✍️',
            'social_sharer' => '🦋',
            'milestone_reader' => '📖',
            'binge_reader' => '🔥',
            default => '🏅',
        };
    }
}