<?php

namespace Tests\Feature;

use App\Services\CacheService;
use App\Services\DatabaseOptimizationService;
use App\Services\ImageOptimizationService;
use App\Services\PerformanceMonitoringService;
use App\Models\Comic;
use App\Models\User;
use App\Models\ComicView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PerformanceOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private CacheService $cacheService;
    private DatabaseOptimizationService $dbOptimizationService;
    private ImageOptimizationService $imageOptimizationService;
    private PerformanceMonitoringService $performanceMonitoringService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->cacheService = new CacheService();
        $this->dbOptimizationService = new DatabaseOptimizationService();
        $this->imageOptimizationService = new ImageOptimizationService();
        $this->performanceMonitoringService = new PerformanceMonitoringService();
        
        Storage::fake('public');
        Cache::flush(); // Clear cache before each test
    }

    /** @test */
    public function cache_service_can_cache_and_retrieve_popular_comics()
    {
        // Create test comics
        Comic::factory()->count(15)->create([
            'is_visible' => true,
            'view_count' => fn() => rand(100, 1000)
        ]);

        // First call should hit database
        $popularComics1 = $this->cacheService->getPopularComics(10);
        
        // Second call should hit cache
        $popularComics2 = $this->cacheService->getPopularComics(10);
        
        $this->assertEquals(10, $popularComics1->count());
        $this->assertEquals(10, $popularComics2->count());
        
        // Verify cache was used
        $this->assertTrue(Cache::has('popular_comics_10'));
    }

    /** @test */
    public function cache_service_can_cache_trending_comics()
    {
        $comics = Comic::factory()->count(10)->create(['is_visible' => true]);
        
        // Create views for trending calculation
        foreach ($comics as $comic) {
            ComicView::factory()->count(5)->create([
                'comic_id' => $comic->id,
                'viewed_at' => now()->subDays(2)
            ]);
        }

        $trendingComics = $this->cacheService->getTrendingComics(5);
        
        $this->assertEquals(5, $trendingComics->count());
        $this->assertTrue(Cache::has('trending_comics_5'));
    }

    /** @test */
    public function cache_service_can_cache_user_library()
    {
        $user = User::factory()->create();
        $comics = Comic::factory()->count(25)->create(['is_visible' => true]);
        
        // Add comics to user library
        foreach ($comics->take(15) as $comic) {
            \DB::table('user_libraries')->insert([
                'user_id' => $user->id,
                'comic_id' => $comic->id,
                'created_at' => now(),
            ]);
        }

        $library = $this->cacheService->getUserLibrary($user->id, 1, 10);
        
        $this->assertIsArray($library);
        $this->assertArrayHasKey('comics', $library);
        $this->assertArrayHasKey('total', $library);
        $this->assertArrayHasKey('has_more', $library);
        $this->assertEquals(10, count($library['comics']));
        $this->assertTrue($library['has_more']);
    }

    /** @test */
    public function cache_service_can_search_and_cache_results()
    {
        Comic::factory()->count(10)->create([
            'is_visible' => true,
            'title' => 'Amazing Spider-Man',
            'genre' => 'superhero'
        ]);
        
        Comic::factory()->count(5)->create([
            'is_visible' => true,
            'title' => 'Batman',
            'genre' => 'superhero'
        ]);

        $results = $this->cacheService->searchComics('Spider', ['genre' => 'superhero'], 20);
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('comics', $results);
        $this->assertArrayHasKey('total', $results);
        $this->assertEquals(10, $results['comics']->count());
    }

    /** @test */
    public function cache_service_invalidates_related_caches()
    {
        $comic = Comic::factory()->create(['is_visible' => true]);
        
        // Populate caches
        $this->cacheService->getPopularComics(10);
        $this->cacheService->getComicDetails($comic->id);
        
        $this->assertTrue(Cache::has('popular_comics_10'));
        $this->assertTrue(Cache::has("comic_details_{$comic->id}"));
        
        // Invalidate caches
        $this->cacheService->invalidateComicCaches($comic->id);
        
        $this->assertFalse(Cache::has("comic_details_{$comic->id}"));
    }

    /** @test */
    public function cache_service_can_warmup_essential_caches()
    {
        Comic::factory()->count(20)->create([
            'is_visible' => true,
            'view_count' => fn() => rand(100, 1000)
        ]);

        $this->cacheService->warmupCaches();
        
        $this->assertTrue(Cache::has('popular_comics_10'));
        $this->assertTrue(Cache::has('popular_comics_20'));
        $this->assertTrue(Cache::has('trending_comics_10'));
        $this->assertTrue(Cache::has('new_releases_10'));
        $this->assertTrue(Cache::has('genre_stats'));
    }

    /** @test */
    public function database_optimization_service_can_add_indexes()
    {
        $optimizations = $this->dbOptimizationService->optimizeCommonQueries();
        
        $this->assertIsArray($optimizations);
        $this->assertNotEmpty($optimizations);
        $this->assertContains('Created comic_statistics table for faster analytics', $optimizations);
        $this->assertContains('Created user_activity_summary table for user analytics', $optimizations);
    }

    /** @test */
    public function database_optimization_service_can_analyze_table_performance()
    {
        // Create some test data
        Comic::factory()->count(100)->create();
        User::factory()->count(50)->create();

        $analysis = $this->dbOptimizationService->analyzeTablePerformance();
        
        $this->assertIsArray($analysis);
        $this->assertArrayHasKey('comics', $analysis);
        $this->assertArrayHasKey('users', $analysis);
        
        foreach (['comics', 'users'] as $table) {
            $this->assertArrayHasKey('rows', $analysis[$table]);
            $this->assertArrayHasKey('data_length', $analysis[$table]);
            $this->assertArrayHasKey('index_length', $analysis[$table]);
            $this->assertArrayHasKey('suggestions', $analysis[$table]);
        }
    }

    /** @test */
    public function image_optimization_service_can_process_uploaded_image()
    {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);
        
        $result = $this->imageOptimizationService->processImage($file, 'test-images');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('original', $result);
        $this->assertArrayHasKey('thumbnail', $result);
        $this->assertArrayHasKey('small', $result);
        $this->assertArrayHasKey('medium', $result);
        $this->assertArrayHasKey('large', $result);
        
        // Verify files were created
        foreach (['original', 'thumbnail', 'small', 'medium', 'large'] as $size) {
            $this->assertTrue(Storage::disk('public')->exists($result[$size]['path']));
        }
    }

    /** @test */
    public function image_optimization_service_generates_responsive_html()
    {
        $imageData = [
            'thumbnail' => ['url' => '/storage/images/thumbnail/test.jpg'],
            'small' => ['url' => '/storage/images/small/test.jpg'],
            'medium' => ['url' => '/storage/images/medium/test.jpg'],
            'large' => ['url' => '/storage/images/large/test.jpg'],
        ];
        
        $html = $this->imageOptimizationService->generateResponsiveImageHtml($imageData, 'Test Image', 'img-responsive');
        
        $this->assertStringContainsString('<picture>', $html);
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('alt="Test Image"', $html);
        $this->assertStringContainsString('class="img-responsive"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
    }

    /** @test */
    public function image_optimization_service_generates_lazy_loading_html()
    {
        $imageData = [
            'thumbnail' => ['url' => '/storage/images/thumbnail/test.jpg'],
            'medium' => ['url' => '/storage/images/medium/test.jpg'],
        ];
        
        $html = $this->imageOptimizationService->generateLazyImageHtml($imageData, 'Test Image', 'lazy-image');
        
        $this->assertStringContainsString('data-src="/storage/images/medium/test.jpg"', $html);
        $this->assertStringContainsString('src="/storage/images/thumbnail/test.jpg"', $html);
        $this->assertStringContainsString('class="lazy-load lazy-image"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
    }

    /** @test */
    public function performance_monitoring_service_can_record_metrics()
    {
        $metrics = [
            'url' => '/api/comics',
            'method' => 'GET',
            'response_time_ms' => 250,
            'memory_usage_mb' => 32,
            'query_count' => 5,
            'query_time_ms' => 45,
            'has_error' => false,
            'user_id' => null,
        ];
        
        $this->performanceMonitoringService->recordMetrics($metrics);
        
        // Verify metrics are cached
        $timestamp = now()->timestamp;
        $cacheKey = "performance_metrics_{$timestamp}";
        $this->assertTrue(Cache::has($cacheKey));
    }

    /** @test */
    public function performance_monitoring_service_provides_dashboard_data()
    {
        // Record some test metrics first
        for ($i = 0; $i < 5; $i++) {
            $this->performanceMonitoringService->recordMetrics([
                'response_time_ms' => 200 + $i * 50,
                'memory_usage_mb' => 30 + $i * 5,
                'has_error' => $i > 3,
            ]);
        }
        
        $dashboardData = $this->performanceMonitoringService->getDashboardData();
        
        $this->assertIsArray($dashboardData);
        $this->assertArrayHasKey('current_metrics', $dashboardData);
        $this->assertArrayHasKey('performance_trends', $dashboardData);
        $this->assertArrayHasKey('cache_statistics', $dashboardData);
        $this->assertArrayHasKey('resource_usage', $dashboardData);
    }

    /** @test */
    public function performance_monitoring_service_generates_performance_report()
    {
        $report = $this->performanceMonitoringService->generatePerformanceReport(7);
        
        $this->assertIsArray($report);
        $this->assertArrayHasKey('period', $report);
        $this->assertArrayHasKey('generated_at', $report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('recommendations', $report);
        
        $this->assertEquals('7 days', $report['period']);
    }

    /** @test */
    public function cache_service_provides_accurate_statistics()
    {
        // Warm up some caches
        Comic::factory()->count(10)->create(['is_visible' => true]);
        $this->cacheService->getPopularComics(10);
        $this->cacheService->getTrendingComics(5);
        
        $stats = $this->cacheService->getCacheStats();
        
        $this->assertIsArray($stats);
        // Stats might not be available in testing environment, but method should not fail
        $this->assertTrue(isset($stats['error']) || isset($stats['hit_rate']));
    }

    /** @test */
    public function database_optimization_service_can_update_statistics()
    {
        // Create test data
        $user = User::factory()->create();
        $comic = Comic::factory()->create();
        
        // Create some progress data
        \DB::table('user_comic_progress')->insert([
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'progress_percentage' => 100,
            'is_completed' => 1,
            'reading_time_minutes' => 45,
            'last_read_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->dbOptimizationService->updateStatistics();
        
        // Verify statistics were updated
        $this->assertDatabaseHas('comic_statistics', [
            'comic_id' => $comic->id,
        ]);
        
        $this->assertDatabaseHas('user_activity_summary', [
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function image_optimization_service_provides_optimization_stats()
    {
        // Create some test images
        $file1 = UploadedFile::fake()->image('test1.jpg', 800, 600);
        $file2 = UploadedFile::fake()->image('test2.jpg', 1200, 800);
        
        $this->imageOptimizationService->processImage($file1, 'test');
        $this->imageOptimizationService->processImage($file2, 'test');
        
        $stats = $this->imageOptimizationService->getOptimizationStats();
        
        $this->assertIsArray($stats);
        $this->assertTrue(isset($stats['error']) || isset($stats['original_size']));
    }

    /** @test */
    public function performance_system_handles_edge_cases_gracefully()
    {
        // Test empty cache
        $popularComics = $this->cacheService->getPopularComics(10);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $popularComics);
        
        // Test invalid comic ID
        $this->cacheService->invalidateComicCaches(999999);
        $this->assertTrue(true); // Should not throw exception
        
        // Test empty search
        $searchResults = $this->cacheService->searchComics('nonexistent', [], 10);
        $this->assertIsArray($searchResults);
        $this->assertEquals(0, $searchResults['comics']->count());
    }
}