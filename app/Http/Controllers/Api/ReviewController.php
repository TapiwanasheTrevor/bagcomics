<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comic;
use App\Models\ComicReview;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function __construct(
        protected ReviewService $reviewService
    ) {}

    /**
     * Get reviews for a specific comic
     */
    public function index(Request $request, Comic $comic): JsonResponse
    {
        $filters = $request->only(['rating', 'include_spoilers', 'sort']);
        $perPage = $request->integer('per_page', 15);

        $reviews = $this->reviewService->getComicReviews($comic, $filters, $perPage);
        $statistics = $this->reviewService->getReviewStatistics($comic);

        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => $reviews->items(),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ],
                'statistics' => $statistics,
            ]
        ]);
    }

    /**
     * Submit a new review
     */
    public function store(Request $request, Comic $comic): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $reviewData = $request->validate([
                'rating' => 'required|integer|min:1|max:5',
                'title' => 'nullable|string|max:255',
                'content' => 'required|string|min:10|max:5000',
                'is_spoiler' => 'boolean',
            ]);

            $review = $this->reviewService->submitReview($user, $comic, $reviewData);

            return response()->json([
                'success' => true,
                'message' => $review->is_approved 
                    ? 'Review submitted successfully!' 
                    : 'Review submitted and is pending approval.',
                'data' => [
                    'review' => $review->load(['user']),
                    'requires_approval' => !$review->is_approved,
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Get a specific review
     */
    public function show(ComicReview $review): JsonResponse
    {
        $review->load(['user', 'comic', 'votes']);

        return response()->json([
            'success' => true,
            'data' => [
                'review' => $review,
                'helpfulness_ratio' => $review->getHelpfulnessRatio(),
            ]
        ]);
    }

    /**
     * Update a review
     */
    public function update(Request $request, ComicReview $review): JsonResponse
    {
        // Check if user owns the review
        if ($review->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only edit your own reviews.'
            ], 403);
        }

        try {
            $reviewData = $request->validate([
                'rating' => 'required|integer|min:1|max:5',
                'title' => 'nullable|string|max:255',
                'content' => 'required|string|min:10|max:5000',
                'is_spoiler' => 'boolean',
            ]);

            $updatedReview = $this->reviewService->updateReview($review, $reviewData);

            return response()->json([
                'success' => true,
                'message' => $updatedReview->is_approved 
                    ? 'Review updated successfully!' 
                    : 'Review updated and is pending approval.',
                'data' => [
                    'review' => $updatedReview->load(['user']),
                    'requires_approval' => !$updatedReview->is_approved,
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Delete a review
     */
    public function destroy(ComicReview $review): JsonResponse
    {
        // Check if user owns the review
        if ($review->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete your own reviews.'
            ], 403);
        }

        $this->reviewService->deleteReview($review);

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully.'
        ]);
    }

    /**
     * Vote on review helpfulness
     */
    public function vote(Request $request, ComicReview $review): JsonResponse
    {
        try {
            $request->validate([
                'is_helpful' => 'required|boolean',
            ]);

            $user = Auth::user();
            $vote = $this->reviewService->voteOnReview($user, $review, $request->boolean('is_helpful'));

            return response()->json([
                'success' => true,
                'message' => 'Vote recorded successfully.',
                'data' => [
                    'vote' => $vote,
                    'review_stats' => [
                        'helpful_votes' => $review->fresh()->helpful_votes,
                        'total_votes' => $review->fresh()->total_votes,
                        'helpfulness_ratio' => $review->fresh()->getHelpfulnessRatio(),
                    ]
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Remove vote from review
     */
    public function removeVote(ComicReview $review): JsonResponse
    {
        $user = Auth::user();
        $removed = $this->reviewService->removeVoteFromReview($user, $review);

        if ($removed) {
            return response()->json([
                'success' => true,
                'message' => 'Vote removed successfully.',
                'data' => [
                    'review_stats' => [
                        'helpful_votes' => $review->fresh()->helpful_votes,
                        'total_votes' => $review->fresh()->total_votes,
                        'helpfulness_ratio' => $review->fresh()->getHelpfulnessRatio(),
                    ]
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No vote found to remove.'
        ], 404);
    }

    /**
     * Get user's review for a specific comic
     */
    public function getUserReview(Comic $comic): JsonResponse
    {
        $user = Auth::user();
        $review = $this->reviewService->getUserReviewForComic($user, $comic);

        if ($review) {
            return response()->json([
                'success' => true,
                'data' => [
                    'review' => $review->load(['votes']),
                    'can_edit' => true,
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'review' => null,
                'can_review' => $user->hasAccessToComic($comic),
            ]
        ]);
    }

    /**
     * Get user's review history
     */
    public function getUserReviews(Request $request): JsonResponse
    {
        $user = Auth::user();
        $perPage = $request->integer('per_page', 15);

        $reviews = $this->reviewService->getUserReviewHistory($user, $perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => $reviews->items(),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ],
            ]
        ]);
    }

    /**
     * Get most helpful reviews across platform
     */
    public function getMostHelpful(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 10);
        $reviews = $this->reviewService->getMostHelpfulReviews($limit);

        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => $reviews,
            ]
        ]);
    }

    /**
     * Get recent reviews across platform
     */
    public function getRecent(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 10);
        $reviews = $this->reviewService->getRecentReviews($limit);

        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => $reviews,
            ]
        ]);
    }
}