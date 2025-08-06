<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsContent extends Model
{
    use HasFactory;
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
        'status',
        'published_at',
        'scheduled_at',
        'created_by',
        'updated_by',
        'current_version',
        'change_summary',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
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

    /**
     * Get all versions of this content
     */
    public function versions(): HasMany
    {
        return $this->hasMany(CmsContentVersion::class)->orderBy('version_number', 'desc');
    }

    /**
     * Get analytics for this content
     */
    public function analytics(): HasMany
    {
        return $this->hasMany(CmsAnalytic::class);
    }

    /**
     * Get the user who created this content
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this content
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to get published content
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope to get draft content
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope to get scheduled content
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled')
                    ->whereNotNull('scheduled_at');
    }

    /**
     * Check if content is ready to be published
     */
    public function isReadyToPublish(): bool
    {
        return $this->status === 'scheduled' 
            && $this->scheduled_at 
            && $this->scheduled_at <= now();
    }

    /**
     * Create a new version of this content
     */
    public function createVersion(array $data, ?int $userId = null): CmsContentVersion
    {
        $nextVersion = $this->versions()->max('version_number') + 1;

        return $this->versions()->create([
            'version_number' => $nextVersion,
            'title' => $data['title'] ?? $this->title,
            'content' => $data['content'] ?? $this->content,
            'metadata' => $data['metadata'] ?? $this->metadata,
            'image_path' => $data['image_path'] ?? $this->image_path,
            'status' => $data['status'] ?? 'draft',
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'created_by' => $userId,
            'change_summary' => $data['change_summary'] ?? null,
        ]);
    }

    /**
     * Get the latest version
     */
    public function getLatestVersion(): ?CmsContentVersion
    {
        return $this->versions()->first();
    }

    /**
     * Track an analytics event
     */
    public function trackEvent(string $eventType, array $metadata = []): CmsAnalytic
    {
        return $this->analytics()->create([
            'event_type' => $eventType,
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
            'referrer' => request()->header('referer'),
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
