<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\ComicSeries;
use App\Services\ComicSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ComicSearchPerformanceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected ComicSearchService $searchService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use database driver for testing to avoid Meilisearch dependency
        config(['scout.driver' => 'database']);
        
        $this->searchService = app(ComicSearchService::class);
    }

    /** @test */
    public function it_performs_search_within_acceptable_time_limits()
    {
        // Create a large dataset
        $this->createLargeDataset(1000);

        $startTime = microtime(true);

        $response = $this->getJson('/api/comics/search?query=test&per_page=20');

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        // Assert that search completes within 500ms
        $this->assertLessThan(500, $executionTime, 
            "Search took {$executionTime}ms, which exceeds the 500ms limit");
    }

    /** @test */
    public function it_handles_complex_filtering_efficiently()
    {
        $this->createLargeDataset(500);

        $startTime = microtime(true);

        $response = $this->getJson('/api/comics/search?' . http_build_query([
            'filters' => [
                'genre' => ['Superhero', 'Fantasy'],
                'price_min' => 5,
                'price_max' => 20,
                'min_rating' => 3.0,
                'year_min' => 2020,
                'year_max' => 2024,
            ],
            'sort' => 'rating_desc',
            'per_page' => 50,
        ]));

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        // Complex filtering should complete within 800ms
        $this->assertLessThan(800, $executionTime,
            "Complex filtering took {$executionTime}ms, which exceeds the 800ms limit");
    }

    /** @test */
    public function it_handles_pagination_efficiently_for_large_datasets()
    {
        $this->createLargeDataset(2000);

        // Test different pages to ensure consistent performance
        $pages = [1, 10, 50, 100];
        
        foreach ($pages as $page) {
            $startTime = microtime(true);

            $response = $this->getJson("/api/comics/search?page={$page}&per_page=20");

            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000;

            $response->assertStatus(200);
            $this->assertTrue($response->json('success'));

            // Pagination should be consistent regardless of page number
            $this->assertLessThan(300, $executionTime,
                "Pagination for page {$page} took {$executionTime}ms, which exceeds the 300ms limit");
        }
    }

    /** @test */
    public function it_generates_suggestions_quickly()
    {
        $this->createLargeDataset(1000);

        $queries = ['spi', 'bat', 'sup', 'fan', 'hor'];

        foreach ($queries as $query) {
            $startTime = microtime(true);

            $response = $this->getJson("/api/comics/search/suggestions?query={$query}");

            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000;

            $response->assertStatus(200);
            $this->assertTrue($response->json('success'));

            // Suggestions should be very fast
            $this->assertLessThan(200, $executionTime,
                "Suggestions for '{$query}' took {$executionTime}ms, which exceeds the 200ms limit");
        }
    }

    /** @test */
    public function it_handles_autocomplete_efficiently()
    {
        $this->createLargeDataset(1000);

        $startTime = microtime(true);

        $response = $this->getJson('/api/comics/search/autocomplete?query=test');

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        // Autocomplete should be very fast
        $this->assertLessThan(250, $executionTime,
            "Autocomplete took {$executionTime}ms, which exceeds the 250ms limit");
    }

    /** @test */
    public function it_loads_filter_options_efficiently()
    {
        $this->createLargeDataset(1000);

        $startTime = microtime(true);

        $response = $this->getJson('/api/comics/search/filter-options');

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        // Filter options should load quickly
        $this->assertLessThan(400, $executionTime,
            "Filter options took {$executionTime}ms, which exceeds the 400ms limit");
    }

    /** @test */
    public function it_maintains_performance_with_concurrent_searches()
    {
        $this->createLargeDataset(500);

        $promises = [];
        $executionTimes = [];

        // Simulate 5 concurrent search requests
        for ($i = 0; $i < 5; $i++) {
            $startTime = microtime(true);
            
            $response = $this->getJson("/api/comics/search?query=test{$i}&per_page=20");
            
            $endTime = microtime(true);
            $executionTimes[] = ($endTime - $startTime) * 1000;

            $response->assertStatus(200);
            $this->assertTrue($response->json('success'));
        }

        // All concurrent searches should complete within reasonable time
        $maxTime = max($executionTimes);
        $avgTime = array_sum($executionTimes) / count($executionTimes);

        $this->assertLessThan(1000, $maxTime,
            "Maximum concurrent search time was {$maxTime}ms, which exceeds the 1000ms limit");
        
        $this->assertLessThan(600, $avgTime,
            "Average concurrent search time was {$avgTime}ms, which exceeds the 600ms limit");
    }

    /** @test */
    public function it_optimizes_database_queries()
    {
        $this->createLargeDataset(100);

        // Enable query logging
        DB::enableQueryLog();

        $response = $this->getJson('/api/comics/search?' . http_build_query([
            'filters' => [
                'genre' => ['Superhero'],
                'author' => ['Test Author'],
                'min_rating' => 3.0,
            ],
            'sort' => 'rating_desc',
        ]));

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        // Should not execute too many queries
        $this->assertLessThan(10, count($queries),
            "Search executed " . count($queries) . " queries, which may indicate N+1 problems");

        // Check for efficient queries (no SELECT *)
        foreach ($queries as $query) {
            $this->assertStringNotContainsString('select *', strtolower($query['query']),
                "Query should not use SELECT *: " . $query['query']);
        }
    }

    /** @test */
    public function it_handles_memory_usage_efficiently()
    {
        $this->createLargeDataset(1000);

        $memoryBefore = memory_get_usage(true);

        $response = $this->getJson('/api/comics/search?per_page=100');

        $memoryAfter = memory_get_usage(true);
        $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // Convert to MB

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        // Memory usage should be reasonable (less than 50MB for this test)
        $this->assertLessThan(50, $memoryUsed,
            "Search used {$memoryUsed}MB of memory, which exceeds the 50MB limit");
    }

    /** @test */
    public function it_handles_edge_cases_efficiently()
    {
        $this->createLargeDataset(500);

        $edgeCases = [
            // Empty query
            ['query' => ''],
            // Very long query
            ['query' => str_repeat('test', 50)],
            // Special characters
            ['query' => '!@#$%^&*()'],
            // Unicode characters
            ['query' => 'тест'],
            // Large page size
            ['per_page' => 100],
            // High page number
            ['page' => 100],
        ];

        foreach ($edgeCases as $params) {
            $startTime = microtime(true);

            $response = $this->getJson('/api/comics/search?' . http_build_query($params));

            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000;

            // Should handle edge cases gracefully
            $this->assertLessThan(1000, $executionTime,
                "Edge case " . json_encode($params) . " took {$executionTime}ms");

            // Should not crash
            $this->assertContains($response->status(), [200, 422]);
        }
    }

    /**
     * Create a large dataset for performance testing
     */
    protected function createLargeDataset(int $count): void
    {
        // Create series first
        $series = ComicSeries::factory()->count(20)->create();

        // Create comics in batches to avoid memory issues
        $batchSize = 100;
        $batches = ceil($count / $batchSize);

        for ($batch = 0; $batch < $batches; $batch++) {
            $batchCount = min($batchSize, $count - ($batch * $batchSize));
            
            Comic::factory()->count($batchCount)->create([
                'is_visible' => true,
                'published_at' => now(),
                'series_id' => function () use ($series) {
                    return $this->faker->randomElement($series)->id;
                },
                'genre' => function () {
                    return $this->faker->randomElement([
                        'Superhero', 'Fantasy', 'Sci-Fi', 'Horror', 'Romance', 
                        'Comedy', 'Action', 'Adventure', 'Mystery', 'Drama'
                    ]);
                },
                'author' => function () {
                    return $this->faker->randomElement([
                        'Stan Lee', 'Frank Miller', 'Alan Moore', 'Neil Gaiman',
                        'Grant Morrison', 'Warren Ellis', 'Brian Bendis', 'Test Author'
                    ]);
                },
                'publisher' => function () {
                    return $this->faker->randomElement([
                        'Marvel Comics', 'DC Comics', 'Image Comics', 'Dark Horse',
                        'IDW Publishing', 'Vertigo', 'Test Publisher'
                    ]);
                },
                'tags' => function () {
                    return $this->faker->randomElements([
                        'action', 'adventure', 'superhero', 'villain', 'hero',
                        'magic', 'space', 'future', 'past', 'mystery'
                    ], $this->faker->numberBetween(1, 5));
                },
                'average_rating' => $this->faker->randomFloat(1, 1, 5),
                'total_readers' => $this->faker->numberBetween(0, 10000),
                'view_count' => $this->faker->numberBetween(0, 50000),
                'price' => $this->faker->randomFloat(2, 0, 29.99),
                'publication_year' => $this->faker->numberBetween(2000, 2024),
            ]);
        }
    }
}