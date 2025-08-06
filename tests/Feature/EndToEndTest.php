<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Comic;
use App\Models\Payment;
use App\Models\UserLibrary;
use App\Models\ComicReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $response = $this->post('/register', [
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
        $loginResponse = $this->post('/login', [
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
        $addToLibraryResponse = $this->actingAs($user)->post("/api/library", [
            'comic_id' => $comic->id
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
        $progressResponse = $this->actingAs($user)->post("/api/progress", [
            'comic_id' => $comic->id,
            'current_page' => 10,
            'total_pages' => 20,
            'progress_percentage' => 50
        ]);
        
        $progressResponse->assertOk();
        $this->assertDatabaseHas('user_comic_progress', [
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'current_page' => 10
        ]);
        
        // 10. Complete Reading
        $completeResponse = $this->actingAs($user)->post("/api/progress", [
            'comic_id' => $comic->id,
            'current_page' => 20,
            'total_pages' => 20,
            'progress_percentage' => 100,
            'is_completed' => true
        ]);
        
        $completeResponse->assertOk();
        
        // 11. Write a Review
        $reviewResponse = $this->actingAs($user)->post("/api/reviews", [
            'comic_id' => $comic->id,
            'rating' => 5,
            'review_text' => 'Amazing comic! Really enjoyed reading it.'
        ]);
        
        $reviewResponse->assertCreated();
        $this->assertDatabaseHas('comic_reviews', [
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'rating' => 5
        ]);
        
        // 12. Bookmark Comic
        $bookmarkResponse = $this->actingAs($user)->post("/api/bookmarks", [
            'comic_id' => $comic->id,
            'page_number' => 15
        ]);
        
        $bookmarkResponse->assertCreated();
        
        // 13. Share Comic Socially
        $shareResponse = $this->actingAs($user)->post("/api/share", [
            'comic_id' => $comic->id,
            'platform' => 'twitter',
            'message' => 'Check out this amazing comic!'
        ]);
        
        $shareResponse->assertOk();
        
        // Verify complete user journey
        $this->assertTrue($user->fresh()->hasReadComic($comic->id));
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

        // 3. Initiate purchase
        $purchaseResponse = $this->actingAs($user)->post("/api/payments", [
            'comic_id' => $paidComic->id,
            'amount' => 9.99,
            'currency' => 'USD'
        ]);

        $purchaseResponse->assertCreated();
        
        // 4. Simulate successful payment (would normally come from Stripe)
        $payment = Payment::where('user_id', $user->id)
            ->where('comic_id', $paidComic->id)
            ->first();
        
        $payment->update([
            'status' => 'succeeded',
            'paid_at' => now()
        ]);

        // 5. Verify comic is added to library after payment
        $this->assertDatabaseHas('user_libraries', [
            'user_id' => $user->id,
            'comic_id' => $paidComic->id
        ]);

        // 6. Now user can access the comic
        $accessResponse = $this->actingAs($user)->get("/comics/{$paidComic->slug}/read");
        $accessResponse->assertOk();

        // 7. Verify payment history
        $historyResponse = $this->actingAs($user)->get('/api/payments');
        $historyResponse->assertOk();
        $historyResponse->assertJsonFragment([
            'comic_id' => $paidComic->id,
            'amount' => 999, // Amount in cents
            'status' => 'succeeded'
        ]);
    }

    /** @test */
    public function admin_complete_workflow()
    {
        // Create admin user
        $admin = User::factory()->create([
            'email' => 'admin@bagcomics.com',
            'is_admin' => true
        ]);

        // 1. Admin Login
        $this->actingAs($admin);

        // 2. Access Admin Panel
        $adminResponse = $this->get('/admin');
        $adminResponse->assertOk();

        // 3. Bulk Upload Comics
        $pdfFile = UploadedFile::fake()->create('comic.pdf', 5000, 'application/pdf');
        $coverImage = UploadedFile::fake()->image('cover.jpg', 400, 600);

        $uploadResponse = $this->post('/admin/comics/bulk-upload', [
            'author' => 'Stan Lee',
            'genre' => 'superhero',
            'language' => 'en',
            'is_free' => false,
            'price' => 4.99,
            'is_visible' => true,
            'comic_files' => [$pdfFile],
            'cover_images' => [$coverImage]
        ]);

        // 4. Manage Comics
        $this->assertDatabaseHas('comics', [
            'author' => 'Stan Lee',
            'genre' => 'superhero',
            'is_free' => false,
            'price' => 4.99
        ]);

        // 5. Review and Moderate Content
        $user = User::factory()->create();
        $comic = Comic::first();
        
        ComicReview::factory()->create([
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'is_approved' => false,
            'is_flagged' => true
        ]);

        $moderationResponse = $this->get('/admin/reviews');
        $moderationResponse->assertOk();

        // 6. View Analytics Dashboard
        $analyticsResponse = $this->get('/admin/analytics');
        $analyticsResponse->assertOk();

        // 7. Manage Users
        $usersResponse = $this->get('/admin/users');
        $usersResponse->assertOk();
    }

    /** @test */
    public function mobile_responsive_user_experience()
    {
        $user = User::factory()->create();
        $comic = Comic::factory()->create(['is_visible' => true, 'is_free' => true]);

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
        
        $libraryResponse = $this->post('/api/library', [
            'comic_id' => $comic->id
        ]);
        
        $readResponse = $this->get("/comics/{$comic->slug}/read");
        $readResponse->assertOk();

        // 4. Mobile-optimized PDF viewing
        $pdfResponse = $this->get("/comics/{$comic->slug}/pdf");
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

        // Get reading progress
        $progressResponse = $this->withHeaders($headers)->getJson('/api/progress');
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
        $titleSearchResponse = $this->getJson('/api/search?q=spider');
        $titleSearchResponse->assertOk();
        $titleSearchResponse->assertJsonFragment(['title' => 'Amazing Spider-Man']);

        // 2. Search by author
        $authorSearchResponse = $this->getJson('/api/search?q=stan%20lee');
        $authorSearchResponse->assertOk();
        $authorSearchResponse->assertJsonFragment(['author' => 'Stan Lee']);

        // 3. Filter by genre
        $genreFilterResponse = $this->getJson('/api/search?genre=superhero');
        $genreFilterResponse->assertOk();
        $genreFilterData = $genreFilterResponse->json();
        $this->assertCount(2, $genreFilterData['data']);

        // 4. Combined search and filter
        $combinedResponse = $this->getJson('/api/search?q=batman&genre=superhero');
        $combinedResponse->assertOk();
        $combinedResponse->assertJsonFragment(['title' => 'Batman: Dark Knight']);

        // 5. Empty search results
        $emptyResponse = $this->getJson('/api/search?q=nonexistent');
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
        $notFoundResponse->assertNotFound();

        // 2. Unauthorized access to admin routes
        $unauthorizedResponse = $this->actingAs($user)->get('/admin');
        $unauthorizedResponse->assertStatus(403);

        // 3. Invalid API requests
        $invalidApiResponse = $this->actingAs($user)->postJson('/api/reviews', [
            'comic_id' => 999999, // Non-existent comic
            'rating' => 6, // Invalid rating
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

        // 5. CSRF protection
        $csrfResponse = $this->actingAs($user)->post('/comics', [
            'title' => 'Test Comic'
        ]);
        $csrfResponse->assertStatus(419); // CSRF token mismatch
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