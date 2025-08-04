<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ComicReview;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewModerationController extends Controller
{
    public function __construct(
        protected ReviewService $reviewService
    ) {
        // Middleware is handled at the route level
    }

    /**
     * Get reviews pending moderation
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 20);
        $reviews = $this->reviewService->getReviewsForModeration($perPage);

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
     * Approve a review
     */
    public function approve(ComicReview $review): JsonResponse
    {
        $approvedReview = $this->reviewService->approveReview($review);

        return response()->json([
            'success' => true,
            'message' => 'Review approved successfully.',
            'data' => [
                'review' => $approvedReview->load(['user', 'comic']),
            ]
        ]);
    }

    /**
     * Reject a review
     */
    public function reject(ComicReview $review): JsonResponse
    {
        $rejectedReview = $this->reviewService->rejectReview($review);

        return response()->json([
            'success' => true,
            'message' => 'Review rejected successfully.',
            'data' => [
                'review' => $rejectedReview->load(['user', 'comic']),
            ]
        ]);
    }

    /**
     * Bulk approve reviews
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $request->validate([
            'review_ids' => 'required|array',
            'review_ids.*' => 'exists:comic_reviews,id',
        ]);

        $reviewIds = $request->input('review_ids');
        $approvedCount = 0;

        foreach ($reviewIds as $reviewId) {
            $review = ComicReview::find($reviewId);
            if ($review && !$review->is_approved) {
                $this->reviewService->approveReview($review);
                $approvedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully approved {$approvedCount} reviews.",
            'data' => [
                'approved_count' => $approvedCount,
            ]
        ]);
    }

    /**
     * Bulk reject reviews
     */
    public function bulkReject(Request $request): JsonResponse
    {
        $request->validate([
            'review_ids' => 'required|array',
            'review_ids.*' => 'exists:comic_reviews,id',
        ]);

        $reviewIds = $request->input('review_ids');
        $rejectedCount = 0;

        foreach ($reviewIds as $reviewId) {
            $review = ComicReview::find($reviewId);
            if ($review && $review->is_approved) {
                $this->reviewService->rejectReview($review);
                $rejectedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully rejected {$rejectedCount} reviews.",
            'data' => [
                'rejected_count' => $rejectedCount,
            ]
        ]);
    }

    /**
     * Get moderation statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'pending_reviews' => ComicReview::where('is_approved', false)->count(),
            'approved_reviews' => ComicReview::where('is_approved', true)->count(),
            'total_reviews' => ComicReview::count(),
            'reviews_today' => ComicReview::whereDate('created_at', today())->count(),
            'reviews_this_week' => ComicReview::whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count(),
            'reviews_this_month' => ComicReview::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Delete a review (admin only)
     */
    public function destroy(ComicReview $review): JsonResponse
    {
        $this->reviewService->deleteReview($review);

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully.'
        ]);
    }
}