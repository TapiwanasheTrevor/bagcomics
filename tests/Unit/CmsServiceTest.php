<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\CmsContent;
use App\Models\User;
use App\Services\CmsService;
use App\Services\CmsVersioningService;
use App\Services\CmsAnalyticsService;
use App\Services\CmsMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class CmsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CmsService $cmsService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        $versioningService = $this->app->make(CmsVersioningService::class);
        $analyticsService = $this->app->make(CmsAnalyticsService::class);
        $mediaService = $this->app->make(CmsMediaService::class);
        
        $this->cmsService = new CmsService($versioningService, $analyticsService, $mediaService);
    }

    public function test_can_get_content_by_key()
    {
        $content = CmsContent::factory()->create([
            'key' => 'test_content',
            'content' => 'Test content value',
            'is_active' => true,
            'status' => 'published',
        ]);

        $result = $this->cmsService->getContent('test_content');

        $this->assertEquals('Test content value', $result);
    }

    public function test_returns_default_when_content_not_found()
    {
        $result = $this->cmsService->getContent('nonexistent_key', 'default_value');

        $this->assertEquals('default_value', $result);
    }

    public function test_can_get_section_content()
    {
        CmsContent::factory()->create([
            'key' => 'hero_title',
            'section' => 'hero',
            'content' => 'Hero Title',
            'is_active' => true,
            'status' => 'published',
        ]);

        CmsContent::factory()->create([
            'key' => 'hero_subtitle',
            'section' => 'hero',
            'content' => 'Hero Subtitle',
            'is_active' => true,
            'status' => 'published',
        ]);

        $result = $this->cmsService->getSection('hero');

        $this->assertArrayHasKey('hero_title', $result);
        $this->assertArrayHasKey('hero_subtitle', $result);
        $this->assertEquals('Hero Title', $result['hero_title']['content']);
        $this->assertEquals('Hero Subtitle', $result['hero_subtitle']['content']);
    }

    public function test_can_create_content()
    {
        $data = [
            'key' => 'new_content',
            'section' => 'test',
            'type' => 'text',
            'title' => 'New Content',
            'content' => 'New content value',
            'status' => 'draft',
        ];

        $content = $this->cmsService->createContent($data, $this->user->id);

        $this->assertInstanceOf(CmsContent::class, $content);
        $this->assertEquals('new_content', $content->key);
        $this->assertEquals('New Content', $content->title);
        $this->assertEquals($this->user->id, $content->created_by);
    }

    public function test_can_update_existing_content()
    {
        $content = CmsContent::factory()->create([
            'key' => 'existing_content',
            'title' => 'Original Title',
            'content' => 'Original content',
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'content' => 'Updated content',
            'change_summary' => 'Updated title and content',
        ];

        $updatedContent = $this->cmsService->updateContent(
            'existing_content', 
            $updateData, 
            $this->user->id
        );

        $this->assertEquals('Updated Title', $updatedContent->title);
        $this->assertEquals('Updated content', $updatedContent->content);
        $this->assertEquals($this->user->id, $updatedContent->updated_by);
    }

    public function test_can_publish_content()
    {
        $content = CmsContent::factory()->create([
            'status' => 'draft',
            'published_at' => null,
        ]);

        $result = $this->cmsService->publishContent($content, $this->user->id);

        $this->assertTrue($result);
        $content->refresh();
        $this->assertEquals('published', $content->status);
        $this->assertNotNull($content->published_at);
        $this->assertEquals($this->user->id, $content->updated_by);
    }

    public function test_can_schedule_content()
    {
        $content = CmsContent::factory()->create([
            'status' => 'draft',
        ]);

        $scheduledAt = new \DateTime('+1 hour');
        $result = $this->cmsService->scheduleContent($content, $scheduledAt, $this->user->id);

        $this->assertTrue($result);
        $content->refresh();
        $this->assertEquals('scheduled', $content->status);
        $this->assertNotNull($content->scheduled_at);
        $this->assertEquals($this->user->id, $content->updated_by);
    }

    public function test_can_archive_content()
    {
        $content = CmsContent::factory()->create([
            'status' => 'published',
            'is_active' => true,
        ]);

        $result = $this->cmsService->archiveContent($content, $this->user->id);

        $this->assertTrue($result);
        $content->refresh();
        $this->assertEquals('archived', $content->status);
        $this->assertFalse($content->is_active);
        $this->assertEquals($this->user->id, $content->updated_by);
    }

    public function test_can_get_content_details()
    {
        $content = CmsContent::factory()->create([
            'key' => 'detailed_content',
        ]);

        $details = $this->cmsService->getContentDetails('detailed_content');

        $this->assertIsArray($details);
        $this->assertArrayHasKey('content', $details);
        $this->assertArrayHasKey('performance', $details);
        $this->assertArrayHasKey('versions', $details);
        $this->assertEquals($content->id, $details['content']->id);
    }

    public function test_returns_null_for_nonexistent_content_details()
    {
        $details = $this->cmsService->getContentDetails('nonexistent_key');

        $this->assertNull($details);
    }

    public function test_can_process_scheduled_content()
    {
        // Create scheduled content that's ready to publish
        CmsContent::factory()->create([
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinutes(5),
        ]);

        // Create scheduled content that's not ready yet
        CmsContent::factory()->create([
            'status' => 'scheduled',
            'scheduled_at' => now()->addHours(1),
        ]);

        $published = $this->cmsService->processScheduledContent();

        $this->assertEquals(1, $published);
    }

    public function test_clears_cache_when_updating_content()
    {
        $content = CmsContent::factory()->create([
            'key' => 'cached_content',
            'section' => 'test_section',
        ]);

        // Prime the cache
        Cache::put("cms_content_cached_content", 'cached_value');
        Cache::put("cms_section_test_section", ['cached' => 'data']);

        $this->cmsService->updateContent('cached_content', [
            'content' => 'updated_content',
        ], $this->user->id);

        // Cache should be cleared
        $this->assertNull(Cache::get("cms_content_cached_content"));
        $this->assertNull(Cache::get("cms_section_test_section"));
    }

    public function test_can_get_default_content_values()
    {
        $defaults = $this->cmsService->getDefaults();

        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('hero_title', $defaults);
        $this->assertArrayHasKey('site_name', $defaults);
        $this->assertEquals('African Stories, Boldly Told', $defaults['hero_title']);
        $this->assertEquals('BAG Comics', $defaults['site_name']);
    }

    public function test_tracks_analytics_when_getting_content()
    {
        $content = CmsContent::factory()->create([
            'key' => 'tracked_content',
            'is_active' => true,
            'status' => 'published',
        ]);

        $this->cmsService->getContent('tracked_content', null, true);

        $this->assertDatabaseHas('cms_analytics', [
            'cms_content_id' => $content->id,
            'event_type' => 'view',
        ]);
    }

    public function test_does_not_track_analytics_when_disabled()
    {
        $content = CmsContent::factory()->create([
            'key' => 'untracked_content',
            'is_active' => true,
            'status' => 'published',
        ]);

        $this->cmsService->getContent('untracked_content', null, false);

        $this->assertDatabaseMissing('cms_analytics', [
            'cms_content_id' => $content->id,
            'event_type' => 'view',
        ]);
    }
}