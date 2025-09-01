<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRecommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'comic_id',
        'recommendation_type',
        'score',
        'reasons',
        'is_dismissed',
        'clicked_at',
        'recommended_at',
        'expires_at'
    ];

    protected $casts = [
        'reasons' => 'array',
        'is_dismissed' => 'boolean',
        'clicked_at' => 'datetime',
        'recommended_at' => 'datetime',
        'expires_at' => 'datetime',
        'score' => 'decimal:4'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }

    /**
     * Get active recommendations (not dismissed and not expired)
     */
    public function scopeActive($query)
    {
        return $query->where('is_dismissed', false)
                    ->where(function($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Get recommendations by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('recommendation_type', $type);
    }

    /**
     * Get high-score recommendations
     */
    public function scopeHighScore($query, float $minScore = 0.7)
    {
        return $query->where('score', '>=', $minScore);
    }

    /**
     * Mark recommendation as clicked
     */
    public function markAsClicked(): void
    {
        $this->update([
            'clicked_at' => now(),
        ]);
    }

    /**
     * Dismiss this recommendation
     */
    public function dismiss(): void
    {
        $this->update([
            'is_dismissed' => true,
        ]);
    }

    /**
     * Get formatted reasons for display
     */
    public function getFormattedReasonsAttribute(): array
    {
        return collect($this->reasons ?? [])->map(function ($reason) {
            return match ($reason) {
                'similar_genre' => 'Similar to genres you enjoy',
                'same_author' => 'From an author you\'ve read',
                'highly_rated' => 'Highly rated by other readers',
                'popular_now' => 'Trending among readers',
                'collaborative_filtering' => 'Readers like you also enjoyed',
                'new_release' => 'New release in your favorite genre',
                'continue_series' => 'Next in a series you\'ve read',
                'similar_readers' => 'Popular with similar readers',
                'reading_pattern' => 'Matches your reading preferences',
                default => ucfirst(str_replace('_', ' ', $reason))
            };
        })->toArray();
    }

    /**
     * Get recommendation confidence level
     */
    public function getConfidenceLevelAttribute(): string
    {
        return match (true) {
            $this->score >= 0.9 => 'very_high',
            $this->score >= 0.8 => 'high',
            $this->score >= 0.6 => 'medium',
            default => 'low'
        };
    }
}