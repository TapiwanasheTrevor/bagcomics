<?php

namespace App\Services;

use App\Models\Comic;
use App\Models\ComicReview;
use App\Models\ReviewVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    /**
     * Submit a new review for a comic
     */
    public function submitReview(User $user, Comic $comic, array $reviewData): ComicReview
    {
        // Check if user has already reviewed this comic
        $existingReview = $this->getUserReviewForComic($user, $comic);
        if ($existingReview) {
            throw ValidationException::withMessages([
                'comic' => 'You have already reviewed this comic.'
            ]);
        }

        // Check if user has access to the comic (purchased or free)
        if (!$user->hasAccessToComic($comic)) {
            throw ValidationException::withMessages([
                'comic' => 'You must own this comic to review it.'
            ]);
        }

        // Validate review data
        $validatedData = $this->validateReviewData($reviewData);

        // Create the review
        $review = $comic->reviews()->create([
            'user_id' => $user->id,
            'rating' => $validatedData['rating'],
            'title' => $validatedData['title'] ?? null,
            'content' => $validatedData['content'],
            'is_spoiler' => $validatedData['is_spoiler'] ?? false,
            'is_approved' => $this->shouldAutoApprove($user, $validatedData),
        ]);

        // Update comic's average rating
        $comic->updateAverageRating();

        return $review;
    }

    /**
     * Update an existing review
     */
    public function updateReview(ComicReview $review, array $reviewData): ComicReview
    {
        $validatedData = $this->validateReviewData($reviewData);

        $review->update([
            'rating' => $validatedData['rating'],
            'title' => $validatedData['title'] ?? $review->title,
            'content' => $validatedData['content'],
            'is_spoiler' => $validatedData['is_spoiler'] ?? $review->is_spoiler,
            'is_approved' => $this->shouldAutoApprove($review->user, $validatedData),
        ]);

        // Update comic's average rating
        $review->comic->updateAverageRating();

        return $review->fresh();
    }

    /**
     * Delete a review
     */
    public function deleteReview(ComicReview $review): bool
    {
        $comic = $review->comic;
        
        DB::transaction(function () use ($review) {
            // Delete all votes for this review
            $review->votes()->delete();
            
            // Delete the review
            $review->delete();
        });

        // Update comic's average rating
        $comic->updateAverageRating();

        return true;
    }

    /**
     * Get reviews for a comic with filtering and pagination
     */
    public function getComicReviews(
        Comic $comic, 
        array $filters = [], 
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = $comic->reviews()
            ->with(['user', 'votes'])
            ->approved();

        // Apply filters
        if (isset($filters['rating']) && $filters['rating'] > 0) {
            $query->where('rating', $filters['rating']);
        }

        if (isset($filters['include_spoilers']) && !$filters['include_spoilers']) {
            $query->where('is_spoiler', false);
        }

        if (isset($filters['sort'])) {
            switch ($filters['sort']) {
                case 'newest':
                    $query->orderByDesc('created_at');
                    break;
                case 'oldest':
                    $query->orderBy('created_at');
                    break;
                case 'highest_rating':
                    $query->orderByDesc('rating')->orderByDesc('created_at');
                    break;
                case 'lowest_rating':
                    $query->orderBy('rating')->orderByDesc('created_at');
                    break;
                case 'most_helpful':
                    $query->orderByDesc('helpful_votes')->orderByDesc('created_at');
                    break;
                default:
                    $query->orderByDesc('helpful_votes')->orderByDesc('created_at');
            }
        } else {
            $query->orderByDesc('helpful_votes')->orderByDesc('created_at');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get user's review for a specific comic
     */
    public function getUserReviewForComic(User $user, Comic $comic): ?ComicReview
    {
        return $user->reviews()
            ->where('comic_id', $comic->id)
            ->first();
    }

    /**
     * Vote on review helpfulness
     */
    public function voteOnReview(User $user, ComicReview $review, bool $isHelpful): ReviewVote
    {
        // Check if user is trying to vote on their own review
        if ($review->user_id === $user->id) {
            throw ValidationException::withMessages([
                'review' => 'You cannot vote on your own review.'
            ]);
        }

        // Get or create vote
        $vote = ReviewVote::updateOrCreate(
            [
                'user_id' => $user->id,
                'review_id' => $review->id,
            ],
            [
                'is_helpful' => $isHelpful,
            ]
        );

        // Recalculate vote counts
        $this->recalculateReviewVotes($review);

        return $vote;
    }

    /**
     * Remove vote from review
     */
    public function removeVoteFromReview(User $user, ComicReview $review): bool
    {
        $vote = ReviewVote::where('user_id', $user->id)
            ->where('review_id', $review->id)
            ->first();

        if ($vote) {
            $vote->delete();
            $this->recalculateReviewVotes($review);
            return true;
        }

        return false;
    }

    /**
     * Get review statistics for a comic
     */
    public function getReviewStatistics(Comic $comic): array
    {
        $reviewsQuery = $comic->approvedReviews();
        $reviews = $reviewsQuery->get();

        $stats = [
            'total_reviews' => $reviews->count(),
            'average_rating' => $reviews->avg('rating') ?? 0.0,
            'rating_distribution' => [],
            'total_helpful_votes' => $reviews->sum('helpful_votes'),
            'spoiler_count' => $reviews->where('is_spoiler', true)->count(),
        ];

        // Calculate rating distribution
        for ($i = 1; $i <= 5; $i++) {
            $count = $reviews->where('rating', $i)->count();
            $percentage = $stats['total_reviews'] > 0 
                ? round(($count / $stats['total_reviews']) * 100, 1) 
                : 0;
            
            $stats['rating_distribution'][$i] = [
                'count' => $count,
                'percentage' => $percentage,
            ];
        }

        return $stats;
    }

    /**
     * Get reviews that need moderation
     */
    public function getReviewsForModeration(int $perPage = 20): LengthAwarePaginator
    {
        return ComicReview::with(['user', 'comic'])
            ->where('is_approved', false)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Approve a review
     */
    public function approveReview(ComicReview $review): ComicReview
    {
        $review->approve();
        
        // Update comic's average rating
        $review->comic->updateAverageRating();

        return $review;
    }

    /**
     * Reject a review
     */
    public function rejectReview(ComicReview $review): ComicReview
    {
        $review->reject();
        
        // Update comic's average rating
        $review->comic->updateAverageRating();

        return $review;
    }

    /**
     * Get user's review history
     */
    public function getUserReviewHistory(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $user->reviews()
            ->with(['comic'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get most helpful reviews across the platform
     */
    public function getMostHelpfulReviews(int $limit = 10): Collection
    {
        return ComicReview::with(['user', 'comic'])
            ->approved()
            ->where('total_votes', '>=', 5) // Minimum votes threshold
            ->orderByDesc(DB::raw('helpful_votes / total_votes'))
            ->orderByDesc('helpful_votes')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent reviews across the platform
     */
    public function getRecentReviews(int $limit = 10): Collection
    {
        return ComicReview::with(['user', 'comic'])
            ->approved()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Validate review data
     */
    protected function validateReviewData(array $data): array
    {
        $rules = [
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'content' => 'required|string|min:10|max:5000',
            'is_spoiler' => 'boolean',
        ];

        return validator($data, $rules)->validate();
    }

    /**
     * Determine if review should be auto-approved
     */
    protected function shouldAutoApprove(User $user, array $reviewData): bool
    {
        // Auto-approve if:
        // 1. User has good review history (no rejected reviews in last 30 days)
        // 2. Content doesn't contain suspicious patterns

        $recentRejectedReviews = $user->reviews()
            ->where('is_approved', false)
            ->where('created_at', '>', now()->subDays(30))
            ->count();

        if ($recentRejectedReviews > 0) {
            return false;
        }

        // Check for suspicious content patterns
        $content = strtolower($reviewData['content']);
        $suspiciousPatterns = [
            'spam', 'fake', 'bot', 'advertisement', 'buy now', 'click here',
            'free download', 'virus', 'malware', 'hack'
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (strpos($content, $pattern) !== false) {
                return false;
            }
        }

        // Auto-approve all reviews (can be changed for stricter moderation later)
        return true;
    }

    /**
     * Recalculate vote counts for a review
     */
    protected function recalculateReviewVotes(ComicReview $review): void
    {
        $helpfulVotes = $review->votes()->where('is_helpful', true)->count();
        $totalVotes = $review->votes()->count();

        $review->update([
            'helpful_votes' => $helpfulVotes,
            'total_votes' => $totalVotes,
        ]);
    }
}