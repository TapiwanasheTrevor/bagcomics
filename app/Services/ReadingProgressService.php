<?php

namespace App\Services;

use App\Models\User;
use App\Models\Comic;
use App\Models\UserComicProgress;
use App\Models\ComicBookmark;
use Illuminate\Database\Eloquent\Collection;

class ReadingProgressService
{
    /**
     * Update reading progress for a user and comic
     */
    public function updateProgress(
        User $user, 
        Comic $comic, 
        int $currentPage, 
        ?array $metadata = null
    ): UserComicProgress {
        $progress = UserComicProgress::firstOrCreate(
            ['user_id' => $user->id, 'comic_id' => $comic->id],
            [
                'current_page' => 1,
                'total_pages' => $comic->page_count ?? 0,
                'progress_percentage' => 0,
                'reading_time_minutes' => 0,
            ]
        );

        // Update basic progress
        $progress->updateProgress($currentPage, $comic->page_count);

        // Update metadata if provided
        if ($metadata) {
            $currentMetadata = $progress->reading_metadata ?? [];
            $progress->reading_metadata = array_merge($currentMetadata, $metadata);
            $progress->save();
        }

        return $progress;
    }

    /**
     * Start a reading session
     */
    public function startReadingSession(
        User $user, 
        Comic $comic, 
        array $metadata = []
    ): UserComicProgress {
        $progress = $this->getOrCreateProgress($user, $comic);
        $progress->startReadingSession($metadata);
        
        return $progress;
    }

    /**
     * End a reading session
     */
    public function endReadingSession(
        User $user, 
        Comic $comic, 
        int $endPage, 
        array $metadata = []
    ): UserComicProgress {
        $progress = $this->getOrCreateProgress($user, $comic);
        $progress->endReadingSession($endPage, $metadata);
        
        // Update current page if it's further than before
        if ($endPage > $progress->current_page) {
            $progress = $this->updateProgress($user, $comic, $endPage);
        }
        
        return $progress;
    }

    /**
     * Add pause time to current session
     */
    public function addPauseTime(User $user, Comic $comic, int $pauseMinutes): void
    {
        $progress = $this->getProgress($user, $comic);
        if ($progress) {
            $progress->addPauseTime($pauseMinutes);
        }
    }

    /**
     * Get reading progress for a user and comic
     */
    public function getProgress(User $user, Comic $comic): ?UserComicProgress
    {
        return UserComicProgress::where('user_id', $user->id)
            ->where('comic_id', $comic->id)
            ->first();
    }

    /**
     * Get or create reading progress
     */
    public function getOrCreateProgress(User $user, Comic $comic): UserComicProgress
    {
        return UserComicProgress::firstOrCreate(
            ['user_id' => $user->id, 'comic_id' => $comic->id],
            [
                'current_page' => 1,
                'total_pages' => $comic->page_count ?? 0,
                'progress_percentage' => 0,
                'reading_time_minutes' => 0,
            ]
        );
    }

    /**
     * Add a bookmark
     */
    public function addBookmark(
        User $user, 
        Comic $comic, 
        int $page, 
        ?string $note = null
    ): ComicBookmark {
        // Check if bookmark already exists for this page
        $existingBookmark = ComicBookmark::where('user_id', $user->id)
            ->where('comic_id', $comic->id)
            ->where('page_number', $page)
            ->first();

        if ($existingBookmark) {
            if ($note) {
                $existingBookmark->updateNote($note);
            }
            return $existingBookmark;
        }

        // Create new bookmark
        $bookmark = ComicBookmark::createBookmark($user, $comic, $page, $note);
        
        // Sync with progress
        $bookmark->syncWithProgress();
        
        return $bookmark;
    }

