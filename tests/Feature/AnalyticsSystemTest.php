<?php

namespace Tests\Feature;

use App\Services\AnalyticsService;
use App\Models\User;
use App\Models\Comic;
use App\Models\Payment;
use App\Models\ComicView;
use App\Models\UserComicProgress;
use App\Models\UserLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class AnalyticsSystemTest extends TestCase
{
    use RefreshDatabase;

    private AnalyticsService $analyticsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyticsService = new AnalyticsService();
    }

    /** @test */
    public function it_can_get_platform_metrics()
    {
        // Create test data
        User::factory()->count(50)->create();
        User::factory()->count(10)->create(['created_at' => now()->subDays(5)]);
        Comic::factory()->count(20)->create(['is_visible' => true]);
        Payment::factory()->count(30)->create([
            'status' => 'succeeded',
            'amount' => 9.99,
            'paid_at' => now()->subDays(3),
        ]);

        $metrics = $this->analyticsService->getPlatformMetrics(7);

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('total_users', $metrics);
        $this->assertArrayHasKey('new_users', $metrics);
        $this->assertArrayHasKey('total_comics', $metrics);
        $this->assertArrayHasKey('total_revenue', $metrics);
        
        $this->assertEquals(60, $metrics['total_users']);
        $this->assertEquals(10, $metrics['new_users']);
        $this->assertEquals(20, $metrics['total_comics']);
    }

    /** @test */
    public function it_can_get_revenue_analytics()
    {
        $comic = Comic::factory()->create();
        
        // Create payments over different days
        Payment::factory()->create([
            'comic_id' => $comic->id,
            'status' => 'succeeded',
            'amount' => 15.99,
            'paid_at' => now()->subDays(2),
        ]);
        
        Payment::factory()->create([
            'comic_id' => $comic->id,
            'status' => 'succeeded',
            'amount' => 12.99,
            'paid_at' => now()->subDays(1),
        ]);

        $analytics = $this->analyticsService->getRevenueAnalytics(7);

        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('daily_revenue', $analytics);
        $this->assertArrayHasKey('top_earning_comics', $analytics);
        $this->assertArrayHasKey('average_transaction_value', $analytics);
        
        $this->assertGreaterThan(0, $analytics['average_transaction_value']);
    }

    /** @test */
    public function it_can_get_user_engagement_analytics()
    {
        $user = User::factory()->create();
        $comic = Comic::factory()->create();
        
        // Create reading progress
        UserComicProgress::factory()->create([
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'progress_percentage' => 75,
            'reading_time_minutes' => 45,
            'is_completed' => false,
            'last_read_at' => now()->subDays(1),
        ]);

        UserComicProgress::factory()->create([
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'progress_percentage' => 100,
            'reading_time_minutes' => 60,
            'is_completed' => true,
            'last_read_at' => now()->subHours(2),
        ]);

        $analytics = $this->analyticsService->getUserEngagementAnalytics(7);

        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('completion_stats', $analytics);
        $this->assertArrayHasKey('active_users', $analytics);
        $this->assertArrayHasKey('reading_completion_rate', $analytics);
        $this->assertArrayHasKey('average_session_duration', $analytics);
        
        $this->assertGreaterThan(0, $analytics['reading_completion_rate']);
        $this->assertGreaterThan(0, $analytics['average_session_duration']);
    }

    /** @test */
    public function it_can_get_comic_performance_analytics()
    {
        $comics = Comic::factory()->count(5)->create(['is_visible' => true]);
        
        // Create views for comics
        foreach ($comics as $index => $comic) {
            ComicView::factory()->count(10 - $index)->create([
                'comic_id' => $comic->id,
                'viewed_at' => now()->subDays(rand(1, 7)),
            ]);
        }

        // Create library entries with ratings
        $user = User::factory()->create();
        UserLibrary::factory()->create([
            'user_id' => $user->id,
            'comic_id' => $comics->first()->id,
            'rating' => 5,
        ]);

        $analytics = $this->analyticsService->getComicPerformanceAnalytics(7);

        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('most_viewed', $analytics);
        $this->assertArrayHasKey('trending', $analytics);
        $this->assertArrayHasKey('best_rated', $analytics);
        $this->assertArrayHasKey('most_purchased', $analytics);
    }

    /** @test */
    public function it_can_get_conversion_analytics()
    {
        $comic = Comic::factory()->create([
            'is_visible' => true,
            'is_free' => false,
            'price' => 9.99,
        ]);

        // Create views and purchases
        ComicView::factory()->count(100)->create([
            'comic_id' => $comic->id,
            'viewed_at' => now()->subDays(rand(1, 7)),
        ]);

        Payment::factory()->count(5)->create([
            'comic_id' => $comic->id,
            'status' => 'succeeded',
            'amount' => 9.99,
            'paid_at' => now()->subDays(rand(1, 7)),
        ]);

        $analytics = $this->analyticsService->getConversionAnalytics(7);

        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('comics_with_metrics', $analytics);
        $this->assertArrayHasKey('overall_conversion_rate', $analytics);
        $this->assertArrayHasKey('total_views', $analytics);
        $this->assertArrayHasKey('total_purchases', $analytics);
        
        $this->assertGreaterThan(0, $analytics['overall_conversion_rate']);
        $this->assertEquals(100, $analytics['total_views']);
        $this->assertEquals(5, $analytics['total_purchases']);
    }

    /** @test */
    public function it_can_generate_comprehensive_report()
    {
        // Create comprehensive test data
        User::factory()->count(25)->create();
        $comics = Comic::factory()->count(10)->create(['is_visible' => true]);
        Payment::factory()->count(15)->create([
            'status' => 'succeeded',
            'amount' => 12.99,
        ]);

        foreach ($comics->take(5) as $comic) {
            ComicView::factory()->count(rand(10, 50))->create([
                'comic_id' => $comic->id,
                'viewed_at' => now()->subDays(rand(1, 7)),
            ]);
        }

        $report = $this->analyticsService->generateComprehensiveReport(['days' => 7]);

        $this->assertIsArray($report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('revenue_analytics', $report);
        $this->assertArrayHasKey('user_engagement', $report);
        $this->assertArrayHasKey('comic_performance', $report);
        $this->assertArrayHasKey('conversion_metrics', $report);
        $this->assertArrayHasKey('realtime_metrics', $report);
        
        // Check summary structure
        $this->assertArrayHasKey('period', $report['summary']);
        $this->assertArrayHasKey('generated_at', $report['summary']);
        $this->assertArrayHasKey('platform_metrics', $report['summary']);
        
        $this->assertEquals('7 days', $report['summary']['period']);
    }

    /** @test */
    public function it_can_export_report_to_csv()
    {
        $reportData = $this->analyticsService->generateComprehensiveReport(['days' => 7]);
        
        $filePath = $this->analyticsService->exportReportToCsv($reportData);
        
        $this->assertFileExists($filePath);
        
        $csvContent = file_get_contents($filePath);
        $this->assertStringContainsString('Section,Metric,Value,Date', $csvContent);
        $this->assertStringContainsString('Platform,Total Users', $csvContent);
        
        // Clean up
        unlink($filePath);
    }

    /** @test */
    public function it_can_export_report_to_json()
    {
        $reportData = $this->analyticsService->generateComprehensiveReport(['days' => 7]);
        
        $filePath = $this->analyticsService->exportReportToJson($reportData);
        
        $this->assertFileExists($filePath);
        
        $jsonContent = json_decode(file_get_contents($filePath), true);
        $this->assertIsArray($jsonContent);
        $this->assertArrayHasKey('summary', $jsonContent);
        
        // Clean up
        unlink($filePath);
    }

    /** @test */
    public function it_can_get_realtime_metrics()
    {
        User::factory()->create();
        $comic = Comic::factory()->create();
        
        // Create recent activity
        UserComicProgress::factory()->create([
            'comic_id' => $comic->id,
            'last_read_at' => now()->subMinutes(5),
        ]);

        Payment::factory()->create([
            'status' => 'succeeded',
            'amount' => 9.99,
            'paid_at' => now()->subHours(2),
        ]);

        $metrics = $this->analyticsService->getRealtimeMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('online_users', $metrics);
        $this->assertArrayHasKey('active_reading_sessions', $metrics);
        $this->assertArrayHasKey('revenue_today', $metrics);
        $this->assertArrayHasKey('new_users_today', $metrics);
        $this->assertArrayHasKey('views_last_hour', $metrics);
        $this->assertArrayHasKey('last_updated', $metrics);
        
        $this->assertGreaterThanOrEqual(0, $metrics['online_users']);
        $this->assertGreaterThanOrEqual(0, $metrics['active_reading_sessions']);
    }

    /** @test */
    public function it_can_get_subscription_analytics()
    {
        User::factory()->count(10)->create(['subscription_status' => 'active']);
        User::factory()->count(5)->create(['subscription_status' => 'trial']);
        User::factory()->count(3)->create(['subscription_status' => 'canceled']);
        User::factory()->count(15)->create(['subscription_status' => null]);

        Payment::factory()->count(8)->create([
            'type' => 'subscription',
            'status' => 'succeeded',
            'amount' => 9.99,
        ]);

        $analytics = $this->analyticsService->getSubscriptionAnalytics();

        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('total_subscribers', $analytics);
        $this->assertArrayHasKey('trial_users', $analytics);
        $this->assertArrayHasKey('canceled_users', $analytics);
        $this->assertArrayHasKey('conversion_rate', $analytics);
        $this->assertArrayHasKey('churn_rate', $analytics);
        
        $this->assertEquals(10, $analytics['total_subscribers']);
        $this->assertEquals(5, $analytics['trial_users']);
        $this->assertEquals(3, $analytics['canceled_users']);
        $this->assertGreaterThan(0, $analytics['conversion_rate']);
        $this->assertGreaterThan(0, $analytics['churn_rate']);
    }

    /** @test */
    public function it_can_get_reading_behavior_analytics()
    {
        $user = User::factory()->create();
        $comic = Comic::factory()->create();

        // Create reading sessions at different hours
        UserComicProgress::factory()->create([
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'reading_time_minutes' => 30,
            'device_type' => 'desktop',
            'last_read_at' => now()->setHour(14)->subDays(1),
        ]);

        UserComicProgress::factory()->create([
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'reading_time_minutes' => 45,
            'device_type' => 'mobile',
            'last_read_at' => now()->setHour(20)->subDays(2),
        ]);

        $analytics = $this->analyticsService->getReadingBehaviorAnalytics(7);

        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('reading_by_hour', $analytics);
        $this->assertArrayHasKey('device_usage', $analytics);
        $this->assertArrayHasKey('average_session_length', $analytics);
        $this->assertArrayHasKey('total_reading_time', $analytics);
        
        $this->assertGreaterThan(0, $analytics['average_session_length']);
        $this->assertEquals(75, $analytics['total_reading_time']);
    }
}