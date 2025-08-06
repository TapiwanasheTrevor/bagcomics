<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

class UserLibrary extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'comic_id',
        'access_type',
        'purchase_price',
        'purchased_at',
        'access_expires_at',
        'is_favorite',
        'rating',
        'review',
        'last_accessed_at',
        'total_reading_time',
        'completion_percentage',
        'device_sync_token',
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
        'access_expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'is_favorite' => 'boolean',
        'purchase_price' => 'decimal:2',
        'total_reading_time' => 'integer',
        'completion_percentage' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }

    public function progress(): HasOne
    {
        return $this->hasOne(UserComicProgress::class, 'comic_id', 'comic_id')
            ->where('user_id', $this->user_id);
    }

    public function hasAccess(): bool
    {
        if ($this->access_type === 'free') {
            return true;
        }

        if ($this->access_type === 'purchased') {
            return $this->purchased_at !== null;
        }

        if ($this->access_type === 'subscription') {
            return $this->access_expires_at === null || $this->access_expires_at->isFuture();
        }

        return false;
    }

    public function setRating(int $rating, string $review = null): void
    {
        $this->rating = max(1, min(5, $rating));
        $this->review = $review;
        $this->save();
    }

    public function updateLastAccessed(): void
    {
        $this->last_accessed_at = now();
        $this->save();
    }

    public function addReadingTime(int $seconds): void
    {
        $this->total_reading_time = ($this->total_reading_time ?? 0) + $seconds;
        $this->save();
    }

    public function updateCompletionPercentage(float $percentage): void
    {
        $this->completion_percentage = max(0, min(100, $percentage));
        $this->save();
    }

    public function generateSyncToken(): string
    {
        $token = hash('sha256', $this->user_id . $this->comic_id . now()->timestamp . random_bytes(16));
        $this->device_sync_token = $token;
        $this->save();
        return $token;
    }

    // Advanced filtering scopes
    public function scopeByAccessType(Builder $query, string $accessType): Builder
    {
        return $query->where('access_type', $accessType);
    }

    public function scopeByGenre(Builder $query, string $genre): Builder
    {
        return $query->whereHas('comic', function ($q) use ($genre) {
            $q->where('genre', $genre);
        });
    }

    public function scopeByPublisher(Builder $query, string $publisher): Builder
    {
        return $query->whereHas('comic', function ($q) use ($publisher) {
            $q->where('publisher', $publisher);
        });
    }

    public function scopeByRating(Builder $query, int $rating): Builder
    {
        return $query->where('rating', $rating);
    }

    public function scopeByRatingRange(Builder $query, int $minRating, int $maxRating): Builder
    {
        return $query->whereBetween('rating', [$minRating, $maxRating]);
    }

    public function scopeFavorites(Builder $query): Builder
    {
        return $query->where('is_favorite', true);
    }

    public function scopeRecentlyAdded(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeRecentlyRead(Builder $query, int $days = 7): Builder
    {
        return $query->where('last_accessed_at', '>=', now()->subDays($days));
    }

    public function scopeByCompletionStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'unread' => $query->where('completion_percentage', 0),
            'reading' => $query->whereBetween('completion_percentage', [0.01, 99.99]),
            'completed' => $query->where('completion_percentage', 100),
            default => $query,
        };
    }

    public function scopeByReadingTime(Builder $query, int $minMinutes, ?int $maxMinutes = null): Builder
    {
        $minSeconds = $minMinutes * 60;
        if ($maxMinutes) {
            $maxSeconds = $maxMinutes * 60;
            return $query->whereBetween('total_reading_time', [$minSeconds, $maxSeconds]);
        }
        return $query->where('total_reading_time', '>=', $minSeconds);
    }

    public function scopeByDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('purchased_at', [$startDate, $endDate]);
    }

    public function scopeWithProgress(Builder $query): Builder
    {
        return $query->with(['progress' => function ($q) {
            $q->select('user_id', 'comic_id', 'current_page', 'total_pages', 'reading_time_minutes', 'last_read_at');
        }]);
    }

    public function scopeByAuthor(Builder $query, string $author): Builder
    {
        return $query->whereHas('comic', function ($q) use ($author) {
            $q->where('author', $author);
        });
    }

    public function scopeByLanguage(Builder $query, string $language): Builder
    {
        return $query->whereHas('comic', function ($q) use ($language) {
            $q->where('language', $language);
        });
    }

    public function scopeByTags(Builder $query, array $tags): Builder
    {
        return $query->whereHas('comic', function ($q) use ($tags) {
            foreach ($tags as $tag) {
                $q->whereJsonContains('tags', $tag);
            }
        });
    }

    public function scopeByPriceRange(Builder $query, float $minPrice, ?float $maxPrice = null): Builder
    {
        if ($maxPrice) {
            return $query->whereBetween('purchase_price', [$minPrice, $maxPrice]);
        }
        return $query->where('purchase_price', '>=', $minPrice);
    }

    public function scopeExpiringSoon(Builder $query, int $days = 7): Builder
    {
        return $query->where('access_type', 'subscription')
            ->whereNotNull('access_expires_at')
            ->whereBetween('access_expires_at', [now(), now()->addDays($days)]);
    }

    public function scopeWithoutReview(Builder $query): Builder
    {
        return $query->whereNull('review');
    }

    public function scopeWithReview(Builder $query): Builder
    {
        return $query->whereNotNull('review');
    }

    public function scopeOrderByLastRead(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('last_accessed_at', $direction);
    }

    public function scopeOrderByRating(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('rating', $direction);
    }

    public function scopeOrderByProgress(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('completion_percentage', $direction);
    }

    public function scopeOrderByReadingTime(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('total_reading_time', $direction);
    }

    // Statistics methods
    public function getReadingTimeFormatted(): string
    {
        if (!$this->total_reading_time) {
            return '0 minutes';
        }

        $hours = floor($this->total_reading_time / 3600);
        $minutes = floor(($this->total_reading_time % 3600) / 60);

        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }

        return $minutes . ' minutes';
    }

    public function isCompleted(): bool
    {
        return $this->completion_percentage >= 100;
    }

    public function isInProgress(): bool
    {
        return $this->completion_percentage > 0 && $this->completion_percentage < 100;
    }

    public function isUnread(): bool
    {
        return $this->completion_percentage == 0;
    }

    public function getDaysInLibrary(): int
    {
        return $this->created_at->diffInDays(now());
    }

    public function getDaysSinceLastRead(): ?int
    {
        return $this->last_accessed_at ? $this->last_accessed_at->diffInDays(now()) : null;
    }
}
