<?php

namespace Tests\Unit;

use App\Models\Comic;
use App\Models\ComicSeries;
use App\Models\User;
use App\Models\UserComicProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComicSeriesModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->series = ComicSeries::factory()->create([
            'name' => 'Test Series',
            'publisher' => 'Test Publisher',
            'status' => 'ongoing'
        ]);
        
        $this->user = User::factory()->create();
    }

    public function test_series_has_many_comics()
    {
        Comic::factory()->count(3)->create(['series_id' => $this->series->id]);
        
        $this->assertCount(3, $this->series->comics);
        $this->assertInstanceOf(Comic::class, $this->series->comics->first());
    }

    public function test_get_comics_in_order()
    {
        $comic3 = Comic::factory()->create([
            'series_id' => $this->series->id,
            'issue_number' => 3,
            'is_visible' => true
        ]);
        
        $comic1 = Comic::factory()->create([
            'series_id' => $this->series->id,
            'issue_number' => 1,
            'is_visible' => true
        ]);
        
        $comic2 = Comic::factory()->create([
            'series_id' => $this->series->id,
            'issue_number' => 2,
            'is_visible' => true
        ]);

        $orderedComics = $this->series->getComicsInOrder();

        $this->assertEquals($comic1->id, $orderedComics->first()->id);
        $this->assertEquals($comic3->id, $orderedComics->last()->id);
    }

    public function test_get_first_issue()
    {
        $comic2 = Comic::factory()->create([
            'series_id' => $this->series->id,
            'issue_number' => 2,
            'is_visible' => true
        ]);
        
        $comic1 = Comic::factory()->create([
            'series_id' => $this->series->id,
            'issue_number' => 1,
            'is_visible' => true
        ]);

        $firstIssue = $this->series->getFirstIssue();

        $this->assertEquals($comic1->id, $firstIssue->id);
    }

    public function test_get_latest_issue()
    {
        // Create a fresh series for this test
        $series = ComicSeries::factory()->create();
        
        $comic1 = Comic::factory()->create([
            'series_id' => $series->id,
            'issue_number' => 1
        ]);
        
        $comic2 = Comic::factory()->create([
            'series_id' => $series->id,
            'issue_number' => 2
        ]);
        
        $comic3 = Comic::factory()->create([
            'series_id' => $series->id,
            'issue_number' => 3
        ]);

        $latestIssue = $series->getLatestIssue();

        $this->assertNotNull($latestIssue);
        $this->assertEquals(3, $latestIssue->issue_number);
    }

    public function test_get_next_issue()
    {
        $comic1 = Comic::factory()->create([
            'series_id' => $this->series->id,
            'issue_number' => 1,
            'is_visible' => true
        ]);
        
        $comic2 = Comic::factory()->create([
            'series_id' => $this->series->id,
            'issue_number' => 2,
            'is_visible' => true
        ]);
        
        $comic3 = Comic::factory()->create([
            'series_id' => $this->series->id,
            'issue_number' => 3,
            'is_visible' => true
        ]);

        $nextIssue = $this->series->getNextIssue($comic1);

        $this->assertEquals($comic2->id, $nextIssue->id);
    }

    public function test_get_previous_issue()
    {
        // Create a fresh series for this test
        $series = ComicSeries::factory()->create();
        
        $comic1 = Comic::factory()->create([
            'series_id' => $series->id,
            'issue_number' => 1,
            'is_visible' => true
        ]);
        
        $comic2 = Comic::factory()->create([
            'series_id' => $series->id,
            'issue_number' => 2,
            'is_visible' => true
        ]);
        
        $comic3 = Comic::factory()->create([
            'series_id' => $series->id,
            'issue_number' => 3,
            'is_visible' => true
        ]);

        $previousIssue = $series->getPreviousIssue($comic3);

        $this->assertNotNull($previousIssue);
        $this->assertEquals(2, $previousIssue->issue_number);
    }

    public function test_get_statistics()
    {
        Comic::factory()->create([
            'series_id' => $this->series->id,
            'is_visible' => true,
            'average_rating' => 4.0,
            'total_readers' => 50,
            'page_count' => 20,
            'published_at' => now()->subDays(30)
        ]);
        
        Comic::factory()->create([
            'series_id' => $this->series->id,
            'is_visible' => true,
            'average_rating' => 5.0,
            'total_readers' => 75,
            'page_count' => 25,
            'published_at' => now()->subDays(15)
        ]);

        $stats = $this->series->getStatistics();

        $this->assertEquals(2, $stats['total_issues']);
        $this->assertEquals(2, $stats['published_issues']);
        $this->assertEquals(4.5, $stats['average_rating']);
        $this->assertEquals(125, $stats['total_readers']);
        $this->assertEquals(45, $stats['total_pages']);
        $this->assertNotNull($stats['first_published']);
        $this->assertNotNull($stats['last_published']);
    }

    public function test_has_missing_issues()
    {
        // Create issues 1, 2, and 4 (missing issue 3)
        Comic::factory()->create([
            'series_id' => $this->series->id,
            'issue_number' => 1
        ]);
        
        Comic::factory()->create([
            'series_id' => $this->series->id,
            'issue_number' => 2
        ]);
        
        Comic::factory()->create([
            'series_id' => $this->series->id,
            'issue_number' => 4
        ]);

        $this->assertTrue($this->series->hasMissingIssues());
        $this->assertEquals([3], $this->series->getMissingIssues());
    }

    public function test_no_missing_issues()
    {
        // Create consecutive issues
        Comic::factory()->create([
            'series_id' => $this->series->id,
            'issue_number' => 1
        ]);
        
        Comic::factory()->create([
            'series_id' => $this->series->id,
            'issue_number' => 2
        ]);
        
        Comic::factory()->create([
            'series_id' => $this->series->id,
            'issue_number' => 3
        ]);

        $this->assertFalse($this->series->hasMissingIssues());
        $this->assertEmpty($this->series->getMissingIssues());
    }

    public function test_get_reading_progress_for_user()
    {
        $comic1 = Comic::factory()->create(['series_id' => $this->series->id]);
        $comic2 = Comic::factory()->create(['series_id' => $this->series->id]);
        $comic3 = Comic::factory()->create(['series_id' => $this->series->id]);

        // User completed comic1, is reading comic2, hasn't started comic3
        UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic1->id,
            'is_completed' => true
        ]);
        
        UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic2->id,
            'is_completed' => false,
            'current_page' => 5
        ]);

        $progress = $this->series->getReadingProgressForUser($this->user);

        $this->assertEquals(3, $progress['total_issues']);
        $this->assertEquals(1, $progress['read_issues']);
        $this->assertEquals(1, $progress['in_progress_issues']);
        $this->assertEquals(1, $progress['unread_issues']);
        $this->assertEquals(33.33, round($progress['completion_percentage'], 2));
    }

    public function test_get_recommended_series()
    {
        // Create another series with same publisher
        $similarSeries = ComicSeries::factory()->create([
            'publisher' => 'Test Publisher'
        ]);
        
        // Create comics for both series with ratings
        Comic::factory()->create([
            'series_id' => $this->series->id,
            'average_rating' => 4.0,
            'total_readers' => 100
        ]);
        
        Comic::factory()->create([
            'series_id' => $similarSeries->id,
            'average_rating' => 4.5,
            'total_readers' => 150
        ]);

        $recommendations = $this->series->getRecommendedSeries(5);

        $this->assertTrue($recommendations->contains($similarSeries));
    }

    public function test_update_status()
    {
        // Create a recent comic (should make series ongoing)
        Comic::factory()->create([
            'series_id' => $this->series->id,
            'published_at' => now()->subDays(30)
        ]);

        $this->series->updateStatus();
        $this->assertEquals('ongoing', $this->series->status);

        // Create an old comic (should make series completed)
        $this->series->comics()->delete();
        Comic::factory()->create([
            'series_id' => $this->series->id,
            'published_at' => now()->subMonths(12)
        ]);

        $this->series->updateStatus();
        $this->assertEquals('completed', $this->series->status);
    }

    public function test_update_total_issues()
    {
        Comic::factory()->count(5)->create(['series_id' => $this->series->id]);

        $this->series->updateTotalIssues();

        $this->assertEquals(5, $this->series->total_issues);
    }

    public function test_get_total_issues()
    {
        Comic::factory()->count(3)->create(['series_id' => $this->series->id]);

        $this->assertEquals(3, $this->series->getTotalIssues());
    }

    public function test_get_average_rating()
    {
        Comic::factory()->create([
            'series_id' => $this->series->id,
            'average_rating' => 4.0
        ]);
        
        Comic::factory()->create([
            'series_id' => $this->series->id,
            'average_rating' => 5.0
        ]);

        $this->assertEquals(4.5, $this->series->getAverageRating());
    }

    public function test_get_total_readers()
    {
        Comic::factory()->create([
            'series_id' => $this->series->id,
            'total_readers' => 100
        ]);
        
        Comic::factory()->create([
            'series_id' => $this->series->id,
            'total_readers' => 150
        ]);

        $this->assertEquals(250, $this->series->getTotalReaders());
    }

    public function test_status_methods()
    {
        $this->series->status = 'ongoing';
        $this->assertTrue($this->series->isOngoing());
        $this->assertFalse($this->series->isCompleted());

        $this->series->status = 'completed';
        $this->assertFalse($this->series->isOngoing());
        $this->assertTrue($this->series->isCompleted());
    }
}