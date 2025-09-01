<?php

namespace App\Services;

use App\Models\User;
use App\Models\Achievement;
use App\Models\UserAchievement;
use App\Models\Comic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AchievementService
{
    public function checkAndUnlockAchievements(User $user, ?string $trigger = null): Collection
    {
        $newAchievements = collect();
        
        // Get all active achievements that user doesn't have
        $availableAchievements = Achievement::active()
            ->whereNotIn('id', $user->achievements()->pluck('achievement_id'))
            ->orderBy('unlock_order')
            ->get();

        foreach ($availableAchievements as $achievement) {
            if ($achievement->checkUnlockConditions($user)) {
                $userAchievement = $user->unlockAchievement($achievement);
                $newAchievements->push($userAchievement);
                
                Log::info('Achievement unlocked', [
                    'user_id' => $user->id,
                    'achievement_key' => $achievement->key,
                    'achievement_name' => $achievement->name,
                    'trigger' => $trigger
                ]);
            }
        }

        return $newAchievements;
    }

    public function getUserAchievements(User $user): array
    {
        $cacheKey = "user.achievements.{$user->id}";
        
        return Cache::remember($cacheKey, 1800, function () use ($user) {
            $userAchievements = $user->userAchievements()
                ->with('achievement')
                ->orderBy('unlocked_at', 'desc')
                ->get();

            $achievements = Achievement::active()->get();
            $unlockedIds = $userAchievements->pluck('achievement_id')->toArray();

            return [
                'unlocked' => $userAchievements->map(function ($ua) {
                    return [
                        'id' => $ua->achievement->id,
                        'key' => $ua->achievement->key,
                        'name' => $ua->achievement->name,
                        'description' => $ua->achievement->description,
                        'category' => $ua->achievement->category,
                        'type' => $ua->achievement->type,
                        'icon' => $ua->achievement->icon,
                        'color' => $ua->achievement->color,
                        'rarity' => $ua->achievement->rarity,
                        'rarity_display' => $ua->achievement->rarity_display_name,
                        'rarity_color' => $ua->achievement->rarity_color,
                        'points' => $ua->achievement->points,
                        'unlocked_at' => $ua->unlocked_at,
                        'progress_data' => $ua->progress_data,
                        'is_seen' => $ua->is_seen
                    ];
                })->toArray(),
                'locked' => $achievements
                    ->whereNotIn('id', $unlockedIds)
                    ->where('is_hidden', false)
                    ->map(function ($achievement) use ($user) {
                        return [
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
                            'progress' => $this->calculateProgress($user, $achievement)
                        ];
                    })->values()->toArray(),
                'stats' => $this->getAchievementStats($user, $userAchievements, $achievements)
            ];
        });
    }

    public function getAchievementStats(User $user, Collection $userAchievements, Collection $allAchievements): array
    {
        $totalAchievements = $allAchievements->where('is_hidden', false)->count();
        $unlockedCount = $userAchievements->count();
        $totalPoints = $userAchievements->sum('achievement.points');
        
        $rarityBreakdown = $userAchievements->groupBy('achievement.rarity')
            ->map(function ($group) {
                return $group->count();
            })
            ->toArray();

        $categoryBreakdown = $userAchievements->groupBy('achievement.category')
            ->map(function ($group) {
                return $group->count();
            })
            ->toArray();

        $recentAchievements = $userAchievements
            ->where('unlocked_at', '>=', now()->subDays(30))
            ->count();

        return [
            'total_achievements' => $totalAchievements,
            'unlocked_count' => $unlockedCount,
            'locked_count' => $totalAchievements - $unlockedCount,
            'completion_percentage' => $totalAchievements > 0 ? round(($unlockedCount / $totalAchievements) * 100, 1) : 0,
            'total_points' => $totalPoints,
            'recent_achievements' => $recentAchievements,
            'rarity_breakdown' => array_merge([
                'common' => 0,
                'uncommon' => 0,
                'rare' => 0,
                'epic' => 0,
                'legendary' => 0
            ], $rarityBreakdown),
            'category_breakdown' => array_merge([
                'reading' => 0,
                'social' => 0,
                'collection' => 0,
                'engagement' => 0,
                'milestone' => 0,
                'special' => 0
            ], $categoryBreakdown)
        ];
    }

    private function calculateProgress(User $user, Achievement $achievement): array
    {
        $progress = [];
        
        foreach ($achievement->requirements as $requirement) {
            $type = $requirement['type'] ?? '';
            $targetValue = $requirement['value'] ?? 0;
            $currentValue = $this->getCurrentValue($user, $type, $requirement);
            
            $progress[] = [
                'type' => $type,
                'current' => $currentValue,
                'target' => $targetValue,
                'percentage' => $targetValue > 0 ? min(100, round(($currentValue / $targetValue) * 100, 1)) : 0,
                'description' => $this->getProgressDescription($type, $currentValue, $targetValue)
            ];
        }
        
        return $progress;
    }

    private function getCurrentValue(User $user, string $type, array $requirement)
    {
        return match($type) {
            'comics_read' => $user->library()->count(),
            'comics_completed' => $user->comicProgress()->where('is_completed', true)->count(),
            'reading_streak' => optional($user->streaks()->where('streak_type', 'daily_reading')->first())->longest_count ?? 0,
            'rating_streak' => optional($user->streaks()->where('streak_type', 'rating_streak')->first())->longest_count ?? 0,
            'reviews_written' => $user->reviews()->count(),
            'lists_created' => $user->readingLists()->count(),
            'followers_count' => $user->followers()->count(),
            'goals_completed' => $user->goals()->where('is_completed', true)->count(),
            'genres_explored' => $user->library()
                ->join('comics', 'user_library.comic_id', '=', 'comics.id')
                ->distinct('comics.genre')
                ->count('comics.genre'),
            'authors_discovered' => $user->library()
                ->join('comics', 'user_library.comic_id', '=', 'comics.id')
                ->distinct('comics.author')
                ->count('comics.author'),
            'total_pages_read' => $user->library()
                ->join('comics', 'user_library.comic_id', '=', 'comics.id')
                ->sum('comics.page_count') ?? 0,
            'average_rating_given' => $user->library()->whereNotNull('rating')->avg('rating') ?? 0,
            'social_interactions' => $user->following()->count() + 
                                   $user->readingLists()->sum('followers_count') +
                                   $user->readingLists()->sum('likes_count'),
            'account_age_days' => $user->created_at->diffInDays(now()),
            'has_specific_comic' => $user->library()
                ->join('comics', 'user_library.comic_id', '=', 'comics.id')
                ->where('comics.slug', $requirement['comic_slug'] ?? '')
                ->exists() ? 1 : 0,
            default => 0
        };
    }

    private function getProgressDescription(string $type, $current, $target): string
    {
        return match($type) {
            'comics_read' => "{$current} / {$target} comics in library",
            'comics_completed' => "{$current} / {$target} comics completed",
            'reading_streak' => "{$current} / {$target} day reading streak",
            'rating_streak' => "{$current} / {$target} day rating streak",
            'reviews_written' => "{$current} / {$target} reviews written",
            'lists_created' => "{$current} / {$target} reading lists created",
            'followers_count' => "{$current} / {$target} followers",
            'goals_completed' => "{$current} / {$target} goals completed",
            'genres_explored' => "{$current} / {$target} genres explored",
            'authors_discovered' => "{$current} / {$target} authors discovered",
            'total_pages_read' => number_format($current) . " / " . number_format($target) . " pages read",
            'average_rating_given' => number_format($current, 1) . " / " . number_format($target, 1) . " average rating",
            'social_interactions' => "{$current} / {$target} social interactions",
            'account_age_days' => "{$current} / {$target} days as member",
            'has_specific_comic' => $current >= 1 ? "Comic found in library" : "Add specific comic to library",
            default => "{$current} / {$target}"
        };
    }

    public function seedDefaultAchievements(): void
    {
        $achievements = [
            // Reading Achievements
            [
                'key' => 'first_comic',
                'name' => 'First Steps',
                'description' => 'Add your first comic to your library',
                'category' => 'reading',
                'type' => 'milestone',
                'icon' => 'book-open',
                'color' => 'blue',
                'rarity' => 'common',
                'points' => 10,
                'requirements' => [['type' => 'comics_read', 'operator' => '>=', 'value' => 1]],
                'unlock_order' => 1
            ],
            [
                'key' => 'comic_collector',
                'name' => 'Comic Collector',
                'description' => 'Add 25 comics to your library',
                'category' => 'collection',
                'type' => 'milestone',
                'icon' => 'library',
                'color' => 'green',
                'rarity' => 'uncommon',
                'points' => 50,
                'requirements' => [['type' => 'comics_read', 'operator' => '>=', 'value' => 25]],
                'unlock_order' => 2
            ],
            [
                'key' => 'avid_reader',
                'name' => 'Avid Reader',
                'description' => 'Add 100 comics to your library',
                'category' => 'collection',
                'type' => 'milestone',
                'icon' => 'books',
                'color' => 'purple',
                'rarity' => 'rare',
                'points' => 100,
                'requirements' => [['type' => 'comics_read', 'operator' => '>=', 'value' => 100]],
                'unlock_order' => 3
            ],
            [
                'key' => 'completionist',
                'name' => 'Completionist',
                'description' => 'Complete 10 comics',
                'category' => 'reading',
                'type' => 'completion',
                'icon' => 'check-circle',
                'color' => 'green',
                'rarity' => 'uncommon',
                'points' => 75,
                'requirements' => [['type' => 'comics_completed', 'operator' => '>=', 'value' => 10]],
                'unlock_order' => 4
            ],
            
            // Streak Achievements
            [
                'key' => 'week_warrior',
                'name' => 'Week Warrior',
                'description' => 'Read comics for 7 days in a row',
                'category' => 'reading',
                'type' => 'streak',
                'icon' => 'zap',
                'color' => 'yellow',
                'rarity' => 'uncommon',
                'points' => 50,
                'requirements' => [['type' => 'reading_streak', 'operator' => '>=', 'value' => 7]],
                'unlock_order' => 5
            ],
            [
                'key' => 'month_master',
                'name' => 'Month Master',
                'description' => 'Read comics for 30 days in a row',
                'category' => 'reading',
                'type' => 'streak',
                'icon' => 'flame',
                'color' => 'orange',
                'rarity' => 'epic',
                'points' => 200,
                'requirements' => [['type' => 'reading_streak', 'operator' => '>=', 'value' => 30]],
                'unlock_order' => 6
            ],
            
            // Social Achievements
            [
                'key' => 'first_review',
                'name' => 'Critic',
                'description' => 'Write your first review',
                'category' => 'engagement',
                'type' => 'milestone',
                'icon' => 'message-circle',
                'color' => 'blue',
                'rarity' => 'common',
                'points' => 25,
                'requirements' => [['type' => 'reviews_written', 'operator' => '>=', 'value' => 1]],
                'unlock_order' => 7
            ],
            [
                'key' => 'list_creator',
                'name' => 'List Creator',
                'description' => 'Create your first reading list',
                'category' => 'social',
                'type' => 'milestone',
                'icon' => 'list',
                'color' => 'purple',
                'rarity' => 'common',
                'points' => 30,
                'requirements' => [['type' => 'lists_created', 'operator' => '>=', 'value' => 1]],
                'unlock_order' => 8
            ],
            [
                'key' => 'social_butterfly',
                'name' => 'Social Butterfly',
                'description' => 'Get 10 followers',
                'category' => 'social',
                'type' => 'milestone',
                'icon' => 'users',
                'color' => 'pink',
                'rarity' => 'rare',
                'points' => 100,
                'requirements' => [['type' => 'followers_count', 'operator' => '>=', 'value' => 10]],
                'unlock_order' => 9
            ],
            
            // Discovery Achievements
            [
                'key' => 'genre_explorer',
                'name' => 'Genre Explorer',
                'description' => 'Read comics from 5 different genres',
                'category' => 'collection',
                'type' => 'discovery',
                'icon' => 'compass',
                'color' => 'teal',
                'rarity' => 'uncommon',
                'points' => 60,
                'requirements' => [['type' => 'genres_explored', 'operator' => '>=', 'value' => 5]],
                'unlock_order' => 10
            ],
            [
                'key' => 'page_turner',
                'name' => 'Page Turner',
                'description' => 'Read 1,000 pages total',
                'category' => 'reading',
                'type' => 'milestone',
                'icon' => 'book',
                'color' => 'indigo',
                'rarity' => 'rare',
                'points' => 150,
                'requirements' => [['type' => 'total_pages_read', 'operator' => '>=', 'value' => 1000]],
                'unlock_order' => 11
            ],
            
            // Legendary Achievements
            [
                'key' => 'legendary_collector',
                'name' => 'Legendary Collector',
                'description' => 'Add 500 comics to your library',
                'category' => 'collection',
                'type' => 'milestone',
                'icon' => 'crown',
                'color' => 'gold',
                'rarity' => 'legendary',
                'points' => 500,
                'requirements' => [['type' => 'comics_read', 'operator' => '>=', 'value' => 500]],
                'unlock_order' => 12
            ]
        ];

        foreach ($achievements as $achievementData) {
            Achievement::updateOrCreate(
                ['key' => $achievementData['key']],
                $achievementData
            );
        }
    }

    public function markAchievementAsSeen(User $user, Achievement $achievement): void
    {
        $userAchievement = $user->userAchievements()
            ->where('achievement_id', $achievement->id)
            ->first();

        if ($userAchievement) {
            $userAchievement->markAsSeen();
        }
    }

    public function getUnseenAchievements(User $user): Collection
    {
        return $user->userAchievements()
            ->with('achievement')
            ->unseen()
            ->orderBy('unlocked_at', 'desc')
            ->get();
    }

    public function handleUserAction(User $user, string $action, array $context = []): Collection
    {
        // Clear user's achievement cache when they perform actions
        Cache::forget("user.achievements.{$user->id}");
        
        return $this->checkAndUnlockAchievements($user, $action);
    }
}