<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\CmsMediaAsset;
use App\Models\User;
use App\Services\CmsMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CmsMediaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CmsMediaService $mediaService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->mediaService = new CmsMediaService();
        
        Storage::fake('public');
        
        // Clean up any existing data for isolated tests
        CmsMediaAsset::query()->delete();
    }

    public function test_can_upload_file()
    {
        $file = UploadedFile::fake()->image('test-image.jpg', 800, 600);
        
        $asset = $this->mediaService->uploadFile($file, $this->user->id, [
            'alt_text' => 'Test image alt text',
        ]);

        $this->assertInstanceOf(CmsMediaAsset::class, $asset);
        $this->assertEquals('test-image.jpg', $asset->original_filename);
        $this->assertEquals('image/jpeg', $asset->mime_type);
        $this->assertEquals('Test image alt text', $asset->alt_text);
        $this->assertEquals($this->user->id, $asset->uploaded_by);
        $this->assertEquals('public', $asset->disk);
        
        // Check file was stored
        Storage::disk('public')->assertExists($asset->path);
    }

    public function test_generates_unique_filename()
    {
        $file1 = UploadedFile::fake()->image('same-name.jpg');
        $file2 = UploadedFile::fake()->image('same-name.jpg');
        
        $asset1 = $this->mediaService->uploadFile($file1, $this->user->id);
        $asset2 = $this->mediaService->uploadFile($file2, $this->user->id);

        $this->assertNotEquals($asset1->filename, $asset2->filename);
        $this->assertEquals('same-name.jpg', $asset1->original_filename);
        $this->assertEquals('same-name.jpg', $asset2->original_filename);
    }

    public function test_processes_image_dimensions_and_variants()
    {
        $file = UploadedFile::fake()->image('large-image.jpg', 1600, 1200);
        
        $asset = $this->mediaService->uploadFile($file, $this->user->id);

        // If Intervention Image is not available, dimensions won't be processed
        if (class_exists('Intervention\Image\ImageManager')) {
            $this->assertEquals(1600, $asset->width);
            $this->assertEquals(1200, $asset->height);
            $this->assertTrue($asset->is_optimized);
            $this->assertNotNull($asset->variants);
            $this->assertIsArray($asset->variants);
        } else {
            // Without image processing library
            $this->assertNull($asset->width);
            $this->assertNull($asset->height);
            $this->assertFalse($asset->is_optimized);
            $this->assertNull($asset->variants);
        }
    }

    public function test_can_delete_asset_and_variants()
    {
        $file = UploadedFile::fake()->image('delete-test.jpg', 800, 600);
        $asset = $this->mediaService->uploadFile($file, $this->user->id);
        
        // Ensure file exists
        Storage::disk('public')->assertExists($asset->path);
        
        $result = $this->mediaService->deleteAsset($asset);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('cms_media_assets', ['id' => $asset->id]);
        Storage::disk('public')->assertMissing($asset->path);
    }

    public function test_can_get_media_library_with_filters()
    {
        // Create different types of assets
        $imageAsset = CmsMediaAsset::factory()->create([
            'mime_type' => 'image/jpeg',
            'uploaded_by' => $this->user->id,
            'original_filename' => 'test-image.jpg',
        ]);

        $videoAsset = CmsMediaAsset::factory()->create([
            'mime_type' => 'video/mp4',
            'uploaded_by' => $this->user->id,
            'original_filename' => 'test-video.mp4',
        ]);

        $otherUserAsset = CmsMediaAsset::factory()->create([
            'mime_type' => 'image/png',
            'uploaded_by' => User::factory()->create()->id,
        ]);

        // Test no filters
        $allMedia = $this->mediaService->getMediaLibrary();
        $this->assertEquals(3, $allMedia->total());

        // Test image filter
        $imageMedia = $this->mediaService->getMediaLibrary(['type' => 'images']);
        $this->assertEquals(2, $imageMedia->total());

        // Test video filter
        $videoMedia = $this->mediaService->getMediaLibrary(['type' => 'videos']);
        $this->assertEquals(1, $videoMedia->total());

        // Test uploader filter
        $userMedia = $this->mediaService->getMediaLibrary(['uploader_id' => $this->user->id]);
        $this->assertEquals(2, $userMedia->total());

        // Test search filter
        $searchMedia = $this->mediaService->getMediaLibrary(['search' => 'test-image']);
        $this->assertEquals(1, $searchMedia->total());
    }

    public function test_can_update_asset_metadata()
    {
        $asset = CmsMediaAsset::factory()->create([
            'alt_text' => 'Original alt text',
            'metadata' => ['original' => 'data'],
        ]);

        $result = $this->mediaService->updateAsset($asset, [
            'alt_text' => 'Updated alt text',
            'metadata' => ['new' => 'metadata'],
        ]);

        $this->assertTrue($result);
        
        $asset->refresh();
        $this->assertEquals('Updated alt text', $asset->alt_text);
        $this->assertArrayHasKey('original', $asset->metadata);
        $this->assertArrayHasKey('new', $asset->metadata);
        $this->assertEquals('data', $asset->metadata['original']);
        $this->assertEquals('metadata', $asset->metadata['new']);
    }

    public function test_can_get_storage_stats()
    {
        CmsMediaAsset::factory()->create([
            'mime_type' => 'image/jpeg',
            'size' => 1024 * 1024, // 1MB
        ]);

        CmsMediaAsset::factory()->create([
            'mime_type' => 'image/png',
            'size' => 2 * 1024 * 1024, // 2MB
        ]);

        CmsMediaAsset::factory()->create([
            'mime_type' => 'video/mp4',
            'size' => 10 * 1024 * 1024, // 10MB
        ]);

        CmsMediaAsset::factory()->create([
            'mime_type' => 'application/pdf',
            'size' => 512 * 1024, // 512KB
        ]);

        $stats = $this->mediaService->getStorageStats();

        $this->assertEquals(4, $stats['total_assets']);
        $this->assertEquals(13.5 * 1024 * 1024, $stats['total_size']); // 13.5MB
        $this->assertEquals(2, $stats['image_count']);
        $this->assertEquals(1, $stats['video_count']);
        $this->assertEquals(1, $stats['other_count']);
        $this->assertStringContainsString('MB', $stats['total_size_human']);
    }

    public function test_formats_bytes_correctly()
    {
        $stats1 = $this->mediaService->getStorageStats();
        $this->assertStringContainsString('B', $stats1['total_size_human']);

        CmsMediaAsset::factory()->create(['size' => 1024]);
        $stats2 = $this->mediaService->getStorageStats();
        $this->assertStringContainsString('KB', $stats2['total_size_human']);

        CmsMediaAsset::factory()->create(['size' => 1024 * 1024]);
        $stats3 = $this->mediaService->getStorageStats();
        $this->assertStringContainsString('MB', $stats3['total_size_human']);
    }

    public function test_asset_model_methods()
    {
        $imageAsset = CmsMediaAsset::factory()->create([
            'mime_type' => 'image/jpeg',
            'size' => 1024 * 1024,
            'variants' => [
                'thumbnail' => 'path/to/thumbnail.jpg',
                'medium' => 'path/to/medium.jpg',
            ],
        ]);

        $videoAsset = CmsMediaAsset::factory()->create([
            'mime_type' => 'video/mp4',
        ]);

        $this->assertTrue($imageAsset->isImage());
        $this->assertFalse($imageAsset->isVideo());
        $this->assertFalse($videoAsset->isImage());
        $this->assertTrue($videoAsset->isVideo());

        $this->assertStringContainsString('MB', $imageAsset->human_size);
        
        Storage::fake('public');
        $thumbnailUrl = $imageAsset->getVariant('thumbnail');
        $this->assertStringContainsString('thumbnail.jpg', $thumbnailUrl);
        
        $nonexistentVariant = $imageAsset->getVariant('nonexistent');
        $this->assertNull($nonexistentVariant);
    }

    public function test_scopes_work_correctly()
    {
        // Clean up for this specific test
        CmsMediaAsset::query()->delete();
        
        CmsMediaAsset::factory()->create([
            'mime_type' => 'image/jpeg',
            'is_optimized' => false,
        ]);
        CmsMediaAsset::factory()->create([
            'mime_type' => 'image/png',
            'is_optimized' => false,
        ]);
        CmsMediaAsset::factory()->create(['mime_type' => 'video/mp4']);
        CmsMediaAsset::factory()->create(['mime_type' => 'application/pdf']);
        
        $optimizedAsset = CmsMediaAsset::factory()->create([
            'mime_type' => 'image/jpeg',
            'is_optimized' => true,
        ]);

        $this->assertEquals(3, CmsMediaAsset::images()->count()); // 2 + 1 optimized
        $this->assertEquals(1, CmsMediaAsset::videos()->count());
        
        // Debug: Check how many are actually optimized
        $optimizedCount = CmsMediaAsset::optimized()->count();
        $this->assertGreaterThanOrEqual(1, $optimizedCount); // At least 1 should be optimized
    }

    public function test_handles_non_image_files_gracefully()
    {
        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');
        
        $asset = $this->mediaService->uploadFile($file, $this->user->id);

        $this->assertInstanceOf(CmsMediaAsset::class, $asset);
        $this->assertEquals('application/pdf', $asset->mime_type);
        $this->assertNull($asset->width);
        $this->assertNull($asset->height);
        // For non-image files, is_optimized should be false since no image processing occurs
        $this->assertFalse($asset->is_optimized);
        $this->assertNull($asset->variants);
    }
}