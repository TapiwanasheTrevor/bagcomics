<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\ComicReview;
use App\Models\ReviewVote;
use App\Models\User;
use App\Models\UserLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Comic $comic;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->comic = Comic::factory()->create(['is_free' => false]);
        
        // Give user access to the comic
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'access_type' => 'purchased',
        ]);
    }

    public function test_get_comic_reviews()
    {
        ComicReview::factory()->count(5)->create([
            'comic_id' => $this->comic->id,
            'is_approved' => true,
        ]);

        $response = $this->getJson("/api/reviews/comics/{$this->comic->slug}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'reviews' => [
                        '*' => [
                            'id',
                            'rating',
                            'title',
                            'content',
                            'is_spoiler',
                            'helpful_votes',
                            'total_votes',
                            'created_at',
                            'user' => ['id', 'name'],
                        ]
                    ],
                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                    ],
                    'statistics' => [
                        'total_reviews',
                        'average_rating',
                        'rating_distribution',
                        'total_helpful_votes',
                        'spoiler_count',
                    ],
                ]
            ]);

        $this->assertEquals(5, $response->json('data.statistics.total_reviews'));
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

        $response = $this->getJson("/api/reviews/comics/{$this->comic->slug}?rating=5");

        $response->assertOk();
        $this->assertEquals(3, $response->json('data.pagination.total'));
    }

    public function test_submit_review_success()
    {
        $reviewData = [
            'rating' => 4,
            'title' => 'Great comic!',
            'content' => 'This is a really good comic with excellent artwork and story.',
            'is_spoiler' => false,
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/reviews/comics/{$this->comic->slug}", $reviewData);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'review' => [
                        'id',
                        'rating',
                        'title',
                        'content',
                        'is_spoiler',
                        'user',
                    ],
                    'requires_approval',
                ]
            ]);

        $this->assertDatabaseHas('comic_reviews', [
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'rating' => 4,
            'title' => 'Great comic!',
        ]);
    }

    public function test_submit_review_validation_errors()
    {
        $invalidData = [
            'rating' => 6, // Invalid rating
            'content' => 'Too short', // Too short content
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/reviews/comics/{$this->comic->slug}", $invalidData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['rating', 'content']);
    }

    public function test_submit_review_fails_without_access()
    {
        $userWithoutAccess = User::factory()->create();
        
        $reviewData = [
            'rating' => 4,
            'content' => 'Trying to review without access to the comic.',
        ];

        $response = $this->actingAs($userWithoutAccess)
            ->postJson("/api/reviews/comics/{$this->comic->slug}", $reviewData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['comic']);
    }

    public function test_submit_review_fails_if_already_reviewed()
    {
        ComicReview::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);

        $reviewData = [
            'rating' => 4,
            'content' => 'Another review attempt.',
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/reviews/comics/{$this->comic->slug}", $reviewData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['comic']);
    }

    public function test_get_specific_review()
    {
        $review = ComicReview::factory()->create([
            'comic_id' => $this->comic->id,
            'is_approved' => true,
        ]);

        $response = $this->getJson("/api/reviews/{$review->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'review' => [
                        'id',
                        'rating',
                        'title',
                        'content',
                        'user',
                        'comic',
                        'votes',
                    ],
                    'helpfulness_ratio',
                ]
            ]);
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

        $response = $this->actingAs($this->user)
            ->putJson("/api/reviews/{$review->id}", $updateData);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'review',
                    'requires_approval',
                ]
            ]);

        $this->assertDatabaseHas('comic_reviews', [
            'id' => $review->id,
            'rating' => 5,
            'title' => 'Updated title',
            'is_spoiler' => true,
        ]);
    }

    public function test_update_review_fails_for_other_users()
    {
        $otherUser = User::factory()->create();
        $review = ComicReview::factory()->create([
            'user_id' => $otherUser->id,
            'comic_id' => $this->comic->id,
        ]);

        $updateData = [
            'rating' => 5,
            'content' => 'Trying to update someone else\'s review.',
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/reviews/{$review->id}", $updateData);

        $response->assertForbidden();
    }

    public function test_delete_review_success()
    {
        $review = ComicReview::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/reviews/{$review->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Review deleted successfully.'
            ]);

        $this->assertDatabaseMissing('comic_reviews', ['id' => $review->id]);
    }

    public function test_delete_review_fails_for_other_users()
    {
        $otherUser = User::factory()->create();
        $review = ComicReview::factory()->create([
            'user_id' => $otherUser->id,
            'comic_id' => $this->comic->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/reviews/{$review->id}");

        $response->assertForbidden();
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

        $response = $this->actingAs($this->user)
            ->postJson("/api/reviews/{$review->id}/vote", ['is_helpful' => true]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'vote',
                    'review_stats' => [
                        'helpful_votes',
                        'total_votes',
                        'helpfulness_ratio',
                    ]
                ]
            ]);

        $this->assertDatabaseHas('review_votes', [
            'user_id' => $this->user->id,
            'review_id' => $review->id,
            'is_helpful' => true,
        ]);
    }

    public function test_vote_on_own_review_fails()
    {
        $review = ComicReview::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/reviews/{$review->id}/vote", ['is_helpful' => true]);

        $response->assertUnprocessable();
    }

    public function test_remove_vote_from_review()
    {
        $reviewer = User::factory()->create();
        $review = ComicReview::factory()->create([
            'user_id' => $reviewer->id,
            'comic_id' => $this->comic->id,
        ]);

        ReviewVote::factory()->create([
            'user_id' => $this->user->id,
            'review_id' => $review->id,
            'is_helpful' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/reviews/{$review->id}/vote");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Vote removed successfully.'
            ]);

        $this->assertDatabaseMissing('review_votes', [
            'user_id' => $this->user->id,
            'review_id' => $review->id,
        ]);
    }

    public function test_get_user_review_for_comic()
    {
        $review = ComicReview::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/reviews/comics/{$this->comic->slug}/user");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'review' => [
                        'id',
                        'rating',
                        'title',
                        'content',
                        'votes',
                    ],
                    'can_edit',
                ]
            ]);

        $this->assertEquals($review->id, $response->json('data.review.id'));
        $this->assertTrue($response->json('data.can_edit'));
    }

    public function test_get_user_review_for_comic_when_no_review()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/reviews/comics/{$this->comic->slug}/user");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'review' => null,
                    'can_review' => true,
                ]
            ]);
    }

    public function test_get_user_review_history()
    {
        ComicReview::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'web')
            ->getJson('/api/reviews/user');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'reviews' => [
                        '*' => [
                            'id',
                            'rating',
                            'title',
                            'content',
                            'comic',
                        ]
                    ],
                    'pagination',
                ]
            ]);

        $this->assertEquals(3, $response->json('data.pagination.total'));
    }

    public function test_get_most_helpful_reviews()
    {
        ComicReview::factory()->count(3)->create([
            'is_approved' => true,
            'helpful_votes' => 10,
            'total_votes' => 12,
        ]);

        $response = $this->getJson('/api/reviews/most-helpful?limit=5');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'reviews' => [
                        '*' => [
                            'id',
                            'rating',
                            'title',
                            'content',
                            'helpful_votes',
                            'total_votes',
                            'user',
                            'comic',
                        ]
                    ]
                ]
            ]);
    }

    public function test_get_recent_reviews()
    {
        ComicReview::factory()->count(5)->create([
            'is_approved' => true,
            'created_at' => now()->subHours(1),
        ]);

        $response = $this->getJson('/api/reviews/recent?limit=10');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'reviews' => [
                        '*' => [
                            'id',
                            'rating',
                            'title',
                            'content',
                            'created_at',
                            'user',
                            'comic',
                        ]
                    ]
                ]
            ]);

        $this->assertCount(5, $response->json('data.reviews'));
    }

    public function test_unauthenticated_user_cannot_submit_review()
    {
        $reviewData = [
            'rating' => 4,
            'content' => 'This should fail without authentication.',
        ];

        $response = $this->postJson("/api/reviews/comics/{$this->comic->slug}", $reviewData);

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_can_view_reviews()
    {
        ComicReview::factory()->count(3)->create([
            'comic_id' => $this->comic->id,
            'is_approved' => true,
        ]);

        $response = $this->getJson("/api/reviews/comics/{$this->comic->slug}");

        $response->assertOk();
        $this->assertEquals(3, $response->json('data.statistics.total_reviews'));
    }
}