<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Comic;
use App\Models\UserLibrary;
use App\Models\UserComicProgress;
use App\Models\ComicBookmark;
use App\Models\UserPreferences;
use App\Services\LibrarySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class LibrarySyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private LibrarySyncService $syncService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->syncService = new LibrarySyncService();
        $this->user = User::factory()->create();
    }

    public function test_can_get_user_sync_data()
    {
        // Create test data
        $comic = Comic::factory()->create();
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic->id,
            'is_favorite' => true,
            'rating' => 5,
        ]);
        
        UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic->id,
            'current_page' => 10,
        ]);
        
        ComicBookmark::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic->id,
            'page_number' => 5,
        ]);
        
        UserPreferences::factory()->create([
            'user_id' => $this->user->id,
            'theme' => 'dark',
        ]);

        $syncData = $this->syncService->getUserSyncData($this->user);

        $this->assertArrayHasKey('library', $syncData);
        $this->assertArrayHasKey('progress', $syncData);
        $this->assertArrayHasKey('bookmarks', $syncData);
        $this->assertArrayHasKey('preferences', $syncData);
        $this->assertArrayHasKey('sync_timestamp', $syncData);
        
        $this->assertCount(1, $syncData['library']);
        $this->assertCount(1, $syncData['progress']);
        $this->assertCount(1, $syncData['bookmarks']);
        $this->assertNotNull($syncData['preferences']);
    }

    public function test_can_get_incremental_sync_data()
    {
        $comic = Comic::factory()->create();
        
        // Create old data
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic->id,
            'updated_at' => now()->subDays(2),
        ]);
        
        // Create new data
        $newComic = Comic::factory()->create();
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $newComic->id,
            'updated_at' => now(),
        ]);

        $lastSync = now()->subDay();
        $syncData = $this->syncService->getUserSyncData($this->user, $lastSync);

        $this->assertCount(1, $syncData['library']); // Only the new entry
        $this->assertEquals($newComic->id, $syncData['library'][0]['comic_id']);
    }

    public function test_can_sync_library_entries()
    {
        $comic = Comic::factory()->create();
        
        $syncData = [
            'library' => [
                [
                    'comic_id' => $comic->id,
                    'access_type' => 'purchased',
                    'is_favorite' => true,
                    'rating' => 4,
                    'review' => 'Great comic!',
                    'total_reading_time' => 1800,
                    'completion_percentage' => 75.0,
                    'updated_at' => now()->toISOString(),
                ]
            ]
        ];

        $result = $this->syncService->syncUserLibrary($this->user, $syncData, 'device123');

        $this->assertEquals(1, $result['library_updates']);
        
        $libraryEntry = $this->user->library()->where('comic_id', $comic->id)->first();
        $this->assertNotNull($libraryEntry);
        $this->assertEquals('purchased', $libraryEntry->access_type);
        $this->assertTrue($libraryEntry->is_favorite);
        $this->assertEquals(4, $libraryEntry->rating);
        $this->assertEquals(1800, $libraryEntry->total_reading_time);
    }

    public function test_can_sync_reading_progress()
    {
        $comic = Comic::factory()->create();
        
        $syncData = [
            'progress' => [
                [
                    'comic_id' => $comic->id,
                    'current_page' => 15,
                    'total_pages' => 20,
                    'reading_time_minutes' => 30,
                    'is_completed' => false,
                    'last_read_at' => now()->toISOString(),
                    'updated_at' => now()->toISOString(),
                ]
            ]
        ];

        $result = $this->syncService->syncUserLibrary($this->user, $syncData, 'device123');

        $this->assertEquals(1, $result['progress_updates']);
        
        $progress = $this->user->comicProgress()->where('comic_id', $comic->id)->first();
        $this->assertNotNull($progress);
        $this->assertEquals(15, $progress->current_page);
        $this->assertEquals(20, $progress->total_pages);
    }

    public function test_can_sync_bookmarks()
    {
        $comic = Comic::factory()->create();
        
        $syncData = [
            'bookmarks' => [
                [
                    'comic_id' => $comic->id,
                    'page_number' => 10,
                    'note' => 'Important scene',
                    'created_at' => now()->toISOString(),
                    'updated_at' => now()->toISOString(),
                ]
            ]
        ];

        $result = $this->syncService->syncUserLibrary($this->user, $syncData, 'device123');

        $this->assertEquals(1, $result['bookmark_updates']);
        
        $bookmark = $this->user->bookmarks()->where('comic_id', $comic->id)->first();
        $this->assertNotNull($bookmark);
        $this->assertEquals(10, $bookmark->page_number);
        $this->assertEquals('Important scene', $bookmark->note);
    }

    public function test_can_sync_preferences()
    {
        $syncData = [
            'preferences' => [
                'reading_view_mode' => 'continuous',
                'theme' => 'light',
                'reading_zoom_level' => 1.5,
                'updated_at' => now()->toISOString(),
            ]
        ];

        $result = $this->syncService->syncUserLibrary($this->user, $syncData, 'device123');

        $this->assertEquals(1, $result['preference_updates']);
        
        $preferences = $this->user->fresh()->preferences;
        $this->assertNotNull($preferences);
        $this->assertEquals('continuous', $preferences->reading_view_mode);
        $this->assertEquals('light', $preferences->theme);
        $this->assertEquals(1.5, $preferences->reading_zoom_level);
    }

    public function test_conflict_resolution_last_write_wins()
    {
        $comic = Comic::factory()->create();
        
        // Create existing entry
        $existingEntry = UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic->id,
            'rating' => 3,
            'updated_at' => now()->subHour(),
        ]);

        // Sync newer data
        $syncData = [
            'library' => [
                [
                    'comic_id' => $comic->id,
                    'access_type' => 'purchased',
                    'rating' => 5,
                    'updated_at' => now()->toISOString(),
                ]
            ]
        ];

        $result = $this->syncService->syncUserLibrary($this->user, $syncData, 'device123');

        $this->assertEquals(1, $result['library_updates']);
        
        $updatedEntry = $this->user->library()->where('comic_id', $comic->id)->first();
        $this->assertEquals(5, $updatedEntry->rating); // Should be updated
    }

    public function test_conflict_resolution_ignores_older_data()
    {
        $comic = Comic::factory()->create();
        
        // Create existing entry with newer timestamp
        $existingEntry = UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic->id,
            'rating' => 5,
            'updated_at' => now(),
        ]);

        // Try to sync older data
        $syncData = [
            'library' => [
                [
                    'comic_id' => $comic->id,
                    'access_type' => 'purchased',
                    'rating' => 3,
                    'updated_at' => now()->subHour()->toISOString(),
                ]
            ]
        ];

        $result = $this->syncService->syncUserLibrary($this->user, $syncData, 'device123');

        $this->assertEquals(0, $result['library_updates']);
        
        $unchangedEntry = $this->user->library()->where('comic_id', $comic->id)->first();
        $this->assertEquals(5, $unchangedEntry->rating); // Should remain unchanged
    }

    public function test_progress_conflict_resolution_uses_highest_page()
    {
        $comic = Comic::factory()->create();
        
        // Create existing progress
        UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic->id,
            'current_page' => 10,
        ]);

        // Sync higher page number
        $syncData = [
            'progress' => [
                [
                    'comic_id' => $comic->id,
                    'current_page' => 15,
                    'total_pages' => 20,
                    'updated_at' => now()->toISOString(),
                ]
            ]
        ];

        $result = $this->syncService->syncUserLibrary($this->user, $syncData, 'device123');

        $this->assertEquals(1, $result['progress_updates']);
        
        $progress = $this->user->comicProgress()->where('comic_id', $comic->id)->first();
        $this->assertEquals(15, $progress->current_page);
    }

    public function test_needs_sync_detection()
    {
        $comic = Comic::factory()->create();
        
        // No sync needed for empty library
        $this->assertFalse($this->syncService->needsSync($this->user, now()));
        
        // Create new data
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic->id,
            'updated_at' => now(),
        ]);

        // Should need sync for new data
        $this->assertTrue($this->syncService->needsSync($this->user, now()->subHour()));
        
        // Should not need sync if last sync is recent
        $this->assertFalse($this->syncService->needsSync($this->user, now()->addMinute()));
    }

    public function test_sync_returns_token()
    {
        $syncData = [
            'library' => [],
            'progress' => [],
            'bookmarks' => [],
            'preferences' => [],
        ];

        $result = $this->syncService->syncUserLibrary($this->user, $syncData, 'device123');

        $this->assertArrayHasKey('sync_token', $result);
        $this->assertNotNull($result['sync_token']);
        $this->assertEquals(64, strlen($result['sync_token'])); // SHA256 length
    }

    public function test_sync_handles_empty_data()
    {
        $syncData = [];

        $result = $this->syncService->syncUserLibrary($this->user, $syncData, 'device123');

        $this->assertEquals(0, $result['library_updates']);
        $this->assertEquals(0, $result['progress_updates']);
        $this->assertEquals(0, $result['bookmark_updates']);
        $this->assertEquals(0, $result['preference_updates']);
        $this->assertArrayHasKey('sync_token', $result);
    }
}