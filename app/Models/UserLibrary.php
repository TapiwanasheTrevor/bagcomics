<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLibrary extends Model
{
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
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
        'access_expires_at' => 'datetime',
        'is_favorite' => 'boolean',
        'purchase_price' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
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
}
