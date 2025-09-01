<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Achievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'description',
        'category',
        'type',
        'icon',
        'color',
        'rarity',
        'points',
        'requirements',
        'is_active',
        'is_hidden',
        'unlock_order'
    ];

    protected $casts = [
        'requirements' => 'array',
        'is_active' => 'boolean',
        'is_hidden' => 'boolean',
        'points' => 'integer',
        'unlock_order' => 'integer'
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot('unlocked_at', 'progress_data', 'is_seen')
            ->withTimestamps();
    }

    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_hidden', false);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByRarity($query, string $rarity)
    {
        return $query->where('rarity', $rarity);
    }

    public function checkUnlockConditions(User $user): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Check if user already has this achievement
        if ($user->achievements()->where('achievement_id', $this->id)->exists()) {
            return false;
        }

        return $this->evaluateRequirements($user);
    }

    private function evaluateRequirements(User $user): bool
    {
        if (!$this->requirements) {
            return false;
        }

        foreach ($this->requirements as $requirement) {
            if (!$this->checkSingleRequirement($user, $requirement)) {
                return false;
            }
        }

        return true;
    }

    private function checkSingleRequirement(User $user, array $requirement): bool
    {
        $type = $requirement['type'] ?? '';
        $value = $requirement['value'] ?? 0;
        $operator = $requirement['operator'] ?? '>=';

        switch ($type) {
            case 'comics_read':
                $actual = $user->library()->count();
                break;

            case 'comics_completed':
                $actual = $user->comicProgress()->where('is_completed', true)->count();
                break;

            case 'reading_streak':
                $streak = $user->streaks()->where('streak_type', 'daily_reading')->first();
                $actual = $streak ? $streak->longest_count : 0;
                break;

            case 'rating_streak':
                $streak = $user->streaks()->where('streak_type', 'rating_streak')->first();
                $actual = $streak ? $streak->longest_count : 0;
                break;

            case 'reviews_written':
                $actual = $user->reviews()->count();
                break;

            case 'lists_created':
                $actual = $user->readingLists()->count();
                break;

            case 'followers_count':
                $actual = $user->followers()->count();
                break;

            case 'goals_completed':
                $actual = $user->goals()->where('is_completed', true)->count();
                break;

            case 'genres_explored':
                $actual = $user->library()
                    ->join('comics', 'user_library.comic_id', '=', 'comics.id')
                    ->distinct('comics.genre')
                    ->count('comics.genre');
                break;

            case 'authors_discovered':
                $actual = $user->library()
                    ->join('comics', 'user_library.comic_id', '=', 'comics.id')
                    ->distinct('comics.author')
                    ->count('comics.author');
                break;

            case 'total_pages_read':
                $actual = $user->library()
                    ->join('comics', 'user_library.comic_id', '=', 'comics.id')
                    ->sum('comics.page_count') ?? 0;
                break;

            case 'average_rating_given':
                $actual = $user->library()->whereNotNull('rating')->avg('rating') ?? 0;
                break;

            case 'social_interactions':
                $actual = $user->following()->count() + 
                         $user->readingLists()->sum('followers_count') +
                         $user->readingLists()->sum('likes_count');
                break;

            case 'account_age_days':
                $actual = $user->created_at->diffInDays(now());
                break;

            case 'has_specific_comic':
                $comicSlug = $requirement['comic_slug'] ?? '';
                $actual = $user->library()
                    ->join('comics', 'user_library.comic_id', '=', 'comics.id')
                    ->where('comics.slug', $comicSlug)
                    ->exists() ? 1 : 0;
                $value = 1;
                break;

            default:
                return false;
        }

        return $this->compareValues($actual, $operator, $value);
    }

    private function compareValues($actual, string $operator, $expected): bool
    {
        return match($operator) {
            '>=' => $actual >= $expected,
            '>' => $actual > $expected,
            '<=' => $actual <= $expected,
            '<' => $actual < $expected,
            '=' => $actual == $expected,
            '!=' => $actual != $expected,
            default => false
        };
    }

    public function getRarityColorAttribute(): string
    {
        return match($this->rarity) {
            'common' => 'text-gray-400 bg-gray-500/20',
            'uncommon' => 'text-green-400 bg-green-500/20',
            'rare' => 'text-blue-400 bg-blue-500/20',
            'epic' => 'text-purple-400 bg-purple-500/20',
            'legendary' => 'text-yellow-400 bg-yellow-500/20',
            default => 'text-gray-400 bg-gray-500/20'
        };
    }

    public function getRarityDisplayNameAttribute(): string
    {
        return match($this->rarity) {
            'common' => 'Common',
            'uncommon' => 'Uncommon',
            'rare' => 'Rare',
            'epic' => 'Epic',
            'legendary' => 'Legendary',
            default => 'Common'
        };
    }

    public static function getCategories(): array
    {
        return [
            'reading' => 'Reading',
            'social' => 'Social',
            'collection' => 'Collection',
            'engagement' => 'Engagement',
            'milestone' => 'Milestone',
            'special' => 'Special'
        ];
    }

    public static function getRarities(): array
    {
        return [
            'common' => 'Common',
            'uncommon' => 'Uncommon',
            'rare' => 'Rare',
            'epic' => 'Epic',
            'legendary' => 'Legendary'
        ];
    }

    public static function getTypes(): array
    {
        return [
            'progress' => 'Progress',
            'milestone' => 'Milestone',
            'streak' => 'Streak',
            'social' => 'Social',
            'discovery' => 'Discovery',
            'completion' => 'Completion'
        ];
    }
}