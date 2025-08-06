<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsAnalytic extends Model
{
    use HasFactory;
    protected $fillable = [
        'cms_content_id',
        'event_type',
        'user_agent',
        'ip_address',
        'referrer',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * Get the CMS content that this analytic belongs to
     */
    public function cmsContent(): BelongsTo
    {
        return $this->belongsTo(CmsContent::class);
    }

    /**
     * Scope to get analytics for a specific event type
     */
    public function scopeEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to get analytics within a date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('occurred_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get recent analytics
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('occurred_at', '>=', now()->subDays($days));
    }
}
