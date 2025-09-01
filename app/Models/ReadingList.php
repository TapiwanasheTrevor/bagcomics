<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReadingList extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'is_public',
        'is_featured',
        'cover_image_url',
        'tags',
        'followers_count',
        'likes_count',
        'comics_count'
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'tags' => 'array',
        'followers_count' => 'integer',
        'likes_count' => 'integer',
        'comics_count' => 'integer'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($list) {
            if (empty($list->slug)) {
                $list->slug = Str::slug($list->name . '-' . Str::random(6));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comics(): BelongsToMany
    {
        return $this->belongsToMany(Comic::class, 'reading_list_comics')
            ->withPivot('position', 'added_at', 'notes')
            ->withTimestamps()
            ->orderBy('pivot_position');
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'reading_list_followers')
            ->withTimestamps();
    }

    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'reading_list_likes')
            ->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ReadingListActivity::class);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopePopular($query)
    {
        return $query->orderByDesc('followers_count')
            ->orderByDesc('likes_count');
    }

    public function addComic(Comic $comic, ?string $notes = null): void
    {
        $position = $this->comics()->count() + 1;
        
        $this->comics()->attach($comic->id, [
            'position' => $position,
            'added_at' => now(),
            'notes' => $notes
        ]);

        $this->increment('comics_count');
        
        // Log activity
        $this->activities()->create([
            'user_id' => $this->user_id,
            'action' => 'comic_added',
            'comic_id' => $comic->id,
            'metadata' => ['notes' => $notes]
        ]);
    }

    public function removeComic(Comic $comic): void
    {
        $this->comics()->detach($comic->id);
        $this->decrement('comics_count');
        
        // Reorder remaining comics
        $this->reorderComics();
        
        // Log activity
        $this->activities()->create([
            'user_id' => $this->user_id,
            'action' => 'comic_removed',
            'comic_id' => $comic->id
        ]);
    }

    public function reorderComics(): void
    {
        $comics = $this->comics()->get();
        foreach ($comics as $index => $comic) {
            $this->comics()->updateExistingPivot($comic->id, [
                'position' => $index + 1
            ]);
        }
    }

    public function moveComic(Comic $comic, int $newPosition): void
    {
        $currentPosition = $this->comics()
            ->where('comic_id', $comic->id)
            ->first()
            ->pivot
            ->position;

        if ($currentPosition === $newPosition) {
            return;
        }

        // Update positions
        if ($newPosition < $currentPosition) {
            // Moving up
            $this->comics()
                ->wherePivot('position', '>=', $newPosition)
                ->wherePivot('position', '<', $currentPosition)
                ->each(function ($c) {
                    $this->comics()->updateExistingPivot($c->id, [
                        'position' => $c->pivot->position + 1
                    ]);
                });
        } else {
            // Moving down
            $this->comics()
                ->wherePivot('position', '>', $currentPosition)
                ->wherePivot('position', '<=', $newPosition)
                ->each(function ($c) {
                    $this->comics()->updateExistingPivot($c->id, [
                        'position' => $c->pivot->position - 1
                    ]);
                });
        }

        // Update the comic's position
        $this->comics()->updateExistingPivot($comic->id, [
            'position' => $newPosition
        ]);
    }

    public function follow(User $user): void
    {
        if (!$this->followers()->where('user_id', $user->id)->exists()) {
            $this->followers()->attach($user->id);
            $this->increment('followers_count');
            
            // Log activity
            $this->activities()->create([
                'user_id' => $user->id,
                'action' => 'followed'
            ]);
        }
    }

    public function unfollow(User $user): void
    {
        if ($this->followers()->where('user_id', $user->id)->exists()) {
            $this->followers()->detach($user->id);
            $this->decrement('followers_count');
            
            // Log activity
            $this->activities()->create([
                'user_id' => $user->id,
                'action' => 'unfollowed'
            ]);
        }
    }

    public function like(User $user): void
    {
        if (!$this->likes()->where('user_id', $user->id)->exists()) {
            $this->likes()->attach($user->id);
            $this->increment('likes_count');
            
            // Log activity
            $this->activities()->create([
                'user_id' => $user->id,
                'action' => 'liked'
            ]);
        }
    }

    public function unlike(User $user): void
    {
        if ($this->likes()->where('user_id', $user->id)->exists()) {
            $this->likes()->detach($user->id);
            $this->decrement('likes_count');
        }
    }

    public function isFollowedBy(User $user): bool
    {
        return $this->followers()->where('user_id', $user->id)->exists();
    }

    public function isLikedBy(User $user): bool
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->user_id === $user->id || $user->is_admin;
    }

    public function getShareUrl(): string
    {
        return url("/lists/{$this->slug}");
    }

    public function duplicate(User $user, ?string $name = null): self
    {
        $newList = $this->replicate();
        $newList->user_id = $user->id;
        $newList->name = $name ?? $this->name . ' (Copy)';
        $newList->slug = Str::slug($newList->name . '-' . Str::random(6));
        $newList->is_featured = false;
        $newList->followers_count = 0;
        $newList->likes_count = 0;
        $newList->save();

        // Copy comics with their positions
        foreach ($this->comics as $comic) {
            $newList->comics()->attach($comic->id, [
                'position' => $comic->pivot->position,
                'added_at' => now(),
                'notes' => $comic->pivot->notes
            ]);
        }

        $newList->comics_count = $this->comics_count;
        $newList->save();

        return $newList;
    }
}