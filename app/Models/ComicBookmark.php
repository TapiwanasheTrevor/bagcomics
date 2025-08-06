<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComicBookmark extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'comic_id',
        'page_number',
        'note',
    ];

    protected $casts = [
        'page_number' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForComic($query, Comic $comic)
    {
        return $query->where('comic_id', $comic->id);
    }

    public function scopeByPage($query, int $page)
    {
        return $query->where('page_number', $page);
    }

    public static function createBookmark(User $user, Comic $comic, int $page, ?string $note = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'page_number' => $page,
            'note' => $note,
        ]);
    }

    public function updateNote(string $note): void
    {
        $this->note = $note;
        $this->save();
    }

    /**
     * Get bookmarks for a specific user and comic
     */
    public static function getBookmarksForUserComic(User $user, Comic $comic): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('user_id', $user->id)
            ->where('comic_id', $comic->id)
            ->orderBy('page_number')
            ->get();
    }

    /**
     * Check if a bookmark exists for a specific page
     */
    public static function bookmarkExistsForPage(User $user, Comic $comic, int $page): bool
    {
        return self::where('user_id', $user->id)
            ->where('comic_id', $comic->id)
            ->where('page_number', $page)
            ->exists();
    }

    /**
     * Remove bookmark for a specific page
     */
    public static function removeBookmarkForPage(User $user, Comic $comic, int $page): bool
    {
        return self::where('user_id', $user->id)
            ->where('comic_id', $comic->id)
            ->where('page_number', $page)
            ->delete() > 0;
    }

    /**
     * Get bookmark count for a comic
     */
    public static function getBookmarkCountForComic(Comic $comic): int
    {
        return self::where('comic_id', $comic->id)->count();
    }

    /**
     * Get user's bookmark count
     */
    public static function getBookmarkCountForUser(User $user): int
    {
        return self::where('user_id', $user->id)->count();
    }

    /**
     * Sync bookmarks with user progress
     */
    public function syncWithProgress(): void
    {
        $progress = UserComicProgress::where('user_id', $this->user_id)
            ->where('comic_id', $this->comic_id)
            ->first();

        if ($progress) {
            $progress->bookmark_count = self::where('user_id', $this->user_id)
                ->where('comic_id', $this->comic_id)
                ->count();
            $progress->last_bookmark_at = now();
            $progress->is_bookmarked = true;
            $progress->save();
        }
    }
}
