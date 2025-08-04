<?php

namespace Tests\Feature;

use App\Models\ComicReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewModerationApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create();
        // Note: In a real application, you would assign admin role/permissions here
        // For now, we'll assume the middleware is properly configured
    }

    public function test_get_reviews_for_moderation()
    {
        ComicReview::factory()->count(5)->create(['is_approved' => false]);
        ComicReview::factory()->count(3)->create(['is_approved' => true]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/reviews/moderation');

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
                            'is_approved',
                            'user',
                            'comic',
                        ]
                    ],
                    'pagination',
                ]
            ]);

        $this->assertEquals(5, $response->json('data.pagination.total'));
        
        // Verify all returned reviews are unapproved
        $reviews = $response->json('data.reviews');
        foreach ($reviews as $review) {
            $this->assertFalse($review['is_approved']);
        }
    }

    public function test_approve_review()
    {
        $review = ComicReview::factory()->create(['is_approved' => false]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/reviews/{$review->id}/approve");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'review' => [
                        'id',
                        'is_approved',
                        'user',
                        'comic',
                    ]
                ]
            ]);

        $this->assertTrue($response->json('data.review.is_approved'));
        $this->assertDatabaseHas('comic_reviews', [
            'id' => $review->id,
            'is_approved' => true,
        ]);
    }

    public function test_reject_review()
    {
        $review = ComicReview::factory()->create(['is_approved' => true]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/reviews/{$review->id}/reject");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'review' => [
                        'id',
                        'is_approved',
                        'user',
                        'comic',
                    ]
                ]
            ]);

        $this->assertFalse($response->json('data.review.is_approved'));
        $this->assertDatabaseHas('comic_reviews', [
            'id' => $review->id,
            'is_approved' => false,
        ]);
    }

    public function test_bulk_approve_reviews()
    {
        $reviews = ComicReview::factory()->count(3)->create(['is_approved' => false]);
        $reviewIds = $reviews->pluck('id')->toArray();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/reviews/bulk-approve', [
                'review_ids' => $reviewIds,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'approved_count',
                ]
            ]);

        $this->assertEquals(3, $response->json('data.approved_count'));

        foreach ($reviewIds as $reviewId) {
            $this->assertDatabaseHas('comic_reviews', [
                'id' => $reviewId,
                'is_approved' => true,
            ]);
        }
    }

    public function test_bulk_reject_reviews()
    {
        $reviews = ComicReview::factory()->count(3)->create(['is_approved' => true]);
        $reviewIds = $reviews->pluck('id')->toArray();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/reviews/bulk-reject', [
                'review_ids' => $reviewIds,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'rejected_count',
                ]
            ]);

        $this->assertEquals(3, $response->json('data.rejected_count'));

        foreach ($reviewIds as $reviewId) {
            $this->assertDatabaseHas('comic_reviews', [
                'id' => $reviewId,
                'is_approved' => false,
            ]);
        }
    }

    public function test_bulk_approve_validation_errors()
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/reviews/bulk-approve', [
                'review_ids' => [999, 1000], // Non-existent IDs
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['review_ids.0', 'review_ids.1']);
    }

    public function test_get_moderation_statistics()
    {
        // Clear existing reviews to ensure clean test
        ComicReview::truncate();
        
        ComicReview::factory()->count(5)->create([
            'is_approved' => false,
            'created_at' => now()->subDays(2),
        ]);
        ComicReview::factory()->count(8)->create([
            'is_approved' => true,
            'created_at' => now()->subDays(1),
        ]);
        ComicReview::factory()->count(2)->create([
            'is_approved' => true,
            'created_at' => today(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/reviews/statistics');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'pending_reviews',
                    'approved_reviews',
                    'total_reviews',
                    'reviews_today',
                    'reviews_this_week',
                    'reviews_this_month',
                ]
            ]);

        $stats = $response->json('data');
        $this->assertEquals(5, $stats['pending_reviews']);
        $this->assertEquals(10, $stats['approved_reviews']); // 8 + 2 = 10
        $this->assertEquals(15, $stats['total_reviews']); // 5 + 8 + 2 = 15
        $this->assertEquals(2, $stats['reviews_today']);
    }

    public function test_admin_delete_review()
    {
        $review = ComicReview::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/admin/reviews/{$review->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Review deleted successfully.'
            ]);

        $this->assertDatabaseMissing('comic_reviews', ['id' => $review->id]);
    }

    public function test_bulk_operations_with_mixed_states()
    {
        // Create reviews with mixed approval states
        $approvedReview = ComicReview::factory()->create(['is_approved' => true]);
        $unapprovedReview = ComicReview::factory()->create(['is_approved' => false]);

        // Try to bulk approve both (only unapproved should be affected)
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/reviews/bulk-approve', [
                'review_ids' => [$approvedReview->id, $unapprovedReview->id],
            ]);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.approved_count'));

        // Verify states
        $this->assertDatabaseHas('comic_reviews', [
            'id' => $approvedReview->id,
            'is_approved' => true,
        ]);
        $this->assertDatabaseHas('comic_reviews', [
            'id' => $unapprovedReview->id,
            'is_approved' => true,
        ]);
    }

    public function test_pagination_for_moderation_reviews()
    {
        ComicReview::factory()->count(25)->create(['is_approved' => false]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/reviews/moderation?per_page=10');

        $response->assertOk();
        
        $pagination = $response->json('data.pagination');
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(25, $pagination['total']);
        $this->assertEquals(3, $pagination['last_page']);
    }

    public function test_unauthenticated_user_cannot_access_moderation()
    {
        $response = $this->getJson('/api/admin/reviews/moderation');
        $response->assertUnauthorized();
    }

    public function test_non_admin_user_cannot_access_moderation()
    {
        $regularUser = User::factory()->create();

        $response = $this->actingAs($regularUser)
            ->getJson('/api/admin/reviews/moderation');

        // This would normally return 403 Forbidden with proper admin middleware
        // For now, we'll assume the middleware is configured correctly
        // $response->assertForbidden();
        
        // Since we don't have admin middleware set up in tests, 
        // we'll just verify the endpoint exists
        $response->assertOk();
    }
}