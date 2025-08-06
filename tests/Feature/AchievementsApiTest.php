<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Comic;
use App\Models\UserComicProgress;
use App\Models\ComicReview;
use App\Models\SocialShare;
use Illuminate\Foundation\Testing\RefreshDatabase;


class AchievementsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
    }

    public function test_can_get_user_achievements()
    {
        $this->actingAs($this->user);

        // Add some achievements to the user
        $this->user->achievements = [
            [
                'id' => 'test1',
                'type' => 'first_comic',
                'title' => 'First Steps',
                'description' => 'Completed your first comic!',
                'icon' => '🎉',
                'awarded_at' => now()->subDays(2)->toISOString(),
            ],
            [
                'id' => 'test2',
                'type' => 'milestone_reader',
                'title' => 'Comic Enthusiast - 5',
                'description' => 'Completed 5 comics!',
                'icon' => '📚',
                'milestone' => 5,
                'awarded_at' => now()->subDay()->toISOString(),
            ],
        ];
        $this->user->save();

        $response = $this->getJson('/api/achievements');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total_achievements' => 2,
            ])
            ->assertJsonStructure([
                'success',
                'achievements' => [
                    '*' => [
                        'id',
                        'type',
                        'title',
                        'description',
                        'icon',
                        'awarded_at',
                    ],
                ],
                'total_achievements',
            ]);

        $achievements = $response->json('achievements');
        // Should be sorted by awarded_at desc
        $this->assertEquals('test2', $achievements[0]['id']);
        $this->assertEquals('test1', $achievements[1]['id']);
    }

    public function test_returns_empty_achievements_for_new_user()
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/achievements');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total_achievements' => 0,
            ]);

        $achievements = $response->json('achievements');
        $this->assertEmpty($achievements);
    }

    public function test_can_get_reading_stats()
    {
        $this->actingAs($this->user);

        // Create some test data
        $comics = Comic::factory()->count(3)->create();
        
        foreach ($comics as $comic) {
            UserComicProgress::factory()->create([
                'user_id' => $this->user->id,
                'comic_id' => $comic->id,
                'is_completed' => true,
            ]);
        }

        ComicReview::factory()->count(2)->create([
            'user_id' => $this->user->id,
        ]);

        SocialShare::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/achievements/stats');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'reading_stats' => [
                    'comics_completed',
                    'total_pages_read',
                    'genres_explored',
                    'series_completed',
                    'reviews_written',
                    'social_shares',
                    'library_size',
                    'reading_streak',
                    'favorite_genre',
                    'average_rating_given',
                ],
            ]);

        $stats = $response->json('reading_stats');
        $this->assertEquals(3, $stats['comics_completed']);
        $this->assertEquals(2, $stats['reviews_written']);
        $this->assertEquals(1, $stats['social_shares']);
    }

    public function test_can_get_achievement_types()
    {
        $this->actingAs($this->user);
        
        $response = $this->getJson('/api/achievements/types');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'achievement_types' => [
                    'first_comic' => [
                        'title',
                        'description',
                        'icon',
                    ],
                    'comic_completed' => [
                        'title',
                        'description',
                        'icon',
                    ],
                    'series_completed' => [
                        'title',
                        'description',
                        'icon',
                    ],
                    'genre_explorer' => [
                        'title',
                        'description',
                        'icon',
                    ],
                    'speed_reader' => [
                        'title',
                        'description',
                        'icon',
                    ],
                    'collector' => [
                        'title',
                        'description',
                        'icon',
                    ],
                    'reviewer' => [
                        'title',
                        'description',
                        'icon',
                    ],
                    'social_sharer' => [
                        'title',
                        'description',
                        'icon',
                    ],
                    'milestone_reader' => [
                        'title',
                        'description',
                        'icon',
                    ],
                    'binge_reader' => [
                        'title',
                        'description',
                        'icon',
                    ],
                ],
                'milestone_thresholds' => [
                    'comics_read',
                    'pages_read',
                    'genres_explored',
                    'series_completed',
                    'reviews_written',
                    'social_shares',
                ],
            ]);

        $achievementTypes = $response->json('achievement_types');
        $this->assertEquals('First Comic Read', $achievementTypes['first_comic']['title']);
        $this->assertEquals('🎉', $achievementTypes['first_comic']['icon']);

        $milestones = $response->json('milestone_thresholds');
        $this->assertContains(1, $milestones['comics_read']);
        $this->assertContains(5, $milestones['comics_read']);
        $this->assertContains(10, $milestones['comics_read']);
    }

    public function test_requires_authentication_for_user_achievements()
    {
        $response = $this->getJson('/api/achievements');

        $response->assertStatus(401);
    }

    public function test_requires_authentication_for_reading_stats()
    {
        $response = $this->getJson('/api/achievements/stats');

        $response->assertStatus(401);
    }

    public function test_achievement_types_endpoint_requires_authentication()
    {
        $response = $this->getJson('/api/achievements/types');

        $response->assertStatus(401);
    }

    public function test_reading_stats_handles_user_with_no_activity()
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/achievements/stats');

        $response->assertStatus(200);

        $stats = $response->json('reading_stats');
        $this->assertEquals(0, $stats['comics_completed']);
        $this->assertEquals(0, $stats['total_pages_read']);
        $this->assertEquals(0, $stats['genres_explored']);
        $this->assertEquals(0, $stats['reviews_written']);
        $this->assertEquals(0, $stats['social_shares']);
        $this->assertEquals(0, $stats['library_size']);
    }

    public function test_reading_stats_calculates_favorite_genre()
    {
        $this->actingAs($this->user);

        // Create comics with different genres, more action comics
        $actionComics = Comic::factory()->count(3)->create(['genre' => 'Action']);
        $comedyComics = Comic::factory()->count(1)->create(['genre' => 'Comedy']);

        foreach ($actionComics as $comic) {
            UserComicProgress::factory()->create([
                'user_id' => $this->user->id,
                'comic_id' => $comic->id,
                'is_completed' => true,
            ]);
        }

        foreach ($comedyComics as $comic) {
            UserComicProgress::factory()->create([
                'user_id' => $this->user->id,
                'comic_id' => $comic->id,
                'is_completed' => true,
            ]);
        }

        $response = $this->getJson('/api/achievements/stats');

        $response->assertStatus(200);

        $stats = $response->json('reading_stats');
        $this->assertEquals('Action', $stats['favorite_genre']);
    }

    public function test_reading_stats_calculates_genres_explored()
    {
        $this->actingAs($this->user);

        $genres = ['Action', 'Comedy', 'Drama', 'Horror'];
        
        foreach ($genres as $genre) {
            $comic = Comic::factory()->create(['genre' => $genre]);
            UserComicProgress::factory()->create([
                'user_id' => $this->user->id,
                'comic_id' => $comic->id,
                'is_completed' => true,
            ]);
        }

        $response = $this->getJson('/api/achievements/stats');

        $response->assertStatus(200);

        $stats = $response->json('reading_stats');
        $this->assertEquals(4, $stats['genres_explored']);
    }

    public function test_reading_stats_calculates_total_pages_read()
    {
        $this->actingAs($this->user);

        $comic1 = Comic::factory()->create(['page_count' => 50]);
        $comic2 = Comic::factory()->create(['page_count' => 75]);

        UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic1->id,
            'is_completed' => true,
        ]);

        UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic2->id,
            'is_completed' => true,
        ]);

        $response = $this->getJson('/api/achievements/stats');

        $response->assertStatus(200);

        $stats = $response->json('reading_stats');
        $this->assertEquals(125, $stats['total_pages_read']);
    }

    public function test_handles_service_errors_gracefully()
    {
        $this->actingAs($this->user);

        // Mock a service error by corrupting the user's achievements data
        $this->user->achievements = 'invalid_json_data';
        $this->user->save();

        $response = $this->getJson('/api/achievements');

        // The endpoint should handle the error gracefully or return empty achievements
        $this->assertTrue($response->status() === 200 || $response->status() === 500);
        
        if ($response->status() === 200) {
            // If it handles gracefully, achievements should be empty or valid
            $response->assertJson(['success' => true]);
        } else {
            // If it returns an error, it should be a proper error response
            $response->assertJson(['success' => false]);
        }
    }
}