<?php

namespace App\Filament\Resources\ComicResource\Pages;

use App\Filament\Resources\ComicResource;
use App\Services\CloudinaryService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditComic extends EditRecord
{
    protected static string $resource = ComicResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // If cover_image_path is a Cloudinary URL, clear it from the FileUpload
        // so Filament doesn't try to load it as a local file.
        // The Placeholder shows the current cover instead.
        if (!empty($data['cover_image_path']) && str_starts_with($data['cover_image_path'], 'http')) {
            $data['cover_image_path'] = null;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->uploadCoverToCloudinary($data);
    }

    protected function uploadCoverToCloudinary(array $data): array
    {
        // If cover_image_path is null/empty, the user didn't upload a new cover.
        // Preserve the existing Cloudinary URL.
        if (empty($data['cover_image_path'])) {
            $existing = $this->record->cover_image_path;
            if ($existing && str_starts_with($existing, 'http')) {
                $data['cover_image_path'] = $existing;
            }
            return $data;
        }

        // If it's already a Cloudinary URL, skip
        if (str_starts_with($data['cover_image_path'], 'http')) {
            return $data;
        }

        $fullPath = storage_path('app/public/' . $data['cover_image_path']);
        if (!file_exists($fullPath)) {
            return $data;
        }

        $cloudinary = app(CloudinaryService::class);
        if (!$cloudinary->isConfigured()) {
            return $data;
        }

        $slug = $this->record->slug ?? \Illuminate\Support\Str::slug($data['title'] ?? '');
        $result = $cloudinary->uploadCover($fullPath, $slug);

        if ($result['success']) {
            $data['cover_image_path'] = $result['url'];
            @unlink($fullPath);
        } else {
            Log::warning('Cover upload to Cloudinary failed', ['error' => $result['error'] ?? 'unknown']);
            // Preserve existing cover on failure
            $data['cover_image_path'] = $this->record->cover_image_path;
        }

        return $data;
    }
}
