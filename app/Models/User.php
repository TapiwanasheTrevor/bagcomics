<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Collection;
use App\Models\UserPreferences;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function comicProgress(): HasMany
    {
        return $this->hasMany(UserComicProgress::class);
    }

    public function library(): HasMany
    {
        return $this->hasMany(UserLibrary::class);
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreferences::class);
    }

    /**
     * Get user preferences with defaults if none exist
     */
    public function getPreferences(): UserPreferences
    {
        if (!$this->preferences) {
            return $this->preferences()->create(UserPreferences::getDefaults());
        }

        return $this->preferences;
    }

    public function comics(): BelongsToMany
    {
        return $this->belongsToMany(Comic::class, 'user_libraries')
            ->withPivot(['access_type', 'purchase_price', 'purchased_at', 'is_favorite', 'rating', 'review'])
            ->withTimestamps();
    }

    public function favoriteComics(): BelongsToMany
    {
        return $this->comics()->wherePivot('is_favorite', true);
    }

    public function getProgressForComic(Comic $comic): ?UserComicProgress
    {
        return $this->comicProgress()->where('comic_id', $comic->id)->first();
    }

    public function hasAccessToComic(Comic $comic): bool
    {
        if ($comic->is_free) {
            return true;
        }

        $libraryEntry = $this->library()->where('comic_id', $comic->id)->first();
        return $libraryEntry && $libraryEntry->hasAccess();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function successfulPayments(): HasMany
    {
        return $this->payments()->where('status', 'succeeded');
    }

    public function hasPurchasedComic(Comic $comic): bool
    {
        return $this->successfulPayments()
            ->where('comic_id', $comic->id)
            ->exists();
    }

    // New relationships for enhanced functionality
    public function reviews(): HasMany
    {
        return $this->hasMany(ComicReview::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(ComicBookmark::class);
    }

    public function socialShares(): HasMany
    {
        return $this->hasMany(SocialShare::class);
    }

    public function reviewVotes(): HasMany
    {
        return $this->hasMany(ReviewVote::class);
    }

    // Enhanced methods for user statistics and recommendations
    public function getReadingStatistics(): array
    {
        $totalComics = $this->library()->count();
        $completedComics = $this->comicProgress()->where('is_completed', true)->count();
        $totalReadingTime = $this->comicProgress()->sum('reading_time_minutes');
        $averageRating = $this->library()->whereNotNull('rating')->avg('rating') ?? 0.0;
        $totalReviews = $this->reviews()->count();
        $totalBookmarks = $this->bookmarks()->count();

        return [
            'total_comics' => $totalComics,
            'completed_comics' => $completedComics,
            'completion_rate' => $totalComics > 0 ? ($completedComics / $totalComics) * 100 : 0,
            'total_reading_time_minutes' => $totalReadingTime,
            'average_rating_given' => round($averageRating, 2),
            'total_reviews' => $totalReviews,
            'total_bookmarks' => $totalBookmarks,
        ];
    }

    public function getRecommendations(int $limit = 10): Collection
    {
        // Get user's favorite genres and authors
        $favoriteGenres = $this->library()
            ->join('comics', 'user_libraries.comic_id', '=', 'comics.id')
            ->whereNotNull('comics.genre')
            ->where('user_libraries.rating', '>=', 4)
            ->pluck('comics.genre')
            ->unique()
            ->take(3);

        $favoriteAuthors = $this->library()
            ->join('comics', 'user_libraries.comic_id', '=', 'comics.id')
            ->whereNotNull('comics.author')
            ->where('user_libraries.rating', '>=', 4)
            ->pluck('comics.author')
            ->unique()
            ->take(3);

        // Get comics user hasn't read yet
        $ownedComicIds = $this->library()->pluck('comic_id');

        return Comic::whereNotIn('id', $ownedComicIds)
            ->where('is_visible', true)
            ->where(function ($query) use ($favoriteGenres, $favoriteAuthors) {
                if ($favoriteGenres->isNotEmpty()) {
                    $query->whereIn('genre', $favoriteGenres);
                }
                if ($favoriteAuthors->isNotEmpty()) {
                    $query->orWhereIn('author', $favoriteAuthors);
                }
            })
            ->orderByDesc('average_rating')
            ->orderByDesc('total_readers')
            ->limit($limit)
            ->get();
    }
}
