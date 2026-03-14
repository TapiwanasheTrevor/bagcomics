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
        // Clear Cloudinary URL from FileUpload so it doesn't try to load as local file
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
        // No new cover uploaded — preserve existing
        if (empty($data['cover_image_path'])) {
            $existing = $this->record->cover_image_path;
            if ($existing && str_starts_with($existing, 'http')) {
                $data['cover_image_path'] = $existing;
            }
            return $data;
        }

        if (str_starts_with($data['cover_image_path'], 'http')) {
            return $data;
        }

        $fullPath = $this->resolveFilePath($data['cover_image_path']);
        if (!$fullPath) {
            // Can't find uploaded file — preserve existing cover
            $data['cover_image_path'] = $this->record->cover_image_path;
            return $data;
        }

        $cloudinary = app(CloudinaryService::class);
        if (!$cloudinary->isConfigured()) {
            $data['cover_image_path'] = $this->record->cover_image_path;
            return $data;
        }

        $slug = $this->record->slug ?? \Illuminate\Support\Str::slug($data['title'] ?? '');
        $result = $cloudinary->uploadCover($fullPath, $slug);

        if ($result['success']) {
            $data['cover_image_path'] = $result['url'];
            @unlink($fullPath);
        } else {
            Log::warning('Cover upload to Cloudinary failed', ['error' => $result['error'] ?? 'unknown']);
            $data['cover_image_path'] = $this->record->cover_image_path;
        }

        return $data;
    }

    protected function resolveFilePath(string $path): ?string
    {
        $candidates = [
            storage_path('app/livewire-tmp/' . $path),
            storage_path('app/public/' . $path),
            storage_path('app/' . $path),
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        if (file_exists($path)) {
            return $path;
        }

        return null;
    }
}
