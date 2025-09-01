<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserAnalytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'metric_type',
        'metric_name',
        'value',
        'additional_data',
        'date',
        'period'
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'additional_data' => 'json',
        'date' => 'date'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForMetric($query, string $metricType, ?string $metricName = null)
    {
        $query->where('metric_type', $metricType);
        
        if ($metricName) {
            $query->where('metric_name', $metricName);
        }
        
        return $query;
    }

    public function scopeForPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    public function scopeForDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('date', '>=', Carbon::now()->subDays($days));
    }

    // Static methods for data aggregation
    public static function recordMetric(int $userId, string $metricType, string $metricName, float $value, array $additionalData = [], ?Carbon $date = null, string $period = 'daily')
    {
        return static::create([
            'user_id' => $userId,
            'metric_type' => $metricType,
            'metric_name' => $metricName,
            'value' => $value,
            'additional_data' => $additionalData,
            'date' => $date ?? Carbon::today(),
            'period' => $period
        ]);
    }

    public static function getReadingStats(int $userId, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);
        $endDate = Carbon::now();

        $stats = static::forUser($userId)
            ->forMetric('reading')
            ->forDateRange($startDate, $endDate)
            ->get();

        $comicsRead = $stats->where('metric_name', 'comics_read')->sum('value');
        $timeSpent = $stats->where('metric_name', 'reading_time_minutes')->sum('value');
        $pagesRead = $stats->where('metric_name', 'pages_read')->sum('value');
        $sessionsCount = $stats->where('metric_name', 'reading_sessions')->sum('value');

        return [
            'comics_read' => $comicsRead,
            'total_reading_time' => $timeSpent,
            'pages_read' => $pagesRead,
            'reading_sessions' => $sessionsCount,
            'average_session_length' => $sessionsCount > 0 ? $timeSpent / $sessionsCount : 0,
            'daily_average' => [
                'comics' => $comicsRead / $days,
                'minutes' => $timeSpent / $days,
                'pages' => $pagesRead / $days
            ]
        ];
    }

    public static function getEngagementStats(int $userId, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);
        $endDate = Carbon::now();

        $stats = static::forUser($userId)
            ->forMetric('engagement')
            ->forDateRange($startDate, $endDate)
            ->get();

        return [
            'ratings_given' => $stats->where('metric_name', 'ratings_given')->sum('value'),
            'reviews_written' => $stats->where('metric_name', 'reviews_written')->sum('value'),
            'lists_created' => $stats->where('metric_name', 'lists_created')->sum('value'),
            'shares_made' => $stats->where('metric_name', 'shares_made')->sum('value'),
            'comments_posted' => $stats->where('metric_name', 'comments_posted')->sum('value'),
            'bookmarks_added' => $stats->where('metric_name', 'bookmarks_added')->sum('value')
        ];
    }

    public static function getDiscoveryStats(int $userId, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);
        $endDate = Carbon::now();

        $stats = static::forUser($userId)
            ->forMetric('discovery')
            ->forDateRange($startDate, $endDate)
            ->get();

        $genresData = $stats->where('metric_name', 'genre_explored')
            ->pluck('additional_data')
            ->flatten()
            ->groupBy('genre')
            ->map->count();

        $authorsData = $stats->where('metric_name', 'author_discovered')
            ->pluck('additional_data')
            ->flatten()
            ->groupBy('author')
            ->map->count();

        return [
            'new_genres_explored' => $stats->where('metric_name', 'genre_explored')->count(),
            'new_authors_discovered' => $stats->where('metric_name', 'author_discovered')->count(),
            'recommendations_followed' => $stats->where('metric_name', 'recommendation_followed')->sum('value'),
            'trending_comics_read' => $stats->where('metric_name', 'trending_comic_read')->sum('value'),
            'genres_breakdown' => $genresData->toArray(),
            'authors_breakdown' => $authorsData->toArray()
        ];
    }

    public static function getStreakStats(int $userId): array
    {
        $currentStreaks = UserStreak::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        $longestStreaks = UserStreak::where('user_id', $userId)
            ->selectRaw('streak_type, MAX(longest_streak) as max_streak')
            ->groupBy('streak_type')
            ->get();

        return [
            'current_streaks' => $currentStreaks->mapWithKeys(function ($streak) {
                return [$streak->streak_type => $streak->current_streak];
            })->toArray(),
            'longest_streaks' => $longestStreaks->mapWithKeys(function ($streak) {
                return [$streak->streak_type => $streak->max_streak];
            })->toArray()
        ];
    }

    public static function getDailyActivity(int $userId, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);
        $endDate = Carbon::now();

        $dailyStats = static::forUser($userId)
            ->forDateRange($startDate, $endDate)
            ->orderBy('date')
            ->get()
            ->groupBy(function ($item) {
                return $item->date->format('Y-m-d');
            });

        $activity = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayStats = $dailyStats->get($dateStr, collect());

            $activity[] = [
                'date' => $dateStr,
                'comics_read' => $dayStats->where('metric_name', 'comics_read')->sum('value'),
                'reading_time' => $dayStats->where('metric_name', 'reading_time_minutes')->sum('value'),
                'pages_read' => $dayStats->where('metric_name', 'pages_read')->sum('value'),
                'engagement_score' => $dayStats->whereIn('metric_type', ['engagement', 'social'])->sum('value')
            ];

            $currentDate->addDay();
        }

        return $activity;
    }

    public static function getMonthlyTrends(int $userId, int $months = 6): array
    {
        $startDate = Carbon::now()->subMonths($months);
        $endDate = Carbon::now();

        $monthlyStats = static::forUser($userId)
            ->forPeriod('monthly')
            ->forDateRange($startDate, $endDate)
            ->get()
            ->groupBy(function ($item) {
                return $item->date->format('Y-m');
            });

        $trends = [];
        $currentMonth = $startDate->copy()->startOfMonth();

        while ($currentMonth->lte($endDate)) {
            $monthStr = $currentMonth->format('Y-m');
            $monthStats = $monthlyStats->get($monthStr, collect());

            $trends[] = [
                'month' => $monthStr,
                'month_name' => $currentMonth->format('F Y'),
                'comics_read' => $monthStats->where('metric_name', 'comics_read')->sum('value'),
                'reading_time' => $monthStats->where('metric_name', 'reading_time_minutes')->sum('value'),
                'achievements_unlocked' => $monthStats->where('metric_name', 'achievements_unlocked')->sum('value'),
                'engagement_score' => $monthStats->whereIn('metric_type', ['engagement', 'social'])->sum('value')
            ];

            $currentMonth->addMonth();
        }

        return $trends;
    }

    public static function getTopComics(int $userId, int $limit = 10): array
    {
        $topComics = static::forUser($userId)
            ->forMetric('reading', 'comic_read')
            ->recent(90)
            ->get()
            ->flatMap(function ($item) {
                return collect($item->additional_data)->get('comics', []);
            })
            ->groupBy('slug')
            ->map(function ($group) {
                $comic = $group->first();
                return [
                    'title' => $comic['title'] ?? 'Unknown',
                    'slug' => $comic['slug'] ?? '',
                    'cover_image_url' => $comic['cover_image_url'] ?? null,
                    'read_count' => $group->count(),
                    'total_time' => $group->sum('reading_time', 0)
                ];
            })
            ->sortByDesc('read_count')
            ->take($limit)
            ->values();

        return $topComics->toArray();
    }
}