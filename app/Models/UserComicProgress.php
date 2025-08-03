<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserComicProgress extends Model
{
    protected $fillable = [
        'user_id',
        'comic_id',
        'current_page',
        'total_pages',
        'progress_percentage',
        'is_completed',
        'is_bookmarked',
        'reading_time_minutes',
        'last_read_at',
        'completed_at',
        'bookmarks',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'is_bookmarked' => 'boolean',
        'last_read_at' => 'datetime',
        'completed_at' => 'datetime',
        'bookmarks' => 'array',
        'progress_percentage' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }

    public function updateProgress(int $currentPage, int $totalPages = null): void
    {
        $this->current_page = $currentPage;

        if ($totalPages) {
            $this->total_pages = $totalPages;
        }

        if ($this->total_pages > 0) {
            $this->progress_percentage = ($currentPage / $this->total_pages) * 100;
            $this->is_completed = $currentPage >= $this->total_pages;

            if ($this->is_completed && !$this->completed_at) {
                $this->completed_at = now();
            }
        }

        $this->last_read_at = now();
        $this->save();
    }

    public function addBookmark(int $page, string $note = null): void
    {
        $bookmarks = $this->bookmarks ?? [];
        $bookmarks[] = [
            'page' => $page,
            'note' => $note,
            'created_at' => now()->toISOString(),
        ];

        $this->bookmarks = $bookmarks;
        $this->is_bookmarked = true;
        $this->save();
    }
}
