<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComicPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'comic_id',
        'page_number',
        'image_url',
        'image_path',
        'width',
        'height',
        'file_size',
    ];

    protected $casts = [
        'page_number' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'file_size' => 'integer',
    ];

    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }

    public function getFullImageUrl(): string
    {
        if ($this->image_path) {
            $isAuthenticatedAsset = $this->image_url
                && (str_contains($this->image_url, '/authenticated/') || str_contains($this->image_url, '/authenticated_images/'));

            if ($isAuthenticatedAsset) {
                $cloudinary = app(\App\Services\CloudinaryService::class);
                if ($cloudinary->isConfigured()) {
                    $signedUrl = $cloudinary->getAuthenticatedUrl($this->image_path);
                    if ($signedUrl) {
                        return $signedUrl;
                    }
                }
            }
        }

        if ($this->image_url && str_starts_with($this->image_url, 'http')) {
            return $this->image_url;
        }

        return asset('storage/' . $this->image_url);
    }
}
