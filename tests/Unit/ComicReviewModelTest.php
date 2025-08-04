<?php

namespace Tests\Unit;

use App\Models\Comic;
use App\Models\ComicReview;
use App\Models\ReviewVote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComicReviewModelTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Comic $comic;
    protected ComicReview $review;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->comic = Comic::factory()->create();
        $this->review = ComicReview::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'rating' => 4,
            'title' => 'Great comic!',
            'content' => 'This is a really good comic with excellent artwork.',
            'is_spoiler' => false,
            'helpful_votes' => 5,
            'total_votes' => 8,
            'is_approved' => true,
        ]);
    }

    public function test_review_belongs_to_user()
    {
        $this->assertInstanceOf(User::class, $this->review->user);
        $this->assertEquals($this->user->id, $this->review->user->id);
    }

    public function test_review_belongs_to_comic()
    {
        $this->assertInstanceOf(Comic::class, $this->review->comic);
        $this->assertEquals($this->comic->id, $this->review->comic->id);
    }

    public function test_review_has_many_votes()
    {
        ReviewVote::factory()->count(3)->create(['review_id' => $this->review->id]);
        
        $this->assertCount(3, $this->review->votes);
        $this->assertInstanceOf(ReviewVote::class, $this->review->votes->first());
    }

    public function test_get_helpfulness_ratio()
    {
        $this->assertEquals(0.625, $this->review->getHelpfulnessRatio()); // 5/8 = 0.625
        
        // Test with zero votes
        $reviewWithoutVotes = ComicReview::factory()->create([
            'helpful_votes' => 0,
            'total_votes' => 0,
        ]);
        $this->assertEquals(0.0, $reviewWithoutVotes->getHelpfulnessRatio());
    }

    public function test_add_helpful_vote_new_vote()
    {
        $voter = User::factory()->create();
        
        $this->review->addHelpfulVote($voter, true);
        
        $this->review->refresh();
        $this->assertEquals(6, $this->review->helpful_votes);
        $this->assertEquals(9, $this->review->total_votes);
        
        $vote = $this->review->votes()->where('user_id', $voter->id)->first();
        $this->assertNotNull($vote);
        $this->assertTrue($vote->is_helpful);
    }

    public function test_add_helpful_vote_update_existing()
    {
        $voter = User::factory()->create();
        
        // Create initial vote as not helpful
        ReviewVote::factory()->create([
            'user_id' => $voter->id,
            'review_id' => $this->review->id,
            'is_helpful' => false,
        ]);
        
        // Update to helpful
        $this->review->addHelpfulVote($voter, true);
        
        $this->review->refresh();
        $this->assertEquals(6, $this->review->helpful_votes); // Should increase by 1
        $this->assertEquals(8, $this->review->total_votes); // Should stay the same
        
        $vote = $this->review->votes()->where('user_id', $voter->id)->first();
        $this->assertTrue($vote->is_helpful);
    }

    public function test_add_unhelpful_vote_new_vote()
    {
        $voter = User::factory()->create();
        
        $this->review->addHelpfulVote($voter, false);
        
        $this->review->refresh();
        $this->assertEquals(5, $this->review->helpful_votes); // Should stay the same
        $this->assertEquals(9, $this->review->total_votes); // Should increase by 1
        
        $vote = $this->review->votes()->where('user_id', $voter->id)->first();
        $this->assertNotNull($vote);
        $this->assertFalse($vote->is_helpful);
    }

    public function test_is_approved()
    {
        $this->assertTrue($this->review->isApproved());
        
        $unapprovedReview = ComicReview::factory()->create(['is_approved' => false]);
        $this->assertFalse($unapprovedReview->isApproved());
    }

    public function test_approve_review()
    {
        $unapprovedReview = ComicReview::factory()->create(['is_approved' => false]);
        
        $unapprovedReview->approve();
        
        $this->assertTrue($unapprovedReview->is_approved);
    }

    public function test_reject_review()
    {
        $this->review->reject();
        
        $this->assertFalse($this->review->is_approved);
    }

    public function test_approved_scope()
    {
        ComicReview::factory()->count(3)->create(['is_approved' => true]);
        ComicReview::factory()->count(2)->create(['is_approved' => false]);
        
        $approvedReviews = ComicReview::approved()->get();
        
        $this->assertCount(4, $approvedReviews); // 3 new + 1 from setUp
        $approvedReviews->each(function ($review) {
            $this->assertTrue($review->is_approved);
        });
    }

    public function test_by_rating_scope()
    {
        ComicReview::factory()->count(2)->create(['rating' => 5]);
        ComicReview::factory()->count(3)->create(['rating' => 3]);
        
        $fiveStarReviews = ComicReview::byRating(5)->get();
        $threeStarReviews = ComicReview::byRating(3)->get();
        
        $this->assertCount(2, $fiveStarReviews);
        $this->assertCount(3, $threeStarReviews);
        
        $fiveStarReviews->each(function ($review) {
            $this->assertEquals(5, $review->rating);
        });
    }

    public function test_with_spoilers_scope_exclude_spoilers()
    {
        ComicReview::factory()->count(2)->create(['is_spoiler' => true]);
        ComicReview::factory()->count(3)->create(['is_spoiler' => false]);
        
        $nonSpoilerReviews = ComicReview::withSpoilers(false)->get();
        
        $this->assertCount(4, $nonSpoilerReviews); // 3 new + 1 from setUp
        $nonSpoilerReviews->each(function ($review) {
            $this->assertFalse($review->is_spoiler);
        });
    }

    public function test_with_spoilers_scope_include_spoilers()
    {
        ComicReview::factory()->count(2)->create(['is_spoiler' => true]);
        ComicReview::factory()->count(3)->create(['is_spoiler' => false]);
        
        $allReviews = ComicReview::withSpoilers(true)->get();
        
        $this->assertCount(6, $allReviews); // 2 + 3 + 1 from setUp
    }

    public function test_validation_rules()
    {
        $rules = ComicReview::validationRules();
        
        $expectedRules = [
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'content' => 'required|string|min:10|max:5000',
            'is_spoiler' => 'boolean',
        ];
        
        $this->assertEquals($expectedRules, $rules);
    }

    public function test_fillable_attributes()
    {
        $review = new ComicReview();
        
        $expectedFillable = [
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
        
        $this->assertEquals($expectedFillable, $review->getFillable());
    }

    public function test_casts()
    {
        $expectedCasts = [
            'rating' => 'integer',
            'is_spoiler' => 'boolean',
            'helpful_votes' => 'integer',
            'total_votes' => 'integer',
            'is_approved' => 'boolean',
        ];
        
        foreach ($expectedCasts as $attribute => $cast) {
            $this->assertEquals($cast, $this->review->getCasts()[$attribute]);
        }
    }

    public function test_review_creation_with_factory()
    {
        $review = ComicReview::factory()->create();
        
        $this->assertInstanceOf(ComicReview::class, $review);
        $this->assertNotNull($review->user_id);
        $this->assertNotNull($review->comic_id);
        $this->assertGreaterThanOrEqual(1, $review->rating);
        $this->assertLessThanOrEqual(5, $review->rating);
        $this->assertNotEmpty($review->content);
    }

    public function test_review_factory_states()
    {
        $spoilerReview = ComicReview::factory()->spoiler()->create();
        $this->assertTrue($spoilerReview->is_spoiler);
        
        $unapprovedReview = ComicReview::factory()->unapproved()->create();
        $this->assertFalse($unapprovedReview->is_approved);
        
        $highRatingReview = ComicReview::factory()->highRating()->create();
        $this->assertGreaterThanOrEqual(4, $highRatingReview->rating);
        
        $lowRatingReview = ComicReview::factory()->lowRating()->create();
        $this->assertLessThanOrEqual(2, $lowRatingReview->rating);
    }

    public function test_unique_constraint_user_comic()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        // Try to create another review for the same user and comic
        ComicReview::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);
    }
}