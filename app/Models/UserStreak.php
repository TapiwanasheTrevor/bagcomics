<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserStreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'streak_type',
        'current_count',
        'longest_count',
        'last_activity_date',
        'started_at',
        'is_active'
    ];

    protected $casts = [
        'last_activity_date' => 'date',
        'started_at' => 'date',
        'is_active' => 'boolean'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('streak_type', $type);
    }

    public function updateStreak(): void
    {
        $today = Carbon::today();
        
        if ($this->last_activity_date && $this->last_activity_date->isSameDay($today)) {
            // Already updated today
            return;
        }

        if ($this->last_activity_date && $this->last_activity_date->addDay()->isSameDay($today)) {
            // Consecutive day - increment streak
            $this->current_count++;
            $this->longest_count = max($this->longest_count, $this->current_count);
        } elseif ($this->last_activity_date && $this->last_activity_date->isBefore($today->subDay())) {
            // Streak broken - reset
            $this->current_count = 1;
            $this->started_at = $today;
        } else {
            // First activity or same day
            $this->current_count = 1;
            $this->started_at = $this->started_at ?? $today;
        }

        $this->last_activity_date = $today;
        $this->is_active = true;
        $this->save();
    }

    public function breakStreak(): void
    {
        $this->is_active = false;
        $this->save();
    }

    public function getStreakStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'broken';
        }

        $daysSinceActivity = Carbon::today()->diffInDays($this->last_activity_date);
        
        if ($daysSinceActivity === 0) {
            return 'active_today';
        } elseif ($daysSinceActivity === 1) {
            return 'at_risk';
        } else {
            return 'broken';
        }
    }

    public function getDaysUntilBreakAttribute(): int
    {
        if (!$this->is_active) {
            return 0;
        }

        $daysSinceActivity = Carbon::today()->diffInDays($this->last_activity_date);
        return max(0, 1 - $daysSinceActivity);
    }

    public static function getStreakTypes(): array
    {
        return [
            'daily_reading' => 'Daily Reading',
            'weekly_completion' => 'Weekly Comic Completion',
            'rating_streak' => 'Rating Streak',
            'discovery_streak' => 'New Comic Discovery'
        ];
    }

    public static function createOrUpdateStreak(User $user, string $type): self
    {
        $streak = self::where('user_id', $user->id)
            ->where('streak_type', $type)
            ->first();

        if (!$streak) {
            $streak = self::create([
                'user_id' => $user->id,
                'streak_type' => $type,
                'current_count' => 0,
                'longest_count' => 0,
                'is_active' => true
            ]);
        }

        $streak->updateStreak();
        return $streak;
    }
}