<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class CmsContent extends Model
{
    protected $fillable = [
        'key',
        'section',
        'type',
        'title',
        'content',
        'metadata',
        'image_path',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the image URL for image content
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image_path ? asset('storage/' . $this->image_path) : null,
        );
    }

    /**
     * Scope to get active content
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get content by section
     */
    public function scopeBySection($query, $section)
    {
        return $query->where('section', $section);
    }

    /**
     * Scope to get content by key
     */
    public function scopeByKey($query, $key)
    {
        return $query->where('key', $key);
    }

    /**
     * Get content by key (static helper)
     */
    public static function getByKey($key, $default = null)
    {
        $content = static::byKey($key)->active()->first();

        if (!$content) {
            return $default;
        }

        return match($content->type) {
            'image' => $content->image_url,
            'json' => $content->metadata,
            default => $content->content,
        };
    }

    /**
     * Get content by section (static helper)
     */
    public static function getBySection($section)
    {
        return static::bySection($section)
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->keyBy('key');
    }
}
