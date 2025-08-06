<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Comic;
use App\Models\UserComicProgress;
use App\Models\ComicBookmark;
use App\Services\ReadingProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReadingProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReadingProgressService $service;
    private User $user;
    private Comic $comic;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new ReadingProgressService();
        $this->user = User::factory()->create();
        $this->comic = Comic::factory()->create(['page_count' => 100]);
    }

    public function test_update_progress_creates_new_progress_if_not_exists()
    {
        $progress = $this->service->updateProgress($this->user, $this->comic, 25);

        $this->assertInstanceOf(UserComicProgress::class, $progress);
        $this->assertEquals($this->user->id, $progress->user_id);
        $this->assertEquals($this->comic->id, $progress->comic_id);
        $this->assertEquals(25, $progress->current_page);
        $this->assertEquals(25.0, $progress->progress_percentage);
    }

    public function test_update_progress_updates_existing_progress()
    {
        $existingProgress = UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'current_page' => 10,
        ]);

        $progress = $this->service->updateProgress($this->user, $this->comic, 50);

        $this->assertEquals($existingProgress->id, $progress->id);
        $this->assertEquals(50, $progress->current_page);
        $this->assertEquals(50.0, $progress->progress_percentage);
    }

    public function test_update_progress_includes_metadata()
    {
        $metadata = ['device' => 'mobile', 'screen_size' => 'small'];
        
        $progress = $this->service->updateProgress($this->user, $this->comic, 25, $metadata);

        $this->assertEquals($metadata, $progress->reading_metadata);
    }

    public function test_start_reading_session_creates_session()
    {
        $metadata = ['device' => 'tablet'];
        
        $progress = $this->service->startReadingSession($this->user, $this->comic, $metadata);

        $this->assertNotNull($progress->getCurrentSession());
        $this->assertTrue($progress->hasActiveSession());
        $this->assertEquals($metadata, $progress->getCurrentSession()['metadata']);
    }

    public function test_end_reading_session_completes_session()
    {
        $progress = $this->service->startReadingSession($this->user, $this->comic);
        
        $updatedProgress = $this->service->endReadingSession($this->user, $this->comic, 30);

        $this->assertFalse($updatedProgress->hasActiveSession());
        $this->assertEquals(1, $updatedProgress->total_reading_sessions);
        $this->assertEquals(30, $updatedProgress->current_page);
    }

    public function test_add_pause_time_updates_session()
    {
        $progress = $this->service->startReadingSession($this->user, $this->comic);
        
        $this->service->addPauseTime($this->user, $this->comic, 5);

        $session = $progress->fresh()->getCurrentSession();
        $this->assertEquals(5, $session['paused_duration_minutes']);
    }

    public function test_get_progress_returns_existing_progress()
    {
        $existingProgress = UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);

        $progress = $this->service->getProgress($this->user, $this->comic);

        $this->assertEquals($existingProgress->id, $progress->id);
    }

    public function test_get_progress_returns_null_if_not_exists()
    {
        $progress = $this->service->getProgress($this->user, $this->comic);

        $this->assertNull($progress);
    }

    public function test_add_bookmark_creates_new_bookmark()
    {
        $bookmark = $this->service->addBookmark($this->user, $this->comic, 25, 'Great scene!');

        $this->assertInstanceOf(ComicBookmark::class, $bookmark);
        $this->assertEquals(25, $bookmark->page_number);
        $this->assertEquals('Great scene!', $bookmark->note);
    }

    public function test_add_bookmark_updates_existing_bookmark()
    {
        $existingBookmark = ComicBookmark::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'page_number' => 25,
            'note' => 'Original note',
        ]);

        $bookmark = $this->service->addBookmark($this->user, $this->comic, 25, 'Updated note');

        $this->assertEquals($existingBookmark->id, $bookmark->id);
        $this->assertEquals('Updated note', $bookmark->note);
    }

    public function test_add_bookmark_syncs_with_progress()
    {
        $progress = UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'bookmark_count' => 0,
            'is_bookmarked' => false,
        ]);

        $this->service->addBookmark($this->user, $this->comic, 25);

        $progress->refresh();
        $this->assertEquals(1, $progress->bookmark_count);
        $this->assertTrue($progress->is_bookmarked);
        $this->assertNotNull($progress->last_bookmark_at);
    }

    public function test_remove_bookmark_removes_existing_bookmark()
    {
        ComicBookmark::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'page_number' => 25,
        ]);

        $removed = $this->service->removeBookmark($this->user, $this->comic, 25);

        $this->assertTrue($removed);
        $this->assertFalse(ComicBookmark::bookmarkExistsForPage($this->user, $this->comic, 25));
    }

    public function test_remove_bookmark_updates_progress()
    {
        $progress = UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'bookmark_count' => 1,
            'is_bookmarked' => true,
        ]);

        ComicBookmark::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'page_number' => 25,
        ]);

        $this->service->removeBookmark($this->user, $this->comic, 25);

        $progress->refresh();
        $this->assertEquals(0, $progress->bookmark_count);
        $this->assertFalse($progress->is_bookmarked);
        $this->assertNull($progress->last_bookmark_at);
    }

    public function test_get_bookmarks_returns_user_comic_bookmarks()
    {
        ComicBookmark::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);

        // Create bookmark for different user (should not be included)
        ComicBookmark::factory()->create([
            'user_id' => User::factory()->create()->id,
            'comic_id' => $this->comic->id,
        ]);

        $bookmarks = $this->service->getBookmarks($this->user, $this->comic);

        $this->assertCount(3, $bookmarks);
    }

    public function test_update_reading_preferences_updates_progress()
    {
        $preferences = [
            'zoom_level' => 1.5,
            'reading_mode' => 'double_page',
            'background_color' => '#ffffff',
        ];

        $progress = $this->service->updateReadingPreferences($this->user, $this->comic, $preferences);

        $this->assertEquals($preferences, $progress->reading_preferences);
    }

    public function test_get_reading_statistics_returns_complete_stats()
    {
        $progress = UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'total_reading_sessions' => 5,
            'reading_time_minutes' => 150,
            'average_session_duration' => 30,
            'pages_per_session_avg' => 20,
            'reading_speed_pages_per_minute' => 0.67,
            'total_time_paused_minutes' => 15,
            'bookmark_count' => 3,
            'progress_percentage' => 75.5,
            'is_completed' => false,
        ]);

        $stats = $this->service->getReadingStatistics($this->user, $this->comic);

        $this->assertEquals(5, $stats['total_sessions']);
        $this->assertEquals(150, $stats['total_reading_time_minutes']);
        $this->assertEquals(30, $stats['average_session_duration']);
        $this->assertEquals(20, $stats['pages_per_session_avg']);
        $this->assertEquals(0.67, $stats['reading_speed_pages_per_minute']);
        $this->assertEquals(15, $stats['total_time_paused_minutes']);
        $this->assertEquals(3, $stats['bookmark_count']);
        $this->assertEquals(75.5, $stats['progress_percentage']);
        $this->assertFalse($stats['is_completed']);
    }

    public function test_get_reading_statistics_returns_defaults_for_no_progress()
    {
        $stats = $this->service->getReadingStatistics($this->user, $this->comic);

        $this->assertEquals(0, $stats['total_sessions']);
        $this->assertEquals(0, $stats['total_reading_time_minutes']);
        $this->assertEquals(0, $stats['average_session_duration']);
        $this->assertEquals(0, $stats['pages_per_session_avg']);
        $this->assertEquals(0, $stats['reading_speed_pages_per_minute']);
        $this->assertEquals(0, $stats['total_time_paused_minutes']);
        $this->assertEquals(0, $stats['bookmark_count']);
        $this->assertEquals(0, $stats['progress_percentage']);
        $this->assertFalse($stats['is_completed']);
    }

    public function test_get_user_reading_statistics_aggregates_all_progress()
    {
        $comic2 = Comic::factory()->create();

        UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'reading_time_minutes' => 60,
            'total_reading_sessions' => 3,
            'average_session_duration' => 20,
            'reading_speed_pages_per_minute' => 0.5,
            'current_page' => 50,
            'is_completed' => false,
        ]);

        UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic2->id,
            'reading_time_minutes' => 90,
            'total_reading_sessions' => 2,
            'average_session_duration' => 45,
            'reading_speed_pages_per_minute' => 0.8,
            'current_page' => 100,
            'is_completed' => true,
        ]);

        ComicBookmark::factory()->count(5)->create(['user_id' => $this->user->id]);

        $stats = $this->service->getUserReadingStatistics($this->user);

        $this->assertEquals(2, $stats['total_comics_started']);
        $this->assertEquals(1, $stats['total_comics_completed']);
        $this->assertEquals(50.0, $stats['completion_rate']);
        $this->assertEquals(150, $stats['total_reading_time_minutes']);
        $this->assertEquals(5, $stats['total_reading_sessions']);
        $this->assertEquals(32.5, $stats['average_session_duration']); // (20 + 45) / 2
        $this->assertEquals(0.65, $stats['average_reading_speed_pages_per_minute']); // (0.5 + 0.8) / 2
        $this->assertEquals(5, $stats['total_bookmarks']);
        $this->assertEquals(150, $stats['total_pages_read']); // 50 + 100
    }

    public function test_synchronize_bookmarks_updates_progress()
    {
        $progress = UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'bookmark_count' => 0,
            'is_bookmarked' => false,
            'last_bookmark_at' => null,
        ]);

        ComicBookmark::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);

        $this->service->synchronizeBookmarks($this->user, $this->comic);

        $progress->refresh();
        $this->assertEquals(3, $progress->bookmark_count);
        $this->assertTrue($progress->is_bookmarked);
        $this->assertNotNull($progress->last_bookmark_at);
    }
}