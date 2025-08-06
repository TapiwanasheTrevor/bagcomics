<?php

namespace Tests\Feature\Api;

use App\Models\Comic;
use App\Models\ComicSeries;
use App\Models\User;
use App\Models\UserComicProgress;
use App\Models\UserLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComicApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
    }

    public function test_get_comics_list_without_authentication(): void
    {
        Comic::factory()->count(5)->create(['is_visible' => true]);
        Comic::factory()->create(['is_visible' => false]); // Should not be included

        $response = $this->getJson('/api/comics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'author',
                        'publisher',
                        'genre',
                        'description',
                        'cover_image_url',
                        'is_free',
                        'price',
                        'average_rating',
                        'total_readers'
                    ]
                ],
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_get_comics_list_with_authentication_includes_user_data(): void
    {
        Sanctum::actingAs($this->user);

        $comic = Comic::factory()->create(['is_visible' => true]);
        
        // Create user progress for this comic
        UserComicProgress::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic->id,
            'current_page' => 10
        ]);

        $response = $this->getJson('/api/comics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'user_progress'
                    ]
                ]
            ]);
    }

    public function test_get_comics_list_with_genre_filter(): void
    {
        Comic::factory()->create(['genre' => 'Superhero', 'is_visible' => true]);
        Comic::factory()->create(['genre' => 'Sci-Fi', 'is_visible' => true]);
        Comic::factory()->create(['genre' => 'Fantasy', 'is_visible' => true]);

        $response = $this->getJson('/api/comics?genre=Superhero');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Superhero', $response->json('data.0.genre'));
    }

    public function test_get_comics_list_with_search_filter(): void
    {
        Comic::factory()->create([
            'title' => 'Amazing Spider-Man',
            'author' => 'Stan Lee',
            'is_visible' => true
        ]);
        Comic::factory()->create([
            'title' => 'Batman',
            'author' => 'Bob Kane',
            'is_visible' => true
        ]);

        $response = $this->getJson('/api/comics?search=Spider');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertStringContainsString('Spider', $response->json('data.0.title'));
    }

    public function test_get_comics_list_with_tags_filter(): void
    {
        Comic::factory()->create([
            'tags' => ['action', 'superhero'],
            'is_visible' => true
        ]);
        Comic::factory()->create([
            'tags' => ['romance', 'drama'],
            'is_visible' => true
        ]);

        $response = $this->getJson('/api/comics?tags=action,superhero');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_get_comics_list_with_free_filter(): void
    {
        Comic::factory()->create(['is_free' => true, 'is_visible' => true]);
        Comic::factory()->create(['is_free' => false, 'is_visible' => true]);

        $response = $this->getJson('/api/comics?is_free=true');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.is_free'));
    }

    public function test_get_comics_list_with_sorting(): void
    {
        Comic::factory()->create([
            'title' => 'Z Comic',
            'average_rating' => 3.0,
            'is_visible' => true
        ]);
        Comic::factory()->create([
            'title' => 'A Comic',
            'average_rating' => 5.0,
            'is_visible' => true
        ]);

        // Test sorting by title ascending
        $response = $this->getJson('/api/comics?sort_by=title&sort_order=asc');
        $response->assertStatus(200);
        $this->assertEquals('A Comic', $response->json('data.0.title'));

        // Test sorting by rating descending
        $response = $this->getJson('/api/comics?sort_by=average_rating&sort_order=desc');
        $response->assertStatus(200);
        $this->assertEquals(5.0, $response->json('data.0.average_rating'));
    }

    public function test_get_comics_list_with_pagination(): void
    {
        Comic::factory()->count(25)->create(['is_visible' => true]);

        $response = $this->getJson('/api/comics?per_page=10&page=2');

        $response->assertStatus(200)
            ->assertJson([
                'pagination' => [
                    'current_page' => 2,
                    'per_page' => 10,
                    'total' => 25
                ]
            ]);

        $this->assertCount(10, $response->json('data'));
    }

    public function test_get_comic_details(): void
    {
        $comic = Comic::factory()->create(['is_visible' => true]);

        $response = $this->getJson("/api/comics/{$comic->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $comic->id,
                    'title' => $comic->title,
                    'slug' => $comic->slug,
                    'author' => $comic->author,
                    'publisher' => $comic->publisher,
                    'genre' => $comic->genre,
                    'description' => $comic->description,
                    'page_count' => $comic->page_count,
                    'language' => $comic->language,
                    'isbn' => $comic->isbn,
                    'publication_year' => $comic->publication_year,
                    'average_rating' => $comic->average_rating,
                    'total_ratings' => $comic->total_ratings,
                    'total_readers' => $comic->total_readers,
                    'is_free' => $comic->is_free,
                    'price' => $comic->price,
                    'has_mature_content' => $comic->has_mature_content
                ]
            ]);
    }

    public function test_get_comic_details_with_authentication_includes_user_data(): void
    {
        Sanctum::actingAs($this->user);

        $comic = Comic::factory()->create(['is_visible' => true]);
        
        // Add comic to user's library
        UserLibrary::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $comic->id
        ]);

        $response = $this->getJson("/api/comics/{$comic->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'title',
                    'user_has_access',
                    'user_progress'
                ]
            ]);
    }

    public function test_get_comic_details_for_invisible_comic_returns_404(): void
    {
        $comic = Comic::factory()->create(['is_visible' => false]);

        $response = $this->getJson("/api/comics/{$comic->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND'
                ]
            ]);
    }

    public function test_get_comic_details_for_nonexistent_comic_returns_404(): void
    {
        $response = $this->getJson('/api/comics/999');

        $response->assertStatus(404);
    }

    public function test_get_featured_comics(): void
    {
        // Create comics with high ratings
        Comic::factory()->count(3)->create([
            'average_rating' => 4.5,
            'total_readers' => 1000,
            'is_visible' => true
        ]);

        // Create comics with low ratings (should not be featured)
        Comic::factory()->count(2)->create([
            'average_rating' => 2.0,
            'total_readers' => 100,
            'is_visible' => true
        ]);

        $response = $this->getJson('/api/comics/featured');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'average_rating',
                        'total_readers'
                    ]
                ]
            ]);

        $this->assertCount(3, $response->json('data'));
        
        // Verify all featured comics have high ratings
        foreach ($response->json('data') as $comic) {
            $this->assertGreaterThanOrEqual(4.0, $comic['average_rating']);
        }
    }

    public function test_get_new_releases(): void
    {
        // Create recent comics
        Comic::factory()->count(3)->create([
            'published_at' => now()->subDays(15),
            'is_visible' => true
        ]);

        // Create old comics (should not be in new releases)
        Comic::factory()->count(2)->create([
            'published_at' => now()->subDays(45),
            'is_visible' => true
        ]);

        $response = $this->getJson('/api/comics/new-releases');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_get_genres(): void
    {
        Comic::factory()->create(['genre' => 'Superhero', 'is_visible' => true]);
        Comic::factory()->create(['genre' => 'Sci-Fi', 'is_visible' => true]);
        Comic::factory()->create(['genre' => 'Fantasy', 'is_visible' => true]);
        Comic::factory()->create(['genre' => 'Superhero', 'is_visible' => true]); // Duplicate
        Comic::factory()->create(['genre' => 'Horror', 'is_visible' => false]); // Not visible

        $response = $this->getJson('/api/comics/genres');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        $genres = $response->json('data');
        $this->assertCount(3, $genres); // Should not include duplicates or invisible comics
        $this->assertContains('Superhero', $genres);
        $this->assertContains('Sci-Fi', $genres);
        $this->assertContains('Fantasy', $genres);
        $this->assertNotContains('Horror', $genres);
    }

    public function test_get_tags(): void
    {
        Comic::factory()->create([
            'tags' => ['action', 'superhero'],
            'is_visible' => true
        ]);
        Comic::factory()->create([
            'tags' => ['romance', 'drama'],
            'is_visible' => true
        ]);
        Comic::factory()->create([
            'tags' => ['action', 'adventure'], // 'action' is duplicate
            'is_visible' => true
        ]);
        Comic::factory()->create([
            'tags' => ['horror'],
            'is_visible' => false // Not visible
        ]);

        $response = $this->getJson('/api/comics/tags');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        $tags = $response->json('data');
        $this->assertContains('action', $tags);
        $this->assertContains('superhero', $tags);
        $this->assertContains('romance', $tags);
        $this->assertContains('drama', $tags);
        $this->assertContains('adventure', $tags);
        $this->assertNotContains('horror', $tags); // From invisible comic
    }

    public function test_track_comic_view(): void
    {
        $comic = Comic::factory()->create(['is_visible' => true]);

        $response = $this->postJson("/api/comics/{$comic->id}/track-view");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'message' => 'View tracked successfully'
                ]
            ]);

        // Verify view was recorded in database
        $this->assertDatabaseHas('comic_views', [
            'comic_id' => $comic->id
        ]);
    }

    public function test_track_view_for_invisible_comic_returns_404(): void
    {
        $comic = Comic::factory()->create(['is_visible' => false]);

        $response = $this->postJson("/api/comics/{$comic->id}/track-view");

        $response->assertStatus(404);
    }

    public function test_api_response_format_is_consistent(): void
    {
        Comic::factory()->create(['is_visible' => true]);

        $response = $this->getJson('/api/comics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'timestamp'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertNotNull($response->json('timestamp'));
    }

    public function test_api_rate_limiting_headers_are_present(): void
    {
        $response = $this->getJson('/api/comics');

        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_comics_api_validates_pagination_parameters(): void
    {
        $response = $this->getJson('/api/comics?per_page=101'); // Exceeds maximum

        $response->assertStatus(200); // Should still work but limit to max
        
        $response = $this->getJson('/api/comics?per_page=0'); // Below minimum
        $response->assertStatus(200); // Should use default
    }

    public function test_comics_api_handles_invalid_sort_parameters(): void
    {
        Comic::factory()->count(3)->create(['is_visible' => true]);

        $response = $this->getJson('/api/comics?sort_by=invalid_field');

        $response->assertStatus(200); // Should ignore invalid sort and use default
    }
}