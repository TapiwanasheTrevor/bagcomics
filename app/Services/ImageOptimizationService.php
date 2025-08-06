<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Facades\Image;

class ImageOptimizationService
{
    const SIZES = [
        'thumbnail' => ['width' => 150, 'height' => 200, 'quality' => 85],
        'small' => ['width' => 300, 'height' => 400, 'quality' => 90],
        'medium' => ['width' => 600, 'height' => 800, 'quality' => 90],
        'large' => ['width' => 1200, 'height' => 1600, 'quality' => 95],
    ];

    const WEBP_QUALITY = 80;
    const AVIF_QUALITY = 70;

    /**
     * Process and optimize uploaded image
     */
    public function processImage(UploadedFile $file, string $directory = 'images'): array
    {
        Log::info('Processing image upload', [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType()
        ]);

        $filename = $this->generateUniqueFilename($file);
        $results = [];

        try {
            // Create original image
            $originalImage = Image::make($file);
            $originalPath = "{$directory}/original/{$filename}";
            
            // Save original with basic optimization
            $optimizedOriginal = $this->optimizeImage($originalImage, 95);
            Storage::disk('public')->put($originalPath, $optimizedOriginal);
            
            $results['original'] = [
                'path' => $originalPath,
                'url' => Storage::disk('public')->url($originalPath),
                'size' => Storage::disk('public')->size($originalPath),
                'dimensions' => ['width' => $originalImage->width(), 'height' => $originalImage->height()]
            ];

            // Generate responsive versions
            foreach (self::SIZES as $sizeName => $config) {
                $sizePath = "{$directory}/{$sizeName}/{$filename}";
                
                $resizedImage = $this->createResponsiveVersion($originalImage, $config);
                Storage::disk('public')->put($sizePath, $resizedImage);
                
                $results[$sizeName] = [
                    'path' => $sizePath,
                    'url' => Storage::disk('public')->url($sizePath),
                    'size' => Storage::disk('public')->size($sizePath),
                    'dimensions' => $config
                ];

                // Generate WebP version
                $webpPath = $this->generateWebPVersion($originalImage, $sizePath, $config);
                if ($webpPath) {
                    $results[$sizeName]['webp'] = [
                        'path' => $webpPath,
                        'url' => Storage::disk('public')->url($webpPath),
                        'size' => Storage::disk('public')->size($webpPath)
                    ];
                }
            }

            Log::info('Image processing completed', [
                'filename' => $filename,
                'versions_created' => count($results)
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Image processing failed', [
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Failed to process image: ' . $e->getMessage());
        }
    }

    /**
     * Generate WebP version of image
     */
    private function generateWebPVersion($originalImage, string $basePath, array $config): ?string
    {
        try {
            $webpPath = str_replace('.jpg', '.webp', str_replace('.png', '.webp', $basePath));
            
            $webpImage = $originalImage->fit($config['width'], $config['height'])
                ->encode('webp', self::WEBP_QUALITY);
            
            Storage::disk('public')->put($webpPath, $webpImage);
            
            return $webpPath;
        } catch (\Exception $e) {
            Log::warning('Failed to generate WebP version', [
                'base_path' => $basePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Create responsive version of image
     */
    private function createResponsiveVersion($originalImage, array $config): string
    {
        return $originalImage
            ->fit($config['width'], $config['height'])
            ->encode('jpg', $config['quality']);
    }

    /**
     * Optimize image with compression
     */
    private function optimizeImage($image, int $quality = 90): string
    {
        return $image->encode('jpg', $quality);
    }

    /**
     * Generate unique filename
     */
    private function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        return uniqid() . '_' . time() . '.' . $extension;
    }

    /**
     * Generate HTML picture element with responsive images
     */
    public function generateResponsiveImageHtml(array $imageData, string $alt = '', string $class = ''): string
    {
        if (empty($imageData)) {
            return '';
        }

        $picture = '<picture>';
        
        // Add WebP sources if available
        foreach (['large', 'medium', 'small'] as $size) {
            if (isset($imageData[$size]['webp'])) {
                $mediaQuery = $this->getMediaQuery($size);
                $picture .= "<source srcset=\"{$imageData[$size]['webp']['url']}\" type=\"image/webp\" {$mediaQuery}>";
            }
        }
        
        // Add fallback JPEG sources
        foreach (['large', 'medium', 'small'] as $size) {
            if (isset($imageData[$size])) {
                $mediaQuery = $this->getMediaQuery($size);
                $picture .= "<source srcset=\"{$imageData[$size]['url']}\" type=\"image/jpeg\" {$mediaQuery}>";
            }
        }
        
        // Fallback img tag
        $defaultImage = $imageData['medium'] ?? $imageData['small'] ?? $imageData['thumbnail'] ?? $imageData['original'];
        $picture .= "<img src=\"{$defaultImage['url']}\" alt=\"{$alt}\" class=\"{$class}\" loading=\"lazy\">";
        
        $picture .= '</picture>';
        
        return $picture;
    }

    /**
     * Get media query for responsive size
     */
    private function getMediaQuery(string $size): string
    {
        return match($size) {
            'large' => 'media="(min-width: 1200px)"',
            'medium' => 'media="(min-width: 768px)"',
            'small' => 'media="(min-width: 480px)"',
            default => ''
        };
    }

    /**
     * Generate lazy loading image HTML
     */
    public function generateLazyImageHtml(array $imageData, string $alt = '', string $class = ''): string
    {
        if (empty($imageData)) {
            return '';
        }

        $thumbnail = $imageData['thumbnail'] ?? $imageData['small'];
        $fullSize = $imageData['medium'] ?? $imageData['original'];
        
        return sprintf(
            '<img src="%s" data-src="%s" alt="%s" class="lazy-load %s" loading="lazy">',
            $thumbnail['url'],
            $fullSize['url'],
            htmlspecialchars($alt),
            $class
        );
    }

    /**
     * Generate placeholder image (blur effect)
     */
    public function generatePlaceholder(array $imageData): ?string
    {
        if (!isset($imageData['thumbnail'])) {
            return null;
        }

        try {
            $thumbnailPath = $imageData['thumbnail']['path'];
            $image = Image::make(Storage::disk('public')->get($thumbnailPath));
            
            // Create very small, blurred version
            $placeholder = $image->resize(20, 30)
                ->blur(5)
                ->encode('jpg', 60);
            
            return 'data:image/jpeg;base64,' . base64_encode($placeholder);
        } catch (\Exception $e) {
            Log::warning('Failed to generate placeholder', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Clean up old image versions
     */
    public function cleanupOldImages(string $imagePath): void
    {
        try {
            $directory = dirname($imagePath);
            $filename = basename($imagePath);
            
            // Remove all size versions
            foreach (array_keys(self::SIZES) as $size) {
                $sizePath = "{$directory}/{$size}/{$filename}";
                if (Storage::disk('public')->exists($sizePath)) {
                    Storage::disk('public')->delete($sizePath);
                }
                
                // Remove WebP version
                $webpPath = str_replace(['.jpg', '.png'], '.webp', $sizePath);
                if (Storage::disk('public')->exists($webpPath)) {
                    Storage::disk('public')->delete($webpPath);
                }
            }
            
            // Remove original
            if (Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            
            Log::info('Cleaned up old image versions', ['path' => $imagePath]);
        } catch (\Exception $e) {
            Log::error('Failed to cleanup old images', [
                'path' => $imagePath,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Bulk optimize existing images
     */
    public function bulkOptimizeImages(string $directory): array
    {
        Log::info('Starting bulk image optimization', ['directory' => $directory]);
        
        $results = [
            'processed' => 0,
            'failed' => 0,
            'space_saved' => 0
        ];

        $files = Storage::disk('public')->files($directory);
        
        foreach ($files as $file) {
            try {
                if (!$this->isImageFile($file)) {
                    continue;
                }

                $originalSize = Storage::disk('public')->size($file);
                $this->optimizeExistingImage($file);
                $newSize = Storage::disk('public')->size($file);
                
                $results['processed']++;
                $results['space_saved'] += ($originalSize - $newSize);
                
            } catch (\Exception $e) {
                $results['failed']++;
                Log::error('Failed to optimize image', [
                    'file' => $file,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Bulk optimization completed', $results);
        return $results;
    }

    /**
     * Optimize existing image file
     */
    private function optimizeExistingImage(string $filePath): void
    {
        $image = Image::make(Storage::disk('public')->get($filePath));
        $optimized = $this->optimizeImage($image, 85);
        Storage::disk('public')->put($filePath, $optimized);
    }

    /**
     * Check if file is an image
     */
    private function isImageFile(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif']);
    }

    /**
     * Get CDN URL for image
     */
    public function getCdnUrl(string $imagePath, string $size = 'medium'): string
    {
        // If CDN is configured, return CDN URL
        $cdnBaseUrl = config('app.cdn_url');
        if ($cdnBaseUrl) {
            return rtrim($cdnBaseUrl, '/') . '/' . ltrim($imagePath, '/');
        }
        
        // Fallback to local storage URL
        return Storage::disk('public')->url($imagePath);
    }

    /**
     * Generate progressive JPEG
     */
    private function generateProgressiveJpeg($image, int $quality = 90): string
    {
        // Enable progressive JPEG encoding
        return $image->interlace(true)->encode('jpg', $quality);
    }

    /**
     * Get image optimization statistics
     */
    public function getOptimizationStats(): array
    {
        try {
            $originalDir = 'images/original';
            $optimizedDirs = array_map(fn($size) => "images/{$size}", array_keys(self::SIZES));
            
            $originalSize = $this->getDirectorySize($originalDir);
            $optimizedSize = 0;
            
            foreach ($optimizedDirs as $dir) {
                $optimizedSize += $this->getDirectorySize($dir);
            }
            
            return [
                'original_size' => $this->formatBytes($originalSize),
                'optimized_size' => $this->formatBytes($optimizedSize),
                'space_saved' => $this->formatBytes($originalSize - $optimizedSize),
                'compression_ratio' => $originalSize > 0 ? round((1 - ($optimizedSize / $originalSize)) * 100, 2) . '%' : '0%'
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get total size of directory
     */
    private function getDirectorySize(string $directory): int
    {
        $totalSize = 0;
        $files = Storage::disk('public')->files($directory);
        
        foreach ($files as $file) {
            $totalSize += Storage::disk('public')->size($file);
        }
        
        return $totalSize;
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes == 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log(1024));
        
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}