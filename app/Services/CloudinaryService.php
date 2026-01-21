<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    protected Cloudinary $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }

    /**
     * Upload a single image to Cloudinary
     */
    public function uploadImage(
        string|UploadedFile $file,
        string $folder = 'comics',
        array $options = []
    ): array {
        try {
            $uploadOptions = array_merge([
                'folder' => "bagcomics/{$folder}",
                'resource_type' => 'image',
                'transformation' => [
                    'quality' => 'auto:best',
                    'fetch_format' => 'auto',
                ],
            ], $options);

            // Handle file path or UploadedFile
            $filePath = $file instanceof UploadedFile
                ? $file->getRealPath()
                : $file;

            $result = $this->cloudinary->uploadApi()->upload($filePath, $uploadOptions);

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
        try {
            $result = $this->cloudinary->uploadApi()->destroy($publicId);
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
        try {
            $this->cloudinary->adminApi()->deleteResourcesByPrefix("bagcomics/{$folder}");
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
    public function getOptimizedUrl(string $publicId, array $transformations = []): string
    {
        $defaultTransformations = [
            'fetch_format' => 'auto',
            'quality' => 'auto',
        ];

        $mergedTransformations = array_merge($defaultTransformations, $transformations);

        return $this->cloudinary->image($publicId)
            ->addTransformation($mergedTransformations)
            ->toUrl();
    }

    /**
     * Get Cloudinary instance for advanced operations
     */
    public function getClient(): Cloudinary
    {
        return $this->cloudinary;
    }
}
