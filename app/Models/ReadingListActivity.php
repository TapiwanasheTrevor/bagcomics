<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingListActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'reading_list_id',
        'user_id',
        'action',
        'comic_id',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    public function readingList(): BelongsTo
    {
        return $this->belongsTo(ReadingList::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }

    public function getActionDescriptionAttribute(): string
    {
        return match($this->action) {
            'created' => 'created the list',
            'comic_added' => 'added a comic',
            'comic_removed' => 'removed a comic',
            'followed' => 'followed the list',
            'unfollowed' => 'unfollowed the list',
            'liked' => 'liked the list',
            'shared' => 'shared the list',
            'updated' => 'updated the list',
            default => $this->action
        };
    }
}