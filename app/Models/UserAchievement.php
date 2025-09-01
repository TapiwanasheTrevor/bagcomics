<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAchievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'achievement_id',
        'unlocked_at',
        'progress_data',
        'is_seen',
        'notification_sent'
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
        'progress_data' => 'array',
        'is_seen' => 'boolean',
        'notification_sent' => 'boolean'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }

    public function scopeUnseen($query)
    {
        return $query->where('is_seen', false);
    }

    public function scopeRecent($query)
    {
        return $query->where('unlocked_at', '>=', now()->subDays(30));
    }

    public function markAsSeen(): void
    {
        $this->update(['is_seen' => true]);
    }
}