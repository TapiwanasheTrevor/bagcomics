<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
}
