<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Comic;
use App\Models\UserLibrary;
use App\Models\ComicReview;
use App\Models\ComicBookmark;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserReadingStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
    }

    public function test_get_reading_statistics()
    {
        // Create library entries with different completion statuses
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'completion_percentage' => 100,
            'total_reading_time' => 1800,
            'rating' => 5,
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'completion_percentage' => 50,
            'total_reading_time' => 900,
            'rating' => 4,
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'completion_percentage' => 0,
            'total_reading_time' => 0,
        ]);

        // Create reviews and bookmarks
        ComicReview::factory()->count(2)->create(['user_id' => $this->user->id]);
        ComicBookmark::factory()->count(3)->create(['user_id' => $this->user->id]);

        $stats = $this->user->getReadingStatistics();

        $this->assertEquals(3, $stats['total_comics']);
        $this->assertEquals(1, $stats['completed_comics']);
        $this->assertEquals(1, $stats['in_progress_comics']);
        $this->assertEquals(1, $stats['unread_comics']);
        $this->assertEquals(33.33, round($stats['completion_rate'], 2));
        $this->assertEquals(2700, $stats['total_reading_time_seconds']);
        $this->assertEquals('45 minutes', $stats['total_reading_time_formatted']);
        $this->assertEquals(4.5, $stats['average_rating_given']);
        $this->assertEquals(2, $stats['total_reviews']);
        $this->assertEquals(3, $stats['total_bookmarks']);
        $this->assertIsArray($stats['favorite_genres']);
        $this->assertIsInt($stats['reading_streak_days']);
        $this->assertIsArray($stats['monthly_progress']);
    }

    public function test_get_library_analytics()
    {
        // Create comics with different genres and publishers
        $actionComic = Comic::factory()->create([
            'genre' => 'Action',
            'publisher' => 'Marvel',
            'page_count' => 20,
        ]);
        
        $comedyComic = Comic::factory()->create([
            'genre' => 'Comedy',
            'publisher' => 'DC',
            'page_count' => 15,
        ]);

        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $actionComic->id,
            'rating' => 5,
            'purchase_price' => 9.99,
            'purchased_at' => now()->subDays(10),
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comedyComic->id,
            'rating' => 4,
            'purchase_price' => 7.99,
            'purchased_at' => now()->subDays(5),
        ]);

        $analytics = $this->user->getLibraryAnalytics();

        $this->assertArrayHasKey('genre_distribution', $analytics);
        $this->assertArrayHasKey('publisher_distribution', $analytics);
        $this->assertArrayHasKey('rating_distribution', $analytics);
        $this->assertArrayHasKey('monthly_purchases', $analytics);
        $this->assertEquals(17.98, $analytics['total_spent']);
        $this->assertEquals(8.99, $analytics['average_comic_price']);
        $this->assertNotNull($analytics['most_expensive_comic']);
        $this->assertIsFloat($analytics['library_growth_rate']);
    }

    public function test_get_favorite_genres()
    {
        $actionComic1 = Comic::factory()->create(['genre' => 'Action']);
        $actionComic2 = Comic::factory()->create(['genre' => 'Action']);
        $comedyComic = Comic::factory()->create(['genre' => 'Comedy']);

        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $actionComic1->id,
            'rating' => 5,
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $actionComic2->id,
            'rating' => 4,
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comedyComic->id,
            'rating' => 3,
        ]);

        $stats = $this->user->getReadingStatistics();
        $favoriteGenres = $stats['favorite_genres'];

        $this->assertCount(2, $favoriteGenres);
        $this->assertEquals('Action', $favoriteGenres[0]['genre']);
        $this->assertEquals(2, $favoriteGenres[0]['count']);
        $this->assertEquals(4.5, $favoriteGenres[0]['average_rating']);
    }

    public function test_monthly_reading_progress()
    {
        // Create completed comics this month
        UserLibrary::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'comic_id' => function () {
                return Comic::factory()->create()->id;
            },
            'completion_percentage' => 100,
            'updated_at' => now(),
        ]);

        $stats = $this->user->getReadingStatistics();
        $monthlyProgress = $stats['monthly_progress'];

        $this->assertEquals(3, $monthlyProgress['completed']);
        $this->assertEquals(5, $monthlyProgress['goal']); // Default goal
        $this->assertEquals(60, $monthlyProgress['percentage']);
    }

    public function test_reading_velocity()
    {
        // Create completed comics with page counts
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create(['page_count' => 20])->id,
            'completion_percentage' => 100,
            'updated_at' => now()->subDays(15),
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create(['page_count' => 25])->id,
            'completion_percentage' => 100,
            'updated_at' => now()->subDays(10),
        ]);

        $stats = $this->user->getReadingStatistics();
        $velocity = $stats['reading_velocity'];

        $this->assertArrayHasKey('comics_per_week', $velocity);
        $this->assertArrayHasKey('pages_per_day', $velocity);
        $this->assertIsFloat($velocity['comics_per_week']);
        $this->assertIsFloat($velocity['pages_per_day']);
    }

    public function test_reading_streak_calculation()
    {
        // Create library entries with recent access
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'last_accessed_at' => now()->startOfDay(),
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'last_accessed_at' => now()->subDay()->startOfDay(),
        ]);

        $stats = $this->user->getReadingStatistics();
        
        $this->assertGreaterThanOrEqual(0, $stats['reading_streak_days']);
    }

    public function test_empty_library_statistics()
    {
        $stats = $this->user->getReadingStatistics();

        $this->assertEquals(0, $stats['total_comics']);
        $this->assertEquals(0, $stats['completed_comics']);
        $this->assertEquals(0, $stats['in_progress_comics']);
        $this->assertEquals(0, $stats['unread_comics']);
        $this->assertEquals(0, $stats['completion_rate']);
        $this->assertEquals(0, $stats['total_reading_time_seconds']);
        $this->assertEquals('0 seconds', $stats['total_reading_time_formatted']);
        $this->assertEquals(0, $stats['average_rating_given']);
        $this->assertEquals(0, $stats['total_reviews']);
        $this->assertEquals(0, $stats['total_bookmarks']);
        $this->assertEmpty($stats['favorite_genres']);
        $this->assertEquals(0, $stats['reading_streak_days']);
    }

    public function test_format_reading_time()
    {
        $user = new User();
        
        // Test different time formats using reflection to access private method
        $reflection = new \ReflectionClass($user);
        $method = $reflection->getMethod('formatReadingTime');
        $method->setAccessible(true);

        $this->assertEquals('30 seconds', $method->invoke($user, 30));
        $this->assertEquals('5 minutes', $method->invoke($user, 300));
        $this->assertEquals('1h 30m', $method->invoke($user, 5400));
        $this->assertEquals('1d 2h', $method->invoke($user, 93600));
    }

    public function test_get_reading_habits_analysis()
    {
        // Create library entries with different patterns
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create(['genre' => 'Action'])->id,
            'completion_percentage' => 100,
            'total_reading_time' => 1800,
            'rating' => 5,
            'last_accessed_at' => now()->startOfDay(),
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create(['genre' => 'Action'])->id,
            'completion_percentage' => 75,
            'total_reading_time' => 900,
            'rating' => 4,
            'last_accessed_at' => now()->subDay()->startOfDay(),
        ]);

        $habits = $this->user->getReadingHabitsAnalysis();

        $this->assertArrayHasKey('reading_patterns', $habits);
        $this->assertArrayHasKey('genre_preferences', $habits);
        $this->assertArrayHasKey('reading_consistency', $habits);
        $this->assertArrayHasKey('average_session_length', $habits);
        $this->assertArrayHasKey('preferred_reading_times', $habits);
        $this->assertArrayHasKey('completion_trends', $habits);
        
        $this->assertIsArray($habits['reading_patterns']);
        $this->assertIsArray($habits['genre_preferences']);
        $this->assertIsFloat($habits['reading_consistency']);
        $this->assertIsInt($habits['average_session_length']);
    }

    public function test_get_library_health_metrics()
    {
        // Create library with mixed health indicators
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'completion_percentage' => 100,
            'rating' => 5,
            'review' => 'Great comic!',
            'last_accessed_at' => now(),
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => Comic::factory()->create()->id,
            'completion_percentage' => 0,
            'last_accessed_at' => now()->subMonths(6), // Stale
        ]);

        $health = $this->user->getLibraryHealthMetrics();

        $this->assertArrayHasKey('health_score', $health);
        $this->assertArrayHasKey('unread_percentage', $health);
        $this->assertArrayHasKey('stale_comics_count', $health);
        $this->assertArrayHasKey('review_coverage', $health);
        $this->assertArrayHasKey('engagement_score', $health);
        $this->assertArrayHasKey('recommendations', $health);
        
        $this->assertIsFloat($health['health_score']);
        $this->assertIsFloat($health['unread_percentage']);
        $this->assertIsInt($health['stale_comics_count']);
        $this->assertIsFloat($health['review_coverage']);
        $this->assertIsFloat($health['engagement_score']);
        $this->assertIsArray($health['recommendations']);
        
        $this->assertEquals(50.0, $health['unread_percentage']); // 1 out of 2 unread
        $this->assertEquals(1, $health['stale_comics_count']);
        $this->assertEquals(50.0, $health['review_coverage']); // 1 out of 2 reviewed
    }

    public function test_get_reading_goals()
    {
        // Create completed comics this month and year
        UserLibrary::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'comic_id' => function () {
                return Comic::factory()->create()->id;
            },
            'completion_percentage' => 100,
            'updated_at' => now(),
        ]);

        $goals = $this->user->getReadingGoals();

        $this->assertArrayHasKey('monthly', $goals);
        $this->assertArrayHasKey('yearly', $goals);
        $this->assertArrayHasKey('streak', $goals);
        $this->assertArrayHasKey('longest_streak', $goals);
        
        $this->assertEquals(5, $goals['monthly']['goal']); // Default monthly goal
        $this->assertEquals(3, $goals['monthly']['completed']);
        $this->assertEquals(60.0, $goals['monthly']['percentage']);
        
        $this->assertEquals(50, $goals['yearly']['goal']); // Default yearly goal
        $this->assertEquals(3, $goals['yearly']['completed']);
        $this->assertEquals(6.0, $goals['yearly']['percentage']);
        
        $this->assertIsInt($goals['streak']);
        $this->assertIsInt($goals['longest_streak']);
    }

    public function test_empty_library_habits_analysis()
    {
        $habits = $this->user->getReadingHabitsAnalysis();

        $this->assertEmpty($habits['reading_patterns']);
        $this->assertEmpty($habits['genre_preferences']);
        $this->assertEquals(0, $habits['reading_consistency']);
        $this->assertEquals(0, $habits['average_session_length']);
        $this->assertEmpty($habits['preferred_reading_times']);
        $this->assertEmpty($habits['completion_trends']);
    }

    public function test_library_health_with_recommendations()
    {
        // Create many unread comics to trigger recommendations
        UserLibrary::factory()->count(12)->create([
            'user_id' => $this->user->id,
            'comic_id' => function () {
                return Comic::factory()->create()->id;
            },
            'completion_percentage' => 0, // All unread
        ]);

        $health = $this->user->getLibraryHealthMetrics();

        $this->assertNotEmpty($health['recommendations']);
        
        $backlogRecommendation = collect($health['recommendations'])
            ->firstWhere('type', 'reduce_backlog');
            
        $this->assertNotNull($backlogRecommendation);
        $this->assertEquals('high', $backlogRecommendation['priority']);
    }

    public function test_genre_preferences_analysis()
    {
        // Create comics with different genres and ratings
        $actionComic1 = Comic::factory()->create(['genre' => 'Action']);
        $actionComic2 = Comic::factory()->create(['genre' => 'Action']);
        $comedyComic = Comic::factory()->create(['genre' => 'Comedy']);

        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $actionComic1->id,
            'rating' => 5,
            'completion_percentage' => 100,
            'total_reading_time' => 1800,
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $actionComic2->id,
            'rating' => 4,
            'completion_percentage' => 100,
            'total_reading_time' => 1200,
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comedyComic->id,
            'rating' => 3,
            'completion_percentage' => 50,
            'total_reading_time' => 600,
        ]);

        $habits = $this->user->getReadingHabitsAnalysis();
        $genrePrefs = $habits['genre_preferences'];

        $this->assertArrayHasKey('Action', $genrePrefs);
        $this->assertArrayHasKey('Comedy', $genrePrefs);
        
        $actionStats = $genrePrefs['Action'];
        $this->assertEquals(2, $actionStats['count']);
        $this->assertEquals(4.5, $actionStats['average_rating']);
        $this->assertEquals(100.0, $actionStats['completion_percentage']);
        
        // Action should have higher preference score than Comedy
        $this->assertGreaterThan($genrePrefs['Comedy']['preference_score'], $actionStats['preference_score']);
    }
}