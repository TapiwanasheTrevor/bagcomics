<?php

namespace App\Services;

use App\Models\Comic;
use App\Models\User;
use App\Models\UserComicProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AchievementService
{
    const ACHIEVEMENT_TYPES = [
        'first_comic' => 'First Comic Read',
        'comic_completed' => 'Comic Completed',
        'series_completed' => 'Series Completed',
        'genre_explorer' => 'Genre Explorer',
        'speed_reader' => 'Speed Reader',
        'collector' => 'Collector',
        'reviewer' => 'Reviewer',
        'social_sharer' => 'Social Sharer',
        'milestone_reader' => 'Milestone Reader',
        'binge_reader' => 'Binge Reader',
    ];

    const MILESTONE_THRESHOLDS = [
        'comics_read' => [1, 5, 10, 25, 50, 100, 250, 500, 1000],
        'pages_read' => [100, 500, 1000, 2500, 5000, 10000, 25000, 50000],
        'genres_explored' => [3, 5, 10, 15, 20],
        'series_completed' => [1, 3, 5, 10, 20],
        'reviews_written' => [1, 5, 10, 25, 50, 100],
        'social_shares' => [1, 5, 10, 25, 50],
    ];

    private SocialSharingService $socialSharingService;

    public function __construct(SocialSharingService $socialSharingService)
    {
        $this->socialSharingService = $socialSharingService;
    }

    /**
     * Check and award achievements for a user action
     */
    public function checkAchievements(User $user, string $action, array $context = []): array
    {
        $newAchievements = [];

        switch ($action) {
            case 'comic_completed':
                $newAchievements = array_merge(
                    $newAchievements,
                    $this->checkComicCompletionAchievements($user, $context['comic'] ?? null)
                );
                break;

            case 'progress_updated':
                $newAchievements = array_merge(
                    $newAchievements,
                    $this->checkProgressAchievements($user, $context)
                );
                break;

            case 'review_submitted':
                $newAchievements = array_merge(
                    $newAchievements,
                    $this->checkReviewAchievements($user)
                );
                break;

            case 'social_share':
                $newAchievements = array_merge(
                    $newAchievements,
                    $this->checkSocialSharingAchievements($user)
                );
                break;

            case 'comic_purchased':
                $newAchievements = array_merge(
                    $newAchievements,
                    $this->checkCollectorAchievements($user)
                );
                break;
        }

        // Award new achievements
        foreach ($newAchievements as $achievement) {
            $this->awardAchievement($user, $achievement);
        }

        return $newAchievements;
    }

    /**
     * Check achievements related to comic completion
     */
    private function checkComicCompletionAchievements(User $user, ?Comic $comic): array
    {
        $achievements = [];
        $completedComics = $this->getCompletedComicsCount($user);

        // First comic achievement
        if ($completedComics === 1 && !$this->hasAchievement($user, 'first_comic')) {
            $achievements[] = [
                'type' => 'first_comic',
                'title' => 'First Steps',
                'description' => 'Completed your first comic!',
                'icon' => '🎉',
                'comic' => $comic,
            ];
        }

        // Milestone achievements
        foreach (self::MILESTONE_THRESHOLDS['comics_read'] as $threshold) {
            if ($completedComics === $threshold && !$this->hasMilestoneAchievement($user, 'milestone_reader', $threshold)) {
                $achievements[] = [
                    'type' => 'milestone_reader',
                    'title' => "Comic Enthusiast - {$threshold}",
                    'description' => "Completed {$threshold} comics!",
                    'icon' => '📚',
                    'milestone' => $threshold,
                ];
            }
        }

        // Series completion achievement
        if ($comic && $comic->series) {
            $seriesCompletion = $this->checkSeriesCompletion($user, $comic->series);
            if ($seriesCompletion['completed']) {
                $achievements[] = [
                    'type' => 'series_completed',
                    'title' => 'Series Master',
                    'description' => "Completed the entire '{$comic->series->name}' series!",
                    'icon' => '🏆',
                    'series' => $comic->series,
                ];
            }
        }

        // Genre explorer achievement
        $genresExplored = $this->getGenresExploredCount($user);
        foreach (self::MILESTONE_THRESHOLDS['genres_explored'] as $threshold) {
            if ($genresExplored === $threshold && !$this->hasMilestoneAchievement($user, 'genre_explorer', $threshold)) {
                $achievements[] = [
                    'type' => 'genre_explorer',
                    'title' => "Genre Explorer - {$threshold}",
                    'description' => "Explored {$threshold} different genres!",
                    'icon' => '🌟',
                    'milestone' => $threshold,
                ];
            }
        }

        // Speed reader achievement (completed comic in one session)
        if ($comic && $this->isSpeedRead($user, $comic)) {
            $achievements[] = [
                'type' => 'speed_reader',
                'title' => 'Speed Reader',
                'description' => "Read '{$comic->title}' in one sitting!",
                'icon' => '⚡',
                'comic' => $comic,
            ];
        }

        return $achievements;
    }

    /**
     * Check achievements related to reading progress
     */
    private function checkProgressAchievements(User $user, array $context): array
    {
        $achievements = [];
        $totalPagesRead = $this->getTotalPagesRead($user);

        // Pages read milestones
        foreach (self::MILESTONE_THRESHOLDS['pages_read'] as $threshold) {
            if ($totalPagesRead === $threshold) {
                $achievements[] = [
                    'type' => 'milestone_reader',
                    'title' => "Page Turner - {$threshold}",
                    'description' => "Read {$threshold} pages in total!",
                    'icon' => '📖',
                    'milestone' => $threshold,
                ];
            }
        }

        // Binge reader achievement (multiple comics in one day)
        if ($this->isBingeReadingDay($user)) {
            $achievements[] = [
                'type' => 'binge_reader',
                'title' => 'Binge Reader',
                'description' => 'Read multiple comics in one day!',
                'icon' => '🔥',
                'date' => now()->toDateString(),
            ];
        }

        return $achievements;
    }

    /**
     * Check achievements related to reviews
     */
    private function checkReviewAchievements(User $user): array
    {
        $achievements = [];
        $reviewsCount = $user->reviews()->count();

        foreach (self::MILESTONE_THRESHOLDS['reviews_written'] as $threshold) {
            if ($reviewsCount === $threshold && !$this->hasMilestoneAchievement($user, 'reviewer', $threshold)) {
                $achievements[] = [
                    'type' => 'reviewer',
                    'title' => "Critic - {$threshold}",
                    'description' => "Written {$threshold} reviews!",
                    'icon' => '✍️',
                    'milestone' => $threshold,
                ];
            }
        }

        return $achievements;
    }

    /**
     * Check achievements related to social sharing
     */
    private function checkSocialSharingAchievements(User $user): array
    {
        $achievements = [];
        $sharesCount = $user->socialShares()->count();

        foreach (self::MILESTONE_THRESHOLDS['social_shares'] as $threshold) {
            if ($sharesCount === $threshold && !$this->hasMilestoneAchievement($user, 'social_sharer', $threshold)) {
                $achievements[] = [
                    'type' => 'social_sharer',
                    'title' => "Social Butterfly - {$threshold}",
                    'description' => "Shared {$threshold} comics on social media!",
                    'icon' => '🦋',
                    'milestone' => $threshold,
                ];
            }
        }

        return $achievements;
    }

    /**
     * Check achievements related to collecting comics
     */
    private function checkCollectorAchievements(User $user): array
    {
        $achievements = [];
        $librarySize = $user->library()->count();

        // Collector milestones
        $collectorThresholds = [5, 10, 25, 50, 100, 250, 500];
        foreach ($collectorThresholds as $threshold) {
            if ($librarySize === $threshold) {
                $achievements[] = [
                    'type' => 'collector',
                    'title' => "Collector - {$threshold}",
                    'description' => "Built a library of {$threshold} comics!",
                    'icon' => '📚',
                    'milestone' => $threshold,
                ];
            }
        }

        return $achievements;
    }

    /**
     * Award an achievement to a user
     */
    private function awardAchievement(User $user, array $achievement): void
    {
        // Store achievement in user's profile or separate achievements table
        $userAchievements = $user->achievements ?? [];
        
        $achievementData = array_merge($achievement, [
            'awarded_at' => now()->toISOString(),
            'id' => uniqid(),
        ]);
        
        $userAchievements[] = $achievementData;
        $user->achievements = $userAchievements;
        $user->save();

        // Log the achievement
        Log::info('Achievement awarded', [
            'user_id' => $user->id,
            'achievement_type' => $achievement['type'],
            'achievement_title' => $achievement['title'],
        ]);

        // Generate sharing suggestions
        $this->generateAchievementSharingSuggestions($user, $achievement);
    }

    /**
     * Generate sharing suggestions for achievements
     */
    private function generateAchievementSharingSuggestions(User $user, array $achievement): void
    {
        $suggestions = [];

        switch ($achievement['type']) {
            case 'first_comic':
            case 'comic_completed':
                if (isset($achievement['comic'])) {
                    $suggestions = $this->socialSharingService->trackReadingAchievement(
                        $user,
                        $achievement['comic'],
                        'completed',
                        $achievement
                    );
                }
                break;

            case 'milestone_reader':
            case 'series_completed':
                $suggestions = [
                    'sharing_suggestions' => [
                        'facebook' => "Share your reading milestone with friends!",
                        'twitter' => "Tweet about your achievement!",
                    ]
                ];
                break;
        }

        // Cache suggestions for the user (only if achievement has an ID)
        if (!empty($suggestions) && isset($achievement['id'])) {
            Cache::put(
                "achievement_sharing_{$user->id}_{$achievement['id']}",
                $suggestions,
                now()->addHours(24)
            );
        }
    }

    /**
     * Get user's achievements
     */
    public function getUserAchievements(User $user): Collection
    {
        $achievements = $user->achievements ?? [];
        
        return collect($achievements)->sortByDesc('awarded_at')->values();
    }

    /**
     * Get user's reading statistics
     */
    public function getUserReadingStats(User $user): array
    {
        return [
            'comics_completed' => $this->getCompletedComicsCount($user),
            'total_pages_read' => $this->getTotalPagesRead($user),
            'genres_explored' => $this->getGenresExploredCount($user),
            'series_completed' => $this->getCompletedSeriesCount($user),
            'reviews_written' => $user->reviews()->count(),
            'social_shares' => $user->socialShares()->count(),
            'library_size' => $user->library()->count(),
            'reading_streak' => $this->getReadingStreak($user),
            'favorite_genre' => $this->getFavoriteGenre($user),
            'average_rating_given' => $this->getAverageRatingGiven($user),
        ];
    }

    /**
     * Get completed comics count
     */
    private function getCompletedComicsCount(User $user): int
    {
        return $user->comicProgress()
            ->where('is_completed', true)
            ->count();
    }

    /**
     * Get total pages read
     */
    private function getTotalPagesRead(User $user): int
    {
        return $user->comicProgress()
            ->where('is_completed', true)
            ->join('comics', 'user_comic_progress.comic_id', '=', 'comics.id')
            ->sum('comics.page_count') ?? 0;
    }

    /**
     * Get genres explored count
     */
    private function getGenresExploredCount(User $user): int
    {
        return $user->comicProgress()
            ->where('is_completed', true)
            ->join('comics', 'user_comic_progress.comic_id', '=', 'comics.id')
            ->distinct('comics.genre')
            ->count('comics.genre');
    }

    /**
     * Get completed series count
     */
    private function getCompletedSeriesCount(User $user): int
    {
        // This would require more complex logic to determine if a user has completed entire series
        // For now, return a placeholder
        return 0;
    }

    /**
     * Check if series is completed by user
     */
    private function checkSeriesCompletion(User $user, $series): array
    {
        $seriesComics = $series->comics;
        $userCompletedComics = $user->comicProgress()
            ->where('is_completed', true)
            ->whereIn('comic_id', $seriesComics->pluck('id'))
            ->count();

        return [
            'completed' => $userCompletedComics === $seriesComics->count(),
            'progress' => $userCompletedComics,
            'total' => $seriesComics->count(),
        ];
    }

    /**
     * Check if comic was speed read (completed in one session)
     */
    private function isSpeedRead(User $user, Comic $comic): bool
    {
        $progress = $user->comicProgress()
            ->where('comic_id', $comic->id)
            ->first();

        if (!$progress || !$progress->is_completed) {
            return false;
        }

        // Check if started and completed on the same day
        return $progress->created_at->isSameDay($progress->updated_at);
    }

    /**
     * Check if user is binge reading today
     */
    private function isBingeReadingDay(User $user): bool
    {
        $todayCompletions = $user->comicProgress()
            ->where('is_completed', true)
            ->whereDate('updated_at', now()->toDateString())
            ->count();

        return $todayCompletions >= 3; // 3 or more comics in one day
    }

    /**
     * Get user's reading streak
     */
    private function getReadingStreak(User $user): int
    {
        // Implementation would track consecutive days of reading activity
        // For now, return a placeholder
        return 0;
    }

    /**
     * Get user's favorite genre
     */
    private function getFavoriteGenre(User $user): ?string
    {
        return $user->comicProgress()
            ->where('is_completed', true)
            ->join('comics', 'user_comic_progress.comic_id', '=', 'comics.id')
            ->selectRaw('comics.genre, COUNT(*) as count')
            ->groupBy('comics.genre')
            ->orderByDesc('count')
            ->first()?->genre;
    }

    /**
     * Get average rating given by user
     */
    private function getAverageRatingGiven(User $user): float
    {
        return $user->reviews()->avg('rating') ?? 0.0;
    }

    /**
     * Check if user has a specific achievement type
     */
    private function hasAchievement(User $user, string $type): bool
    {
        $achievements = $user->achievements ?? [];
        
        return collect($achievements)->contains('type', $type);
    }

    /**
     * Check if user has a specific milestone achievement
     */
    private function hasMilestoneAchievement(User $user, string $type, int $milestone): bool
    {
        $achievements = $user->achievements ?? [];
        
        return collect($achievements)->contains(function ($achievement) use ($type, $milestone) {
            return $achievement['type'] === $type && 
                   isset($achievement['milestone']) && 
                   $achievement['milestone'] === $milestone;
        });
    }
}