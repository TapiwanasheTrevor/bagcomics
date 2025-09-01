<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'goal_type',
        'title',
        'description',
        'target_value',
        'current_progress',
        'period_type',
        'period_start',
        'period_end',
        'is_completed',
        'completed_at',
        'is_active'
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'completed_at' => 'datetime',
        'is_completed' => 'boolean',
        'is_active' => 'boolean',
        'target_value' => 'integer',
        'current_progress' => 'integer'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopeInProgress($query)
    {
        return $query->where('is_active', true)
            ->where('is_completed', false);
    }

    public function scopeCurrentPeriod($query)
    {
        $today = Carbon::today();
        return $query->where('period_start', '<=', $today)
            ->where('period_end', '>=', $today);
    }

    public function updateProgress(int $increment = 1): void
    {
        if ($this->is_completed || !$this->is_active) {
            return;
        }

        $this->current_progress = min($this->current_progress + $increment, $this->target_value);
        
        if ($this->current_progress >= $this->target_value) {
            $this->is_completed = true;
            $this->completed_at = now();
        }

        $this->save();
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->target_value <= 0) {
            return 0;
        }
        
        return min(100, ($this->current_progress / $this->target_value) * 100);
    }

    public function getRemainingAttribute(): int
    {
        return max(0, $this->target_value - $this->current_progress);
    }

    public function getDaysRemainingAttribute(): int
    {
        if (!$this->period_end) {
            return 0;
        }
        
        return max(0, Carbon::today()->diffInDays($this->period_end, false));
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->period_end && Carbon::today()->isAfter($this->period_end) && !$this->is_completed;
    }

    public static function createGoal(User $user, array $data): self
    {
        $periodStart = Carbon::today();
        $periodEnd = match ($data['period_type']) {
            'daily' => $periodStart->copy()->endOfDay(),
            'weekly' => $periodStart->copy()->endOfWeek(),
            'monthly' => $periodStart->copy()->endOfMonth(),
            'yearly' => $periodStart->copy()->endOfYear(),
            'custom' => Carbon::parse($data['custom_end_date'] ?? $periodStart->addDays(30)),
            default => $periodStart->copy()->addDays(30)
        };

        return self::create([
            'user_id' => $user->id,
            'goal_type' => $data['goal_type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'target_value' => $data['target_value'],
            'current_progress' => 0,
            'period_type' => $data['period_type'],
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'is_active' => true,
            'is_completed' => false
        ]);
    }

    public static function getGoalTypes(): array
    {
        return [
            'comics_read' => 'Comics Read',
            'pages_read' => 'Pages Read',
            'hours_reading' => 'Hours Reading',
            'series_completed' => 'Series Completed',
            'new_authors' => 'New Authors Discovered',
            'genres_explored' => 'Different Genres',
            'ratings_given' => 'Comics Rated',
            'reviews_written' => 'Reviews Written'
        ];
    }

    public static function getPeriodTypes(): array
    {
        return [
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'yearly' => 'Yearly',
            'custom' => 'Custom Period'
        ];
    }

    public static function getRecommendedGoals(User $user): array
    {
        $userStats = self::getUserStats($user);
        
        return [
            [
                'goal_type' => 'comics_read',
                'title' => 'Read 5 Comics This Week',
                'description' => 'Challenge yourself to discover new stories',
                'target_value' => 5,
                'period_type' => 'weekly',
                'difficulty' => 'easy'
            ],
            [
                'goal_type' => 'pages_read',
                'title' => 'Read 100 Pages This Month',
                'description' => 'Build consistent reading habits',
                'target_value' => 100,
                'period_type' => 'monthly',
                'difficulty' => 'medium'
            ],
            [
                'goal_type' => 'new_authors',
                'title' => 'Discover 3 New Authors',
                'description' => 'Expand your literary horizons',
                'target_value' => 3,
                'period_type' => 'monthly',
                'difficulty' => 'medium'
            ],
            [
                'goal_type' => 'series_completed',
                'title' => 'Complete a Comic Series',
                'description' => 'Finish what you started',
                'target_value' => 1,
                'period_type' => 'monthly',
                'difficulty' => 'hard'
            ]
        ];
    }

    private static function getUserStats(User $user): array
    {
        return [
            'total_comics' => $user->library()->count(),
            'completed_comics' => $user->library()->whereHas('progress', function ($q) {
                $q->where('is_completed', true);
            })->count(),
            'average_rating' => $user->library()->whereNotNull('rating')->avg('rating') ?? 0
        ];
    }
}