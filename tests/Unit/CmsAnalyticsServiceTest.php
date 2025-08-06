<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\CmsContent;
use App\Models\CmsAnalytic;
use App\Models\User;
use App\Services\CmsAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class CmsAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CmsAnalyticsService $analyticsService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->analyticsService = new CmsAnalyticsService();
        
        // Clean up any existing data for isolated tests
        CmsContent::query()->delete();
        CmsAnalytic::query()->delete();
        \App\Models\CmsContentVersion::query()->delete();
    }

    public function test_can_track_event()
    {
        $content = CmsContent::factory()->create();
        
        $analytic = $this->analyticsService->trackEvent($content, 'view', [
            'user_id' => $this->user->id,
            'page' => 'homepage',
        ]);

        $this->assertInstanceOf(CmsAnalytic::class, $analytic);
        $this->assertEquals($content->id, $analytic->cms_content_id);
        $this->assertEquals('view', $analytic->event_type);
        $this->assertArrayHasKey('user_id', $analytic->metadata);
        $this->assertEquals($this->user->id, $analytic->metadata['user_id']);
    }

    public function test_can_get_content_performance()
    {
        $content = CmsContent::factory()->create();
        
        // Create some analytics data
        CmsAnalytic::factory()->create([
            'cms_content_id' => $content->id,
            'event_type' => 'view',
            'occurred_at' => now()->subDays(5),
        ]);

        CmsAnalytic::factory()->create([
            'cms_content_id' => $content->id,
            'event_type' => 'view',
            'occurred_at' => now()->subDays(3),
        ]);

        CmsAnalytic::factory()->create([
            'cms_content_id' => $content->id,
            'event_type' => 'edit',
            'occurred_at' => now()->subDays(2),
        ]);

        $performance = $this->analyticsService->getContentPerformance($content, 30);

        $this->assertIsArray($performance);
        $this->assertEquals($content->id, $performance['content_id']);
        $this->assertEquals($content->key, $performance['content_key']);
        $this->assertEquals(30, $performance['period_days']);
        $this->assertEquals(2, $performance['total_views']);
        $this->assertEquals(1, $performance['total_edits']);
        $this->assertEquals(0, $performance['total_publishes']);
        $this->assertArrayHasKey('daily_views', $performance);
        $this->assertArrayHasKey('average_daily_views', $performance);
    }

    public function test_can_get_platform_analytics()
    {
        $content1 = CmsContent::factory()->create();
        $content2 = CmsContent::factory()->create();

        // Create analytics for different content
        CmsAnalytic::factory()->create([
            'cms_content_id' => $content1->id,
            'event_type' => 'view',
            'occurred_at' => now()->subDays(5),
        ]);

        CmsAnalytic::factory()->create([
            'cms_content_id' => $content2->id,
            'event_type' => 'edit',
            'occurred_at' => now()->subDays(3),
        ]);

        CmsAnalytic::factory()->create([
            'cms_content_id' => $content1->id,
            'event_type' => 'view',
            'occurred_at' => now()->subDays(1),
        ]);

        $analytics = $this->analyticsService->getPlatformAnalytics(30);

        $this->assertIsArray($analytics);
        $this->assertEquals(30, $analytics['period_days']);
        $this->assertEquals(3, $analytics['total_events']);
        $this->assertArrayHasKey('event_counts', $analytics);
        $this->assertEquals(2, $analytics['event_counts']['view']);
        $this->assertEquals(1, $analytics['event_counts']['edit']);
        $this->assertArrayHasKey('most_viewed_content', $analytics);
        $this->assertArrayHasKey('daily_activity', $analytics);
    }

    public function test_can_get_engagement_metrics()
    {
        // Clear any existing content to ensure clean test
        CmsContent::query()->delete();
        CmsAnalytic::query()->delete();
        
        $content1 = CmsContent::factory()->create(['section' => 'hero']);
        $content2 = CmsContent::factory()->create(['section' => 'about']);
        $content3 = CmsContent::factory()->create(['section' => 'hero']);

        // Create analytics for some content (not all)
        CmsAnalytic::factory()->create([
            'cms_content_id' => $content1->id,
            'event_type' => 'view',
            'occurred_at' => now()->subDays(5),
        ]);

        CmsAnalytic::factory()->create([
            'cms_content_id' => $content2->id,
            'event_type' => 'edit',
            'occurred_at' => now()->subDays(3),
        ]);

        $metrics = $this->analyticsService->getEngagementMetrics(30);

        $this->assertIsArray($metrics);
        $this->assertEquals(30, $metrics['period_days']);
        $this->assertEquals(2, $metrics['active_content_pieces']);
        $this->assertEquals(3, $metrics['total_content_pieces']);
        $this->assertGreaterThan(0, $metrics['engagement_rate']);
        $this->assertArrayHasKey('section_activity', $metrics);
    }

    public function test_can_get_user_activity()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $content = CmsContent::factory()->create();

        // Create versions with different users
        $content->versions()->create([
            'version_number' => 1,
            'title' => 'Version 1',
            'content' => 'Content 1',
            'created_by' => $user1->id,
        ]);

        $content->versions()->create([
            'version_number' => 2,
            'title' => 'Version 2',
            'content' => 'Content 2',
            'created_by' => $user2->id,
        ]);

        $content->versions()->create([
            'version_number' => 3,
            'title' => 'Version 3',
            'content' => 'Content 3',
            'created_by' => $user1->id,
        ]);

        // Create corresponding analytics
        CmsAnalytic::factory()->create([
            'cms_content_id' => $content->id,
            'event_type' => 'version_created',
            'occurred_at' => now()->subDays(5),
        ]);

        CmsAnalytic::factory()->create([
            'cms_content_id' => $content->id,
            'event_type' => 'version_created',
            'occurred_at' => now()->subDays(3),
        ]);

        CmsAnalytic::factory()->create([
            'cms_content_id' => $content->id,
            'event_type' => 'version_created',
            'occurred_at' => now()->subDays(1),
        ]);

        $activity = $this->analyticsService->getUserActivity(30);

        $this->assertIsArray($activity);
        $this->assertEquals(30, $activity['period_days']);
        $this->assertArrayHasKey('user_edits', $activity);
        $this->assertGreaterThanOrEqual(2, $activity['total_editors']);
        $this->assertGreaterThanOrEqual(3, $activity['total_edits']);
    }

    public function test_can_generate_comprehensive_report()
    {
        $content = CmsContent::factory()->create();
        
        CmsAnalytic::factory()->create([
            'cms_content_id' => $content->id,
            'event_type' => 'view',
            'occurred_at' => now()->subDays(5),
        ]);

        $report = $this->analyticsService->generateReport(30);

        $this->assertIsArray($report);
        $this->assertArrayHasKey('report_generated_at', $report);
        $this->assertEquals(30, $report['period_days']);
        $this->assertArrayHasKey('platform_analytics', $report);
        $this->assertArrayHasKey('engagement_metrics', $report);
        $this->assertArrayHasKey('user_activity', $report);
    }

    public function test_can_get_trending_content()
    {
        $content1 = CmsContent::factory()->create(['title' => 'Popular Content']);
        $content2 = CmsContent::factory()->create(['title' => 'Less Popular Content']);

        // Create more views for content1
        for ($i = 0; $i < 5; $i++) {
            CmsAnalytic::factory()->create([
                'cms_content_id' => $content1->id,
                'event_type' => 'view',
                'occurred_at' => now()->subDays(rand(1, 7)),
            ]);
        }

        // Create fewer views for content2
        for ($i = 0; $i < 2; $i++) {
            CmsAnalytic::factory()->create([
                'cms_content_id' => $content2->id,
                'event_type' => 'view',
                'occurred_at' => now()->subDays(rand(1, 7)),
            ]);
        }

        $trending = $this->analyticsService->getTrendingContent(7, 10);

        $this->assertCount(2, $trending);
        $this->assertEquals($content1->title, $trending->first()->title);
        $this->assertEquals(5, $trending->first()->view_count);
        $this->assertEquals($content2->title, $trending->last()->title);
        $this->assertEquals(2, $trending->last()->view_count);
    }

    public function test_can_clean_old_analytics()
    {
        $content = CmsContent::factory()->create();

        // Create old analytics (older than 365 days)
        CmsAnalytic::factory()->create([
            'cms_content_id' => $content->id,
            'event_type' => 'view',
            'occurred_at' => now()->subDays(400),
        ]);

        // Create recent analytics
        CmsAnalytic::factory()->create([
            'cms_content_id' => $content->id,
            'event_type' => 'view',
            'occurred_at' => now()->subDays(30),
        ]);

        $this->assertEquals(2, CmsAnalytic::count());

        $deleted = $this->analyticsService->cleanOldAnalytics(365);

        $this->assertEquals(1, $deleted);
        $this->assertEquals(1, CmsAnalytic::count());
    }

    public function test_enriches_content_ids_with_content_information()
    {
        $content1 = CmsContent::factory()->create([
            'key' => 'content_1',
            'title' => 'Content 1 Title',
            'section' => 'hero',
        ]);

        $content2 = CmsContent::factory()->create([
            'key' => 'content_2',
            'title' => 'Content 2 Title',
            'section' => 'about',
        ]);

        // Create analytics
        CmsAnalytic::factory()->create([
            'cms_content_id' => $content1->id,
            'event_type' => 'view',
            'occurred_at' => now()->subDays(5),
        ]);

        CmsAnalytic::factory()->create([
            'cms_content_id' => $content2->id,
            'event_type' => 'view',
            'occurred_at' => now()->subDays(3),
        ]);

        $analytics = $this->analyticsService->getPlatformAnalytics(30);
        $mostViewed = $analytics['most_viewed_content'];

        $this->assertCount(2, $mostViewed);
        
        $firstContent = $mostViewed->first();
        $this->assertArrayHasKey('content_key', $firstContent);
        $this->assertArrayHasKey('content_title', $firstContent);
        $this->assertArrayHasKey('content_section', $firstContent);
        $this->assertEquals('content_1', $firstContent['content_key']);
        $this->assertEquals('Content 1 Title', $firstContent['content_title']);
        $this->assertEquals('hero', $firstContent['content_section']);
    }
}