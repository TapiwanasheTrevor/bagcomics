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
}
