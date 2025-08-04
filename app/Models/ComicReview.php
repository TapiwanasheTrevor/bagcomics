<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComicReview extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'comic_id',
        'rating',
        'title',
        'content',
        'is_spoiler',
        'helpful_votes',
        'total_votes',
        'is_approved',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_spoiler' => 'boolean',
        'helpful_votes' => 'integer',
        'total_votes' => 'integer',
        'is_approved' => 'boolean',
    ];

    /**
     * Validation rules for review creation/update
     */
    public static function validationRules(): array
    {
        return [
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'content' => 'required|string|min:10|max:5000',
            'is_spoiler' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ReviewVote::class, 'review_id');
    }

    public function getHelpfulnessRatio(): float
    {
        if ($this->total_votes === 0) {
            return 0.0;
        }

        return $this->helpful_votes / $this->total_votes;
    }

    public function addHelpfulVote(User $user, bool $helpful): void
    {
        // Check if user already voted
        $existingVote = $this->votes()->where('user_id', $user->id)->first();
        
        if ($existingVote) {
            // Update existing vote
            if ($existingVote->is_helpful !== $helpful) {
                if ($existingVote->is_helpful) {
                    $this->helpful_votes--;
                } else {
                    $this->helpful_votes++;
                }
                $existingVote->is_helpful = $helpful;
                $existingVote->save();
            }
        } else {
            // Create new vote
            $this->votes()->create([
                'user_id' => $user->id,
                'is_helpful' => $helpful,
            ]);
            
            $this->total_votes++;
            if ($helpful) {
                $this->helpful_votes++;
            }
        }

        $this->save();
    }

    public function isApproved(): bool
    {
        return $this->is_approved;
    }

    public function approve(): void
    {
        $this->is_approved = true;
        $this->save();
    }

    public function reject(): void
    {
        $this->is_approved = false;
        $this->save();
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeByRating($query, int $rating)
    {
        return $query->where('rating', $rating);
    }

    public function scopeWithSpoilers($query, bool $includeSpoilers = false)
    {
        if (!$includeSpoilers) {
            return $query->where('is_spoiler', false);
        }
        
        return $query;
    }
}
