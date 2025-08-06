<?php

namespace App\Services;

use App\Models\CmsMediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class CmsMediaService
{
    protected $imageManager;
    
    public function __construct()
    {
        // Only initialize image manager if Intervention Image is available
        if (class_exists('Intervention\Image\ImageManager')) {
            $this->imageManager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
        }
    }

    /**
     * Upload and process a media file
     */
    public function uploadFile(UploadedFile $file, ?int $userId = null, array $options = []): CmsMediaAsset
    {
        $filename = $this->generateFilename($file);
        $path = $file->storeAs('cms/media', $filename, 'public');
        
        $asset = CmsMediaAsset::create([
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => 'public',
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'alt_text' => $options['alt_text'] ?? null,
            'uploaded_by' => $userId,
        ]);
        
        // Process image if it's an image file
        if ($asset->isImage()) {
            $this->processImage($asset);
        } else {
            // For non-image files, mark as not optimized
            $asset->update(['is_optimized' => false]);
        }
        
        return $asset;
    }

    /**
     * Process an image asset (extract dimensions, create variants)
     */
    protected function processImage(CmsMediaAsset $asset): void
    {
        if (!$this->imageManager) {
            // Image processing not available, just mark as processed
            $asset->update(['is_optimized' => false]);
            return;
        }

        try {
            $image = $this->imageManager->read(Storage::disk($asset->disk)->path($asset->path));
            
            // Update dimensions
            $asset->update([
                'width' => $image->width(),
                'height' => $image->height(),
            ]);
            
            // Create variants
            $variants = $this->createImageVariants($asset, $image);
            $asset->update(['variants' => $variants]);
            
            // Mark as optimized
            $asset->update(['is_optimized' => true]);
            
        } catch (\Exception $e) {
            // Log error but don't fail the upload
            logger()->error('Failed to process image: ' . $e->getMessage(), [
                'asset_id' => $asset->id,
                'path' => $asset->path,
            ]);
        }
    }

    /**
     * Create image variants (thumbnails, different sizes)
     */
    protected function createImageVariants(CmsMediaAsset $asset, $image): array
    {
        if (!$this->imageManager) {
            return [];
        }

        $variants = [];
        $basePath = pathinfo($asset->path, PATHINFO_DIRNAME);
        $filename = pathinfo($asset->path, PATHINFO_FILENAME);
        $extension = pathinfo($asset->path, PATHINFO_EXTENSION);
        
        $sizes = [
            'thumbnail' => ['width' => 150, 'height' => 150],
            'small' => ['width' => 300, 'height' => 300],
            'medium' => ['width' => 600, 'height' => 600],
            'large' => ['width' => 1200, 'height' => 1200],
        ];
        
        foreach ($sizes as $variant => $size) {
            // Skip if original is smaller
            if ($asset->width <= $size['width'] && $asset->height <= $size['height']) {
                continue;
            }
            
            $variantPath = "{$basePath}/{$filename}_{$variant}.{$extension}";
            
            try {
                $resized = clone $image;
                $resized->scaleDown($size['width'], $size['height']);
                
                Storage::disk($asset->disk)->put($variantPath, $resized->encode());
                $variants[$variant] = $variantPath;
            } catch (\Exception $e) {
                // Skip this variant if processing fails
                logger()->warning("Failed to create variant {$variant}: " . $e->getMessage());
            }
        }
        
        return $variants;
    }

    /**
     * Generate a unique filename
     */
    protected function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $slug = Str::slug($name);
        
        return $slug . '_' . time() . '_' . Str::random(8) . '.' . $extension;
    }

    /**
     * Delete a media asset and its variants
     */
    public function deleteAsset(CmsMediaAsset $asset): bool
    {
        // Delete main file
        Storage::disk($asset->disk)->delete($asset->path);
        
        // Delete variants
        if ($asset->variants) {
            foreach ($asset->variants as $variantPath) {
                Storage::disk($asset->disk)->delete($variantPath);
            }
        }
        
        return $asset->delete();
    }

    /**
     * Get media library with filtering and pagination
     */
    public function getMediaLibrary(array $filters = [], int $perPage = 20)
    {
        $query = CmsMediaAsset::query()->with('uploader');
        
        if (isset($filters['type'])) {
            if ($filters['type'] === 'images') {
                $query->images();
            } elseif ($filters['type'] === 'videos') {
                $query->videos();
            }
        }
        
        if (isset($filters['uploader_id'])) {
            $query->where('uploaded_by', $filters['uploader_id']);
        }
        
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('original_filename', 'like', "%{$search}%")
                  ->orWhere('alt_text', 'like', "%{$search}%");
            });
        }
        
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Update asset metadata
     */
    public function updateAsset(CmsMediaAsset $asset, array $data): bool
    {
        return $asset->update([
            'alt_text' => $data['alt_text'] ?? $asset->alt_text,
            'metadata' => array_merge($asset->metadata ?? [], $data['metadata'] ?? []),
        ]);
    }

    /**
     * Get storage usage statistics
     */
    public function getStorageStats(): array
    {
        $totalAssets = CmsMediaAsset::count();
        $totalSize = CmsMediaAsset::sum('size');
        $imageCount = CmsMediaAsset::images()->count();
        $videoCount = CmsMediaAsset::videos()->count();
        
        return [
            'total_assets' => $totalAssets,
            'total_size' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
            'image_count' => $imageCount,
            'video_count' => $videoCount,
            'other_count' => $totalAssets - $imageCount - $videoCount,
        ];
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}