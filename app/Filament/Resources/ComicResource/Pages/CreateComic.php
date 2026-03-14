<?php

namespace App\Filament\Resources\ComicResource\Pages;

use App\Filament\Resources\ComicResource;
use App\Services\CloudinaryService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateComic extends CreateRecord
{
    protected static string $resource = ComicResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->uploadCoverToCloudinary($data);
    }

    protected function uploadCoverToCloudinary(array $data): array
    {
        if (empty($data['cover_image_path'])) {
            return $data;
        }

        $localPath = $data['cover_image_path'];

        // If it's already a Cloudinary URL, skip
        if (str_starts_with($localPath, 'http')) {
            return $data;
        }

        $fullPath = storage_path('app/public/' . $localPath);
        if (!file_exists($fullPath)) {
            return $data;
        }

        $cloudinary = app(CloudinaryService::class);
        if (!$cloudinary->isConfigured()) {
            return $data;
        }

        $slug = \Illuminate\Support\Str::slug($data['title']);
        $result = $cloudinary->uploadCover($fullPath, $slug);

        if ($result['success']) {
            $data['cover_image_path'] = $result['url'];
            @unlink($fullPath);
        } else {
            Log::warning('Cover upload to Cloudinary failed', ['error' => $result['error'] ?? 'unknown']);
        }

        return $data;
    }
}
