<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Comic;
use App\Models\UserLibrary;
use App\Models\UserComicProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class UserLibraryEnhancedTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Comic $comic;
    private UserLibrary $libraryEntry;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->comic = Comic::factory()->create([
            'genre' => 'Action',
            'publisher' => 'Marvel',
            'page_count' => 20,
        ]);
        
        $this->libraryEntry = UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'access_type' => 'purchased',
            'purchased_at' => now(),
            'total_reading_time' => 1800, // 30 minutes
            'completion_percentage' => 75.5,
        ]);
    }

    public function test_can_update_last_accessed_time()
    {
        $originalTime = $this->libraryEntry->last_accessed_at;
        
        $this->libraryEntry->updateLastAccessed();
        
        $this->assertNotEquals($originalTime, $this->libraryEntry->fresh()->last_accessed_at);
        $this->assertNotNull($this->libraryEntry->fresh()->last_accessed_at);
    }

    public function test_can_add_reading_time()
    {
        $originalTime = $this->libraryEntry->total_reading_time;
        
        $this->libraryEntry->addReadingTime(300); // 5 minutes
        
        $this->assertEquals($originalTime + 300, $this->libraryEntry->fresh()->total_reading_time);
    }

    public function test_can_update_completion_percentage()
    {
        $this->libraryEntry->updateCompletionPercentage(85.5);
        
        $this->assertEquals(85.5, $this->libraryEntry->fresh()->completion_percentage);
    }

    public function test_completion_percentage_is_clamped()
    {
        $this->libraryEntry->updateCompletionPercentage(-10);
        $this->assertEquals(0, $this->libraryEntry->fresh()->completion_percentage);
        
        $this->libraryEntry->updateCompletionPercentage(150);
        $this->assertEquals(100, $this->libraryEntry->fresh()->completion_percentage);
    }

    public function test_can_generate_sync_token()
    {
        $token = $this->libraryEntry->generateSyncToken();
        
        $this->assertNotNull($token);
        $this->assertEquals(64, strlen($token)); // SHA256 hash length
        $this->assertEquals($token, $this->libraryEntry->fresh()->device_sync_token);
    }

    public function test_scope_by_access_type()
    {
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'access_type' => 'free',
        ]);

        $purchased = $this->user->library()->byAccessType('purchased')->get();
        $free = $this->user->library()->byAccessType('free')->get();

        $this->assertCount(1, $purchased);
        $this->assertCount(1, $free);
    }

    public function test_scope_by_genre()
    {
        $actionComic = Comic::factory()->create(['genre' => 'Action']);
        $comedyComic = Comic::factory()->create(['genre' => 'Comedy']);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $actionComic->id,
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comedyComic->id,
        ]);

        $actionEntries = $this->user->library()->byGenre('Action')->get();
        $comedyEntries = $this->user->library()->byGenre('Comedy')->get();

        $this->assertCount(2, $actionEntries); // Original + new
        $this->assertCount(1, $comedyEntries);
    }

    public function test_scope_by_rating_range()
    {
        // Update the original entry to have a rating
        $this->libraryEntry->update(['rating' => 4]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'rating' => 3,
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'rating' => 5,
        ]);

        $highRated = $this->user->library()->byRatingRange(4, 5)->get();
        $lowRated = $this->user->library()->byRatingRange(1, 3)->get();

        $this->assertCount(2, $highRated); // Original (4) + new (5)
        $this->assertCount(1, $lowRated); // Only the rating 3 entry
    }

    public function test_scope_by_completion_status()
    {
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'completion_percentage' => 0,
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'completion_percentage' => 50,
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'completion_percentage' => 100,
        ]);

        $unread = $this->user->library()->byCompletionStatus('unread')->get();
        $reading = $this->user->library()->byCompletionStatus('reading')->get();
        $completed = $this->user->library()->byCompletionStatus('completed')->get();

        $this->assertCount(1, $unread);
        $this->assertCount(2, $reading); // Original (75.5%) + new (50%)
        $this->assertCount(1, $completed);
    }

    public function test_scope_recently_added()
    {
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'created_at' => now()->subDays(40),
        ]);

        $recent = $this->user->library()->recentlyAdded(30)->get();
        $this->assertCount(1, $recent); // Only the original entry
    }

    public function test_scope_recently_read()
    {
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'last_accessed_at' => now()->subDays(10),
        ]);

        $recent = $this->user->library()->recentlyRead(7)->get();
        $this->assertCount(0, $recent); // None within 7 days
        
        $recent = $this->user->library()->recentlyRead(14)->get();
        $this->assertCount(1, $recent); // One within 14 days
    }

    public function test_reading_time_formatted()
    {
        $this->libraryEntry->total_reading_time = 3665; // 1h 1m 5s
        $this->assertEquals('1h 1m', $this->libraryEntry->getReadingTimeFormatted());
        
        $this->libraryEntry->total_reading_time = 300; // 5 minutes
        $this->assertEquals('5 minutes', $this->libraryEntry->getReadingTimeFormatted());
        
        $this->libraryEntry->total_reading_time = 0;
        $this->assertEquals('0 minutes', $this->libraryEntry->getReadingTimeFormatted());
    }

    public function test_completion_status_methods()
    {
        $this->libraryEntry->completion_percentage = 0;
        $this->assertTrue($this->libraryEntry->isUnread());
        $this->assertFalse($this->libraryEntry->isInProgress());
        $this->assertFalse($this->libraryEntry->isCompleted());
        
        $this->libraryEntry->completion_percentage = 50;
        $this->assertFalse($this->libraryEntry->isUnread());
        $this->assertTrue($this->libraryEntry->isInProgress());
        $this->assertFalse($this->libraryEntry->isCompleted());
        
        $this->libraryEntry->completion_percentage = 100;
        $this->assertFalse($this->libraryEntry->isUnread());
        $this->assertFalse($this->libraryEntry->isInProgress());
        $this->assertTrue($this->libraryEntry->isCompleted());
    }

    public function test_days_in_library()
    {
        $this->libraryEntry->created_at = now()->subDays(5);
        $this->libraryEntry->save();
        
        $this->assertEquals(5, $this->libraryEntry->getDaysInLibrary());
    }

    public function test_days_since_last_read()
    {
        $this->libraryEntry->last_accessed_at = now()->subDays(3);
        $this->libraryEntry->save();
        
        $this->assertEquals(3, $this->libraryEntry->getDaysSinceLastRead());
        
        $this->libraryEntry->last_accessed_at = null;
        $this->libraryEntry->save();
        
        $this->assertNull($this->libraryEntry->getDaysSinceLastRead());
    }

    public function test_scope_order_by_methods()
    {
        $entry1 = UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'last_accessed_at' => now()->subDays(1),
            'rating' => 3,
            'completion_percentage' => 25,
            'total_reading_time' => 600,
        ]);
        
        $entry2 = UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'last_accessed_at' => now(),
            'rating' => 5,
            'completion_percentage' => 90,
            'total_reading_time' => 1200,
        ]);

        // Test order by last read
        $byLastRead = $this->user->library()->orderByLastRead()->get();
        $this->assertEquals($entry2->id, $byLastRead->first()->id);
        
        // Test order by rating
        $byRating = $this->user->library()->orderByRating()->get();
        $this->assertEquals($entry2->id, $byRating->first()->id);
        
        // Test order by progress
        $byProgress = $this->user->library()->orderByProgress()->get();
        $this->assertEquals($entry2->id, $byProgress->first()->id);
        
        // Test order by reading time
        $byReadingTime = $this->user->library()->orderByReadingTime()->get();
        $this->assertEquals($this->libraryEntry->id, $byReadingTime->first()->id); // 1800 seconds
    }

    public function test_scope_by_author()
    {
        $authorComic = Comic::factory()->create(['author' => 'Stan Lee']);
        $otherComic = Comic::factory()->create(['author' => 'Jack Kirby']);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $authorComic->id,
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $otherComic->id,
        ]);

        $stanLeeComics = $this->user->library()->byAuthor('Stan Lee')->get();
        $this->assertCount(1, $stanLeeComics);
        $this->assertEquals($authorComic->id, $stanLeeComics->first()->comic_id);
    }

    public function test_scope_by_language()
    {
        $englishComic = Comic::factory()->create(['language' => 'en']);
        $spanishComic = Comic::factory()->create(['language' => 'es']);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $englishComic->id,
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $spanishComic->id,
        ]);

        $englishComics = $this->user->library()->byLanguage('en')->get();
        $this->assertCount(1, $englishComics);
        $this->assertEquals($englishComic->id, $englishComics->first()->comic_id);
    }

    public function test_scope_by_tags()
    {
        $superheroComic = Comic::factory()->create(['tags' => ['superhero', 'action']]);
        $romanceComic = Comic::factory()->create(['tags' => ['romance', 'drama']]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $superheroComic->id,
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $romanceComic->id,
        ]);

        $superheroComics = $this->user->library()->byTags(['superhero'])->get();
        $this->assertCount(1, $superheroComics);
        $this->assertEquals($superheroComic->id, $superheroComics->first()->comic_id);
    }

    public function test_scope_by_price_range()
    {
        // Clear the existing library entry first
        $this->user->library()->delete();
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'purchase_price' => 5.99,
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'purchase_price' => 12.99,
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'purchase_price' => 19.99,
        ]);

        $cheapComics = $this->user->library()->byPriceRange(0, 10)->get();
        $this->assertCount(1, $cheapComics);
        
        $expensiveComics = $this->user->library()->byPriceRange(15)->get();
        $this->assertCount(1, $expensiveComics);
    }

    public function test_scope_expiring_soon()
    {
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'access_type' => 'subscription',
            'access_expires_at' => now()->addDays(3),
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'access_type' => 'subscription',
            'access_expires_at' => now()->addDays(10),
        ]);

        $expiringSoon = $this->user->library()->expiringSoon(7)->get();
        $this->assertCount(1, $expiringSoon);
    }

    public function test_scope_with_and_without_review()
    {
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'review' => 'Great comic!',
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'review' => null,
        ]);

        $withReview = $this->user->library()->withReview()->get();
        $this->assertCount(1, $withReview);
        
        $withoutReview = $this->user->library()->withoutReview()->get();
        $this->assertCount(2, $withoutReview); // Original entry + new one without review
    }
}