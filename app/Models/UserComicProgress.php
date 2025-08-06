<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserComicProgress extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'comic_id',
        'current_page',
        'total_pages',
        'progress_percentage',
        'is_completed',
        'is_bookmarked',
        'reading_time_minutes',
        'last_read_at',
        'completed_at',
        'bookmarks',
        'reading_sessions',
        'total_reading_sessions',
        'first_read_at',
        'reading_metadata',
        'average_session_duration',
        'pages_per_session_avg',
        'reading_preferences',
        'reading_speed_pages_per_minute',
        'total_time_paused_minutes',
        'bookmark_count',
        'last_bookmark_at',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'is_bookmarked' => 'boolean',
        'last_read_at' => 'datetime',
        'completed_at' => 'datetime',
        'first_read_at' => 'datetime',
        'last_bookmark_at' => 'datetime',
        'bookmarks' => 'array',
        'reading_sessions' => 'array',
        'reading_metadata' => 'array',
        'reading_preferences' => 'array',
        'progress_percentage' => 'decimal:2',
        'average_session_duration' => 'decimal:2',
        'reading_speed_pages_per_minute' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }

    public function updateProgress(int $currentPage, int $totalPages = null): void
    {
        $this->current_page = $currentPage;

        if ($totalPages) {
            $this->total_pages = $totalPages;
        }

        if ($this->total_pages > 0) {
            $this->progress_percentage = ($currentPage / $this->total_pages) * 100;
            $this->is_completed = $currentPage >= $this->total_pages;

            if ($this->is_completed && !$this->completed_at) {
                $this->completed_at = now();
            }
        }

        $this->last_read_at = now();
        $this->save();
    }

    public function addBookmark(int $page, string $note = null): void
    {
        $bookmarks = $this->bookmarks ?? [];
        $bookmarks[] = [
            'page' => $page,
            'note' => $note,
            'created_at' => now()->toISOString(),
        ];

        $this->bookmarks = $bookmarks;
        $this->is_bookmarked = true;
        $this->bookmark_count = count($bookmarks);
        $this->last_bookmark_at = now();
        $this->save();
    }

    /**
     * Start a new reading session
     */
    public function startReadingSession(array $metadata = []): void
    {
        $sessions = $this->reading_sessions ?? [];
        $sessionId = uniqid();
        
        $sessions[$sessionId] = [
            'id' => $sessionId,
            'started_at' => now()->toISOString(),
            'ended_at' => null,
            'start_page' => $this->current_page,
            'end_page' => null,
            'pages_read' => 0,
            'duration_minutes' => 0,
            'paused_duration_minutes' => 0,
            'metadata' => $metadata,
            'is_active' => true,
        ];

        $this->reading_sessions = $sessions;
        
        if (!$this->first_read_at) {
            $this->first_read_at = now();
        }
        
        $this->save();
    }

    /**
     * End the current reading session
     */
    public function endReadingSession(int $endPage, array $metadata = []): void
    {
        $sessions = $this->reading_sessions ?? [];
        
        // Find the active session
        foreach ($sessions as $sessionId => $session) {
            if ($session['is_active'] ?? false) {
                $startTime = \Carbon\Carbon::parse($session['started_at']);
                $endTime = now();
                $durationMinutes = $startTime->diffInMinutes($endTime);
                
                $sessions[$sessionId] = array_merge($session, [
                    'ended_at' => $endTime->toISOString(),
                    'end_page' => $endPage,
                    'pages_read' => max(0, $endPage - $session['start_page']),
                    'duration_minutes' => $durationMinutes,
                    'metadata' => array_merge($session['metadata'] ?? [], $metadata),
                    'is_active' => false,
                ]);
                
                break;
            }
        }

        $this->reading_sessions = $sessions;
        $this->total_reading_sessions = count($sessions);
        $this->updateReadingAnalytics();
        $this->save();
    }

    /**
     * Update reading analytics based on sessions
     */
    public function updateReadingAnalytics(): void
    {
        $sessions = $this->reading_sessions ?? [];
        $completedSessions = array_filter($sessions, fn($s) => !($s['is_active'] ?? false));
        
        if (empty($completedSessions)) {
            return;
        }

        // Calculate average session duration
        $totalDuration = array_sum(array_column($completedSessions, 'duration_minutes'));
        $this->average_session_duration = $totalDuration / count($completedSessions);

        // Calculate average pages per session
        $totalPages = array_sum(array_column($completedSessions, 'pages_read'));
        $this->pages_per_session_avg = $totalPages / count($completedSessions);

        // Calculate reading speed (pages per minute)
        if ($totalDuration > 0) {
            $this->reading_speed_pages_per_minute = $totalPages / $totalDuration;
        }

        // Update total reading time
        $this->reading_time_minutes = $totalDuration;

        // Calculate total paused time
        $this->total_time_paused_minutes = array_sum(array_column($completedSessions, 'paused_duration_minutes'));
    }

    /**
     * Add pause time to current session
     */
    public function addPauseTime(int $pauseMinutes): void
    {
        $sessions = $this->reading_sessions ?? [];
        
        foreach ($sessions as $sessionId => $session) {
            if ($session['is_active'] ?? false) {
                $sessions[$sessionId]['paused_duration_minutes'] = 
                    ($session['paused_duration_minutes'] ?? 0) + $pauseMinutes;
                break;
            }
        }

        $this->reading_sessions = $sessions;
        $this->save();
    }

    /**
     * Update reading preferences
     */
    public function updateReadingPreferences(array $preferences): void
    {
        $currentPreferences = $this->reading_preferences ?? [];
        $this->reading_preferences = array_merge($currentPreferences, $preferences);
        $this->save();
    }

    /**
     * Get reading statistics
     */
    public function getReadingStatistics(): array
    {
        $sessions = $this->reading_sessions ?? [];
        $completedSessions = array_filter($sessions, fn($s) => !($s['is_active'] ?? false));

        return [
            'total_sessions' => $this->total_reading_sessions,
            'total_reading_time_minutes' => $this->reading_time_minutes,
            'average_session_duration' => $this->average_session_duration,
            'pages_per_session_avg' => $this->pages_per_session_avg,
            'reading_speed_pages_per_minute' => $this->reading_speed_pages_per_minute,
            'total_time_paused_minutes' => $this->total_time_paused_minutes,
            'bookmark_count' => $this->bookmark_count,
            'progress_percentage' => $this->progress_percentage,
            'is_completed' => $this->is_completed,
            'first_read_at' => $this->first_read_at,
            'last_read_at' => $this->last_read_at,
            'last_bookmark_at' => $this->last_bookmark_at,
            'completed_sessions' => count($completedSessions),
            'active_sessions' => count($sessions) - count($completedSessions),
        ];
    }

    /**
     * Get current active session
     */
    public function getCurrentSession(): ?array
    {
        $sessions = $this->reading_sessions ?? [];
        
        foreach ($sessions as $session) {
            if ($session['is_active'] ?? false) {
                return $session;
            }
        }

        return null;
    }

    /**
     * Check if user has an active reading session
     */
    public function hasActiveSession(): bool
    {
        return $this->getCurrentSession() !== null;
    }
}
