<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComicView extends Model
{
    protected $fillable = [
        'comic_id',
        'user_id',
        'ip_address',
        'user_agent',
        'session_id',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a view for a comic
     */
    public static function recordView(Comic $comic, ?User $user = null, ?string $ipAddress = null, ?string $userAgent = null, ?string $sessionId = null): void
    {
        // Don't record duplicate views from the same user/session within 1 hour
        $recentView = static::where('comic_id', $comic->id)
            ->where(function ($query) use ($user, $ipAddress, $sessionId) {
                if ($user) {
                    $query->where('user_id', $user->id);
                } elseif ($sessionId) {
                    $query->where('session_id', $sessionId);
                } elseif ($ipAddress) {
                    $query->where('ip_address', $ipAddress);
                }
            })
            ->where('viewed_at', '>', now()->subHour())
            ->exists();

        if (!$recentView) {
            static::create([
                'comic_id' => $comic->id,
                'user_id' => $user?->id,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'session_id' => $sessionId,
                'viewed_at' => now(),
            ]);

            // Update comic view counts
            $comic->increment('view_count');
            
            // Update unique viewers count
            $uniqueViewers = static::where('comic_id', $comic->id)
                ->distinct()
                ->count('COALESCE(user_id, ip_address)');
            
            $comic->update(['unique_viewers' => $uniqueViewers]);
        }
    }

    /**
     * Get popular comics based on views
     */
    public static function getPopularComics(int $limit = 10, int $days = 30)
    {
        return Comic::select('comics.*')
            ->leftJoin('comic_views', 'comics.id', '=', 'comic_views.comic_id')
            ->where('comic_views.viewed_at', '>', now()->subDays($days))
            ->groupBy('comics.id')
            ->orderByRaw('COUNT(comic_views.id) DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Get trending comics (views in last 7 days vs previous 7 days)
     */
    public static function getTrendingComics(int $limit = 10)
    {
        $recentViews = static::selectRaw('comic_id, COUNT(*) as recent_views')
            ->where('viewed_at', '>', now()->subDays(7))
            ->groupBy('comic_id');

        $previousViews = static::selectRaw('comic_id, COUNT(*) as previous_views')
            ->whereBetween('viewed_at', [now()->subDays(14), now()->subDays(7)])
            ->groupBy('comic_id');

        return Comic::select('comics.*')
            ->leftJoinSub($recentViews, 'recent', function ($join) {
                $join->on('comics.id', '=', 'recent.comic_id');
            })
            ->leftJoinSub($previousViews, 'previous', function ($join) {
                $join->on('comics.id', '=', 'previous.comic_id');
            })
            ->selectRaw('comics.*, COALESCE(recent.recent_views, 0) as recent_views, COALESCE(previous.previous_views, 0) as previous_views')
            ->orderByRaw('(COALESCE(recent.recent_views, 0) - COALESCE(previous.previous_views, 0)) DESC')
            ->limit($limit)
            ->get();
    }
}
