<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserStreak;
use App\Models\UserGoal;
use App\Models\Comic;
use App\Models\UserLibrary;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GamificationService
{
    public function updateReadingStreak(User $user): UserStreak
    {
        return UserStreak::createOrUpdateStreak($user, 'daily_reading');
    }

    public function updateRatingStreak(User $user): UserStreak
    {
        return UserStreak::createOrUpdateStreak($user, 'rating_streak');
    }

    public function updateDiscoveryStreak(User $user): UserStreak
    {
        return UserStreak::createOrUpdateStreak($user, 'discovery_streak');
    }

    public function updateCompletionStreak(User $user): UserStreak
    {
        return UserStreak::createOrUpdateStreak($user, 'weekly_completion');
    }

    public function getUserStreaks(User $user): Collection
    {
        return $user->streaks()->active()->get()->map(function ($streak) {
            return [
                'id' => $streak->id,
                'type' => $streak->streak_type,
                'current_count' => $streak->current_count,
                'longest_count' => $streak->longest_count,
                'status' => $streak->streak_status,
                'days_until_break' => $streak->days_until_break,
                'started_at' => $streak->started_at,
                'last_activity' => $streak->last_activity_date,
                'display_name' => $this->getStreakDisplayName($streak->streak_type),
                'description' => $this->getStreakDescription($streak->streak_type),
                'icon' => $this->getStreakIcon($streak->streak_type),
                'color' => $this->getStreakColor($streak->streak_type)
            ];
        });
    }

    public function createGoal(User $user, array $goalData): UserGoal
    {
        return UserGoal::createGoal($user, $goalData);
    }

    public function updateGoalProgress(User $user, string $goalType, int $increment = 1): void
    {
        $goals = $user->goals()
            ->active()
            ->inProgress()
            ->currentPeriod()
            ->where('goal_type', $goalType)
            ->get();

        foreach ($goals as $goal) {
            $goal->updateProgress($increment);
        }
    }

    public function getUserGoals(User $user): Collection
    {
        return $user->goals()
            ->active()
            ->currentPeriod()
            ->get()
            ->map(function ($goal) {
                return [
                    'id' => $goal->id,
                    'type' => $goal->goal_type,
                    'title' => $goal->title,
                    'description' => $goal->description,
                    'target_value' => $goal->target_value,
                    'current_progress' => $goal->current_progress,
                    'progress_percentage' => $goal->progress_percentage,
                    'remaining' => $goal->remaining,
                    'days_remaining' => $goal->days_remaining,
                    'is_completed' => $goal->is_completed,
                    'is_overdue' => $goal->is_overdue,
                    'period_type' => $goal->period_type,
                    'period_start' => $goal->period_start,
                    'period_end' => $goal->period_end,
                    'completed_at' => $goal->completed_at,
                    'difficulty' => $this->getGoalDifficulty($goal),
                    'icon' => $this->getGoalIcon($goal->goal_type),
                    'color' => $this->getGoalColor($goal->goal_type)
                ];
            });
    }

    public function getRecommendedGoals(User $user): array
    {
        return UserGoal::getRecommendedGoals($user);
    }

    public function getGamificationStats(User $user): array
    {
        $cacheKey = "gamification.stats.{$user->id}";
        
        return Cache::remember($cacheKey, 1800, function () use ($user) {
            $streaks = $user->streaks()->get();
            $goals = $user->goals()->get();
            $completedGoals = $goals->where('is_completed', true);
            $activeGoals = $goals->where('is_active', true)->where('is_completed', false);

            return [
                'streaks' => [
                    'total_active' => $streaks->where('is_active', true)->count(),
                    'longest_streak' => $streaks->max('longest_count') ?? 0,
                    'current_best_streak' => $streaks->where('is_active', true)->max('current_count') ?? 0,
                    'streaks_broken_this_month' => $this->getStreaksBrokenThisMonth($user),
                ],
                'goals' => [
                    'total_set' => $goals->count(),
                    'completed' => $completedGoals->count(),
                    'active' => $activeGoals->count(),
                    'completion_rate' => $goals->count() > 0 ? round(($completedGoals->count() / $goals->count()) * 100, 1) : 0,
                    'this_month_completed' => $completedGoals->where('completed_at', '>=', Carbon::now()->startOfMonth())->count(),
                ],
                'achievements' => [
                    'level' => $this->calculateUserLevel($user),
                    'total_points' => $this->calculateTotalPoints($user),
                    'next_level_points' => $this->getNextLevelPoints($user),
                    'recent_achievements' => $this->getRecentAchievements($user),
                ],
                'reading_stats' => $this->getReadingStats($user)
            ];
        });
    }

    public function handleReadingSession(User $user, Comic $comic, array $sessionData): void
    {
        // Update reading streak
        $this->updateReadingStreak($user);

        // Update relevant goals
        $this->updateGoalProgress($user, 'comics_read');
        
        if (isset($sessionData['pages_read'])) {
            $this->updateGoalProgress($user, 'pages_read', $sessionData['pages_read']);
        }
        
        if (isset($sessionData['reading_time_minutes'])) {
            $this->updateGoalProgress($user, 'hours_reading', ceil($sessionData['reading_time_minutes'] / 60));
        }

        // Check for new author discovery
        $hasReadAuthorBefore = $user->library()
            ->join('comics', 'user_library.comic_id', '=', 'comics.id')
            ->where('comics.author', $comic->author)
            ->where('user_library.comic_id', '!=', $comic->id)
            ->exists();

        if (!$hasReadAuthorBefore && $comic->author) {
            $this->updateGoalProgress($user, 'new_authors');
            $this->updateDiscoveryStreak($user);
        }

        // Check for series completion
        if (isset($sessionData['series_completed']) && $sessionData['series_completed']) {
            $this->updateGoalProgress($user, 'series_completed');
            $this->updateCompletionStreak($user);
        }

        Log::info('Gamification updated for reading session', [
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'session_data' => $sessionData
        ]);
    }

    public function handleRatingGiven(User $user, Comic $comic, int $rating): void
    {
        $this->updateRatingStreak($user);
        $this->updateGoalProgress($user, 'ratings_given');

        Log::info('Gamification updated for rating', [
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'rating' => $rating
        ]);
    }

    public function handleReviewWritten(User $user, Comic $comic): void
    {
        $this->updateGoalProgress($user, 'reviews_written');
        
        Log::info('Gamification updated for review', [
            'user_id' => $user->id,
            'comic_id' => $comic->id
        ]);
    }

    private function getStreakDisplayName(string $type): string
    {
        return match ($type) {
            'daily_reading' => 'Daily Reading',
            'weekly_completion' => 'Weekly Completion',
            'rating_streak' => 'Rating Streak',
            'discovery_streak' => 'Discovery Streak',
            default => ucfirst(str_replace('_', ' ', $type))
        };
    }

    private function getStreakDescription(string $type): string
    {
        return match ($type) {
            'daily_reading' => 'Read comics every day',
            'weekly_completion' => 'Complete at least one comic per week',
            'rating_streak' => 'Rate comics consistently',
            'discovery_streak' => 'Discover new authors regularly',
            default => 'Keep up your progress'
        };
    }

    private function getStreakIcon(string $type): string
    {
        return match ($type) {
            'daily_reading' => 'book-open',
            'weekly_completion' => 'check-circle',
            'rating_streak' => 'star',
            'discovery_streak' => 'compass',
            default => 'zap'
        };
    }

    private function getStreakColor(string $type): string
    {
        return match ($type) {
            'daily_reading' => 'blue',
            'weekly_completion' => 'green',
            'rating_streak' => 'yellow',
            'discovery_streak' => 'purple',
            default => 'gray'
        };
    }

    private function getGoalIcon(string $type): string
    {
        return match ($type) {
            'comics_read' => 'book',
            'pages_read' => 'file-text',
            'hours_reading' => 'clock',
            'series_completed' => 'check-square',
            'new_authors' => 'users',
            'genres_explored' => 'map',
            'ratings_given' => 'star',
            'reviews_written' => 'edit-3',
            default => 'target'
        };
    }

    private function getGoalColor(string $type): string
    {
        return match ($type) {
            'comics_read' => 'blue',
            'pages_read' => 'indigo',
            'hours_reading' => 'green',
            'series_completed' => 'emerald',
            'new_authors' => 'purple',
            'genres_explored' => 'pink',
            'ratings_given' => 'yellow',
            'reviews_written' => 'orange',
            default => 'gray'
        };
    }

    private function getGoalDifficulty(UserGoal $goal): string
    {
        $progressRate = $goal->current_progress / max(1, $goal->target_value);
        $timeRate = Carbon::today()->diffInDays($goal->period_start) / max(1, $goal->period_start->diffInDays($goal->period_end));
        
        if ($progressRate >= $timeRate * 1.5) {
            return 'easy';
        } elseif ($progressRate >= $timeRate * 0.8) {
            return 'medium';
        } else {
            return 'hard';
        }
    }

    private function calculateUserLevel(User $user): int
    {
        $points = $this->calculateTotalPoints($user);
        return min(100, floor($points / 1000) + 1);
    }

    private function calculateTotalPoints(User $user): int
    {
        $points = 0;
        
        // Points from streaks
        $streaks = $user->streaks()->get();
        foreach ($streaks as $streak) {
            $points += $streak->longest_count * 10;
            if ($streak->is_active && $streak->current_count >= 7) {
                $points += 50; // Bonus for active week+ streaks
            }
        }
        
        // Points from completed goals
        $completedGoals = $user->goals()->where('is_completed', true)->get();
        foreach ($completedGoals as $goal) {
            $points += match ($goal->period_type) {
                'daily' => 25,
                'weekly' => 100,
                'monthly' => 500,
                'yearly' => 2000,
                default => 100
            };
        }
        
        // Points from library stats
        $points += $user->library()->count() * 5; // 5 points per comic in library
        $points += $user->library()->whereNotNull('rating')->count() * 2; // 2 points per rating
        
        return $points;
    }

    private function getNextLevelPoints(User $user): int
    {
        $currentLevel = $this->calculateUserLevel($user);
        return ($currentLevel * 1000) - $this->calculateTotalPoints($user);
    }

    private function getRecentAchievements(User $user): array
    {
        // This would typically come from an achievements table
        // For now, return recent completed goals and streak milestones
        $recentGoals = $user->goals()
            ->where('is_completed', true)
            ->where('completed_at', '>=', Carbon::now()->subDays(30))
            ->latest('completed_at')
            ->take(5)
            ->get()
            ->map(function ($goal) {
                return [
                    'type' => 'goal_completed',
                    'title' => "Completed: {$goal->title}",
                    'achieved_at' => $goal->completed_at,
                    'icon' => $this->getGoalIcon($goal->goal_type),
                    'color' => $this->getGoalColor($goal->goal_type)
                ];
            });

        return $recentGoals->toArray();
    }

    private function getStreaksBrokenThisMonth(User $user): int
    {
        // This would require tracking streak breaks in the database
        // For now, return 0 as a placeholder
        return 0;
    }

    private function getReadingStats(User $user): array
    {
        $library = $user->library()->with('comic')->get();
        
        return [
            'total_comics' => $library->count(),
            'completed_comics' => $library->whereNotNull('progress')->where('progress.is_completed', true)->count(),
            'average_rating' => round($library->whereNotNull('rating')->avg('rating') ?? 0, 1),
            'favorite_genres' => $library->pluck('comic.genre')
                ->filter()
                ->countBy()
                ->sortByDesc(fn($count) => $count)
                ->take(3)
                ->keys()
                ->toArray(),
            'reading_days_this_month' => $this->getReadingDaysThisMonth($user),
            'pages_read_this_month' => $this->getPagesReadThisMonth($user)
        ];
    }

    private function getReadingDaysThisMonth(User $user): int
    {
        // This would require tracking daily reading activity
        // For now, return a placeholder based on current streak
        $readingStreak = $user->streaks()->where('streak_type', 'daily_reading')->first();
        return $readingStreak && $readingStreak->is_active ? min($readingStreak->current_count, Carbon::now()->day) : 0;
    }

    private function getPagesReadThisMonth(User $user): int
    {
        // This would require tracking page reading activity
        // For now, return an estimate based on completed comics this month
        $thisMonth = Carbon::now()->startOfMonth();
        $completedThisMonth = $user->library()
            ->whereHas('progress', function ($q) use ($thisMonth) {
                $q->where('is_completed', true)->where('updated_at', '>=', $thisMonth);
            })
            ->with('comic')
            ->get();
            
        return $completedThisMonth->sum('comic.page_count') ?? 0;
    }
}