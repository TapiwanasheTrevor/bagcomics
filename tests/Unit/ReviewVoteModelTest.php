<?php

namespace Tests\Unit;

use App\Models\ComicReview;
use App\Models\ReviewVote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewVoteModelTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ComicReview $review;
    protected ReviewVote $vote;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->review = ComicReview::factory()->create();
        $this->vote = ReviewVote::factory()->create([
            'user_id' => $this->user->id,
            'review_id' => $this->review->id,
            'is_helpful' => true,
        ]);
    }

    public function test_vote_belongs_to_user()
    {
        $this->assertInstanceOf(User::class, $this->vote->user);
        $this->assertEquals($this->user->id, $this->vote->user->id);
    }

    public function test_vote_belongs_to_review()
    {
        $this->assertInstanceOf(ComicReview::class, $this->vote->review);
        $this->assertEquals($this->review->id, $this->vote->review->id);
    }

    public function test_fillable_attributes()
    {
        $vote = new ReviewVote();
        
        $expectedFillable = [
            'user_id',
            'review_id',
            'is_helpful',
        ];
        
        $this->assertEquals($expectedFillable, $vote->getFillable());
    }

    public function test_casts()
    {
        $this->assertEquals('boolean', $this->vote->getCasts()['is_helpful']);
    }

    public function test_vote_creation_with_factory()
    {
        $vote = ReviewVote::factory()->create();
        
        $this->assertInstanceOf(ReviewVote::class, $vote);
        $this->assertNotNull($vote->user_id);
        $this->assertNotNull($vote->review_id);
        $this->assertIsBool($vote->is_helpful);
    }

    public function test_vote_factory_states()
    {
        $helpfulVote = ReviewVote::factory()->helpful()->create();
        $this->assertTrue($helpfulVote->is_helpful);
        
        $notHelpfulVote = ReviewVote::factory()->notHelpful()->create();
        $this->assertFalse($notHelpfulVote->is_helpful);
    }

    public function test_unique_constraint_user_review()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        // Try to create another vote for the same user and review
        ReviewVote::factory()->create([
            'user_id' => $this->user->id,
            'review_id' => $this->review->id,
        ]);
    }

    public function test_vote_can_be_updated()
    {
        $this->assertTrue($this->vote->is_helpful);
        
        $this->vote->update(['is_helpful' => false]);
        
        $this->assertFalse($this->vote->fresh()->is_helpful);
    }

    public function test_vote_deletion_cascades_properly()
    {
        $voteId = $this->vote->id;
        
        // Delete the user
        $this->user->delete();
        
        // Vote should be deleted due to cascade
        $this->assertNull(ReviewVote::find($voteId));
    }

    public function test_vote_deletion_when_review_deleted()
    {
        $voteId = $this->vote->id;
        
        // Delete the review
        $this->review->delete();
        
        // Vote should be deleted due to cascade
        $this->assertNull(ReviewVote::find($voteId));
    }
}