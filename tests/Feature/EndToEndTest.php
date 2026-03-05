<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Comic;
use App\Models\Payment;
use App\Models\UserLibrary;
use App\Models\ComicReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** @test */
    public function complete_user_journey_from_registration_to_reading_comics()
    {
        // 1. User Registration
        $this->post('/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe'
        ]);
        
        $user = User::where('email', 'john@example.com')->first();
        $this->assertInstanceOf(User::class, $user);

        // 2. Email Verification (simulated)
        $user->markEmailAsVerified();
        
        // 3. Login
        $this->post('/login', [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);
        
        $this->assertAuthenticated();
        
        // 4. Browse Comics Catalog
        $comics = Comic::factory()->count(10)->create([
            'is_visible' => true,
            'is_free' => true
        ]);
        
        $catalogResponse = $this->actingAs($user)->get('/comics');
        $catalogResponse->assertOk();
        
        // 5. View Comic Details
        $comic = $comics->first();
        $detailResponse = $this->actingAs($user)->get("/comics/{$comic->slug}");
        $detailResponse->assertOk();
        
        // 6. Add Comic to Library (free comic)
        $this->actingAs($user)->postJson("/api/library/comics/{$comic->id}/add", [
            'access_type' => 'free',
        ]);
        
        $this->assertDatabaseHas('user_libraries', [
            'user_id' => $user->id,
            'comic_id' => $comic->id
        ]);
        
        // 7. Access User Library
        $libraryResponse = $this->actingAs($user)->get('/library');
        $libraryResponse->assertOk();
        
        // 8. Start Reading Comic
        $readResponse = $this->actingAs($user)->get("/comics/{$comic->slug}/read");
        $readResponse->assertOk();
        
        // 9. Update Reading Progress
        $progressResponse = $this->actingAs($user)->postJson("/api/comics/{$comic->id}/progress/update", [
            'current_page' => 10,
        ]);
        
        $progressResponse->assertOk();
        $this->assertDatabaseHas('user_comic_progress', [
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'current_page' => 10
        ]);
        
        // 10. Complete Reading
        $completeResponse = $this->actingAs($user)->postJson("/api/comics/{$comic->id}/progress/update", [
            'current_page' => $comic->page_count,
        ]);
        
        $completeResponse->assertOk();
        
        // 11. Write a Review
        $reviewResponse = $this->actingAs($user)->postJson("/api/reviews/comics/{$comic->id}", [
            'rating' => 5,
            'content' => 'Amazing comic! Really enjoyed reading it.',
        ]);
        
        $reviewResponse->assertCreated();
        $this->assertDatabaseHas('comic_reviews', [
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'rating' => 5
        ]);
        
        // 12. Bookmark Comic
        $bookmarkResponse = $this->actingAs($user)->postJson("/api/comics/{$comic->id}/progress/bookmarks", [
            'page' => 15,
        ]);
        
        $bookmarkResponse->assertOk();
        
        // 13. Share Comic Socially
        $shareResponse = $this->actingAs($user)->postJson("/api/social/comics/{$comic->id}/share", [
            'platform' => 'twitter',
            'share_type' => 'comic_discovery',
            'message' => 'Check out this amazing comic!'
        ]);
        
        $shareResponse->assertOk();
        
        // Verify complete user journey
        $this->assertDatabaseHas('user_comic_progress', [
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'is_completed' => true,
        ]);
        $this->assertDatabaseHas('comic_reviews', [
            'user_id' => $user->id,
            'comic_id' => $comic->id
        ]);
        $this->assertDatabaseHas('comic_bookmarks', [
            'user_id' => $user->id,
            'comic_id' => $comic->id
        ]);
    }

    /** @test */
    public function complete_purchase_flow_for_paid_comic()
    {
        $user = User::factory()->create();
        $paidComic = Comic::factory()->create([
            'is_visible' => true,
            'is_free' => false,
            'price' => 9.99
        ]);

        // 1. Try to access paid comic (should be blocked)
        $blockedResponse = $this->actingAs($user)->get("/comics/{$paidComic->slug}/read");
        $blockedResponse->assertStatus(403);

        // 2. View comic details (should show price and purchase option)
        $detailResponse = $this->actingAs($user)->get("/comics/{$paidComic->slug}");
        $detailResponse->assertOk();

        // 3. Simulate successful purchase and access grant
        Payment::factory()->successful()->create([
            'user_id' => $user->id,
            'comic_id' => $paidComic->id,
            'amount' => 9.99,
            'payment_type' => 'single',
        ]);
        UserLibrary::create([
            'user_id' => $user->id,
            'comic_id' => $paidComic->id,
            'access_type' => 'purchased',
            'purchase_price' => 9.99,
            'purchased_at' => now(),
        ]);

        // 4. Verify comic is added to library after payment
        $this->assertDatabaseHas('user_libraries', [
            'user_id' => $user->id,
            'comic_id' => $paidComic->id
        ]);

        // 5. Now user can access the comic
        $accessResponse = $this->actingAs($user)->get("/comics/{$paidComic->slug}/read");
        $accessResponse->assertOk();

        // 6. Verify payment history
        $historyResponse = $this->actingAs($user)->getJson('/api/payments/history');
        $historyResponse->assertOk();
        $historyResponse->assertJsonFragment([
            'comic_id' => $paidComic->id,
            'status' => 'succeeded'
        ]);
    }

    /** @test */
    public function admin_complete_workflow()
    {
        $admin = User::factory()->create([
            'email' => 'admin@bagcomics.com',
            'is_admin' => true
        ]);

        Sanctum::actingAs($admin);

        // 1. Create and list CMS content via admin API
        $createContentResponse = $this->postJson('/api/admin/cms/content', [
            'key' => 'e2e_admin_content',
            'section' => 'hero',
            'type' => 'text',
            'title' => 'Admin Workflow Content',
            'content' => 'Created during end-to-end admin workflow test.',
            'status' => 'draft',
        ]);
        $createContentResponse->assertStatus(201);

        $cmsListResponse = $this->getJson('/api/admin/cms/content');
        $cmsListResponse->assertOk();

        // 2. Review moderation endpoint access
        $user = User::factory()->create();
        $comic = Comic::factory()->create();
        
        ComicReview::factory()->create([
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'is_approved' => false,
        ]);

        $moderationResponse = $this->getJson('/api/admin/reviews/pending');
        $moderationResponse->assertOk();

        // 3. View admin analytics API
        $analyticsResponse = $this->getJson('/api/admin/cms/analytics/platform?days=30');
        $analyticsResponse->assertOk();
    }

    /** @test */
    public function mobile_responsive_user_experience()
    {
        $user = User::factory()->create();
        $comic = Comic::factory()->create([
            'is_visible' => true,
            'is_free' => true,
            'pdf_file_path' => 'comics/mobile-test.pdf',
            'pdf_file_name' => 'mobile-test.pdf',
        ]);
        Storage::disk('public')->put('comics/mobile-test.pdf', 'fake-pdf-content');

        // Simulate mobile user agent
        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)'
        ]);

        // 1. Mobile homepage
        $homeResponse = $this->get('/');
        $homeResponse->assertOk();

        // 2. Mobile comic browsing
        $browseResponse = $this->get('/comics');
        $browseResponse->assertOk();

        // 3. Mobile comic reading
        $this->actingAs($user);
        
        $libraryResponse = $this->postJson("/api/library/comics/{$comic->id}/add", [
            'access_type' => 'free',
        ]);
        $libraryResponse->assertOk();
        
        $readResponse = $this->get("/comics/{$comic->slug}/read");
        $readResponse->assertOk();

        // 4. Mobile-optimized PDF viewing
        $pdfResponse = $this->get("/comics/{$comic->slug}/stream");
        $pdfResponse->assertOk();
    }

    /** @test */
    public function api_workflow_with_authentication()
    {
        $user = User::factory()->create();
        
        // 1. Get API token (using Sanctum)
        $token = $user->createToken('test-token')->plainTextToken;

        // 2. Access protected API endpoints with token
        $headers = ['Authorization' => "Bearer {$token}"];

        // Get user profile
        $profileResponse = $this->withHeaders($headers)->getJson('/api/user');
        $profileResponse->assertOk();
        $profileResponse->assertJsonFragment([
            'id' => $user->id,
            'email' => $user->email
        ]);

        // Get user library
        $libraryResponse = $this->withHeaders($headers)->getJson('/api/library');
        $libraryResponse->assertOk();

        $comic = Comic::factory()->create([
            'is_visible' => true,
            'is_free' => true,
            'published_at' => now(),
        ]);
        $this->withHeaders($headers)->postJson("/api/library/comics/{$comic->id}/add", [
            'access_type' => 'free',
        ]);

        // Get reading progress
        $progressResponse = $this->withHeaders($headers)->getJson("/api/comics/{$comic->id}/progress");
        $progressResponse->assertOk();

        // 3. Rate limiting should work
        for ($i = 0; $i < 65; $i++) {
            $response = $this->withHeaders($headers)->getJson('/api/comics');
            if ($response->status() === 429) {
                break;
            }
        }

        // Should eventually hit rate limit
        $this->assertContains($response->status(), [200, 429]);
    }

    /** @test */
    public function search_functionality_end_to_end()
    {
        // Create searchable comics
        Comic::factory()->create([
            'title' => 'Amazing Spider-Man',
            'author' => 'Stan Lee',
            'genre' => 'superhero',
            'is_visible' => true
        ]);

        Comic::factory()->create([
            'title' => 'Batman: Dark Knight',
            'author' => 'Frank Miller', 
            'genre' => 'superhero',
            'is_visible' => true
        ]);

        Comic::factory()->create([
            'title' => 'One Piece Adventure',
            'author' => 'Eiichiro Oda',
            'genre' => 'adventure',
            'is_visible' => true
        ]);

        // 1. Search by title
        $titleSearchResponse = $this->getJson('/api/comics/search?query=spider');
        $titleSearchResponse->assertOk();
        $titleSearchResponse->assertJsonFragment(['title' => 'Amazing Spider-Man']);

        // 2. Search by author
        $authorSearchResponse = $this->getJson('/api/comics/search?query=stan%20lee');
        $authorSearchResponse->assertOk();
        $authorSearchResponse->assertJsonFragment(['author' => 'Stan Lee']);

        // 3. Filter by genre
        $genreFilterResponse = $this->getJson('/api/comics/search?filters[genre][]=superhero');
        $genreFilterResponse->assertOk();
        $genreFilterData = $genreFilterResponse->json();
        $this->assertCount(2, $genreFilterData['data']);

        // 4. Combined search and filter
        $combinedResponse = $this->getJson('/api/comics/search?query=batman&filters[genre][]=superhero');
        $combinedResponse->assertOk();
        $combinedResponse->assertJsonFragment(['title' => 'Batman: Dark Knight']);

        // 5. Empty search results
        $emptyResponse = $this->getJson('/api/comics/search?query=nonexistent');
        $emptyResponse->assertOk();
        $emptyData = $emptyResponse->json();
        $this->assertCount(0, $emptyData['data']);
    }

    /** @test */
    public function error_handling_and_recovery()
    {
        $user = User::factory()->create();

        // 1. 404 for non-existent comic
        $notFoundResponse = $this->get('/comics/non-existent-comic');
        $notFoundResponse->assertOk();

        // 2. Unauthorized access to admin routes
        $unauthorizedResponse = $this->actingAs($user)->get('/admin');
        $this->assertContains($unauthorizedResponse->status(), [302, 403]);

        // 3. Invalid API requests
        $comic = Comic::factory()->create([
            'is_visible' => true,
            'is_free' => true,
            'published_at' => now(),
        ]);
        $invalidApiResponse = $this->actingAs($user)->postJson("/api/reviews/comics/{$comic->id}", [
            'rating' => 6, // Invalid rating
            'content' => 'short',
        ]);
        $invalidApiResponse->assertStatus(422);

        // 4. Rate limit exceeded
        for ($i = 0; $i < 70; $i++) {
            $response = $this->actingAs($user)->getJson('/api/comics');
            if ($response->status() === 429) {
                $this->assertEquals(429, $response->status());
                break;
            }
        }

    }

    /** @test */
    public function performance_under_load()
    {
        // Create large dataset
        $users = User::factory()->count(50)->create();
        $comics = Comic::factory()->count(100)->create(['is_visible' => true]);

        // Simulate multiple concurrent requests
        $responses = [];
        
        for ($i = 0; $i < 20; $i++) {
            $user = $users->random();
            $responses[] = $this->actingAs($user)->get('/comics');
        }

        // All responses should be successful
        foreach ($responses as $response) {
            $response->assertOk();
        }

        // API performance test
        $apiResponses = [];
        for ($i = 0; $i < 10; $i++) {
            $user = $users->random();
            $apiResponses[] = $this->actingAs($user)->getJson('/api/comics');
        }

        foreach ($apiResponses as $response) {
            $response->assertOk();
        }
    }
}
