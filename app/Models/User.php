<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Collection;
use App\Models\UserPreferences;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Billable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'social_profiles',
        'achievements',
        'subscription_type',
        'subscription_status',
        'subscription_expires_at',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'social_profiles' => 'array',
            'achievements' => 'array',
            'subscription_expires_at' => 'datetime',
            'is_admin' => 'boolean',
        ];
    }

    public function comicProgress(): HasMany
    {
        return $this->hasMany(UserComicProgress::class);
    }

    public function library(): HasMany
    {
        return $this->hasMany(UserLibrary::class);
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreferences::class);
    }

    /**
     * Get user preferences with defaults if none exist
     */
    public function getPreferences(): UserPreferences
    {
        if (!$this->preferences) {
            return $this->preferences()->create(UserPreferences::getDefaults());
        }

        return $this->preferences;
    }

    public function comics(): BelongsToMany
    {
        return $this->belongsToMany(Comic::class, 'user_libraries')
            ->withPivot(['access_type', 'purchase_price', 'purchased_at', 'is_favorite', 'rating', 'review'])
            ->withTimestamps();
    }

    public function favoriteComics(): BelongsToMany
    {
        return $this->comics()->wherePivot('is_favorite', true);
    }

    public function getProgressForComic(Comic $comic): ?UserComicProgress
    {
        return $this->comicProgress()->where('comic_id', $comic->id)->first();
    }



    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function successfulPayments(): HasMany
    {
        return $this->payments()->where('status', 'succeeded');
    }

    public function hasPurchasedComic(Comic $comic): bool
    {
        return $this->successfulPayments()
            ->where('comic_id', $comic->id)
            ->exists();
    }

    // New relationships for enhanced functionality
    public function reviews(): HasMany
    {
        return $this->hasMany(ComicReview::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(ComicBookmark::class);
    }

    public function socialShares(): HasMany
    {
        return $this->hasMany(SocialShare::class);
    }

    public function reviewVotes(): HasMany
    {
        return $this->hasMany(ReviewVote::class);
    }

    // Enhanced methods for user statistics and recommendations
    public function getReadingStatistics(): array
    {
        $totalComics = $this->library()->count();
        $completedComics = $this->library()->where('completion_percentage', 100)->count();
        $inProgressComics = $this->library()->whereBetween('completion_percentage', [0.01, 99.99])->count();
        $unreadComics = $this->library()->where('completion_percentage', 0)->count();
        
        $totalReadingTime = $this->library()->sum('total_reading_time');
        $averageRating = $this->library()->whereNotNull('rating')->avg('rating') ?? 0.0;
        $totalReviews = $this->reviews()->count();
        $totalBookmarks = $this->bookmarks()->count();
        
        $favoriteGenres = $this->getFavoriteGenres();
        $readingStreak = $this->getCurrentReadingStreak();
        $monthlyReadingGoal = $this->getMonthlyReadingProgress();

        return [
            'total_comics' => $totalComics,
            'completed_comics' => $completedComics,
            'in_progress_comics' => $inProgressComics,
            'unread_comics' => $unreadComics,
            'completion_rate' => $totalComics > 0 ? ($completedComics / $totalComics) * 100 : 0,
            'total_reading_time_seconds' => $totalReadingTime,
            'total_reading_time_formatted' => $this->formatReadingTime($totalReadingTime),
            'average_reading_session' => $this->getAverageReadingSession(),
            'average_rating_given' => round($averageRating, 2),
            'total_reviews' => $totalReviews,
            'total_bookmarks' => $totalBookmarks,
            'favorite_genres' => $favoriteGenres,
            'reading_streak_days' => $readingStreak,
            'monthly_progress' => $monthlyReadingGoal,
            'most_read_day' => $this->getMostActiveReadingDay(),
            'reading_velocity' => $this->getReadingVelocity(),
        ];
    }

    public function getLibraryAnalytics(): array
    {
        $library = $this->library()->with('comic')->get();
        
        $genreDistribution = $library->groupBy('comic.genre')
            ->map(fn($comics) => $comics->count())
            ->sortDesc()
            ->take(5);
            
        $publisherDistribution = $library->groupBy('comic.publisher')
            ->map(fn($comics) => $comics->count())
            ->sortDesc()
            ->take(5);
            
        $ratingDistribution = $library->whereNotNull('rating')
            ->groupBy('rating')
            ->map(fn($comics) => $comics->count());
            
        $purchases = $this->library()
            ->whereNotNull('purchased_at')
            ->where('purchased_at', '>=', now()->subYear())
            ->get();
            
        $monthlyPurchases = [];
        foreach ($purchases as $purchase) {
            $month = $purchase->purchased_at->format('Y-m');
            $monthlyPurchases[$month] = ($monthlyPurchases[$month] ?? 0) + 1;
        }
        ksort($monthlyPurchases);

        return [
            'genre_distribution' => $genreDistribution,
            'publisher_distribution' => $publisherDistribution,
            'rating_distribution' => $ratingDistribution,
            'monthly_purchases' => $monthlyPurchases,
            'total_spent' => $this->library()->sum('purchase_price'),
            'average_comic_price' => $this->library()->whereNotNull('purchase_price')->avg('purchase_price'),
            'most_expensive_comic' => $this->library()->orderByDesc('purchase_price')->first(),
            'library_growth_rate' => $this->getLibraryGrowthRate(),
        ];
    }

    private function getFavoriteGenres(int $limit = 5): array
    {
        return $this->library()
            ->join('comics', 'user_libraries.comic_id', '=', 'comics.id')
            ->selectRaw('comics.genre, COUNT(*) as count, AVG(user_libraries.rating) as avg_rating')
            ->whereNotNull('comics.genre')
            ->groupBy('comics.genre')
            ->orderByDesc('count')
            ->orderByDesc('avg_rating')
            ->limit($limit)
            ->get()
            ->map(fn($item) => [
                'genre' => $item->genre,
                'count' => $item->count,
                'average_rating' => round($item->avg_rating, 2)
            ])
            ->toArray();
    }

    public function getCurrentReadingStreak(): int
    {
        $streak = 0;
        $currentDate = now()->startOfDay();
        
        while (true) {
            $hasReadingActivity = $this->library()
                ->whereDate('last_accessed_at', $currentDate)
                ->exists();
                
            if (!$hasReadingActivity) {
                break;
            }
            
            $streak++;
            $currentDate->subDay();
        }
        
        return $streak;
    }

    private function getMonthlyReadingProgress(): array
    {
        $currentMonth = now()->startOfMonth();
        $completedThisMonth = $this->library()
            ->where('completion_percentage', 100)
            ->where('updated_at', '>=', $currentMonth)
            ->count();
            
        // Assume a goal of 5 comics per month (could be user-configurable)
        $monthlyGoal = 5;
        
        return [
            'completed' => $completedThisMonth,
            'goal' => $monthlyGoal,
            'percentage' => min(100, ($completedThisMonth / $monthlyGoal) * 100),
        ];
    }

    private function getMostActiveReadingDay(): string
    {
        $entries = $this->library()
            ->whereNotNull('last_accessed_at')
            ->where('last_accessed_at', '>=', now()->subMonths(3))
            ->get();
            
        if ($entries->isEmpty()) {
            return 'No data';
        }
        
        $dayCount = [];
        foreach ($entries as $entry) {
            $dayName = $entry->last_accessed_at->format('l'); // Full day name
            $dayCount[$dayName] = ($dayCount[$dayName] ?? 0) + 1;
        }
        
        arsort($dayCount);
        return array_key_first($dayCount) ?? 'No data';
    }

    private function getReadingVelocity(): array
    {
        $recentComics = $this->library()
            ->where('completion_percentage', 100)
            ->where('updated_at', '>=', now()->subMonth())
            ->get();
            
        if ($recentComics->isEmpty()) {
            return ['comics_per_week' => 0, 'pages_per_day' => 0];
        }
        
        $comicsPerWeek = $recentComics->count() * (7 / 30); // Approximate weekly rate
        $totalPages = $recentComics->sum(fn($entry) => $entry->comic->page_count ?? 0);
        $pagesPerDay = $totalPages / 30;
        
        return [
            'comics_per_week' => round($comicsPerWeek, 1),
            'pages_per_day' => round($pagesPerDay, 1),
        ];
    }

    private function getAverageReadingSession(): int
    {
        $sessions = $this->library()
            ->whereNotNull('total_reading_time')
            ->where('total_reading_time', '>', 0)
            ->get();
            
        if ($sessions->isEmpty()) {
            return 0;
        }
        
        // Estimate sessions based on reading patterns
        $totalTime = $sessions->sum('total_reading_time');
        $estimatedSessions = $sessions->count() * 3; // Assume 3 sessions per comic on average
        
        return $estimatedSessions > 0 ? round($totalTime / $estimatedSessions) : 0;
    }

    private function getLibraryGrowthRate(): float
    {
        $thisMonth = $this->library()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
            
        $lastMonth = $this->library()
            ->whereBetween('created_at', [
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth()
            ])
            ->count();
            
        if ($lastMonth === 0) {
            return $thisMonth > 0 ? 100 : 0;
        }
        
        return round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1);
    }

    private function formatReadingTime(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        }
        
        $minutes = floor($seconds / 60);
        $hours = floor($minutes / 60);
        $days = floor($hours / 24);
        
        if ($days > 0) {
            $remainingHours = $hours % 24;
            return $days . 'd ' . $remainingHours . 'h';
        }
        
        if ($hours > 0) {
            $remainingMinutes = $minutes % 60;
            return $hours . 'h ' . $remainingMinutes . 'm';
        }
        
        return $minutes . ' minutes';
    }

    public function getRecommendations(int $limit = 10): Collection
    {
        // Get user's favorite genres and authors
        $favoriteGenres = $this->library()
            ->join('comics', 'user_libraries.comic_id', '=', 'comics.id')
            ->whereNotNull('comics.genre')
            ->where('user_libraries.rating', '>=', 4)
            ->pluck('comics.genre')
            ->unique()
            ->take(3);

        $favoriteAuthors = $this->library()
            ->join('comics', 'user_libraries.comic_id', '=', 'comics.id')
            ->whereNotNull('comics.author')
            ->where('user_libraries.rating', '>=', 4)
            ->pluck('comics.author')
            ->unique()
            ->take(3);

        // Get comics user hasn't read yet
        $ownedComicIds = $this->library()->pluck('comic_id');

        return Comic::whereNotIn('id', $ownedComicIds)
            ->where('is_visible', true)
            ->where(function ($query) use ($favoriteGenres, $favoriteAuthors) {
                if ($favoriteGenres->isNotEmpty()) {
                    $query->whereIn('genre', $favoriteGenres);
                }
                if ($favoriteAuthors->isNotEmpty()) {
                    $query->orWhereIn('author', $favoriteAuthors);
                }
            })
            ->orderByDesc('average_rating')
            ->orderByDesc('total_readers')
            ->limit($limit)
            ->get();
    }

    // Subscription methods
    public function hasActiveSubscription(): bool
    {
        return $this->subscription_status === 'active' 
            && $this->subscription_expires_at 
            && $this->subscription_expires_at->isFuture();
    }

    public function hasExpiredSubscription(): bool
    {
        return $this->subscription_status === 'expired' 
            || ($this->subscription_expires_at && $this->subscription_expires_at->isPast());
    }

    public function getSubscriptionDaysRemaining(): int
    {
        if (!$this->hasActiveSubscription()) {
            return 0;
        }

        return now()->diffInDays($this->subscription_expires_at, false);
    }

    public function hasAccessToComic(Comic $comic): bool
    {
        if ($comic->is_free) {
            return true;
        }

        // Check subscription access
        if ($this->hasActiveSubscription()) {
            return true;
        }

        // Check individual purchase
        $libraryEntry = $this->library()->where('comic_id', $comic->id)->first();
        return $libraryEntry && $libraryEntry->hasAccess();
    }

    public function getSubscriptionDisplayName(): ?string
    {
        if (!$this->subscription_type) {
            return null;
        }

        return match($this->subscription_type) {
            'monthly' => 'Monthly Subscription',
            'yearly' => 'Annual Subscription',
            default => 'Subscription',
        };
    }

    /**
     * Get detailed reading habits analysis
     */
    public function getReadingHabitsAnalysis(): array
    {
        $library = $this->library()->with('comic')->get();
        
        if ($library->isEmpty()) {
            return [
                'reading_patterns' => [],
                'genre_preferences' => [],
                'reading_consistency' => 0,
                'average_session_length' => 0,
                'preferred_reading_times' => [],
                'completion_trends' => [],
            ];
        }

        return [
            'reading_patterns' => $this->analyzeReadingPatterns($library),
            'genre_preferences' => $this->analyzeGenrePreferences($library),
            'reading_consistency' => $this->calculateReadingConsistency($library),
            'average_session_length' => $this->calculateAverageSessionLength($library),
            'preferred_reading_times' => $this->analyzePreferredReadingTimes($library),
            'completion_trends' => $this->analyzeCompletionTrends($library),
        ];
    }

    /**
     * Get library health metrics
     */
    public function getLibraryHealthMetrics(): array
    {
        $library = $this->library()->get();
        $totalComics = $library->count();
        
        if ($totalComics === 0) {
            return [
                'health_score' => 0,
                'unread_percentage' => 0,
                'stale_comics_count' => 0,
                'review_coverage' => 0,
                'engagement_score' => 0,
                'recommendations' => [],
            ];
        }

        $unreadCount = $library->where('completion_percentage', 0)->count();
        $staleComics = $library->where('last_accessed_at', '<', now()->subMonths(3))->count();
        $reviewedComics = $library->whereNotNull('review')->count();
        
        $healthScore = $this->calculateLibraryHealthScore($library);
        $engagementScore = $this->calculateEngagementScore($library);

        return [
            'health_score' => $healthScore,
            'unread_percentage' => round(($unreadCount / $totalComics) * 100, 1),
            'stale_comics_count' => $staleComics,
            'review_coverage' => round(($reviewedComics / $totalComics) * 100, 1),
            'engagement_score' => $engagementScore,
            'recommendations' => $this->generateLibraryRecommendations($library),
        ];
    }

    /**
     * Get personalized reading goals and progress
     */
    public function getReadingGoals(): array
    {
        $currentMonth = now()->startOfMonth();
        $currentYear = now()->startOfYear();
        
        $monthlyCompleted = $this->library()
            ->where('completion_percentage', 100)
            ->where('updated_at', '>=', $currentMonth)
            ->count();
            
        $yearlyCompleted = $this->library()
            ->where('completion_percentage', 100)
            ->where('updated_at', '>=', $currentYear)
            ->count();

        // Default goals (could be user-configurable in the future)
        $monthlyGoal = 5;
        $yearlyGoal = 50;

        return [
            'monthly' => [
                'goal' => $monthlyGoal,
                'completed' => $monthlyCompleted,
                'percentage' => min(100, round(($monthlyCompleted / $monthlyGoal) * 100, 1)),
                'remaining_days' => now()->daysInMonth - now()->day + 1,
                'daily_target' => max(0, round(($monthlyGoal - $monthlyCompleted) / (now()->daysInMonth - now()->day + 1), 1)),
            ],
            'yearly' => [
                'goal' => $yearlyGoal,
                'completed' => $yearlyCompleted,
                'percentage' => min(100, round(($yearlyCompleted / $yearlyGoal) * 100, 1)),
                'remaining_days' => now()->dayOfYear - now()->dayOfYear + 365,
                'monthly_target' => max(0, round(($yearlyGoal - $yearlyCompleted) / (12 - now()->month + 1), 1)),
            ],
            'streak' => $this->getCurrentReadingStreak(),
            'longest_streak' => $this->getLongestReadingStreak(),
        ];
    }

    private function analyzeReadingPatterns(Collection $library): array
    {
        $patterns = [];
        
        // Analyze reading frequency by day of week
        $dayPatterns = [];
        foreach ($library as $entry) {
            if ($entry->last_accessed_at) {
                $day = $entry->last_accessed_at->format('l');
                $dayPatterns[$day] = ($dayPatterns[$day] ?? 0) + 1;
            }
        }
        arsort($dayPatterns);
        
        // Analyze reading session lengths
        $sessionLengths = $library->where('total_reading_time', '>', 0)
            ->pluck('total_reading_time')
            ->map(fn($time) => round($time / 60)) // Convert to minutes
            ->groupBy(function ($minutes) {
                if ($minutes < 15) return 'short';
                if ($minutes < 45) return 'medium';
                return 'long';
            })
            ->map(fn($group) => $group->count());

        return [
            'most_active_days' => array_slice($dayPatterns, 0, 3, true),
            'session_length_distribution' => $sessionLengths->toArray(),
            'average_completion_time' => $this->calculateAverageCompletionTime($library),
        ];
    }

    private function analyzeGenrePreferences(Collection $library): array
    {
        $genreStats = [];
        
        foreach ($library as $entry) {
            $genre = $entry->comic->genre ?? 'Unknown';
            
            if (!isset($genreStats[$genre])) {
                $genreStats[$genre] = [
                    'count' => 0,
                    'total_rating' => 0,
                    'rated_count' => 0,
                    'completion_rate' => 0,
                    'total_time' => 0,
                ];
            }
            
            $genreStats[$genre]['count']++;
            
            if ($entry->rating) {
                $genreStats[$genre]['total_rating'] += $entry->rating;
                $genreStats[$genre]['rated_count']++;
            }
            
            if ($entry->completion_percentage >= 100) {
                $genreStats[$genre]['completion_rate']++;
            }
            
            $genreStats[$genre]['total_time'] += $entry->total_reading_time ?? 0;
        }

        // Calculate averages and sort by preference score
        foreach ($genreStats as $genre => &$stats) {
            $stats['average_rating'] = $stats['rated_count'] > 0 
                ? round($stats['total_rating'] / $stats['rated_count'], 2) 
                : 0;
            $stats['completion_percentage'] = round(($stats['completion_rate'] / $stats['count']) * 100, 1);
            $stats['average_time'] = round($stats['total_time'] / $stats['count'] / 60); // minutes
            
            // Preference score based on rating, completion rate, and time spent
            $stats['preference_score'] = ($stats['average_rating'] * 0.4) + 
                                       ($stats['completion_percentage'] * 0.004) + 
                                       (min($stats['average_time'] / 30, 2) * 0.2);
        }

        uasort($genreStats, fn($a, $b) => $b['preference_score'] <=> $a['preference_score']);

        return array_slice($genreStats, 0, 5, true);
    }

    private function calculateReadingConsistency(Collection $library): float
    {
        $readingDays = $library->whereNotNull('last_accessed_at')
            ->pluck('last_accessed_at')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->unique()
            ->count();
            
        $totalDays = max(1, now()->diffInDays($library->min('created_at') ?? now()));
        
        return round(($readingDays / $totalDays) * 100, 1);
    }

    private function calculateAverageSessionLength(Collection $library): int
    {
        $sessions = $library->where('total_reading_time', '>', 0);
        
        if ($sessions->isEmpty()) {
            return 0;
        }
        
        // Estimate number of sessions (rough approximation)
        $totalSessions = $sessions->sum(fn($entry) => max(1, $entry->completion_percentage / 25));
        $totalTime = $sessions->sum('total_reading_time');
        
        return $totalSessions > 0 ? round($totalTime / $totalSessions / 60) : 0; // minutes
    }

    private function analyzePreferredReadingTimes(Collection $library): array
    {
        $timeSlots = [
            'morning' => 0,   // 6-12
            'afternoon' => 0, // 12-18
            'evening' => 0,   // 18-22
            'night' => 0,     // 22-6
        ];

        foreach ($library as $entry) {
            if ($entry->last_accessed_at) {
                $hour = $entry->last_accessed_at->hour;
                
                if ($hour >= 6 && $hour < 12) {
                    $timeSlots['morning']++;
                } elseif ($hour >= 12 && $hour < 18) {
                    $timeSlots['afternoon']++;
                } elseif ($hour >= 18 && $hour < 22) {
                    $timeSlots['evening']++;
                } else {
                    $timeSlots['night']++;
                }
            }
        }

        arsort($timeSlots);
        return $timeSlots;
    }

    private function analyzeCompletionTrends(Collection $library): array
    {
        $monthlyTrends = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthKey = $month->format('Y-m');
            
            $completed = $library->where('completion_percentage', 100)
                ->where('updated_at', '>=', $month->startOfMonth())
                ->where('updated_at', '<=', $month->endOfMonth())
                ->count();
                
            $monthlyTrends[$monthKey] = $completed;
        }

        return $monthlyTrends;
    }

    private function calculateAverageCompletionTime(Collection $library): float
    {
        $completedComics = $library->where('completion_percentage', 100);
        
        if ($completedComics->isEmpty()) {
            return 0;
        }

        $totalTime = 0;
        $count = 0;

        foreach ($completedComics as $entry) {
            if ($entry->created_at && $entry->updated_at) {
                $completionDays = $entry->created_at->diffInDays($entry->updated_at);
                $totalTime += $completionDays;
                $count++;
            }
        }

        return $count > 0 ? round($totalTime / $count, 1) : 0;
    }

    private function calculateLibraryHealthScore(Collection $library): float
    {
        $totalComics = $library->count();
        $completedComics = $library->where('completion_percentage', 100)->count();
        $recentlyAccessedComics = $library->where('last_accessed_at', '>=', now()->subMonth())->count();
        $reviewedComics = $library->whereNotNull('review')->count();
        
        $completionScore = ($completedComics / $totalComics) * 30;
        $activityScore = ($recentlyAccessedComics / $totalComics) * 40;
        $engagementScore = ($reviewedComics / $totalComics) * 30;
        
        return round($completionScore + $activityScore + $engagementScore, 1);
    }

    private function calculateEngagementScore(Collection $library): float
    {
        $totalComics = $library->count();
        $ratedComics = $library->whereNotNull('rating')->count();
        $favoriteComics = $library->where('is_favorite', true)->count();
        $averageRating = $library->whereNotNull('rating')->avg('rating') ?? 0;
        
        $ratingScore = ($ratedComics / $totalComics) * 40;
        $favoriteScore = ($favoriteComics / $totalComics) * 30;
        $qualityScore = ($averageRating / 5) * 30;
        
        return round($ratingScore + $favoriteScore + $qualityScore, 1);
    }

    private function generateLibraryRecommendations(Collection $library): array
    {
        $recommendations = [];
        
        $unreadCount = $library->where('completion_percentage', 0)->count();
        $staleCount = $library->where('last_accessed_at', '<', now()->subMonths(3))->count();
        $unratedCount = $library->whereNull('rating')->count();
        
        if ($unreadCount > 10) {
            $recommendations[] = [
                'type' => 'reduce_backlog',
                'message' => "You have {$unreadCount} unread comics. Consider focusing on completing some before adding more.",
                'priority' => 'high',
            ];
        }
        
        if ($staleCount > 5) {
            $recommendations[] = [
                'type' => 'revisit_old',
                'message' => "You have {$staleCount} comics you haven't read in 3+ months. Maybe it's time to revisit them?",
                'priority' => 'medium',
            ];
        }
        
        if ($unratedCount > 5) {
            $recommendations[] = [
                'type' => 'add_ratings',
                'message' => "Consider rating {$unratedCount} comics to get better recommendations.",
                'priority' => 'low',
            ];
        }

        return $recommendations;
    }

    private function getLongestReadingStreak(): int
    {
        $libraryEntries = $this->library()
            ->whereNotNull('last_accessed_at')
            ->orderBy('last_accessed_at')
            ->pluck('last_accessed_at')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->unique()
            ->values();

        if ($libraryEntries->isEmpty()) {
            return 0;
        }

        $longestStreak = 0;
        $currentStreak = 1;
        
        for ($i = 1; $i < $libraryEntries->count(); $i++) {
            $currentDate = Carbon::parse($libraryEntries[$i]);
            $previousDate = Carbon::parse($libraryEntries[$i - 1]);
            
            if ($currentDate->diffInDays($previousDate) === 1) {
                $currentStreak++;
            } else {
                $longestStreak = max($longestStreak, $currentStreak);
                $currentStreak = 1;
            }
        }
        
        return max($longestStreak, $currentStreak);
    }

    /**
     * Get total reading time in minutes from UserComicProgress
     */
    public function getTotalReadingTimeMinutes(): int
    {
        return $this->comicProgress()->sum('reading_time_minutes') ?? 0;
    }

    /**
     * Determine if the user can access Filament admin panel
     */
    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        // Check if user is marked as admin
        if ($this->is_admin) {
            return true;
        }
        
        // For production, also allow specific email addresses from environment variable
        if (app()->environment('production')) {
            $adminEmails = explode(',', env('ADMIN_EMAILS', ''));
            return in_array($this->email, $adminEmails);
        }
        
        return false;
    }
}
