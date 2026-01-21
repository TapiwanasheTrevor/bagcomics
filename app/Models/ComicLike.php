<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComicLike extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'comic_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }

    protected static function booted(): void
    {
        static::created(function (ComicLike $like) {
            $like->comic->increment('likes_count');
        });

        static::deleted(function (ComicLike $like) {
            $like->comic->decrement('likes_count');
        });
    }
}
