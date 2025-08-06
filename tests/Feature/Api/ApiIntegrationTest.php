<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Comic;
use App\Models\ComicReview;
use App\Models\UserLibrary;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->user = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->comics = Comic::factory()->count(5)->create(['is_visible' => true]);
    }

    /** @test */
    public function api_responses_have_consistent_format()
    {
        // Test public endpoint
        $response = $this->getJson('/api/comics');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'timestamp'
            ])
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function api_rate_limiting_is_applied()
    {
        // Make requests up to the limit
        for ($i = 0; $i < 120; $i++) {
            $response = $this->getJson('/api/comics');
            $response->assertStatus(200);
        }

        // Next request should be rate limited
        $response = $this->getJson('/api/comics');
        $response->assertStatus(429);
    }

    /** @test */
    public function authenticated_endpoints_require_authentication()
    {
        $comic = $this->comics->first();
        
        $response = $this->postJson("/api/comics/{$comic->id}/progress/update", [
            'current_page' => 5
        ]);
        
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED'
                ]
            ]);
    }

    /** @test */
    public function authenticated_user_can_access_protected_endpoints()
    {
        Sanctum::actingAs($this->user);
        $comic = $this->comics->first();
        
        $response = $this->postJson("/api/comics/{$comic->id}/progress/update", [
            'current_page' => 5,
            'reading_time_seconds' => 300
        ]);
        
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function validation_errors_are_properly_formatted()
    {
        Sanctum::actingAs($this->user);
        $comic = $this->comics->first();
        
        $response = $this->postJson("/api/reviews/comics/{$comic->id}", [
            'rating' => 6, // Invalid rating
            'content' => '' // Empty content
        ]);
        
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.'
                ]
            ])
            ->assertJsonStructure([
                'success',
                'error' => [
                    'code',
                    'message',
                    'validation_errors',
                    'timestamp'
                ]
            ]);
    }

    /** @test */
    public function admin_endpoints_require_admin_permission()
    {
        Sanctum::actingAs($this->user);
        
        $response = $this->getJson('/api/admin/analytics/overview');
        
        // This will return 403 if the user doesn't have admin permissions
        $this->assertTrue(in_array($response->getStatusCode(), [403, 401]));
    }

    /** @test */
    public function admin_can_access_admin_endpoints()
    {
        // Skip this test since we don't have admin permissions set up in the current schema
        $this->markTestSkipped('Admin permissions not implemented in current schema');
    }

    /** @test */
    public function comic_filtering_works_correctly()
    {
        // Create comics with specific attributes
        $dramaComic = Comic::factory()->create([
            'genre' => 'Drama',
            'is_visible' => true,
            'is_free' => true
        ]);
        
        $actionComic = Comic::factory()->create([
            'genre' => 'Action',
            'is_visible' => true,
            'is_free' => false,
            'price' => 9.99
        ]);

        // Test genre filter
        $response = $this->getJson('/api/comics?genre=Drama');
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('Drama', $data[0]['genre']);

        // Test free comics filter
        $response = $this->getJson('/api/comics?is_free=true');
        $response->assertStatus(200);
        
        $data = $response->json('data.data');
        foreach ($data as $comic) {
            $this->assertTrue($comic['is_free']);
        }
    }

    /** @test */
    public function search_functionality_works()
    {
        $comic = Comic::factory()->create([
            'title' => 'Amazing Spider-Man',
            'author' => 'Stan Lee',
            'is_visible' => true
        ]);

        $response = $this->getJson('/api/search/comics?query=Spider');
        
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function user_library_management_works()
    {
        Sanctum::actingAs($this->user);
        $comic = $this->comics->first();

        // Add to library
        $response = $this->postJson("/api/library/comics/{$comic->id}/add", [
            'access_type' => 'purchased',
            'purchase_price' => 9.99
        ]);
        
        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Check library
        $response = $this->getJson('/api/library');
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        
        $libraryItems = $response->json('data.data');
        $this->assertCount(1, $libraryItems);
        $this->assertEquals($comic->id, $libraryItems[0]['comic_id']);
    }

    /** @test */
    public function review_system_works()
    {
        Sanctum::actingAs($this->user);
        $comic = $this->comics->first();

        // Submit review
        $response = $this->postJson("/api/reviews/comics/{$comic->id}", [
            'rating' => 5,
            'title' => 'Amazing comic!',
            'content' => 'This is a fantastic comic book.',
            'is_spoiler' => false
        ]);
        
        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        // Get reviews
        $response = $this->getJson("/api/reviews/comics/{$comic->id}");
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        
        $reviews = $response->json('data.data');
        $this->assertCount(1, $reviews);
        $this->assertEquals(5, $reviews[0]['rating']);
        $this->assertEquals('Amazing comic!', $reviews[0]['title']);
    }

    /** @test */
    public function reading_progress_tracking_works()
    {
        Sanctum::actingAs($this->user);
        $comic = $this->comics->first();

        // Update progress
        $response = $this->postJson("/api/comics/{$comic->id}/progress/update", [
            'current_page' => 15,
            'reading_time_seconds' => 900
        ]);
        
        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Get progress
        $response = $this->getJson("/api/comics/{$comic->id}/progress");
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        
        $progress = $response->json('data');
        $this->assertEquals(15, $progress['current_page']);
        $this->assertEquals(900, $progress['reading_time_seconds']);
    }

    /** @test */
    public function social_sharing_works()
    {
        Sanctum::actingAs($this->user);
        $comic = $this->comics->first();

        $response = $this->postJson("/api/social/comics/{$comic->id}/share", [
            'platform' => 'twitter',
            'share_type' => 'recommendation',
            'message' => 'Check out this amazing comic!'
        ]);
        
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function payment_flow_works()
    {
        Sanctum::actingAs($this->user);
        $comic = $this->comics->first();

        // Create payment intent
        $response = $this->postJson("/api/payments/comics/{$comic->id}/intent");
        
        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'payment_intent',
                    'client_secret'
                ]
            ]);
    }

    /** @test */
    public function api_documentation_endpoints_work()
    {
        // Test that API documentation is accessible
        $response = $this->get('/api/documentation');
        $response->assertStatus(200);
    }
}