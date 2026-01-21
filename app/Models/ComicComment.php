<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComicComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'comic_id',
        'content',
        'is_approved',
        'is_spoiler',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'is_spoiler' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    protected static function booted(): void
    {
        static::created(function (ComicComment $comment) {
            if ($comment->is_approved) {
                $comment->comic->increment('comments_count');
            }
        });

        static::deleted(function (ComicComment $comment) {
            if ($comment->is_approved) {
                $comment->comic->decrement('comments_count');
            }
        });
    }
}