    /**
     * Remove a bookmark
     */
    public function removeBookmark(User $user, Comic $comic, int $page): bool
    {
        $removed = ComicBookmark::removeBookmarkForPage($user, $comic, $page);
        
        if ($removed) {
            // Update progress bookmark count
            $progress = $this->getProgress($user, $comic);
            if ($progress) {
                $progress->bookmark_count = ComicBookmark::where('user_id', $user->id)
                    ->where('comic_id', $comic->id)
                    ->count();
                
                if ($progress->bookmark_count === 0) {
                    $progress->is_bookmarked = false;
                    $progress->last_bookmark_at = null;
                }
                
                $progress->save();
            }
        }
        
        return $removed;
    }

    /**
     * Get bookmarks for a user and comic
     */
    public function getBookmarks(User $user, Comic $comic): Collection
    {
        return ComicBookmark::getBookmarksForUserComic($user, $comic);
    }

    /**
     * Update reading preferences
     */
    public function updateReadingPreferences(
        User $user, 
        Comic $comic, 
        array $preferences
    ): UserComicProgress {
        $progress = $this->getOrCreateProgress($user, $comic);
        $progress->updateReadingPreferences($preferences);
        
        return $progress;
    }

    /**
     * Get reading statistics for a user and comic
     */
    public function getReadingStatistics(User $user, Comic $comic): array
    {
        $progress = $this->getProgress($user, $comic);
        
        if (!$progress) {
            return [
                'total_sessions' => 0,
                'total_reading_time_minutes' => 0,
                'average_session_duration' => 0,
                'pages_per_session_avg' => 0,
                'reading_speed_pages_per_minute' => 0,
                'total_time_paused_minutes' => 0,
                'bookmark_count' => 0,
                'progress_percentage' => 0,
                'is_completed' => false,
                'first_read_at' => null,
                'last_read_at' => null,
                'last_bookmark_at' => null,
                'completed_sessions' => 0,
                'active_sessions' => 0,
            ];
        }

        return $progress->getReadingStatistics();
    }

    /**
     * Get user's overall reading statistics
     */
    public function getUserReadingStatistics(User $user): array
    {
        $allProgress = UserComicProgress::where('user_id', $user->id)->get();
        
        $totalReadingTime = $allProgress->sum('reading_time_minutes');
        $totalSessions = $allProgress->sum('total_reading_sessions');
        $completedComics = $allProgress->where('is_completed', true)->count();
        $totalBookmarks = ComicBookmark::getBookmarkCountForUser($user);
        
        $averageSessionDuration = $totalSessions > 0 
            ? $allProgress->avg('average_session_duration') 
            : 0;
            
        $averageReadingSpeed = $allProgress->where('reading_speed_pages_per_minute', '>', 0)
            ->avg('reading_speed_pages_per_minute');

        return [
            'total_comics_started' => $allProgress->count(),
            'total_comics_completed' => $completedComics,
            'completion_rate' => $allProgress->count() > 0 
                ? ($completedComics / $allProgress->count()) * 100 
                : 0,
            'total_reading_time_minutes' => $totalReadingTime,
            'total_reading_sessions' => $totalSessions,
            'average_session_duration' => round($averageSessionDuration, 2),
            'average_reading_speed_pages_per_minute' => round($averageReadingSpeed ?? 0, 2),
            'total_bookmarks' => $totalBookmarks,
            'total_pages_read' => $allProgress->sum('current_page'),
            'average_progress_percentage' => round($allProgress->avg('progress_percentage'), 2),
        ];
    }

    /**
     * Synchronize bookmarks between UserComicProgress and ComicBookmark models
     */
    public function synchronizeBookmarks(User $user, Comic $comic): void
    {
        $progress = $this->getProgress($user, $comic);
        $bookmarks = $this->getBookmarks($user, $comic);
        
        if ($progress) {
            $progress->bookmark_count = $bookmarks->count();
            $progress->is_bookmarked = $bookmarks->count() > 0;
            $progress->last_bookmark_at = $bookmarks->count() > 0 
                ? $bookmarks->max('created_at') 
                : null;
            $progress->save();
        }
    }
}