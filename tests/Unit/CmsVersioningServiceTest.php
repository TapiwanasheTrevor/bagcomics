<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\CmsContent;
use App\Models\CmsContentVersion;
use App\Models\User;
use App\Services\CmsVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CmsVersioningServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CmsVersioningService $versioningService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->versioningService = new CmsVersioningService();
    }

    public function test_can_create_version()
    {
        $content = CmsContent::factory()->create();
        
        $versionData = [
            'title' => 'Version Title',
            'content' => 'Version content',
            'status' => 'draft',
            'change_summary' => 'Initial version',
        ];

        $version = $this->versioningService->createVersion($content, $versionData, $this->user->id);

        $this->assertInstanceOf(CmsContentVersion::class, $version);
        $this->assertEquals($content->id, $version->cms_content_id);
        $this->assertEquals('Version Title', $version->title);
        $this->assertEquals('Version content', $version->content);
        $this->assertEquals($this->user->id, $version->created_by);
        $this->assertEquals('Initial version', $version->change_summary);
    }

    public function test_version_numbers_increment_correctly()
    {
        $content = CmsContent::factory()->create();

        $version1 = $this->versioningService->createVersion($content, [
            'title' => 'Version 1',
        ], $this->user->id);

        $version2 = $this->versioningService->createVersion($content, [
            'title' => 'Version 2',
        ], $this->user->id);

        $this->assertEquals(1, $version1->version_number);
        $this->assertEquals(2, $version2->version_number);
    }

    public function test_can_publish_version()
    {
        $content = CmsContent::factory()->create([
            'title' => 'Original Title',
            'content' => 'Original content',
            'status' => 'draft',
        ]);

        $version = CmsContentVersion::factory()->create([
            'cms_content_id' => $content->id,
            'version_number' => 1,
            'title' => 'New Title',
            'content' => 'New content',
            'status' => 'draft',
        ]);

        $result = $this->versioningService->publishVersion($version, $this->user->id);

        $this->assertTrue($result);
        
        $version->refresh();
        $this->assertEquals('published', $version->status);
        $this->assertTrue($version->is_active);
        $this->assertNotNull($version->published_at);

        $content->refresh();
        $this->assertEquals('New Title', $content->title);
        $this->assertEquals('New content', $content->content);
        $this->assertEquals('published', $content->status);
        $this->assertEquals(1, $content->current_version);
    }

    public function test_can_schedule_version()
    {
        $content = CmsContent::factory()->create();
        $version = CmsContentVersion::factory()->create([
            'cms_content_id' => $content->id,
            'status' => 'draft',
        ]);

        $scheduledAt = new \DateTime('+1 hour');
        $result = $this->versioningService->scheduleVersion($version, $scheduledAt, $this->user->id);

        $this->assertTrue($result);
        
        $version->refresh();
        $this->assertEquals('scheduled', $version->status);
        $this->assertNotNull($version->scheduled_at);
    }

    public function test_can_revert_to_previous_version()
    {
        $content = CmsContent::factory()->create([
            'title' => 'Current Title',
            'content' => 'Current content',
        ]);

        // Create an older version
        $oldVersion = CmsContentVersion::factory()->create([
            'cms_content_id' => $content->id,
            'version_number' => 1,
            'title' => 'Old Title',
            'content' => 'Old content',
        ]);

        $result = $this->versioningService->revertToVersion($content, 1, $this->user->id);

        $this->assertTrue($result);
        
        $content->refresh();
        $this->assertEquals('Old Title', $content->title);
        $this->assertEquals('Old content', $content->content);
        $this->assertEquals('published', $content->status);
    }

    public function test_revert_fails_for_nonexistent_version()
    {
        $content = CmsContent::factory()->create();

        $result = $this->versioningService->revertToVersion($content, 999, $this->user->id);

        $this->assertFalse($result);
    }

    public function test_can_get_version_history()
    {
        $content = CmsContent::factory()->create();

        CmsContentVersion::factory()->create([
            'cms_content_id' => $content->id,
            'version_number' => 1,
            'created_by' => $this->user->id,
        ]);

        CmsContentVersion::factory()->create([
            'cms_content_id' => $content->id,
            'version_number' => 2,
            'created_by' => $this->user->id,
        ]);

        $history = $this->versioningService->getVersionHistory($content);

        $this->assertCount(2, $history);
        $this->assertEquals(2, $history->first()->version_number); // Should be ordered desc
        $this->assertEquals(1, $history->last()->version_number);
    }

    public function test_can_compare_versions()
    {
        $content = CmsContent::factory()->create();

        $version1 = CmsContentVersion::factory()->create([
            'cms_content_id' => $content->id,
            'version_number' => 1,
            'title' => 'Title 1',
            'content' => 'Content 1',
            'created_by' => $this->user->id,
        ]);

        $version2 = CmsContentVersion::factory()->create([
            'cms_content_id' => $content->id,
            'version_number' => 2,
            'title' => 'Title 2',
            'content' => 'Content 1', // Same content
            'created_by' => $this->user->id,
        ]);

        $comparison = $this->versioningService->compareVersions($version1, $version2);

        $this->assertIsArray($comparison);
        $this->assertTrue($comparison['title_changed']);
        $this->assertFalse($comparison['content_changed']);
        $this->assertArrayHasKey('version1', $comparison);
        $this->assertArrayHasKey('version2', $comparison);
    }

    public function test_can_process_scheduled_versions()
    {
        $content = CmsContent::factory()->create();

        // Create a scheduled version that's ready
        $readyVersion = CmsContentVersion::factory()->create([
            'cms_content_id' => $content->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinutes(5),
        ]);

        // Create a scheduled version that's not ready yet
        CmsContentVersion::factory()->create([
            'cms_content_id' => $content->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->addHours(1),
        ]);

        $published = $this->versioningService->processScheduledContent();

        $this->assertEquals(1, $published);
        
        $readyVersion->refresh();
        $this->assertEquals('published', $readyVersion->status);
    }

    public function test_tracks_analytics_when_creating_version()
    {
        $content = CmsContent::factory()->create();

        $this->versioningService->createVersion($content, [
            'title' => 'Test Version',
            'change_summary' => 'Test changes',
        ], $this->user->id);

        $this->assertDatabaseHas('cms_analytics', [
            'cms_content_id' => $content->id,
            'event_type' => 'version_created',
        ]);
    }

    public function test_tracks_analytics_when_publishing_version()
    {
        $content = CmsContent::factory()->create();
        $version = CmsContentVersion::factory()->create([
            'cms_content_id' => $content->id,
            'status' => 'draft',
        ]);

        $this->versioningService->publishVersion($version, $this->user->id);

        $this->assertDatabaseHas('cms_analytics', [
            'cms_content_id' => $content->id,
            'event_type' => 'version_published',
        ]);
    }

    public function test_deactivates_previous_published_version()
    {
        $content = CmsContent::factory()->create();

        $oldVersion = CmsContentVersion::factory()->create([
            'cms_content_id' => $content->id,
            'version_number' => 1,
            'status' => 'published',
            'is_active' => true,
        ]);

        $newVersion = CmsContentVersion::factory()->create([
            'cms_content_id' => $content->id,
            'version_number' => 2,
            'status' => 'draft',
        ]);

        $this->versioningService->publishVersion($newVersion, $this->user->id);

        $oldVersion->refresh();
        $this->assertEquals('archived', $oldVersion->status);
        $this->assertFalse($oldVersion->is_active);

        $newVersion->refresh();
        $this->assertEquals('published', $newVersion->status);
        $this->assertTrue($newVersion->is_active);
    }
}