<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    protected ?Cloudinary $cloudinary = null;
    protected ?bool $isConfigured = null;

    /**
     * Get the Cloudinary client (lazy initialization)
     */
    protected function getCloudinary(): ?Cloudinary
    {
        if ($this->cloudinary === null && $this->isConfigured === null) {
            $cloudName = config('services.cloudinary.cloud_name');
            $apiKey = config('services.cloudinary.api_key');
            $apiSecret = config('services.cloudinary.api_secret');

            // Only initialize if all credentials are provided and non-empty
            if (!empty($cloudName) && !empty($apiKey) && !empty($apiSecret)) {
                try {
                    $this->cloudinary = new Cloudinary([
                        'cloud' => [
                            'cloud_name' => $cloudName,
                            'api_key' => $apiKey,
                            'api_secret' => $apiSecret,
                        ],
                        'url' => [
                            'secure' => true,
                        ],
                    ]);
                    $this->isConfigured = true;
                } catch (\Exception $e) {
                    Log::warning('Cloudinary initialization failed: ' . $e->getMessage());
                    $this->isConfigured = false;
                }
            } else {
                $this->isConfigured = false;
            }
        }

        return $this->cloudinary;
    }

    /**
     * Check if Cloudinary is properly configured
     */
    public function isConfigured(): bool
    {
        $this->getCloudinary(); // Trigger lazy initialization
        return $this->isConfigured ?? false;
    }

    /**
     * Upload a single image to Cloudinary
     */
    public function uploadImage(
        string|UploadedFile $file,
        string $folder = 'comics',
        array $options = []
    ): array {
        $cloudinary = $this->getCloudinary();

        if ($cloudinary === null) {
            return [
                'success' => false,
                'error' => 'Cloudinary is not configured. Please set CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, and CLOUDINARY_API_SECRET.',
            ];
        }

        try {
            $uploadOptions = array_merge([
                'folder' => "bagcomics/{$folder}",
                'resource_type' => 'image',
                'type' => 'authenticated',
                'transformation' => [
                    'quality' => 'auto:best',
                    'fetch_format' => 'auto',
                ],
            ], $options);

            $filePath = $file instanceof UploadedFile
                ? $file->getRealPath()
                : $file;

            $result = $cloudinary->uploadApi()->upload($filePath, $uploadOptions);

            return [
                'success' => true,
                'url' => $result['secure_url'],
                'public_id' => $result['public_id'],
                'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null,
                'format' => $result['format'] ?? null,
                'size' => $result['bytes'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Cloudinary upload failed', [
                'error' => $e->getMessage(),
                'file' => is_string($file) ? $file : $file->getClientOriginalName(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload comic cover image
     */
    public function uploadCover(string|UploadedFile $file, string $comicSlug): array
    {
        return $this->uploadImage($file, "covers/{$comicSlug}", [
            'public_id' => "cover",
            'overwrite' => true,
            'type' => 'upload', // Covers remain public for sharing/thumbnails
            'transformation' => [
                'width' => 600,
                'height' => 900,
                'crop' => 'fill',
                'gravity' => 'center',
                'quality' => 'auto:best',
            ],
        ]);
    }

    /**
     * Upload comic page image
     */
    public function uploadPage(
        string|UploadedFile $file,
        string $comicSlug,
        int $pageNumber
    ): array {
        $paddedNumber = str_pad($pageNumber, 4, '0', STR_PAD_LEFT);

        return $this->uploadImage($file, "pages/{$comicSlug}", [
            'public_id' => "page_{$paddedNumber}",
            'transformation' => [
                'width' => 1200,
                'quality' => 'auto:best',
                'fetch_format' => 'auto',
            ],
        ]);
    }

    /**
     * Upload multiple comic pages
     */
    public function uploadPages(array $files, string $comicSlug): array
    {
        $results = [];
        $pageNumber = 1;

        foreach ($files as $file) {
            $result = $this->uploadPage($file, $comicSlug, $pageNumber);
            $result['page_number'] = $pageNumber;
            $results[] = $result;
            $pageNumber++;
        }

        return $results;
    }

    /**
     * Delete an image from Cloudinary
     */
    public function deleteImage(string $publicId): bool
    {
        $cloudinary = $this->getCloudinary();

        if ($cloudinary === null) {
            Log::warning('Cloudinary delete skipped - not configured');
            return false;
        }

        try {
            $result = $cloudinary->uploadApi()->destroy($publicId);
            return $result['result'] === 'ok';
        } catch (\Exception $e) {
            Log::error('Cloudinary delete failed', [
                'error' => $e->getMessage(),
                'public_id' => $publicId,
            ]);
            return false;
        }
    }

    /**
     * Delete all images in a folder
     */
    public function deleteFolder(string $folder): bool
    {
        $cloudinary = $this->getCloudinary();

        if ($cloudinary === null) {
            Log::warning('Cloudinary folder delete skipped - not configured');
            return false;
        }

        try {
            $cloudinary->adminApi()->deleteResourcesByPrefix("bagcomics/{$folder}");
            return true;
        } catch (\Exception $e) {
            Log::error('Cloudinary folder delete failed', [
                'error' => $e->getMessage(),
                'folder' => $folder,
            ]);
            return false;
        }
    }

    /**
     * Generate optimized URL for an image
     */
    public function getOptimizedUrl(string $publicId, array $transformations = []): ?string
    {
        $cloudinary = $this->getCloudinary();

        if ($cloudinary === null) {
            return null;
        }

        $defaultTransformations = [
            'fetch_format' => 'auto',
            'quality' => 'auto',
        ];

        $mergedTransformations = array_merge($defaultTransformations, $transformations);

        return $cloudinary->image($publicId)
            ->addTransformation($mergedTransformations)
            ->toUrl();
    }

    /**
     * Get Cloudinary instance for advanced operations
     */
    public function getClient(): ?Cloudinary
    {
        return $this->getCloudinary();
    }
}
