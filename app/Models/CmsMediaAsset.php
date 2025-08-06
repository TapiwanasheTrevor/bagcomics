<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class CmsMediaAsset extends Model
{
    use HasFactory;
    protected $fillable = [
        'filename',
        'original_filename',
        'path',
        'disk',
        'mime_type',
        'size',
        'width',
        'height',
        'alt_text',
        'metadata',
        'is_optimized',
        'variants',
        'uploaded_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'variants' => 'array',
        'is_optimized' => 'boolean',
    ];

    /**
     * Get the user who uploaded this asset
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the full URL for this asset
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => Storage::disk($this->disk)->url($this->path),
        );
    }

    /**
     * Get the file size in human readable format
     */
    protected function humanSize(): Attribute
    {
        return Attribute::make(
            get: function () {
                $bytes = $this->size;
                $units = ['B', 'KB', 'MB', 'GB'];
                
                for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
                    $bytes /= 1024;
                }
                
                return round($bytes, 2) . ' ' . $units[$i];
            }
        );
    }

    /**
     * Check if this is an image
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if this is a video
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Get a specific variant of this asset
     */
    public function getVariant(string $variant): ?string
    {
        if (!$this->variants || !isset($this->variants[$variant])) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->variants[$variant]);
    }

    /**
     * Scope to get images only
     */
    public function scopeImages($query)
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    /**
     * Scope to get videos only
     */
    public function scopeVideos($query)
    {
        return $query->where('mime_type', 'like', 'video/%');
    }

    /**
     * Scope to get optimized assets
     */
    public function scopeOptimized($query)
    {
        return $query->where('is_optimized', true);
    }
}
