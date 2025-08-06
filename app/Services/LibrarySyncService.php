<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserLibrary;
use App\Models\UserComicProgress;
use App\Models\ComicBookmark;
use App\Models\UserPreferences;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LibrarySyncService
{
    /**
     * Sync user library data across devices
     */
    public function syncUserLibrary(User $user, array $syncData, string $deviceId): array
    {
        DB::beginTransaction();
        
        try {
            $syncResult = [
                'library_updates' => 0,
                'progress_updates' => 0,
                'bookmark_updates' => 0,
                'preference_updates' => 0,
                'conflicts_resolved' => 0,
                'last_sync' => now(),
            ];

            // Sync library entries
            if (isset($syncData['library'])) {
                $syncResult['library_updates'] = $this->syncLibraryEntries($user, $syncData['library']);
            }

            // Sync reading progress
            if (isset($syncData['progress'])) {
                $syncResult['progress_updates'] = $this->syncReadingProgress($user, $syncData['progress']);
            }

            // Sync bookmarks
            if (isset($syncData['bookmarks'])) {
                $syncResult['bookmark_updates'] = $this->syncBookmarks($user, $syncData['bookmarks']);
            }

            // Sync preferences
            if (isset($syncData['preferences'])) {
                $syncResult['preference_updates'] = $this->syncPreferences($user, $syncData['preferences']);
            }

            // Generate new sync token for the user
            $syncToken = $this->generateSyncToken($user, $deviceId);
            $syncResult['sync_token'] = $syncToken;

            DB::commit();
            
            Log::info('Library sync completed', [
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'result' => $syncResult
            ]);

            return $syncResult;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Library sync failed', [
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get user's library data for sync
     */
    public function getUserSyncData(User $user, ?Carbon $lastSync = null): array
    {
        $query = $user->library()->with(['comic', 'progress']);
        
        if ($lastSync) {
            $query->where('updated_at', '>', $lastSync);
        }

        $libraryEntries = $query->get();

        $progressQuery = $user->comicProgress();
        if ($lastSync) {
            $progressQuery->where('updated_at', '>', $lastSync);
        }
        $progressEntries = $progressQuery->get();

        $bookmarkQuery = $user->bookmarks();
        if ($lastSync) {
            $bookmarkQuery->where('updated_at', '>', $lastSync);
        }
        $bookmarks = $bookmarkQuery->get();

        $preferences = $user->preferences;

        return [
            'library' => $libraryEntries->map(function ($entry) {
                return [
                    'comic_id' => $entry->comic_id,
                    'access_type' => $entry->access_type,
                    'is_favorite' => $entry->is_favorite,
                    'rating' => $entry->rating,
                    'review' => $entry->review,
                    'last_accessed_at' => $entry->last_accessed_at,
                    'total_reading_time' => $entry->total_reading_time,
                    'completion_percentage' => $entry->completion_percentage,
                    'updated_at' => $entry->updated_at,
                ];
            }),
            'progress' => $progressEntries->map(function ($progress) {
                return [
                    'comic_id' => $progress->comic_id,
                    'current_page' => $progress->current_page,
                    'total_pages' => $progress->total_pages,
                    'reading_time_minutes' => $progress->reading_time_minutes,
                    'is_completed' => $progress->is_completed,
                    'last_read_at' => $progress->last_read_at,
                    'updated_at' => $progress->updated_at,
                ];
            }),
            'bookmarks' => $bookmarks->map(function ($bookmark) {
                return [
                    'comic_id' => $bookmark->comic_id,
                    'page_number' => $bookmark->page_number,
                    'note' => $bookmark->note,
                    'created_at' => $bookmark->created_at,
                    'updated_at' => $bookmark->updated_at,
                ];
            }),
            'preferences' => $preferences ? [
                'reading_view_mode' => $preferences->reading_view_mode,
                'reading_direction' => $preferences->reading_direction,
                'reading_zoom_level' => $preferences->reading_zoom_level,
                'auto_hide_controls' => $preferences->auto_hide_controls,
                'control_hide_delay' => $preferences->control_hide_delay,
                'theme' => $preferences->theme,
                'reduce_motion' => $preferences->reduce_motion,
                'high_contrast' => $preferences->high_contrast,
                'updated_at' => $preferences->updated_at,
            ] : null,
            'sync_timestamp' => now(),
        ];
    }

    /**
     * Sync library entries with conflict resolution
     */
    private function syncLibraryEntries(User $user, array $libraryData): int
    {
        $updates = 0;

        foreach ($libraryData as $entryData) {
            $existingEntry = $user->library()
                ->where('comic_id', $entryData['comic_id'])
                ->first();

            if (!$existingEntry) {
                // Create new entry
                $user->library()->create($entryData);
                $updates++;
            } else {
                // Resolve conflicts using last-write-wins strategy
                $incomingTimestamp = Carbon::parse($entryData['updated_at']);
                $existingTimestamp = $existingEntry->updated_at;

                if ($incomingTimestamp->gt($existingTimestamp)) {
                    $existingEntry->update($entryData);
                    $updates++;
                }
            }
        }

        return $updates;
    }

    /**
     * Sync reading progress with conflict resolution
     */
    private function syncReadingProgress(User $user, array $progressData): int
    {
        $updates = 0;

        foreach ($progressData as $progressEntry) {
            $existingProgress = $user->comicProgress()
                ->where('comic_id', $progressEntry['comic_id'])
                ->first();

            if (!$existingProgress) {
                // Create new progress entry
                $user->comicProgress()->create(array_merge($progressEntry, ['user_id' => $user->id]));
                $updates++;
            } else {
                // Use the progress with the highest page number (most recent)
                $incomingPage = $progressEntry['current_page'] ?? 0;
                $existingPage = $existingProgress->current_page ?? 0;

                if ($incomingPage > $existingPage) {
                    $existingProgress->update($progressEntry);
                    $updates++;
                } elseif ($incomingPage === $existingPage) {
                    // Same page, use latest timestamp
                    $incomingTimestamp = Carbon::parse($progressEntry['updated_at']);
                    if ($incomingTimestamp->gt($existingProgress->updated_at)) {
                        $existingProgress->update($progressEntry);
                        $updates++;
                    }
                }
            }
        }

        return $updates;
    }

    /**
     * Sync bookmarks
     */
    private function syncBookmarks(User $user, array $bookmarkData): int
    {
        $updates = 0;

        foreach ($bookmarkData as $bookmarkEntry) {
            $existingBookmark = $user->bookmarks()
                ->where('comic_id', $bookmarkEntry['comic_id'])
                ->where('page_number', $bookmarkEntry['page_number'])
                ->first();

            if (!$existingBookmark) {
                // Create new bookmark
                $user->bookmarks()->create(array_merge($bookmarkEntry, ['user_id' => $user->id]));
                $updates++;
            } else {
                // Update if incoming is newer
                $incomingTimestamp = Carbon::parse($bookmarkEntry['updated_at']);
                if ($incomingTimestamp->gt($existingBookmark->updated_at)) {
                    $existingBookmark->update($bookmarkEntry);
                    $updates++;
                }
            }
        }

        return $updates;
    }

    /**
     * Sync user preferences
     */
    private function syncPreferences(User $user, array $preferencesData): int
    {
        $preferences = $user->preferences;
        
        if (!$preferences) {
            $user->preferences()->create(array_merge($preferencesData, ['user_id' => $user->id]));
            return 1;
        }

        $incomingTimestamp = Carbon::parse($preferencesData['updated_at']);
        if ($incomingTimestamp->gt($preferences->updated_at)) {
            $preferences->update($preferencesData);
            return 1;
        }

        return 0;
    }

    /**
     * Generate a sync token for device identification
     */
    private function generateSyncToken(User $user, string $deviceId): string
    {
        return hash('sha256', $user->id . $deviceId . now()->timestamp . random_bytes(16));
    }

    /**
     * Check if sync is needed based on last sync time
     */
    public function needsSync(User $user, ?Carbon $lastSync = null): bool
    {
        if (!$lastSync) {
            return true;
        }

        // Check if any data has been updated since last sync
        $hasLibraryUpdates = $user->library()
            ->where('updated_at', '>', $lastSync)
            ->exists();

        $hasProgressUpdates = $user->comicProgress()
            ->where('updated_at', '>', $lastSync)
            ->exists();

        $hasBookmarkUpdates = $user->bookmarks()
            ->where('updated_at', '>', $lastSync)
            ->exists();

        $hasPreferenceUpdates = $user->preferences 
            && $user->preferences->updated_at->gt($lastSync);

        return $hasLibraryUpdates || $hasProgressUpdates || $hasBookmarkUpdates || $hasPreferenceUpdates;
    }

    /**
     * Get sync conflicts for manual resolution
     */
    public function getSyncConflicts(User $user, array $incomingData): array
    {
        $conflicts = [];

        // Check for library conflicts
        if (isset($incomingData['library'])) {
            foreach ($incomingData['library'] as $entry) {
                $existing = $user->library()
                    ->where('comic_id', $entry['comic_id'])
                    ->first();

                if ($existing) {
                    $incomingTime = Carbon::parse($entry['updated_at']);
                    $existingTime = $existing->updated_at;

                    // If timestamps are very close (within 1 minute) but data differs
                    if (abs($incomingTime->diffInSeconds($existingTime)) < 60) {
                        if ($this->hasDataDifferences($existing->toArray(), $entry)) {
                            $conflicts[] = [
                                'type' => 'library',
                                'comic_id' => $entry['comic_id'],
                                'local' => $existing->toArray(),
                                'remote' => $entry,
                            ];
                        }
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * Check if two data arrays have meaningful differences
     */
    private function hasDataDifferences(array $local, array $remote): bool
    {
        $significantFields = ['rating', 'is_favorite', 'completion_percentage', 'current_page'];
        
        foreach ($significantFields as $field) {
            if (isset($local[$field]) && isset($remote[$field])) {
                if ($local[$field] != $remote[$field]) {
                    return true;
                }
            }
        }

        return false;
    }
}