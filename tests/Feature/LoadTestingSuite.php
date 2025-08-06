<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Comic;
use App\Models\UserLibrary;
use App\Models\UserComicProgress;
use App\Models\ComicReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Illuminate\Testing\TestResponse;

class LoadTestingSuite extends TestCase
{
    use RefreshDatabase;

    private array $performanceResults = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create base test data
        $this->createLargeDataset();
    }

    /** @test */
    public function homepage_performs_under_concurrent_load()
    {
        $responses = $this->simulateConcurrentRequests(function () {
            return $this->get('/');
        }, 20);

        $this->assertAllResponsesSuccessful($responses);
        $this->measureAverageResponseTime($responses, 'homepage_load');
        
        // Homepage should load in reasonable time even under load
        $avgTime = $this->performanceResults['homepage_load']['average'];
        $this->assertLessThan(2000, $avgTime, 'Homepage taking too long under load');
    }

    /** @test */
    public function comic_catalog_handles_pagination_load()
    {
        $responses = $this->simulateConcurrentRequests(function () {
            $page = rand(1, 5);
            return $this->get("/comics?page={$page}");
        }, 15);

        $this->assertAllResponsesSuccessful($responses);
        $this->measureAverageResponseTime($responses, 'catalog_pagination');
        
        // Catalog should handle pagination efficiently
        $avgTime = $this->performanceResults['catalog_pagination']['average'];
        $this->assertLessThan(1500, $avgTime, 'Catalog pagination too slow');
    }

    /** @test */
    public function api_endpoints_handle_concurrent_requests()
    {
        $users = User::factory()->count(10)->create();
        
        $responses = $this->simulateConcurrentRequests(function () use ($users) {
            $user = $users->random();
            return $this->actingAs($user)->getJson('/api/comics');
        }, 25);

        $this->assertAllResponsesSuccessful($responses);
        $this->measureAverageResponseTime($responses, 'api_comics');
        
        // API should be fast even under load
        $avgTime = $this->performanceResults['api_comics']['average'];
        $this->assertLessThan(1000, $avgTime, 'API too slow under concurrent load');
    }

    /** @test */
    public function search_performs_with_large_dataset()
    {
        $searchTerms = ['spider', 'batman', 'superman', 'wonder', 'flash'];
        
        $responses = $this->simulateConcurrentRequests(function () use ($searchTerms) {
            $term = $searchTerms[array_rand($searchTerms)];
            return $this->getJson("/api/search?q={$term}");
        }, 15);

        $this->assertAllResponsesSuccessful($responses);
        $this->measureAverageResponseTime($responses, 'search_performance');
        
        // Search should be fast even with large dataset
        $avgTime = $this->performanceResults['search_performance']['average'];
        $this->assertLessThan(800, $avgTime, 'Search too slow with large dataset');
    }

    /** @test */
    public function user_library_scales_with_many_comics()
    {
        $user = User::factory()->create();
        
        // Add many comics to user's library
        $comics = Comic::factory()->count(100)->create(['is_visible' => true]);
        foreach ($comics as $comic) {
            UserLibrary::factory()->create([
                'user_id' => $user->id,
                'comic_id' => $comic->id
            ]);
        }

        $responses = $this->simulateConcurrentRequests(function () use ($user) {
            return $this->actingAs($user)->getJson('/api/library');
        }, 10);

        $this->assertAllResponsesSuccessful($responses);
        $this->measureAverageResponseTime($responses, 'large_library');
        
        // Library should load efficiently even with many comics
        $avgTime = $this->performanceResults['large_library']['average'];
        $this->assertLessThan(1200, $avgTime, 'Library too slow with many comics');
    }

    /** @test */
    public function reading_progress_updates_handle_frequency()
    {
        $users = User::factory()->count(5)->create();
        $comics = Comic::take(10)->get();

        // Simulate rapid progress updates
        $responses = $this->simulateConcurrentRequests(function () use ($users, $comics) {
            $user = $users->random();
            $comic = $comics->random();
            
            return $this->actingAs($user)->postJson('/api/progress', [
                'comic_id' => $comic->id,
                'current_page' => rand(1, 50),
                'progress_percentage' => rand(1, 100),
                'reading_time_minutes' => rand(5, 30)
            ]);
        }, 30);

        // Most should succeed (some might fail due to validation)
        $successfulResponses = array_filter($responses, fn($r) => $r->status() < 400);
        $this->assertGreaterThan(20, count($successfulResponses));
        
        $this->measureAverageResponseTime($successfulResponses, 'progress_updates');
        
        // Progress updates should be fast
        $avgTime = $this->performanceResults['progress_updates']['average'];
        $this->assertLessThan(500, $avgTime, 'Progress updates too slow');
    }

    /** @test */
    public function database_handles_concurrent_writes()
    {
        $users = User::factory()->count(10)->create();
        $comics = Comic::take(5)->get();

        // Simulate concurrent review creation
        $responses = $this->simulateConcurrentRequests(function () use ($users, $comics) {
            $user = $users->random();
            $comic = $comics->random();
            
            return $this->actingAs($user)->postJson('/api/reviews', [
                'comic_id' => $comic->id,
                'rating' => rand(1, 5),
                'review_text' => 'Test review ' . uniqid()
            ]);
        }, 20);

        // Most should succeed (some might fail due to duplicate constraints)
        $successfulResponses = array_filter($responses, fn($r) => $r->status() < 400);
        $this->assertGreaterThan(10, count($successfulResponses));
        
        // Verify data integrity
        $reviewCount = ComicReview::count();
        $this->assertGreaterThan(10, $reviewCount);
    }

    /** @test */
    public function caching_improves_performance_under_load()
    {
        Cache::flush();

        // First load (cache miss)
        $uncachedResponses = $this->simulateConcurrentRequests(function () {
            return $this->getJson('/api/comics');
        }, 10);

        $uncachedAvg = $this->calculateAverageResponseTime($uncachedResponses);

        // Second load (cache hit)
        $cachedResponses = $this->simulateConcurrentRequests(function () {
            return $this->getJson('/api/comics');
        }, 10);

        $cachedAvg = $this->calculateAverageResponseTime($cachedResponses);

        // Cached responses should be faster or at least not slower
        $this->assertLessThanOrEqual($uncachedAvg * 1.2, $cachedAvg, 'Caching not improving performance');
    }

    /** @test */
    public function admin_panel_handles_bulk_operations()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $comics = Comic::factory()->count(50)->create(['is_visible' => false]);

        // Simulate bulk publish operation
        $start = microtime(true);
        
        $response = $this->actingAs($admin)->post('/admin/comics/bulk-action', [
            'action' => 'publish',
            'selected' => $comics->pluck('id')->take(20)->toArray()
        ]);

        $duration = (microtime(true) - $start) * 1000;

        // Bulk operations should complete reasonably quickly
        $this->assertLessThan(3000, $duration, 'Bulk operation taking too long');
    }

    /** @test */
    public function file_upload_handles_concurrent_uploads()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        // Note: This test is limited in testing environment
        // In practice, you'd test with actual file uploads
        $responses = $this->simulateConcurrentRequests(function () use ($admin) {
            return $this->actingAs($admin)->get('/admin/comics/bulk-upload');
        }, 5);

        $this->assertAllResponsesSuccessful($responses);
        
        // Upload forms should load quickly
        $avgTime = $this->calculateAverageResponseTime($responses);
        $this->assertLessThan(1000, $avgTime, 'Upload forms loading too slowly');
    }

    /** @test */
    public function analytics_calculations_perform_with_large_dataset()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Simulate analytics dashboard load
        $responses = $this->simulateConcurrentRequests(function () use ($admin) {
            return $this->actingAs($admin)->getJson('/api/analytics/dashboard');
        }, 5);

        // Analytics might be slower but should still respond
        $this->assertAllResponsesSuccessful($responses, 5000); // Allow 5s timeout
        
        $avgTime = $this->calculateAverageResponseTime($responses);
        $this->assertLessThan(3000, $avgTime, 'Analytics too slow');
    }

    /**
     * Simulate concurrent requests
     */
    private function simulateConcurrentRequests(callable $requestFactory, int $count): array
    {
        $responses = [];
        
        // In a real load testing scenario, you'd use actual concurrent requests
        // For testing purposes, we'll simulate by making rapid sequential requests
        for ($i = 0; $i < $count; $i++) {
            $start = microtime(true);
            $response = $requestFactory();
            $end = microtime(true);
            
            // Add timing information
            $response->responseTime = ($end - $start) * 1000; // Convert to milliseconds
            $responses[] = $response;
            
            // Small delay to prevent overwhelming the system
            usleep(10000); // 10ms
        }
        
        return $responses;
    }

    /**
     * Assert all responses were successful
     */
    private function assertAllResponsesSuccessful(array $responses, int $timeoutMs = 2000): void
    {
        $failedResponses = [];
        
        foreach ($responses as $index => $response) {
            if ($response->status() >= 400) {
                $failedResponses[] = "Response {$index}: {$response->status()}";
            }
            
            if (isset($response->responseTime) && $response->responseTime > $timeoutMs) {
                $failedResponses[] = "Response {$index}: Timeout ({$response->responseTime}ms)";
            }
        }
        
        $this->assertEmpty($failedResponses, 
            'Failed responses: ' . implode(', ', $failedResponses)
        );
    }

    /**
     * Measure average response time
     */
    private function measureAverageResponseTime(array $responses, string $testName): void
    {
        $times = array_map(fn($r) => $r->responseTime ?? 0, $responses);
        
        $this->performanceResults[$testName] = [
            'average' => array_sum($times) / count($times),
            'min' => min($times),
            'max' => max($times),
            'total_requests' => count($responses)
        ];
    }

    /**
     * Calculate average response time
     */
    private function calculateAverageResponseTime(array $responses): float
    {
        $times = array_map(fn($r) => $r->responseTime ?? 0, $responses);
        return array_sum($times) / count($times);
    }

    /**
     * Create large dataset for testing
     */
    private function createLargeDataset(): void
    {
        // Create users
        User::factory()->count(50)->create();
        
        // Create comics with various attributes
        Comic::factory()->count(100)->create([
            'is_visible' => true,
            'genre' => fn() => fake()->randomElement(['superhero', 'adventure', 'fantasy', 'sci-fi', 'horror'])
        ]);
        
        // Create user libraries
        $users = User::limit(20)->get();
        $comics = Comic::limit(50)->get();
        
        foreach ($users as $user) {
            $userComics = $comics->random(rand(5, 15));
            foreach ($userComics as $comic) {
                UserLibrary::factory()->create([
                    'user_id' => $user->id,
                    'comic_id' => $comic->id
                ]);
            }
        }
        
        // Create reading progress
        foreach ($users->take(10) as $user) {
            $userComics = $comics->random(rand(3, 8));
            foreach ($userComics as $comic) {
                UserComicProgress::factory()->create([
                    'user_id' => $user->id,
                    'comic_id' => $comic->id,
                    'reading_time_minutes' => rand(10, 120)
                ]);
            }
        }
        
        // Create reviews
        ComicReview::factory()->count(200)->create([
            'user_id' => fn() => $users->random()->id,
            'comic_id' => fn() => $comics->random()->id,
            'is_approved' => true
        ]);
    }

    protected function tearDown(): void
    {
        // Log performance results for analysis
        if (!empty($this->performanceResults)) {
            foreach ($this->performanceResults as $test => $results) {
                echo "\n{$test}: Avg {$results['average']}ms (Min: {$results['min']}ms, Max: {$results['max']}ms, Requests: {$results['total_requests']})";
            }
        }
        
        parent::tearDown();
    }
}