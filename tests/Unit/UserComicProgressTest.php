<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Comic;
use App\Models\UserComicProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserComicProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Comic $comic;
    private UserComicProgress $progress;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->comic = Comic::factory()->create(['page_count' => 100]);
        $this->progress = UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'current_page' => 1,
            'total_pages' => 100,
        ]);
    }

    public function test_update_progress_calculates_percentage_correctly()
    {
        $this->progress->updateProgress(50, 100);

        $this->assertEquals(50, $this->progress->current_page);
        $this->assertEquals(50.00, $this->progress->progress_percentage);
        $this->assertFalse($this->progress->is_completed);
        $this->assertNotNull($this->progress->last_read_at);
    }

    public function test_update_progress_marks_as_completed_when_finished()
    {
        $this->progress->updateProgress(100, 100);

        $this->assertEquals(100, $this->progress->current_page);
        $this->assertEquals(100.00, $this->progress->progress_percentage);
        $this->assertTrue($this->progress->is_completed);
        $this->assertNotNull($this->progress->completed_at);
    }

    public function test_add_bookmark_updates_bookmark_data()
    {
        $this->progress->addBookmark(25, 'Great scene here!');

        $this->assertTrue($this->progress->is_bookmarked);
        $this->assertEquals(1, $this->progress->bookmark_count);
        $this->assertNotNull($this->progress->last_bookmark_at);
        
        $bookmarks = $this->progress->bookmarks;
        $this->assertCount(1, $bookmarks);
        $this->assertEquals(25, $bookmarks[0]['page']);
        $this->assertEquals('Great scene here!', $bookmarks[0]['note']);
    }

    public function test_start_reading_session_creates_new_session()
    {
        $metadata = ['device' => 'mobile', 'app_version' => '1.0.0'];
        
        $this->progress->startReadingSession($metadata);

        $sessions = $this->progress->reading_sessions;
        $this->assertCount(1, $sessions);
        
        $session = array_values($sessions)[0];
        $this->assertTrue($session['is_active']);
        $this->assertEquals(1, $session['start_page']);
        $this->assertEquals($metadata, $session['metadata']);
        $this->assertNotNull($this->progress->first_read_at);
    }

    public function test_end_reading_session_completes_session()
    {
        // Start a session first
        $this->progress->startReadingSession();
        
        // End the session
        $this->progress->endReadingSession(30, ['notes' => 'Good reading session']);

        $sessions = $this->progress->reading_sessions;
        $session = array_values($sessions)[0];
        
        $this->assertFalse($session['is_active']);
        $this->assertEquals(30, $session['end_page']);
        $this->assertEquals(29, $session['pages_read']); // 30 - 1
        $this->assertGreaterThan(0, $session['duration_minutes']);
        $this->assertEquals(1, $this->progress->total_reading_sessions);
    }

    public function test_add_pause_time_updates_current_session()
    {
        $this->progress->startReadingSession();
        
        $this->progress->addPauseTime(5);

        $sessions = $this->progress->reading_sessions;
        $session = array_values($sessions)[0];
        
        $this->assertEquals(5, $session['paused_duration_minutes']);
    }

    public function test_update_reading_analytics_calculates_correctly()
    {
        // Create multiple completed sessions
        $this->progress->reading_sessions = [
            'session1' => [
                'id' => 'session1',
                'started_at' => now()->subMinutes(60)->toISOString(),
                'ended_at' => now()->subMinutes(30)->toISOString(),
                'start_page' => 1,
                'end_page' => 20,
                'pages_read' => 19,
                'duration_minutes' => 30,
                'paused_duration_minutes' => 5,
                'is_active' => false,
            ],
            'session2' => [
                'id' => 'session2',
                'started_at' => now()->subMinutes(30)->toISOString(),
                'ended_at' => now()->toISOString(),
                'start_page' => 20,
                'end_page' => 40,
                'pages_read' => 20,
                'duration_minutes' => 30,
                'paused_duration_minutes' => 3,
                'is_active' => false,
            ],
        ];

        $this->progress->updateReadingAnalytics();

        $this->assertEquals(30.0, $this->progress->average_session_duration);
        $this->assertEquals(19.5, $this->progress->pages_per_session_avg); // (19 + 20) / 2
        $this->assertEquals(0.65, $this->progress->reading_speed_pages_per_minute); // 39 pages / 60 minutes
        $this->assertEquals(60, $this->progress->reading_time_minutes);
        $this->assertEquals(8, $this->progress->total_time_paused_minutes);
    }

    public function test_get_reading_statistics_returns_complete_data()
    {
        $this->progress->update([
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

        $stats = $this->progress->getReadingStatistics();

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

    public function test_get_current_session_returns_active_session()
    {
        $this->progress->startReadingSession(['device' => 'tablet']);
        
        $currentSession = $this->progress->getCurrentSession();
        
        $this->assertNotNull($currentSession);
        $this->assertTrue($currentSession['is_active']);
        $this->assertEquals(['device' => 'tablet'], $currentSession['metadata']);
    }

    public function test_has_active_session_returns_correct_status()
    {
        $this->assertFalse($this->progress->hasActiveSession());
        
        $this->progress->startReadingSession();
        $this->assertTrue($this->progress->hasActiveSession());
        
        $this->progress->endReadingSession(10);
        $this->assertFalse($this->progress->hasActiveSession());
    }

    public function test_update_reading_preferences_merges_preferences()
    {
        $initialPreferences = ['zoom_level' => 1.0, 'reading_mode' => 'single_page'];
        $this->progress->reading_preferences = $initialPreferences;
        $this->progress->save();

        $newPreferences = ['zoom_level' => 1.5, 'background_color' => '#ffffff'];
        $this->progress->updateReadingPreferences($newPreferences);

        $expectedPreferences = [
            'zoom_level' => 1.5,
            'reading_mode' => 'single_page',
            'background_color' => '#ffffff',
        ];

        $this->assertEquals($expectedPreferences, $this->progress->reading_preferences);
    }

    public function test_relationships_work_correctly()
    {
        $this->assertInstanceOf(User::class, $this->progress->user);
        $this->assertInstanceOf(Comic::class, $this->progress->comic);
        $this->assertEquals($this->user->id, $this->progress->user->id);
        $this->assertEquals($this->comic->id, $this->progress->comic->id);
    }
}