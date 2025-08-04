<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialShare extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'comic_id',
        'platform',
        'share_type',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }

    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeByShareType($query, string $shareType)
    {
        return $query->where('share_type', $shareType);
    }

    public function getShareUrl(): ?string
    {
        $metadata = $this->metadata ?? [];
        return $metadata['share_url'] ?? null;
    }

    public function setShareUrl(string $url): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['share_url'] = $url;
        $this->metadata = $metadata;
        $this->save();
    }

    public static function createShare(User $user, Comic $comic, string $platform, string $shareType, array $metadata = []): self
    {
        return self::create([
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'platform' => $platform,
            'share_type' => $shareType,
            'metadata' => $metadata,
        ]);
    }
}
