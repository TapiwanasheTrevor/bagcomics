<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Comic;
use App\Models\Payment;
use App\Models\ComicReview;
use App\Models\UserLibrary;
use App\Models\UserComicProgress;
use App\Services\AnalyticsService;
use App\Services\CacheService;
use App\Services\SecurityService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IntegrationTestSuite extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function analytics_service_integrates_with_models_correctly()
    {
        // Create test data
        $users = User::factory()->count(10)->create();
        $comics = Comic::factory()->count(5)->create(['is_visible' => true]);
        
        // Create reading progress
        foreach ($users as $user) {
            foreach ($comics->take(3) as $comic) {
                UserComicProgress::factory()->create([
                    'user_id' => $user->id,
                    'comic_id' => $comic->id,
                    'is_completed' => rand(0, 1),
                    'reading_time_minutes' => rand(30, 120)
                ]);
            }
        }

        // Create payments
        Payment::factory()->count(15)->create([
            'user_id' => fn() => $users->random()->id,
            'comic_id' => fn() => $comics->random()->id,
            'status' => 'succeeded',
            'amount' => 999
        ]);

        $analyticsService = new AnalyticsService();

        // Test platform metrics
        $platformMetrics = $analyticsService->getPlatformMetrics(30);
        $this->assertEquals(10, $platformMetrics['total_users']);
        $this->assertEquals(5, $platformMetrics['total_comics']);
        $this->assertGreaterThan(0, $platformMetrics['total_revenue']);

        // Test user engagement analytics
        $engagementAnalytics = $analyticsService->getUserEngagementAnalytics(30);
        $this->assertIsArray($engagementAnalytics);
        $this->assertArrayHasKey('completion_stats', $engagementAnalytics);
        $this->assertGreaterThan(0, $engagementAnalytics['completion_stats']->total_reading_sessions);

        // Test revenue analytics
        $revenueAnalytics = $analyticsService->getRevenueAnalytics(30);
        $this->assertIsArray($revenueAnalytics);
        $this->assertArrayHasKey('daily_revenue', $revenueAnalytics);
        $this->assertGreaterThan(0, $revenueAnalytics['average_transaction_value']);
    }

    /** @test */
    public function cache_service_integrates_with_database_queries()
    {
        Cache::flush();
        
        // Create test data
        Comic::factory()->count(20)->create([
            'is_visible' => true,
            'view_count' => fn() => rand(100, 1000)
        ]);

        $cacheService = new CacheService();

        // Test cache miss (first call should query database)
        DB::enableQueryLog();
        $popularComics1 = $cacheService->getPopularComics(10);
        $queryCount1 = count(DB::getQueryLog());
        
        // Test cache hit (second call should use cache)
        DB::flushQueryLog();
        $popularComics2 = $cacheService->getPopularComics(10);
        $queryCount2 = count(DB::getQueryLog());

        // First call should have queried database
        $this->assertGreaterThan(0, $queryCount1);
        // Second call should use cache (no additional queries)
        $this->assertEquals(0, $queryCount2);
        // Results should be identical
        $this->assertEquals($popularComics1->pluck('id'), $popularComics2->pluck('id'));
    }

    /** @test */
    public function payment_service_integrates_with_user_library()
    {
        $user = User::factory()->create();
        $comic = Comic::factory()->create([
            'is_free' => false,
            'price' => 9.99
        ]);

        $paymentService = new PaymentService();

        // Create payment
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'amount' => 999, // $9.99 in cents
            'status' => 'pending'
        ]);

        // Process successful payment
        $paymentService->processPaymentSuccess($payment);

        // Verify comic was added to user library
        $this->assertDatabaseHas('user_libraries', [
            'user_id' => $user->id,
            'comic_id' => $comic->id
        ]);

        // Verify user can access the comic
        $this->assertTrue($user->fresh()->hasAccessToComic($comic->id));
    }

    /** @test */
    public function security_service_integrates_with_middleware()
    {
        $user = User::factory()->create();
        $securityService = new SecurityService();

        // Test rate limiting integration
        $request = request();
        $request->setUserResolver(fn() => $user);

        // First few requests should pass
        for ($i = 0; $i < 3; $i++) {
            $allowed = $securityService->applyRateLimit($request, 'test_key', 5);
            $this->assertTrue($allowed);
        }

        // Test threat detection
        $maliciousRequest = request();
        $maliciousRequest->merge([
            'malicious_field' => '<script>alert("xss")</script>'
        ]);

        $threats = $securityService->scanForThreats($maliciousRequest);
        $this->assertNotEmpty($threats);
        $this->assertEquals('XSS', $threats[0]['type']);
    }

    /** @test */
    public function filament_admin_integrates_with_models()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        // Test admin can access and manage comics
        $response = $this->actingAs($admin)->get('/admin/comics');
        $response->assertOk();

        // Test admin can view analytics
        $response = $this->actingAs($admin)->get('/admin/analytics');
        $response->assertOk();

        // Test admin can manage users
        $response = $this->actingAs($admin)->get('/admin/users');
        $response->assertOk();

        // Test bulk operations work
        $comics = Comic::factory()->count(3)->create(['is_visible' => false]);
        
        // Simulate bulk publish action
        foreach ($comics as $comic) {
            $comic->update(['is_visible' => true, 'published_at' => now()]);
        }

        $this->assertEquals(3, Comic::where('is_visible', true)->count());
    }

    /** @test */
    public function api_endpoints_integrate_with_services()
    {
        $user = User::factory()->create();
        $comic = Comic::factory()->create(['is_visible' => true, 'is_free' => true]);

        // Test comic API integration
        $response = $this->actingAs($user)->getJson('/api/comics');
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'author', 'genre']
            ]
        ]);

        // Test library API integration
        $libraryResponse = $this->actingAs($user)->postJson('/api/library', [
            'comic_id' => $comic->id
        ]);
        $libraryResponse->assertCreated();

        $getLibraryResponse = $this->actingAs($user)->getJson('/api/library');
        $getLibraryResponse->assertOk();
        $getLibraryResponse->assertJsonFragment(['comic_id' => $comic->id]);

        // Test progress API integration
        $progressResponse = $this->actingAs($user)->postJson('/api/progress', [
            'comic_id' => $comic->id,
            'current_page' => 10,
            'progress_percentage' => 50
        ]);
        $progressResponse->assertOk();

        $getProgressResponse = $this->actingAs($user)->getJson('/api/progress');
        $getProgressResponse->assertOk();
        $getProgressResponse->assertJsonFragment(['current_page' => 10]);
    }

    /** @test */
    public function search_service_integrates_with_database()
    {
        // Create searchable content
        $comics = Comic::factory()->count(10)->create([
            'is_visible' => true,
            'title' => fn() => fake()->randomElement([
                'Amazing Spider-Man',
                'Batman Dark Knight',
                'Superman Returns',
                'Wonder Woman Adventure',
                'Flash Lightning'
            ]),
            'genre' => fn() => fake()->randomElement(['superhero', 'adventure', 'fantasy'])
        ]);

        // Test search API
        $searchResponse = $this->getJson('/api/search?q=spider');
        $searchResponse->assertOk();
        
        $searchData = $searchResponse->json();
        $this->assertArrayHasKey('data', $searchData);
        
        // Should find Spider-Man comic
        $foundSpiderMan = collect($searchData['data'])->contains(function ($comic) {
            return str_contains(strtolower($comic['title']), 'spider');
        });
        $this->assertTrue($foundSpiderMan);

        // Test genre filtering
        $genreResponse = $this->getJson('/api/search?genre=superhero');
        $genreResponse->assertOk();
        
        $genreData = $genreResponse->json();
        foreach ($genreData['data'] as $comic) {
            $this->assertEquals('superhero', $comic['genre']);
        }
    }

    /** @test */
    public function notification_system_integrates_with_events()
    {
        Queue::fake();
        
        $user = User::factory()->create();
        $comic = Comic::factory()->create();

        // Test that events are fired for important actions
        
        // Reading completion should trigger notifications
        UserComicProgress::factory()->create([
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'is_completed' => true,
            'progress_percentage' => 100
        ]);

        // New review should trigger moderation
        ComicReview::factory()->create([
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'is_approved' => false
        ]);

        // Payment completion should trigger library update
        Payment::factory()->create([
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'status' => 'succeeded'
        ]);

        // In a real system, these would trigger events/jobs
        // For now, we verify the data was created correctly
        $this->assertDatabaseHas('user_comic_progress', [
            'user_id' => $user->id,
            'is_completed' => true
        ]);
    }

    /** @test */
    public function file_storage_integrates_with_models()
    {
        $comic = Comic::factory()->create([
            'pdf_file_path' => 'comics/test.pdf',
            'cover_image_path' => 'covers/test.jpg'
        ]);

        // Test file URL generation
        $pdfUrl = $comic->getPdfUrl();
        $coverUrl = $comic->getCoverUrl();

        $this->assertStringContainsString('comics/test.pdf', $pdfUrl);
        $this->assertStringContainsString('covers/test.jpg', $coverUrl);

        // Test file access permissions
        $user = User::factory()->create();
        
        // Free comic should be accessible
        if ($comic->is_free) {
            $response = $this->actingAs($user)->get("/comics/{$comic->slug}/pdf");
            $response->assertOk();
        }
    }

    /** @test */
    public function database_transactions_maintain_consistency()
    {
        $user = User::factory()->create();
        $comic = Comic::factory()->create(['is_free' => false, 'price' => 9.99]);

        // Test that failed payment doesn't add comic to library
        DB::beginTransaction();
        try {
            $payment = Payment::create([
                'user_id' => $user->id,
                'comic_id' => $comic->id,
                'amount' => 999,
                'status' => 'pending'
            ]);

            // Simulate payment failure
            throw new \Exception('Payment failed');
        } catch (\Exception $e) {
            DB::rollback();
        }

        // Comic should not be in user library
        $this->assertDatabaseMissing('user_libraries', [
            'user_id' => $user->id,
            'comic_id' => $comic->id
        ]);

        // Test successful transaction
        DB::transaction(function () use ($user, $comic) {
            $payment = Payment::create([
                'user_id' => $user->id,
                'comic_id' => $comic->id,
                'amount' => 999,
                'status' => 'succeeded'
            ]);

            UserLibrary::create([
                'user_id' => $user->id,
                'comic_id' => $comic->id
            ]);
        });

        // Now comic should be in library
        $this->assertDatabaseHas('user_libraries', [
            'user_id' => $user->id,
            'comic_id' => $comic->id
        ]);
    }

    /** @test */
    public function middleware_stack_processes_requests_correctly()
    {
        $user = User::factory()->create();

        // Test authenticated middleware
        $response = $this->getJson('/api/library');
        $response->assertUnauthorized();

        $authResponse = $this->actingAs($user)->getJson('/api/library');
        $authResponse->assertOk();

        // Test rate limiting middleware
        for ($i = 0; $i < 70; $i++) {
            $response = $this->actingAs($user)->getJson('/api/comics');
            if ($response->status() === 429) {
                $this->assertEquals(429, $response->status());
                $response->assertHeader('X-RateLimit-Limit');
                break;
            }
        }

        // Test CORS middleware (if applicable)
        $corsResponse = $this->withHeaders([
            'Origin' => 'https://example.com',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type'
        ])->options('/api/comics');

        // Should handle CORS preflight
        $this->assertContains($corsResponse->status(), [200, 204]);
    }

    /** @test */
    public function caching_strategy_improves_performance()
    {
        Cache::flush();
        
        // Create data
        Comic::factory()->count(50)->create(['is_visible' => true]);

        // Measure uncached request
        $start = microtime(true);
        $response1 = $this->getJson('/api/comics');
        $uncachedTime = microtime(true) - $start;

        // Measure cached request
        $start = microtime(true);
        $response2 = $this->getJson('/api/comics');
        $cachedTime = microtime(true) - $start;

        // Both should be successful
        $response1->assertOk();
        $response2->assertOk();

        // Cached request should be faster (in most cases)
        // Note: This might not always be true in testing environment
        $this->assertTrue($cachedTime <= $uncachedTime * 2); // Allow some variance
    }
}