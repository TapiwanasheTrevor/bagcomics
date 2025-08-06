<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\CmsContent;
use App\Models\CmsContentVersion;
use App\Models\CmsMediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class CmsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = User::factory()->create();
        Storage::fake('public');
    }

    public function test_can_get_cms_content_list()
    {
        Sanctum::actingAs($this->adminUser);

        CmsContent::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/cms/content');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'key',
                            'section',
                            'type',
                            'title',
                            'content',
                            'status',
                            'created_at',
                            'updated_at',
                        ]
                    ],
                    'meta',
                    'links'
                ]);
    }

    public function test_can_filter_cms_content_by_section()
    {
        Sanctum::actingAs($this->adminUser);

        CmsContent::factory()->create(['section' => 'hero']);
        CmsContent::factory()->create(['section' => 'about']);
        CmsContent::factory()->create(['section' => 'hero']);

        $response = $this->getJson('/api/admin/cms/content?section=hero');

        $response->assertStatus(200);
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_can_create_cms_content()
    {
        Sanctum::actingAs($this->adminUser);

        $contentData = [
            'key' => 'test_content',
            'section' => 'test',
            'type' => 'text',
            'title' => 'Test Content',
            'content' => 'This is test content',
            'status' => 'draft',
            'change_summary' => 'Initial creation',
        ];

        $response = $this->postJson('/api/admin/cms/content', $contentData);

        $response->assertStatus(201)
                ->assertJson([
                    'message' => 'Content created successfully',
                    'content' => [
                        'key' => 'test_content',
                        'title' => 'Test Content',
                        'status' => 'draft',
                    ]
                ]);

        $this->assertDatabaseHas('cms_contents', [
            'key' => 'test_content',
            'title' => 'Test Content',
            'created_by' => $this->adminUser->id,
        ]);
    }

    public function test_can_update_cms_content()
    {
        Sanctum::actingAs($this->adminUser);

        $content = CmsContent::factory()->create([
            'key' => 'update_test',
            'title' => 'Original Title',
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'content' => 'Updated content',
            'change_summary' => 'Updated title and content',
        ];

        $response = $this->putJson("/api/admin/cms/content/{$content->key}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Content updated successfully',
                    'content' => [
                        'title' => 'Updated Title',
                    ]
                ]);

        $content->refresh();
        $this->assertEquals('Updated Title', $content->title);
        $this->assertEquals($this->adminUser->id, $content->updated_by);
    }

    public function test_can_publish_content()
    {
        Sanctum::actingAs($this->adminUser);

        $content = CmsContent::factory()->create([
            'status' => 'draft',
            'published_at' => null,
        ]);

        $response = $this->postJson("/api/admin/cms/content/{$content->key}/publish");

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Content published successfully',
                ]);

        $content->refresh();
        $this->assertEquals('published', $content->status);
        $this->assertNotNull($content->published_at);
    }

    public function test_can_schedule_content()
    {
        Sanctum::actingAs($this->adminUser);

        $content = CmsContent::factory()->create(['status' => 'draft']);
        $scheduledAt = now()->addHours(2)->toISOString();

        $response = $this->postJson("/api/admin/cms/content/{$content->key}/schedule", [
            'scheduled_at' => $scheduledAt,
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Content scheduled successfully',
                ]);

        $content->refresh();
        $this->assertEquals('scheduled', $content->status);
        $this->assertNotNull($content->scheduled_at);
    }

    public function test_can_archive_content()
    {
        Sanctum::actingAs($this->adminUser);

        $content = CmsContent::factory()->create([
            'status' => 'published',
            'is_active' => true,
        ]);

        $response = $this->postJson("/api/admin/cms/content/{$content->key}/archive");

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Content archived successfully',
                ]);

        $content->refresh();
        $this->assertEquals('archived', $content->status);
        $this->assertFalse($content->is_active);
    }

    public function test_can_get_content_versions()
    {
        Sanctum::actingAs($this->adminUser);

        $content = CmsContent::factory()->create();
        CmsContentVersion::factory()->count(3)->create([
            'cms_content_id' => $content->id,
        ]);

        $response = $this->getJson("/api/admin/cms/content/{$content->key}/versions");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json());
    }

    public function test_can_revert_to_previous_version()
    {
        Sanctum::actingAs($this->adminUser);

        $content = CmsContent::factory()->create([
            'title' => 'Current Title',
        ]);

        CmsContentVersion::factory()->create([
            'cms_content_id' => $content->id,
            'version_number' => 1,
            'title' => 'Old Title',
            'content' => 'Old content',
        ]);

        $response = $this->postJson("/api/admin/cms/content/{$content->key}/revert", [
            'version_number' => 1,
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Content reverted successfully',
                ]);

        $content->refresh();
        $this->assertEquals('Old Title', $content->title);
    }

    public function test_can_compare_versions()
    {
        Sanctum::actingAs($this->adminUser);

        $content = CmsContent::factory()->create();

        $version1 = CmsContentVersion::factory()->create([
            'cms_content_id' => $content->id,
            'version_number' => 1,
            'title' => 'Title 1',
        ]);

        $version2 = CmsContentVersion::factory()->create([
            'cms_content_id' => $content->id,
            'version_number' => 2,
            'title' => 'Title 2',
        ]);

        $response = $this->postJson("/api/admin/cms/content/{$content->key}/versions/compare", [
            'version1' => 1,
            'version2' => 2,
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'title_changed',
                    'content_changed',
                    'version1',
                    'version2',
                ]);
    }

    public function test_can_get_content_analytics()
    {
        Sanctum::actingAs($this->adminUser);

        $content = CmsContent::factory()->create();

        $response = $this->getJson("/api/admin/cms/content/{$content->key}/analytics?days=30");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'content_id',
                    'content_key',
                    'period_days',
                    'total_views',
                    'total_edits',
                    'daily_views',
                    'average_daily_views',
                ]);
    }

    public function test_can_get_platform_analytics()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/cms/analytics/platform?days=30');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'period_days',
                    'total_events',
                    'event_counts',
                    'most_viewed_content',
                    'daily_activity',
                ]);
    }

    public function test_can_bulk_update_content_status()
    {
        Sanctum::actingAs($this->adminUser);

        $content1 = CmsContent::factory()->create(['status' => 'draft']);
        $content2 = CmsContent::factory()->create(['status' => 'draft']);

        $response = $this->postJson('/api/admin/cms/content/bulk-status', [
            'content_keys' => [$content1->key, $content2->key],
            'status' => 'published',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'updated_count' => 2,
                ]);

        $content1->refresh();
        $content2->refresh();
        $this->assertEquals('published', $content1->status);
        $this->assertEquals('published', $content2->status);
    }

    public function test_can_upload_media_file()
    {
        Sanctum::actingAs($this->adminUser);

        $file = UploadedFile::fake()->image('test-upload.jpg', 800, 600);

        $response = $this->postJson('/api/admin/cms/media', [
            'file' => $file,
            'alt_text' => 'Test upload alt text',
        ]);

        $response->assertStatus(201)
                ->assertJson([
                    'message' => 'File uploaded successfully',
                    'asset' => [
                        'original_filename' => 'test-upload.jpg',
                        'alt_text' => 'Test upload alt text',
                        'uploaded_by' => $this->adminUser->id,
                    ]
                ]);

        $this->assertDatabaseHas('cms_media_assets', [
            'original_filename' => 'test-upload.jpg',
            'uploaded_by' => $this->adminUser->id,
        ]);
    }

    public function test_can_get_media_library()
    {
        Sanctum::actingAs($this->adminUser);

        CmsMediaAsset::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/cms/media');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'filename',
                            'original_filename',
                            'mime_type',
                            'size',
                            'alt_text',
                            'created_at',
                        ]
                    ]
                ]);
    }

    public function test_can_update_media_asset()
    {
        Sanctum::actingAs($this->adminUser);

        $asset = CmsMediaAsset::factory()->create([
            'alt_text' => 'Original alt text',
        ]);

        $response = $this->putJson("/api/admin/cms/media/{$asset->id}", [
            'alt_text' => 'Updated alt text',
            'metadata' => ['custom' => 'data'],
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Asset updated successfully',
                ]);

        $asset->refresh();
        $this->assertEquals('Updated alt text', $asset->alt_text);
        $this->assertArrayHasKey('custom', $asset->metadata);
    }

    public function test_can_delete_media_asset()
    {
        Sanctum::actingAs($this->adminUser);

        $asset = CmsMediaAsset::factory()->create();

        $response = $this->deleteJson("/api/admin/cms/media/{$asset->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Asset deleted successfully',
                ]);

        $this->assertDatabaseMissing('cms_media_assets', ['id' => $asset->id]);
    }

    public function test_can_get_workflow_dashboard()
    {
        Sanctum::actingAs($this->adminUser);

        CmsContent::factory()->create(['status' => 'draft']);
        CmsContent::factory()->create(['status' => 'published']);
        CmsContent::factory()->create(['status' => 'scheduled']);

        $response = $this->getJson('/api/admin/cms/workflow/dashboard');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'counts' => [
                        'draft',
                        'published',
                        'scheduled',
                        'archived',
                        'total',
                    ],
                    'recent_activity',
                    'scheduled_content',
                ]);
    }

    public function test_can_get_content_by_status()
    {
        Sanctum::actingAs($this->adminUser);

        CmsContent::factory()->count(2)->create(['status' => 'draft']);
        CmsContent::factory()->create(['status' => 'published']);

        $response = $this->getJson('/api/admin/cms/workflow/status/draft');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_approve_content()
    {
        Sanctum::actingAs($this->adminUser);

        $content = CmsContent::factory()->create(['status' => 'draft']);

        $response = $this->postJson("/api/admin/cms/workflow/{$content->key}/approve");

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Content approved and published',
                ]);

        $content->refresh();
        $this->assertEquals('published', $content->status);
    }

    public function test_can_reject_content()
    {
        Sanctum::actingAs($this->adminUser);

        $content = CmsContent::factory()->create(['status' => 'published']);

        $response = $this->postJson("/api/admin/cms/workflow/{$content->key}/reject", [
            'reason' => 'Content needs revision',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Content rejected and moved to draft',
                ]);

        $content->refresh();
        $this->assertEquals('draft', $content->status);
    }

    public function test_requires_admin_authentication()
    {
        $regularUser = User::factory()->create();
        Sanctum::actingAs($regularUser);

        $response = $this->getJson('/api/admin/cms/content');

        // This would normally return 403 Forbidden with proper admin middleware
        // For now, we'll assume the middleware is configured correctly
        $response->assertStatus(403);
    }

    public function test_validates_content_creation_data()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/admin/cms/content', [
            'key' => '', // Invalid: empty key
            'section' => 'test',
            'type' => 'invalid_type', // Invalid: not in allowed types
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['key', 'type']);
    }

    public function test_validates_file_upload()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/admin/cms/media', [
            'file' => 'not-a-file', // Invalid: not a file
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['file']);
    }
}