<?php

namespace Tests\Unit;

use App\Models\Comic;
use App\Models\ComicReview;
use App\Models\ReviewVote;
use App\Models\User;
use App\Models\UserLibrary;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ReviewService $reviewService;
    protected User $user;
    protected Comic $comic;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->reviewService = new ReviewService();
        $this->user = User::factory()->create();
        $this->comic = Comic::factory()->create(['is_free' => false]);
        
        // Give user access to the comic
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'access_type' => 'purchased',
        ]);
    }

    public function test_submit_review_success()
    {
        $reviewData = [
            'rating' => 4,
            'title' => 'Great comic!',
            'content' => 'This is a really good comic with excellent artwork and story.',
            'is_spoiler' => false,
        ];

        $review = $this->reviewService->submitReview($this->user, $this->comic, $reviewData);

        $this->assertInstanceOf(ComicReview::class, $review);
        $this->assertEquals($this->user->id, $review->user_id);
        $this->assertEquals($this->comic->id, $review->comic_id);
        $this->assertEquals(4, $review->rating);
        $this->assertEquals('Great comic!', $review->title);
        $this->assertEquals($reviewData['content'], $review->content);
        $this->assertFalse($review->is_spoiler);
    }

    public function test_submit_review_fails_if_already_reviewed()
    {
        // Create existing review
        ComicReview::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);

        $reviewData = [
            'rating' => 4,
            'content' => 'Another review attempt.',
        ];

        $this->expectException(ValidationException::class);
        $this->reviewService->submitReview($this->user, $this->comic, $reviewData);
    }

    public function test_submit_review_fails_if_no_access()
    {
        $userWithoutAccess = User::factory()->create();
        
        $reviewData = [
            'rating' => 4,
            'content' => 'Trying to review without access.',
        ];

        $this->expectException(ValidationException::class);
        $this->reviewService->submitReview($userWithoutAccess, $this->comic, $reviewData);
    }

    public function test_update_review_success()
    {
        $review = ComicReview::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'rating' => 3,
            'content' => 'Original content',
        ]);

        $updateData = [
            'rating' => 5,
            'title' => 'Updated title',
            'content' => 'Updated content with more details about the comic.',
            'is_spoiler' => true,
        ];

        $updatedReview = $this->reviewService->updateReview($review, $updateData);

        $this->assertEquals(5, $updatedReview->rating);
        $this->assertEquals('Updated title', $updatedReview->title);
        $this->assertEquals($updateData['content'], $updatedReview->content);
        $this->assertTrue($updatedReview->is_spoiler);
    }

    public function test_delete_review_success()
    {
        $review = ComicReview::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);

        // Add some votes to test cascade deletion
        ReviewVote::factory()->count(3)->create(['review_id' => $review->id]);

        $reviewId = $review->id;
        $result = $this->reviewService->deleteReview($review);

        $this->assertTrue($result);
        $this->assertNull(ComicReview::find($reviewId));
        $this->assertEquals(0, ReviewVote::where('review_id', $reviewId)->count());
    }

    public function test_get_comic_reviews_with_pagination()
    {
        ComicReview::factory()->count(20)->create([
            'comic_id' => $this->comic->id,
            'is_approved' => true,
        ]);

        $reviews = $this->reviewService->getComicReviews($this->comic, [], 10);

        $this->assertInstanceOf(LengthAwarePaginator::class, $reviews);
        $this->assertEquals(10, $reviews->perPage());
        $this->assertEquals(20, $reviews->total());
    }

    public function test_get_comic_reviews_with_filters()
    {
        ComicReview::factory()->count(3)->create([
            'comic_id' => $this->comic->id,
            'rating' => 5,
            'is_approved' => true,
        ]);
        ComicReview::factory()->count(2)->create([
            'comic_id' => $this->comic->id,
            'rating' => 3,
            'is_approved' => true,
        ]);

        $filters = ['rating' => 5];
        $reviews = $this->reviewService->getComicReviews($this->comic, $filters);

        $this->assertEquals(3, $reviews->total());
        foreach ($reviews->items() as $review) {
            $this->assertEquals(5, $review->rating);
        }
    }

    public function test_get_comic_reviews_excludes_spoilers()
    {
        ComicReview::factory()->count(2)->create([
            'comic_id' => $this->comic->id,
            'is_spoiler' => true,
            'is_approved' => true,
        ]);
        ComicReview::factory()->count(3)->create([
            'comic_id' => $this->comic->id,
            'is_spoiler' => false,
            'is_approved' => true,
        ]);

        $filters = ['include_spoilers' => false];
        $reviews = $this->reviewService->getComicReviews($this->comic, $filters);

        $this->assertEquals(3, $reviews->total());
        foreach ($reviews->items() as $review) {
            $this->assertFalse($review->is_spoiler);
        }
    }

    public function test_get_user_review_for_comic()
    {
        $review = ComicReview::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);

        $foundReview = $this->reviewService->getUserReviewForComic($this->user, $this->comic);

        $this->assertInstanceOf(ComicReview::class, $foundReview);
        $this->assertEquals($review->id, $foundReview->id);
    }

    public function test_vote_on_review_success()
    {
        $reviewer = User::factory()->create();
        $review = ComicReview::factory()->create([
            'user_id' => $reviewer->id,
            'comic_id' => $this->comic->id,
            'helpful_votes' => 0,
            'total_votes' => 0,
        ]);

        $vote = $this->reviewService->voteOnReview($this->user, $review, true);

        $this->assertInstanceOf(ReviewVote::class, $vote);
        $this->assertEquals($this->user->id, $vote->user_id);
        $this->assertEquals($review->id, $vote->review_id);
        $this->assertTrue($vote->is_helpful);

        $review->refresh();
        $this->assertEquals(1, $review->helpful_votes);
        $this->assertEquals(1, $review->total_votes);
    }

    public function test_vote_on_review_fails_for_own_review()
    {
        $review = ComicReview::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->reviewService->voteOnReview($this->user, $review, true);
    }

    public function test_vote_on_review_updates_existing_vote()
    {
        $reviewer = User::factory()->create();
        $review = ComicReview::factory()->create([
            'user_id' => $reviewer->id,
            'comic_id' => $this->comic->id,
            'helpful_votes' => 0,
            'total_votes' => 1,
        ]);

        // Create existing vote
        ReviewVote::factory()->create([
            'user_id' => $this->user->id,
            'review_id' => $review->id,
            'is_helpful' => false,
        ]);

        // Update vote to helpful
        $vote = $this->reviewService->voteOnReview($this->user, $review, true);

        $this->assertTrue($vote->is_helpful);
        
        $review->refresh();
        $this->assertEquals(1, $review->helpful_votes);
        $this->assertEquals(1, $review->total_votes); // Should stay the same
    }

    public function test_remove_vote_from_review()
    {
        $reviewer = User::factory()->create();
        $review = ComicReview::factory()->create([
            'user_id' => $reviewer->id,
            'comic_id' => $this->comic->id,
            'helpful_votes' => 1,
            'total_votes' => 1,
        ]);

        ReviewVote::factory()->create([
            'user_id' => $this->user->id,
            'review_id' => $review->id,
            'is_helpful' => true,
        ]);

        $result = $this->reviewService->removeVoteFromReview($this->user, $review);

        $this->assertTrue($result);
        
        $review->refresh();
        $this->assertEquals(0, $review->helpful_votes);
        $this->assertEquals(0, $review->total_votes);
    }

    public function test_get_review_statistics()
    {
        // Create reviews with different ratings
        ComicReview::factory()->count(2)->create([
            'comic_id' => $this->comic->id,
            'rating' => 5,
            'is_approved' => true,
            'is_spoiler' => false,
            'helpful_votes' => 10,
        ]);
        ComicReview::factory()->count(3)->create([
            'comic_id' => $this->comic->id,
            'rating' => 4,
            'is_approved' => true,
            'is_spoiler' => false,
            'helpful_votes' => 5,
        ]);
        ComicReview::factory()->create([
            'comic_id' => $this->comic->id,
            'rating' => 3,
            'is_approved' => true,
            'is_spoiler' => true,
            'helpful_votes' => 2,
        ]);

        $stats = $this->reviewService->getReviewStatistics($this->comic);

        $this->assertEquals(6, $stats['total_reviews']);
        $this->assertEquals(4.17, round($stats['average_rating'], 2)); // (5*2 + 4*3 + 3*1) / 6 = 25/6 ≈ 4.17
        $this->assertEquals(37, $stats['total_helpful_votes']); // 10*2 + 5*3 + 2*1
        $this->assertEquals(1, $stats['spoiler_count']);
        
        // Check rating distribution
        $this->assertEquals(2, $stats['rating_distribution'][5]['count']);
        $this->assertEquals(33.3, $stats['rating_distribution'][5]['percentage']);
        $this->assertEquals(3, $stats['rating_distribution'][4]['count']);
        $this->assertEquals(50.0, $stats['rating_distribution'][4]['percentage']);
    }

    public function test_approve_review()
    {
        $review = ComicReview::factory()->create([
            'is_approved' => false,
            'comic_id' => $this->comic->id,
        ]);

        $approvedReview = $this->reviewService->approveReview($review);

        $this->assertTrue($approvedReview->is_approved);
    }

    public function test_reject_review()
    {
        $review = ComicReview::factory()->create([
            'is_approved' => true,
            'comic_id' => $this->comic->id,
        ]);

        $rejectedReview = $this->reviewService->rejectReview($review);

        $this->assertFalse($rejectedReview->is_approved);
    }

    public function test_get_reviews_for_moderation()
    {
        ComicReview::factory()->count(5)->create(['is_approved' => false]);
        ComicReview::factory()->count(3)->create(['is_approved' => true]);

        $reviews = $this->reviewService->getReviewsForModeration(10);

        $this->assertInstanceOf(LengthAwarePaginator::class, $reviews);
        $this->assertEquals(5, $reviews->total());
        foreach ($reviews->items() as $review) {
            $this->assertFalse($review->is_approved);
        }
    }

    public function test_get_user_review_history()
    {
        ComicReview::factory()->count(3)->create(['user_id' => $this->user->id]);
        ComicReview::factory()->count(2)->create(); // Other users' reviews

        $reviews = $this->reviewService->getUserReviewHistory($this->user, 10);

        $this->assertInstanceOf(LengthAwarePaginator::class, $reviews);
        $this->assertEquals(3, $reviews->total());
        foreach ($reviews->items() as $review) {
            $this->assertEquals($this->user->id, $review->user_id);
        }
    }

    public function test_get_most_helpful_reviews()
    {
        // Create reviews with different helpfulness ratios
        ComicReview::factory()->create([
            'is_approved' => true,
            'helpful_votes' => 9,
            'total_votes' => 10, // 90% helpful
        ]);
        ComicReview::factory()->create([
            'is_approved' => true,
            'helpful_votes' => 4,
            'total_votes' => 5, // 80% helpful
        ]);
        ComicReview::factory()->create([
            'is_approved' => true,
            'helpful_votes' => 2,
            'total_votes' => 3, // 66% helpful, but below threshold
        ]);

        $reviews = $this->reviewService->getMostHelpfulReviews(5);

        $this->assertCount(2, $reviews); // Only reviews with >= 5 votes
        $this->assertEquals(9, $reviews->first()->helpful_votes);
    }

    public function test_get_recent_reviews()
    {
        ComicReview::factory()->count(5)->create([
            'is_approved' => true,
            'created_at' => now()->subDays(1),
        ]);
        ComicReview::factory()->count(3)->create([
            'is_approved' => true,
            'created_at' => now()->subDays(2),
        ]);

        $reviews = $this->reviewService->getRecentReviews(10);

        $this->assertCount(8, $reviews);
        // Should be ordered by created_at desc
        $this->assertTrue($reviews->first()->created_at->isAfter($reviews->last()->created_at));
    }
}