<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsContentVersion extends Model
{
    use HasFactory;
    protected $fillable = [
        'cms_content_id',
        'version_number',
        'title',
        'content',
        'metadata',
        'image_path',
        'is_active',
        'status',
        'published_at',
        'scheduled_at',
        'created_by',
        'change_summary',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    /**
     * Get the CMS content that owns this version
     */
    public function cmsContent(): BelongsTo
    {
        return $this->belongsTo(CmsContent::class);
    }

    /**
     * Get the user who created this version
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get published versions
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope to get draft versions
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope to get scheduled versions
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled')
                    ->whereNotNull('scheduled_at');
    }

    /**
     * Check if this version is ready to be published
     */
    public function isReadyToPublish(): bool
    {
        return $this->status === 'scheduled' 
            && $this->scheduled_at 
            && $this->scheduled_at <= now();
    }
}
