<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Comic;
use App\Models\ComicSeries;
use App\Models\UserComicProgress;
use App\Models\ComicReview;
use App\Models\SocialShare;
use App\Services\AchievementService;
use App\Services\SocialSharingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class AchievementServiceTest extends TestCase
{
    use RefreshDatabase;

    private AchievementService $achievementService;
    private User $user;
    private Comic $comic;

    protected function setUp(): void
    {
        parent::setUp();
        
        $socialSharingService = Mockery::mock(SocialSharingService::class);
        $socialSharingService->shouldReceive('trackReadingAchievement')
            ->andReturn(['sharing_suggestions' => []]);
        
        $this->achievementService = new AchievementService($socialSharingService);
        $this->user = User::factory()->create();
        $this->comic = Comic::factory()->create(['page_count' => 100]);
    }

    public function test_awards_first_comic_achievement()
    {
        // Create first completed comic progress
        UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'is_completed' => true,
        ]);

        $achievements = $this->achievementService->checkAchievements(
            $this->user,
            'comic_completed',
            ['comic' => $this->comic]
        );

        // Should include first_comic achievement
        $firstComicAchievement = collect($achievements)->firstWhere('type', 'first_comic');
        $this->assertNotNull($firstComicAchievement);
        $this->assertEquals('First Steps', $firstComicAchievement['title']);
        $this->assertStringContainsString('first comic', $firstComicAchievement['description']);
    }

    public function test_awards_milestone_achievements()
    {
        // Create 5 completed comics to trigger milestone
        UserComicProgress::factory()->count(4)->create([
            'user_id' => $this->user->id,
            'is_completed' => true,
        ]);

        UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'is_completed' => true,
        ]);

        $achievements = $this->achievementService->checkAchievements(
            $this->user,
            'comic_completed',
            ['comic' => $this->comic]
        );

        $milestoneAchievement = collect($achievements)->firstWhere('type', 'milestone_reader');
        $this->assertNotNull($milestoneAchievement);
        $this->assertEquals(5, $milestoneAchievement['milestone']);
    }

    public function test_awards_series_completion_achievement()
    {
        $series = ComicSeries::factory()->create();
        $comic1 = Comic::factory()->create(['series_id' => $series->id]);
        $comic2 = Comic::factory()->create(['series_id' => $series->id]);

        // Complete first comic in series
        UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic1->id,
            'is_completed' => true,
        ]);

        // Complete second comic in series (should trigger series completion)
        UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic2->id,
            'is_completed' => true,
        ]);

        $achievements = $this->achievementService->checkAchievements(
            $this->user,
            'comic_completed',
            ['comic' => $comic2]
        );

        $seriesAchievement = collect($achievements)->firstWhere('type', 'series_completed');
        $this->assertNotNull($seriesAchievement);
        $this->assertEquals('Series Master', $seriesAchievement['title']);
    }

    public function test_awards_speed_reader_achievement()
    {
        // Create progress that was completed on the same day it was started
        $progress = UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'is_completed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $achievements = $this->achievementService->checkAchievements(
            $this->user,
            'comic_completed',
            ['comic' => $this->comic]
        );

        $speedReaderAchievement = collect($achievements)->firstWhere('type', 'speed_reader');
        $this->assertNotNull($speedReaderAchievement);
        $this->assertEquals('Speed Reader', $speedReaderAchievement['title']);
    }

    public function test_awards_genre_explorer_achievement()
    {
        // Create comics with different genres
        $genres = ['Action', 'Comedy', 'Drama'];
        foreach ($genres as $genre) {
            $comic = Comic::factory()->create(['genre' => $genre]);
            UserComicProgress::factory()->create([
                'user_id' => $this->user->id,
                'comic_id' => $comic->id,
                'is_completed' => true,
            ]);
        }

        $achievements = $this->achievementService->checkAchievements(
            $this->user,
            'comic_completed',
            ['comic' => $this->comic]
        );

        $genreAchievement = collect($achievements)->firstWhere('type', 'genre_explorer');
        $this->assertNotNull($genreAchievement);
        $this->assertEquals(3, $genreAchievement['milestone']);
    }

    public function test_awards_reviewer_achievement()
    {
        // Create first review
        ComicReview::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);

        $achievements = $this->achievementService->checkAchievements(
            $this->user,
            'review_submitted'
        );

        $this->assertCount(1, $achievements);
        $this->assertEquals('reviewer', $achievements[0]['type']);
        $this->assertEquals(1, $achievements[0]['milestone']);
    }

    public function test_awards_social_sharer_achievement()
    {
        // Create first social share
        SocialShare::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);

        $achievements = $this->achievementService->checkAchievements(
            $this->user,
            'social_share'
        );

        $this->assertCount(1, $achievements);
        $this->assertEquals('social_sharer', $achievements[0]['type']);
        $this->assertEquals(1, $achievements[0]['milestone']);
    }

    public function test_awards_binge_reader_achievement()
    {
        // Create 3 comics completed today
        UserComicProgress::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_completed' => true,
            'updated_at' => now(),
        ]);

        $achievements = $this->achievementService->checkAchievements(
            $this->user,
            'progress_updated',
            ['comic' => $this->comic]
        );

        $bingeAchievement = collect($achievements)->firstWhere('type', 'binge_reader');
        $this->assertNotNull($bingeAchievement);
        $this->assertEquals('Binge Reader', $bingeAchievement['title']);
    }

    public function test_gets_user_achievements()
    {
        // Add some achievements to user
        $this->user->achievements = [
            [
                'id' => 'test1',
                'type' => 'first_comic',
                'title' => 'First Steps',
                'awarded_at' => now()->subDays(2)->toISOString(),
            ],
            [
                'id' => 'test2',
                'type' => 'milestone_reader',
                'title' => 'Comic Enthusiast - 5',
                'awarded_at' => now()->subDay()->toISOString(),
            ],
        ];
        $this->user->save();

        $achievements = $this->achievementService->getUserAchievements($this->user);

        $this->assertCount(2, $achievements);
        // Should be sorted by awarded_at desc
        $this->assertEquals('test2', $achievements->first()['id']);
    }

    public function test_gets_user_reading_stats()
    {
        // Create some test data
        UserComicProgress::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_completed' => true,
        ]);

        ComicReview::factory()->count(2)->create([
            'user_id' => $this->user->id,
        ]);

        SocialShare::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $stats = $this->achievementService->getUserReadingStats($this->user);

        $this->assertEquals(3, $stats['comics_completed']);
        $this->assertEquals(2, $stats['reviews_written']);
        $this->assertEquals(1, $stats['social_shares']);
        $this->assertArrayHasKey('total_pages_read', $stats);
        $this->assertArrayHasKey('genres_explored', $stats);
        $this->assertArrayHasKey('reading_streak', $stats);
    }

    public function test_does_not_award_duplicate_achievements()
    {
        // Award first comic achievement
        UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'is_completed' => true,
        ]);

        $firstCheck = $this->achievementService->checkAchievements(
            $this->user,
            'comic_completed',
            ['comic' => $this->comic]
        );

        // Complete another comic
        $comic2 = Comic::factory()->create();
        UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic2->id,
            'is_completed' => true,
        ]);

        $secondCheck = $this->achievementService->checkAchievements(
            $this->user,
            'comic_completed',
            ['comic' => $comic2]
        );

        // First check should have first_comic achievement
        $firstComicAchievement = collect($firstCheck)->firstWhere('type', 'first_comic');
        $this->assertNotNull($firstComicAchievement);
        $this->assertEquals('first_comic', $firstComicAchievement['type']);

        // Second check should not have first_comic achievement again
        $firstComicAchievements = collect($secondCheck)->where('type', 'first_comic');
        $this->assertCount(0, $firstComicAchievements);
    }

    public function test_achievement_types_constant()
    {
        $types = AchievementService::ACHIEVEMENT_TYPES;

        $this->assertArrayHasKey('first_comic', $types);
        $this->assertArrayHasKey('comic_completed', $types);
        $this->assertArrayHasKey('series_completed', $types);
        $this->assertArrayHasKey('genre_explorer', $types);
        $this->assertArrayHasKey('speed_reader', $types);
        $this->assertArrayHasKey('collector', $types);
        $this->assertArrayHasKey('reviewer', $types);
        $this->assertArrayHasKey('social_sharer', $types);
        $this->assertArrayHasKey('milestone_reader', $types);
        $this->assertArrayHasKey('binge_reader', $types);
    }

    public function test_milestone_thresholds_constant()
    {
        $thresholds = AchievementService::MILESTONE_THRESHOLDS;

        $this->assertArrayHasKey('comics_read', $thresholds);
        $this->assertArrayHasKey('pages_read', $thresholds);
        $this->assertArrayHasKey('genres_explored', $thresholds);
        $this->assertArrayHasKey('series_completed', $thresholds);
        $this->assertArrayHasKey('reviews_written', $thresholds);
        $this->assertArrayHasKey('social_shares', $thresholds);

        $this->assertIsArray($thresholds['comics_read']);
        $this->assertContains(1, $thresholds['comics_read']);
        $this->assertContains(5, $thresholds['comics_read']);
        $this->assertContains(10, $thresholds['comics_read']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}