<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserGoal;
use App\Models\UserStreak;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GamificationController extends Controller
{
    public function __construct(
        private GamificationService $gamificationService
    ) {}

    public function getStreaks(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $streaks = $this->gamificationService->getUserStreaks($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'streaks' => $streaks,
                    'total' => $streaks->count(),
                    'generated_at' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get user streaks', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load streaks'
            ], 500);
        }
    }

    public function getGoals(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $goals = $this->gamificationService->getUserGoals($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'goals' => $goals,
                    'total' => $goals->count(),
                    'generated_at' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get user goals', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load goals'
            ], 500);
        }
    }

    public function createGoal(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'goal_type' => 'required|in:comics_read,pages_read,hours_reading,series_completed,new_authors,genres_explored,ratings_given,reviews_written',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'target_value' => 'required|integer|min:1|max:10000',
            'period_type' => 'required|in:daily,weekly,monthly,yearly,custom',
            'custom_end_date' => 'required_if:period_type,custom|nullable|date|after:today'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $goal = $this->gamificationService->createGoal($user, $request->validated());

            return response()->json([
                'success' => true,
                'data' => [
                    'goal' => [
                        'id' => $goal->id,
                        'type' => $goal->goal_type,
                        'title' => $goal->title,
                        'description' => $goal->description,
                        'target_value' => $goal->target_value,
                        'current_progress' => $goal->current_progress,
                        'progress_percentage' => $goal->progress_percentage,
                        'period_type' => $goal->period_type,
                        'period_start' => $goal->period_start,
                        'period_end' => $goal->period_end,
                        'is_completed' => $goal->is_completed,
                        'created_at' => $goal->created_at
                    ]
                ],
                'message' => 'Goal created successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create goal', [
                'user_id' => $request->user()?->id,
                'goal_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create goal'
            ], 500);
        }
    }

    public function updateGoal(Request $request, UserGoal $goal): JsonResponse
    {
        if ($goal->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'target_value' => 'sometimes|integer|min:1|max:10000',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $goal->update($request->validated());

            return response()->json([
                'success' => true,
                'data' => [
                    'goal' => [
                        'id' => $goal->id,
                        'title' => $goal->title,
                        'description' => $goal->description,
                        'target_value' => $goal->target_value,
                        'current_progress' => $goal->current_progress,
                        'progress_percentage' => $goal->progress_percentage,
                        'is_active' => $goal->is_active,
                        'updated_at' => $goal->updated_at
                    ]
                ],
                'message' => 'Goal updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update goal', [
                'goal_id' => $goal->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update goal'
            ], 500);
        }
    }

    public function deleteGoal(Request $request, UserGoal $goal): JsonResponse
    {
        if ($goal->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            $goal->delete();

            return response()->json([
                'success' => true,
                'message' => 'Goal deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete goal', [
                'goal_id' => $goal->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete goal'
            ], 500);
        }
    }

    public function getRecommendedGoals(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $recommendedGoals = $this->gamificationService->getRecommendedGoals($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'recommended_goals' => $recommendedGoals,
                    'total' => count($recommendedGoals)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get recommended goals', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load recommended goals'
            ], 500);
        }
    }

    public function getStats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $stats = $this->gamificationService->getGamificationStats($user);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get gamification stats', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load stats'
            ], 500);
        }
    }

    public function trackActivity(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'activity_type' => 'required|in:reading_session,rating_given,review_written,comic_completed',
            'comic_id' => 'required|exists:comics,id',
            'session_data' => 'nullable|array',
            'session_data.pages_read' => 'nullable|integer|min:1',
            'session_data.reading_time_minutes' => 'nullable|integer|min:1',
            'session_data.series_completed' => 'nullable|boolean',
            'rating' => 'required_if:activity_type,rating_given|nullable|integer|min:1|max:5'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $comic = \App\Models\Comic::findOrFail($request->comic_id);

            match ($request->activity_type) {
                'reading_session' => $this->gamificationService->handleReadingSession(
                    $user, 
                    $comic, 
                    $request->session_data ?? []
                ),
                'rating_given' => $this->gamificationService->handleRatingGiven(
                    $user, 
                    $comic, 
                    $request->rating
                ),
                'review_written' => $this->gamificationService->handleReviewWritten($user, $comic),
                'comic_completed' => $this->gamificationService->handleReadingSession(
                    $user, 
                    $comic, 
                    ['series_completed' => true]
                ),
                default => null
            };

            return response()->json([
                'success' => true,
                'message' => 'Activity tracked successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to track gamification activity', [
                'user_id' => $request->user()?->id,
                'activity_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to track activity'
            ], 500);
        }
    }

    public function getGoalTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'goal_types' => UserGoal::getGoalTypes(),
                'period_types' => UserGoal::getPeriodTypes(),
                'streak_types' => UserStreak::getStreakTypes()
            ]
        ]);
    }
}